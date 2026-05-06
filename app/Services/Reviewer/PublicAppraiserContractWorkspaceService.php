<?php

namespace App\Services\Reviewer;

use App\Models\AppraisalRequest;
use App\Models\ReportSigner;
use App\Models\SignatureEnvelope;
use App\Models\User;
use App\Services\Peruri\PeruriSignerReadinessService;
use App\Services\Signatures\ContractSignatureService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class PublicAppraiserContractWorkspaceService
{
    public function __construct(
        private readonly ContractSignatureService $contractSignatureService,
        private readonly PeruriSignerReadinessService $readinessService,
    ) {
    }

    public function indexPayload(User $actor): array
    {
        $signer = $this->assignedSigner($actor, failIfMissing: false);

        if (! $signer) {
            return [
                'signer' => null,
                'summary' => [
                    'siap_sign' => 0,
                    'gagal' => 0,
                    'selesai' => 0,
                ],
                'items' => [],
            ];
        }

        $readiness = $this->readinessService->forSigner($signer, sync: true);

        $baseQuery = AppraisalRequest::query()
            ->where('contract_public_appraiser_signer_id', $signer->id);

        $items = AppraisalRequest::query()
            ->where('contract_public_appraiser_signer_id', $signer->id)
            ->with([
                'user:id,name,email',
                'signatureEnvelopes' => function ($query): void {
                    $query
                        ->where('document_type', 'contract')
                        ->where('provider', 'peruri_signit')
                        ->with(['participants' => function ($participantsQuery): void {
                            $participantsQuery->select([
                                'id',
                                'signature_envelope_id',
                                'role',
                                'status',
                                'signed_at',
                                'email',
                                'external_order_id',
                            ]);
                        }]);
                },
            ])
            ->whereHas('signatureEnvelopes', function (Builder $query): void {
                $query
                    ->where('document_type', 'contract')
                    ->where('provider', 'peruri_signit')
                    ->whereIn('status', ['awaiting_internal', 'failed']);
            })
            ->orderByDesc('id')
            ->limit(50)
            ->get()
            ->map(fn (AppraisalRequest $record): array => $this->queueItem($record, $readiness))
            ->values()
            ->all();

        return [
            'signer' => [
                'id' => $signer->id,
                'name' => $signer->name,
                'email' => $signer->email,
                'position_title' => $signer->position_title,
                'readiness' => $readiness['readiness'],
            ],
            'bulkSignUrl' => route('reviewer.contract-signatures.bulk-sign'),
            'summary' => [
                'siap_sign' => (clone $baseQuery)
                    ->whereHas('signatureEnvelopes', fn (Builder $query) => $query
                        ->where('document_type', 'contract')
                        ->where('provider', 'peruri_signit')
                        ->where('status', 'awaiting_internal'))
                    ->count(),
                'gagal' => (clone $baseQuery)
                    ->whereHas('signatureEnvelopes', fn (Builder $query) => $query
                        ->where('document_type', 'contract')
                        ->where('provider', 'peruri_signit')
                        ->where('status', 'failed'))
                    ->count(),
                'selesai' => (clone $baseQuery)
                    ->whereHas('signatureEnvelopes', fn (Builder $query) => $query
                        ->where('document_type', 'contract')
                        ->where('provider', 'peruri_signit')
                        ->where('status', 'completed'))
                    ->count(),
            ],
            'items' => $items,
        ];
    }

    public function hasAssignedSigner(?User $actor): bool
    {
        if (! $actor) {
            return false;
        }

        return ReportSigner::query()
            ->where('role', 'public_appraiser')
            ->where('is_active', true)
            ->where('user_id', $actor->id)
            ->exists();
    }

    public function showPayload(User $actor, AppraisalRequest $record): array
    {
        $signer = $this->assignedSigner($actor);
        $this->ensureAssignedToSigner($record, $signer);

        $record->load([
            'user:id,name,email',
            'contractPublicAppraiserSigner:id,user_id,name,email,position_title,peruri_certificate_status,peruri_keyla_status,peruri_last_checked_at',
            'signatureEnvelopes' => function ($query): void {
                $query
                    ->where('document_type', 'contract')
                    ->where('provider', 'peruri_signit')
                    ->with('participants');
            },
        ]);

        $envelope = $this->contractEnvelope($record);
        $participants = $envelope?->participants ?? collect();
        $customer = $participants->firstWhere('role', 'customer');
        $publicAppraiser = $participants->firstWhere('role', 'public_appraiser');
        $readiness = $this->readinessService->forSigner($signer, sync: true);
        $status = $this->queueStatus((string) ($envelope?->status ?? 'draft'));

        return [
            'item' => [
                'id' => $record->id,
                'request_number' => $record->request_number ?? ('REQ-' . $record->id),
                'client_name' => $record->client_name ?: ($record->user?->name ?? '-'),
                'client_email' => $record->user?->email ?? '-',
                'contract_number' => $record->contract_number,
                'queue_status' => $status,
                'customer' => [
                    'name' => $record->user?->name ?? '-',
                    'email' => $record->user?->email ?? '-',
                    'status' => $customer?->status ?? 'pending',
                    'status_label' => $this->participantStatusLabel((string) ($customer?->status ?? 'pending')),
                    'signed_at' => $customer?->signed_at?->toDateTimeString(),
                ],
                'public_appraiser' => [
                    'name' => $signer->name,
                    'email' => $signer->email,
                    'position_title' => $signer->position_title,
                    'status' => $publicAppraiser?->status ?? 'pending',
                    'status_label' => $this->participantStatusLabel((string) ($publicAppraiser?->status ?? 'pending')),
                    'signed_at' => $publicAppraiser?->signed_at?->toDateTimeString(),
                    'readiness' => $readiness['readiness'],
                ],
                'envelope' => [
                    'status' => $envelope?->status,
                    'status_label' => $status['label'],
                    'last_error' => $envelope?->last_error,
                    'external_envelope_id' => $envelope?->external_envelope_id,
                ],
                'documents' => [
                    'original_pdf_url' => $this->publicUrl($envelope?->original_pdf_path),
                    'signed_pdf_url' => $this->publicUrl($envelope?->signed_pdf_path),
                ],
                'actions' => [
                    'back_url' => route('reviewer.contract-signatures.index'),
                    'sign_url' => route('reviewer.contract-signatures.sign', $record),
                    'can_sign' => $this->canSign($envelope, (string) ($customer?->status ?? 'pending'), $readiness),
                ],
            ],
        ];
    }

    public function signContract(User $actor, AppraisalRequest $record, string $keylaToken): void
    {
        $signer = $this->assignedSigner($actor);
        $this->ensureAssignedToSigner($record, $signer);

        $this->contractSignatureService->publicAppraiserSignContract($actor, $record, $keylaToken);
    }

    /**
     * @param  array<int, int>  $requestIds
     * @return array<string, mixed>
     */
    public function bulkSignContracts(User $actor, array $requestIds, string $keylaToken): array
    {
        $signer = $this->assignedSigner($actor);
        $normalizedIds = collect($requestIds)
            ->filter(fn ($id) => is_int($id) || is_numeric($id))
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();

        if ($normalizedIds->isEmpty()) {
            throw new RuntimeException('Pilih minimal satu kontrak untuk bulk sign.');
        }

        /** @var Collection<int, AppraisalRequest> $records */
        $records = AppraisalRequest::query()
            ->whereIn('id', $normalizedIds->all())
            ->where('contract_public_appraiser_signer_id', $signer->id)
            ->with([
                'user:id,name,email',
                'contractPublicAppraiserSigner:id,user_id,name,email,position_title,peruri_certificate_status,peruri_keyla_status,peruri_last_checked_at',
                'signatureEnvelopes' => function ($query): void {
                    $query
                        ->where('document_type', 'contract')
                        ->where('provider', 'peruri_signit')
                        ->with('participants');
                },
            ])
            ->get()
            ->keyBy('id');

        $results = [];

        foreach ($normalizedIds as $requestId) {
            $record = $records->get($requestId);

            if (! $record) {
                $results[] = [
                    'request_id' => $requestId,
                    'request_number' => 'REQ-' . $requestId,
                    'success' => false,
                    'message' => 'Request tidak ditemukan atau tidak ditugaskan ke akun Anda.',
                ];
                continue;
            }

            $canSign = $this->canSign(
                $this->contractEnvelope($record),
                (string) ($this->contractEnvelope($record)?->participants?->firstWhere('role', 'customer')?->status ?? 'pending'),
                $this->readinessService->forSigner($signer, sync: true),
            );

            if (! $canSign) {
                $results[] = [
                    'request_id' => $record->id,
                    'request_number' => $record->request_number ?? ('REQ-' . $record->id),
                    'success' => false,
                    'message' => 'Kontrak belum siap ditandatangani dalam bulk session.',
                ];
                continue;
            }

            try {
                $this->contractSignatureService->publicAppraiserSignContract($actor, $record, $keylaToken);

                $results[] = [
                    'request_id' => $record->id,
                    'request_number' => $record->request_number ?? ('REQ-' . $record->id),
                    'success' => true,
                    'message' => 'Kontrak berhasil ditandatangani.',
                ];
            } catch (RuntimeException $exception) {
                $results[] = [
                    'request_id' => $record->id,
                    'request_number' => $record->request_number ?? ('REQ-' . $record->id),
                    'success' => false,
                    'message' => $exception->getMessage(),
                ];
            } catch (\Throwable $exception) {
                report($exception);

                $results[] = [
                    'request_id' => $record->id,
                    'request_number' => $record->request_number ?? ('REQ-' . $record->id),
                    'success' => false,
                    'message' => 'Terjadi kesalahan saat memproses kontrak ini.',
                ];
            }
        }

        $successCount = collect($results)->where('success', true)->count();
        $failed = collect($results)->where('success', false)->values()->all();

        return [
            'selected_count' => $normalizedIds->count(),
            'success_count' => $successCount,
            'failed_count' => count($failed),
            'results' => $results,
            'failed_items' => $failed,
        ];
    }

    public function dashboardSummary(?User $actor): ?array
    {
        if (! $actor) {
            return null;
        }

        $signer = $this->assignedSigner($actor, failIfMissing: false);
        if (! $signer) {
            return null;
        }

        $baseQuery = AppraisalRequest::query()
            ->where('contract_public_appraiser_signer_id', $signer->id)
            ->whereHas('signatureEnvelopes', function (Builder $query): void {
                $query
                    ->where('document_type', 'contract')
                    ->where('provider', 'peruri_signit');
            });

        return [
            'queue_url' => route('reviewer.contract-signatures.index'),
            'ready_count' => (clone $baseQuery)
                ->whereHas('signatureEnvelopes', fn (Builder $query) => $query
                    ->where('document_type', 'contract')
                    ->where('provider', 'peruri_signit')
                    ->where('status', 'awaiting_internal'))
                ->count(),
            'failed_count' => (clone $baseQuery)
                ->whereHas('signatureEnvelopes', fn (Builder $query) => $query
                    ->where('document_type', 'contract')
                    ->where('provider', 'peruri_signit')
                    ->where('status', 'failed'))
                ->count(),
        ];
    }

    private function assignedSigner(User $actor, bool $failIfMissing = true): ?ReportSigner
    {
        $signer = ReportSigner::query()
            ->where('role', 'public_appraiser')
            ->where('is_active', true)
            ->where('user_id', $actor->id)
            ->first();

        if ($signer || ! $failIfMissing) {
            return $signer;
        }

        throw new RuntimeException('Akun Anda belum terhubung sebagai penilai publik aktif.');
    }

    private function ensureAssignedToSigner(AppraisalRequest $record, ReportSigner $signer): void
    {
        if ((int) $record->contract_public_appraiser_signer_id !== (int) $signer->id) {
            abort(404);
        }
    }

    private function contractEnvelope(AppraisalRequest $record): ?SignatureEnvelope
    {
        return $record->signatureEnvelopes
            ->firstWhere('document_type', 'contract');
    }

    /**
     * @param  array<string, mixed>  $readiness
     * @return array<string, mixed>
     */
    private function queueItem(AppraisalRequest $record, array $readiness): array
    {
        $envelope = $this->contractEnvelope($record);
        $participants = $envelope?->participants ?? collect();
        $customer = $participants->firstWhere('role', 'customer');
        $publicAppraiser = $participants->firstWhere('role', 'public_appraiser');
        $status = $this->queueStatus((string) ($envelope?->status ?? 'draft'));

        return [
            'id' => $record->id,
            'request_number' => $record->request_number ?? ('REQ-' . $record->id),
            'client_name' => $record->client_name ?: ($record->user?->name ?? '-'),
            'contract_number' => $record->contract_number,
            'queue_status' => $status,
            'customer' => [
                'name' => $record->user?->name ?? '-',
                'email' => $record->user?->email ?? '-',
                'status' => $customer?->status ?? 'pending',
                'status_label' => $this->participantStatusLabel((string) ($customer?->status ?? 'pending')),
                'signed_at' => $customer?->signed_at?->toDateTimeString(),
            ],
            'public_appraiser' => [
                'status' => $publicAppraiser?->status ?? 'pending',
                'status_label' => $this->participantStatusLabel((string) ($publicAppraiser?->status ?? 'pending')),
                'signed_at' => $publicAppraiser?->signed_at?->toDateTimeString(),
                'readiness' => $readiness['readiness'],
            ],
            'envelope' => [
                'status' => $envelope?->status,
                'last_error' => $envelope?->last_error,
            ],
            'can_bulk_sign' => $this->canSign($envelope, (string) ($customer?->status ?? 'pending'), $readiness),
            'detail_url' => route('reviewer.contract-signatures.show', $record),
            'sign_url' => route('reviewer.contract-signatures.sign', $record),
        ];
    }

    /**
     * @return array{value:string,label:string,tone:string}
     */
    private function queueStatus(string $status): array
    {
        return match ($status) {
            'awaiting_internal' => ['value' => 'siap_sign', 'label' => 'Siap Sign', 'tone' => 'success'],
            'failed' => ['value' => 'gagal', 'label' => 'Gagal', 'tone' => 'danger'],
            'completed' => ['value' => 'selesai', 'label' => 'Selesai', 'tone' => 'muted'],
            default => ['value' => 'draft', 'label' => 'Menunggu', 'tone' => 'warning'],
        };
    }

    private function participantStatusLabel(string $status): string
    {
        return match ($status) {
            'signed' => 'Sudah Sign',
            'pending' => 'Menunggu',
            'failed' => 'Gagal',
            default => 'Belum Diketahui',
        };
    }

    /**
     * @param  array<string, mixed>  $readiness
     */
    private function canSign(?SignatureEnvelope $envelope, string $customerStatus, array $readiness): bool
    {
        return in_array((string) ($envelope?->status ?? ''), ['awaiting_internal', 'failed'], true)
            && $customerStatus === 'signed'
            && (bool) data_get($readiness, 'readiness.overall.is_ready', false);
    }

    private function publicUrl(?string $path): ?string
    {
        if (! $path || ! Storage::disk('public')->exists($path)) {
            return null;
        }

        $url = Storage::disk('public')->url($path);
        $appUrl = (string) config('app.url', '');

        if (parse_url($appUrl, PHP_URL_SCHEME) !== 'https') {
            return $url;
        }

        return preg_replace('/^http:\/\//i', 'https://', $url) ?: $url;
    }
}

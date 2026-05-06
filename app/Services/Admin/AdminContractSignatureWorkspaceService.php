<?php

namespace App\Services\Admin;

use App\Models\AppraisalRequest;
use App\Models\ReportSigner;
use App\Services\Peruri\PeruriSignerReadinessService;
use App\Services\Signatures\ContractSignatureService;
use Illuminate\Database\Eloquent\Builder;

class AdminContractSignatureWorkspaceService
{
    public function __construct(
        private readonly ContractSignatureService $contractSignatureService,
        private readonly PeruriSignerReadinessService $readinessService,
    ) {
    }

    public function indexPayload(): array
    {
        $actorId = (int) request()->user()->id;

        $signerIds = ReportSigner::query()
            ->where('role', 'public_appraiser')
            ->where('is_active', true)
            ->where('user_id', $actorId)
            ->pluck('id')
            ->values()
            ->all();

        $items = [];
        if (! empty($signerIds)) {
            $readinessBySignerId = ReportSigner::query()
                ->whereIn('id', $signerIds)
                ->get()
                ->mapWithKeys(fn (ReportSigner $signer) => [
                    $signer->id => $this->readinessService->forSigner($signer, sync: true),
                ]);

            $items = AppraisalRequest::query()
                ->whereIn('contract_public_appraiser_signer_id', $signerIds)
                ->with([
                    'user:id,name,email',
                    'contractPublicAppraiserSigner:id,name,email,user_id,peruri_certificate_status,peruri_keyla_status,peruri_last_checked_at',
                    'signatureEnvelopes' => function (Builder $query): void {
                        $query
                            ->where('document_type', 'contract')
                            ->where('provider', 'peruri_signit')
                            ->whereIn('status', ['awaiting_internal', 'failed'])
                            ->with(['participants' => function (Builder $participantsQuery): void {
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
                ->map(function (AppraisalRequest $record): array {
                    $envelope = $record->signatureEnvelopes->first();
                    $participants = $envelope?->participants ?? collect();
                    $customer = $participants->firstWhere('role', 'customer');
                    $publicAppraiser = $participants->firstWhere('role', 'public_appraiser');

                    return [
                        'id' => $record->id,
                        'request_number' => $record->request_number ?? ('REQ-' . $record->id),
                        'client_name' => $record->client_name ?: '-',
                        'customer' => [
                            'name' => $record->user?->name ?? '-',
                            'email' => $record->user?->email ?? '-',
                            'signed_at' => $customer?->signed_at?->toDateTimeString(),
                            'status' => $customer?->status,
                        ],
                        'public_appraiser' => [
                            'name' => $record->contractPublicAppraiserSigner?->name ?? '-',
                            'email' => $record->contractPublicAppraiserSigner?->email ?? '-',
                            'signed_at' => $publicAppraiser?->signed_at?->toDateTimeString(),
                            'status' => $publicAppraiser?->status,
                            'readiness' => data_get($readinessBySignerId->get($record->contract_public_appraiser_signer_id), 'readiness'),
                        ],
                        'envelope' => [
                            'status' => $envelope?->status,
                            'external_envelope_id' => $envelope?->external_envelope_id,
                            'last_error' => $envelope?->last_error,
                        ],
                        'sign_url' => route('admin.signatures.contracts.sign', $record),
                        'detail_url' => route('admin.appraisal-requests.show', $record),
                    ];
                })
                ->values()
                ->all();
        }

        return [
            'items' => $items,
        ];
    }

    public function signContract(AppraisalRequest $record, int $actorId, string $keylaToken): void
    {
        /** @var \App\Models\User $actor */
        $actor = request()->user();
        if (! $actor || (int) $actor->id !== $actorId) {
            throw new \RuntimeException('Akun tidak valid.');
        }

        $this->contractSignatureService->publicAppraiserSignContract($actor, $record, $keylaToken);
    }
}

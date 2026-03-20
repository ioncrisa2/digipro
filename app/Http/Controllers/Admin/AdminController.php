<?php

namespace App\Http\Controllers\Admin;

use App\Enums\AppraisalStatusEnum;
use App\Enums\AssetTypeEnum;
use App\Enums\ContractStatusEnum;
use App\Enums\ReportTypeEnum;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreAppraisalRequestFileRequest;
use App\Http\Requests\Admin\StoreAppraisalOfferRequest;
use App\Http\Requests\Admin\UpsertAppraisalAssetRequest;
use App\Http\Requests\Admin\UpdateAppraisalRequestBasicRequest;
use App\Models\AppraisalAsset;
use App\Models\AppraisalRequestFile;
use App\Models\AppraisalRequest;
use App\Models\District;
use App\Models\Province;
use App\Models\Regency;
use App\Models\Village;
use App\Services\Admin\AppraisalContractNumberService;
use App\Services\Admin\AppraisalRequestWorkflowService;
use App\Support\AppraisalAssetFieldOptions;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Storage;
use Inertia\Response;

class AdminController extends Controller
{
    public function dashboard(): Response
    {
        $stats = [
            [
                'key' => 'submitted',
                'label' => 'Submitted',
                'value' => AppraisalRequest::query()->where('status', AppraisalStatusEnum::Submitted)->count(),
                'description' => 'Menunggu verifikasi',
                'tone' => 'info',
            ],
            [
                'key' => 'docs_incomplete',
                'label' => 'Dokumen Kurang',
                'value' => AppraisalRequest::query()->where('status', AppraisalStatusEnum::DocsIncomplete)->count(),
                'description' => 'Perlu tindak lanjut',
                'tone' => 'warning',
            ],
            [
                'key' => 'waiting_offer',
                'label' => 'Waiting Offer',
                'value' => AppraisalRequest::query()->where('status', AppraisalStatusEnum::WaitingOffer)->count(),
                'description' => 'Siap diberi penawaran',
                'tone' => 'warning',
            ],
            [
                'key' => 'offer_sent',
                'label' => 'Offer Sent',
                'value' => AppraisalRequest::query()->where('status', AppraisalStatusEnum::OfferSent)->count(),
                'description' => 'Menunggu respons klien',
                'tone' => 'primary',
            ],
            [
                'key' => 'waiting_signature',
                'label' => 'Waiting Signature',
                'value' => AppraisalRequest::query()->where('status', AppraisalStatusEnum::WaitingSignature)->count(),
                'description' => 'Kontrak belum ditandatangani',
                'tone' => 'warning',
            ],
            [
                'key' => 'contract_signed',
                'label' => 'Contract Signed',
                'value' => AppraisalRequest::query()->where('status', AppraisalStatusEnum::ContractSigned)->count(),
                'description' => 'Siap proses valuasi',
                'tone' => 'success',
            ],
            [
                'key' => 'requests_today',
                'label' => 'Permohonan Hari Ini',
                'value' => AppraisalRequest::query()->whereDate('requested_at', now()->toDateString())->count(),
                'description' => 'Permohonan baru',
                'tone' => 'success',
            ],
            [
                'key' => 'assets_today',
                'label' => 'Aset Hari Ini',
                'value' => AppraisalAsset::query()->whereDate('created_at', now()->toDateString())->count(),
                'description' => 'Aset baru diunggah',
                'tone' => 'info',
            ],
        ];

        $actionItems = AppraisalRequest::query()
            ->whereIn('status', [
                AppraisalStatusEnum::Submitted,
                AppraisalStatusEnum::DocsIncomplete,
                AppraisalStatusEnum::Verified,
                AppraisalStatusEnum::WaitingOffer,
            ])
            ->with('user')
            ->withCount('assets')
            ->latest('requested_at')
            ->limit(8)
            ->get()
            ->map(fn (AppraisalRequest $record) => $this->transformRequestListItem($record))
            ->values();

        $paymentQueue = AppraisalRequest::query()
            ->where('status', AppraisalStatusEnum::ContractSigned)
            ->with('user')
            ->latest('updated_at')
            ->limit(8)
            ->get()
            ->map(fn (AppraisalRequest $record) => [
                'id' => $record->id,
                'request_number' => $record->request_number ?? ('REQ-' . $record->id),
                'requester_name' => $record->user?->name ?? '-',
                'fee_total' => (int) ($record->fee_total ?? 0),
                'offer_validity_days' => $record->offer_validity_days,
                'updated_at' => $record->updated_at?->toIso8601String(),
                'show_url' => route('admin.appraisal-requests.show', $record),
                'legacy_url' => $this->legacyAppraisalRequestUrl($record),
            ])
            ->values();

        return inertia('Admin/Dashboard', [
            'stats' => $stats,
            'actionItems' => $actionItems,
            'paymentQueue' => $paymentQueue,
            'modules' => $this->moduleCards(),
            'legacyPanelUrl' => url('/legacy-admin'),
        ]);
    }

    public function appraisalRequestsIndex(Request $request): Response
    {
        $filters = [
            'q' => trim((string) $request->query('q', '')),
            'status' => (string) $request->query('status', 'all'),
        ];

        $records = AppraisalRequest::query()
            ->with('user')
            ->withCount('assets')
            ->withCount([
                'offerNegotiations as negotiation_rounds_used' => fn ($query) => $query->where('action', 'counter_request'),
            ])
            ->when($filters['q'] !== '', function ($query) use ($filters) {
                $query->where(function ($innerQuery) use ($filters): void {
                    $innerQuery
                        ->where('request_number', 'like', '%' . $filters['q'] . '%')
                        ->orWhere('client_name', 'like', '%' . $filters['q'] . '%')
                        ->orWhereHas('user', fn ($userQuery) => $userQuery->where('name', 'like', '%' . $filters['q'] . '%'));
                });
            })
            ->when($filters['status'] !== 'all', fn ($query) => $query->where('status', $filters['status']))
            ->latest('requested_at')
            ->paginate(15)
            ->withQueryString();

        $records->through(fn (AppraisalRequest $record) => $this->transformRequestTableRow($record));

        return inertia('Admin/AppraisalRequests/Index', [
            'filters' => $filters,
            'statusOptions' => array_map(
                fn (AppraisalStatusEnum $status) => [
                    'value' => $status->value,
                    'label' => $status->label(),
                ],
                AppraisalStatusEnum::cases()
            ),
            'summary' => [
                'total' => AppraisalRequest::query()->count(),
                'needs_action' => AppraisalRequest::query()
                    ->whereIn('status', [
                        AppraisalStatusEnum::Submitted,
                        AppraisalStatusEnum::DocsIncomplete,
                        AppraisalStatusEnum::Verified,
                        AppraisalStatusEnum::WaitingOffer,
                    ])
                    ->count(),
                'payment_pending' => AppraisalRequest::query()
                    ->where('status', AppraisalStatusEnum::ContractSigned)
                    ->count(),
            ],
            'records' => [
                'data' => $records->items(),
                'meta' => [
                    'from' => $records->firstItem(),
                    'to' => $records->lastItem(),
                    'total' => $records->total(),
                    'links' => $records->linkCollection()->toArray(),
                ],
            ],
            'legacyPanelUrl' => url('/legacy-admin'),
        ]);
    }

    public function appraisalRequestsShow(
        AppraisalRequest $appraisalRequest,
        AppraisalRequestWorkflowService $workflowService
    ): Response
    {
        $appraisalRequest->load([
            'guidelineSet',
            'user',
            'files',
            'assets.files',
            'payments' => fn ($query) => $query->latest('id'),
            'offerNegotiations' => fn ($query) => $query->with('user')->latest('id'),
        ]);

        $locationMaps = $this->buildLocationMaps($appraisalRequest);
        $latestCounterRequest = $appraisalRequest->offerNegotiations
            ->first(fn ($entry) => $entry->action === 'counter_request');

        return inertia('Admin/AppraisalRequests/Show', [
            'record' => [
                'id' => $appraisalRequest->id,
                'request_number' => $appraisalRequest->request_number ?? ('REQ-' . $appraisalRequest->id),
                'purpose_label' => $appraisalRequest->purpose?->label() ?? '-',
                'status_label' => $appraisalRequest->status?->label() ?? '-',
                'status_value' => $appraisalRequest->status?->value ?? null,
                'contract_status_label' => $appraisalRequest->contract_status?->label() ?? '-',
                'contract_status_value' => $appraisalRequest->contract_status?->value ?? null,
                'report_type_label' => $appraisalRequest->report_type?->label() ?? '-',
                'requested_at' => $appraisalRequest->requested_at?->toIso8601String(),
                'verified_at' => $appraisalRequest->verified_at?->toIso8601String(),
                'client_name' => $appraisalRequest->client_name ?: '-',
                'contract_number' => $appraisalRequest->contract_number ?: '-',
                'contract_date' => $appraisalRequest->contract_date?->toIso8601String(),
                'valuation_duration_days' => $appraisalRequest->valuation_duration_days,
                'offer_validity_days' => $appraisalRequest->offer_validity_days,
                'fee_total' => (int) ($appraisalRequest->fee_total ?? 0),
                'fee_has_dp' => (bool) $appraisalRequest->fee_has_dp,
                'fee_dp_percent' => $appraisalRequest->fee_dp_percent,
                'latest_expected_fee' => $latestCounterRequest?->expected_fee,
                'latest_negotiation_reason' => $latestCounterRequest?->reason,
                'notes' => $appraisalRequest->notes,
                'user_request_note' => $appraisalRequest->user_request_note,
                'guideline_set' => $appraisalRequest->guidelineSet?->name ?? '-',
                'legacy_url' => $this->legacyAppraisalRequestUrl($appraisalRequest),
            ],
            'requester' => [
                'id' => $appraisalRequest->user?->id,
                'name' => $appraisalRequest->user?->name ?? '-',
                'email' => $appraisalRequest->user?->email ?? '-',
            ],
            'availableActions' => $this->buildAvailableActions($appraisalRequest, $workflowService),
            'offerAction' => $this->buildOfferAction($appraisalRequest, $workflowService),
            'approveLatestNegotiationAction' => $this->buildApproveLatestNegotiationAction($appraisalRequest, $workflowService),
            'paymentVerification' => $this->buildPaymentVerification($appraisalRequest, $workflowService),
            'requestFiles' => $appraisalRequest->files
                ->map(fn ($file) => $this->transformRequestFile($file))
                ->values(),
            'assets' => $appraisalRequest->assets
                ->sortBy('id')
                ->values()
                ->map(fn ($asset, $index) => $this->transformAsset($asset, $index + 1, $locationMaps))
                ->values(),
            'assetCreateUrl' => route('admin.appraisal-requests.assets.create', $appraisalRequest),
            'payments' => $appraisalRequest->payments->map(fn ($payment) => [
                'id' => $payment->id,
                'amount' => (int) $payment->amount,
                'method_label' => $payment->method === 'gateway' ? 'Gateway' : 'Manual',
                'status' => $payment->status,
                'gateway' => $payment->gateway,
                'external_payment_id' => $payment->external_payment_id,
                'paid_at' => $payment->paid_at?->toIso8601String(),
            ])->values(),
            'negotiations' => $appraisalRequest->offerNegotiations->map(fn ($negotiation) => [
                'id' => $negotiation->id,
                'action_label' => $this->formatNegotiationAction($negotiation->action),
                'actor_name' => $negotiation->user?->name ?? 'System',
                'round' => $negotiation->round,
                'offered_fee' => $negotiation->offered_fee,
                'expected_fee' => $negotiation->expected_fee,
                'selected_fee' => $negotiation->selected_fee,
                'reason' => $negotiation->reason,
                'created_at' => $negotiation->created_at?->toIso8601String(),
            ])->values(),
            'requestFileTypeOptions' => $this->requestFileTypeOptions(),
            'legacyPanelUrl' => url('/legacy-admin'),
        ]);
    }

    public function appraisalRequestsEdit(AppraisalRequest $appraisalRequest): Response
    {
        return inertia('Admin/AppraisalRequests/Edit', [
            'record' => [
                'id' => $appraisalRequest->id,
                'request_number' => $appraisalRequest->request_number ?? ('REQ-' . $appraisalRequest->id),
                'client_name' => $appraisalRequest->client_name,
                'report_type' => $appraisalRequest->report_type?->value ?? $appraisalRequest->report_type,
                'contract_sequence' => $appraisalRequest->contract_sequence,
                'contract_number' => $appraisalRequest->contract_number,
                'contract_date' => $appraisalRequest->contract_date?->toDateString(),
                'contract_status' => $appraisalRequest->contract_status?->value ?? $appraisalRequest->contract_status,
                'valuation_duration_days' => $appraisalRequest->valuation_duration_days,
                'offer_validity_days' => $appraisalRequest->offer_validity_days,
                'fee_total' => $appraisalRequest->fee_total,
                'fee_has_dp' => (bool) $appraisalRequest->fee_has_dp,
                'fee_dp_percent' => $appraisalRequest->fee_dp_percent,
                'user_request_note' => $appraisalRequest->user_request_note,
                'notes' => $appraisalRequest->notes,
            ],
            'contractStatusOptions' => array_map(
                fn (ContractStatusEnum $status) => [
                    'value' => $status->value,
                    'label' => $status->label(),
                ],
                ContractStatusEnum::cases()
            ),
            'reportTypeOptions' => array_map(
                fn (ReportTypeEnum $type) => [
                    'value' => $type->value,
                    'label' => $type->label(),
                ],
                ReportTypeEnum::cases()
            ),
            'legacyPanelUrl' => url('/legacy-admin'),
        ]);
    }

    public function appraisalRequestsUpdate(
        UpdateAppraisalRequestBasicRequest $request,
        AppraisalRequest $appraisalRequest,
        AppraisalContractNumberService $contractNumberService
    ) {
        $validated = $request->validated();
        $contractMeta = $contractNumberService->deriveMetadata($validated['contract_sequence'] ?? null);
        $contractDate = $this->blankToNull($validated['contract_date'] ?? null);
        $contractStatus = array_key_exists('contract_status', $validated)
            ? ($this->blankToNull($validated['contract_status']) ?? ContractStatusEnum::None->value)
            : ($appraisalRequest->contract_status?->value ?? $appraisalRequest->contract_status ?? ContractStatusEnum::None->value);

        if (($validated['contract_sequence'] ?? null) && $contractDate === null) {
            $contractDate = now()->toDateString();
        }

        $appraisalRequest->update([
            'client_name' => $this->blankToNull($validated['client_name'] ?? null),
            'report_type' => $this->blankToNull($validated['report_type'] ?? null),
            'contract_sequence' => $this->blankToNull($validated['contract_sequence'] ?? null),
            'contract_number' => $contractMeta['contract_number'],
            'contract_office_code' => $contractMeta['contract_office_code'],
            'contract_month' => $contractMeta['contract_month'],
            'contract_year' => $contractMeta['contract_year'],
            'contract_date' => $contractDate,
            'contract_status' => $contractStatus,
            'valuation_duration_days' => $this->blankToNull($validated['valuation_duration_days'] ?? null),
            'offer_validity_days' => $this->blankToNull($validated['offer_validity_days'] ?? null),
            'fee_total' => $this->blankToNull($validated['fee_total'] ?? null),
            'fee_has_dp' => (bool) ($validated['fee_has_dp'] ?? false),
            'fee_dp_percent' => ($validated['fee_has_dp'] ?? false)
                ? $this->blankToNull($validated['fee_dp_percent'] ?? null)
                : null,
            'user_request_note' => $this->blankToNull($validated['user_request_note'] ?? null),
            'notes' => $this->blankToNull($validated['notes'] ?? null),
        ]);

        return redirect()
            ->route('admin.appraisal-requests.show', $appraisalRequest)
            ->with('success', 'Informasi dasar request berhasil diperbarui.');
    }

    public function appraisalRequestAssetCreate(Request $request, AppraisalRequest $appraisalRequest): Response
    {
        return inertia('Admin/AppraisalRequests/AssetForm', $this->buildAssetEditorProps($request, $appraisalRequest));
    }

    public function appraisalRequestAssetEdit(
        Request $request,
        AppraisalRequest $appraisalRequest,
        AppraisalAsset $asset
    ): Response {
        $this->ensureAssetBelongsToRequest($appraisalRequest, $asset);

        return inertia('Admin/AppraisalRequests/AssetForm', $this->buildAssetEditorProps($request, $appraisalRequest, $asset));
    }

    public function storeAppraisalRequestAsset(
        UpsertAppraisalAssetRequest $request,
        AppraisalRequest $appraisalRequest
    )
    {
        $asset = $appraisalRequest->assets()->create($this->assetPayload($request->validated()));

        return redirect()
            ->route('admin.appraisal-requests.show', $appraisalRequest)
            ->with('success', "Aset #{$asset->id} berhasil ditambahkan.");
    }

    public function updateAppraisalRequestAsset(
        UpsertAppraisalAssetRequest $request,
        AppraisalRequest $appraisalRequest,
        AppraisalAsset $asset
    )
    {
        $this->ensureAssetBelongsToRequest($appraisalRequest, $asset);

        $asset->update($this->assetPayload($request->validated()));

        return redirect()
            ->route('admin.appraisal-requests.show', $appraisalRequest)
            ->with('success', 'Data aset berhasil diperbarui.');
    }

    public function destroyAppraisalRequestAsset(
        AppraisalRequest $appraisalRequest,
        AppraisalAsset $asset
    )
    {
        $this->ensureAssetBelongsToRequest($appraisalRequest, $asset);

        foreach ($asset->files as $file) {
            Storage::disk('public')->delete($file->path);
        }

        $asset->delete();

        return back()->with('success', 'Aset berhasil dihapus.');
    }

    public function sendOffer(
        StoreAppraisalOfferRequest $request,
        AppraisalRequest $appraisalRequest,
        AppraisalRequestWorkflowService $workflowService
    )
    {
        try {
            $result = $workflowService->sendOffer(
                $appraisalRequest,
                (int) $request->user()->id,
                $request->validated()
            );

            $message = $result['action'] === 'offer_revised'
                ? 'Counter offer berhasil dikirim.'
                : 'Penawaran berhasil dikirim.';

            return back()->with('success', $message);
        } catch (\RuntimeException $exception) {
            return back()->with('error', $exception->getMessage());
        }
    }

    public function approveLatestNegotiation(
        AppraisalRequest $appraisalRequest,
        AppraisalRequestWorkflowService $workflowService,
        Request $request
    )
    {
        try {
            $workflowService->approveLatestNegotiation($appraisalRequest, (int) $request->user()->id);

            return back()->with('success', 'Harapan fee user disetujui dan counter offer berhasil dikirim.');
        } catch (\RuntimeException $exception) {
            return back()->with('error', $exception->getMessage());
        }
    }

    public function verifyDocs(
        AppraisalRequest $appraisalRequest,
        AppraisalRequestWorkflowService $workflowService
    )
    {
        try {
            $workflowService->verifyDocs($appraisalRequest);

            return back()->with('success', 'Dokumen berhasil diverifikasi. Request masuk ke tahap menunggu penawaran.');
        } catch (\RuntimeException $exception) {
            return back()->with('error', $exception->getMessage());
        }
    }

    public function markDocsIncomplete(
        AppraisalRequest $appraisalRequest,
        AppraisalRequestWorkflowService $workflowService
    )
    {
        try {
            $workflowService->markDocsIncomplete($appraisalRequest);

            return back()->with('success', 'Request berhasil ditandai dokumen kurang.');
        } catch (\RuntimeException $exception) {
            return back()->with('error', $exception->getMessage());
        }
    }

    public function markContractSigned(
        AppraisalRequest $appraisalRequest,
        AppraisalRequestWorkflowService $workflowService
    )
    {
        try {
            $workflowService->markContractSigned($appraisalRequest);

            return back()->with('success', 'Status kontrak berhasil diperbarui menjadi ditandatangani.');
        } catch (\RuntimeException $exception) {
            return back()->with('error', $exception->getMessage());
        }
    }

    public function verifyPayment(
        AppraisalRequest $appraisalRequest,
        AppraisalRequestWorkflowService $workflowService
    )
    {
        try {
            $workflowService->verifyPayment($appraisalRequest);

            return back()->with('success', 'Pembayaran terverifikasi. Request masuk ke proses valuasi.');
        } catch (\RuntimeException $exception) {
            return back()->with('error', $exception->getMessage());
        }
    }

    public function storeRequestFile(
        StoreAppraisalRequestFileRequest $request,
        AppraisalRequest $appraisalRequest
    )
    {
        $validated = $request->validated();
        $file = $request->file('file');
        $storedPath = $file->storeAs(
            "appraisal-requests/{$appraisalRequest->id}/request-files",
            now()->format('YmdHis') . '-' . uniqid() . '.' . $file->getClientOriginalExtension(),
            'public'
        );

        $appraisalRequest->files()->create([
            'type' => $validated['type'],
            'path' => $storedPath,
            'original_name' => $file->getClientOriginalName(),
            'mime' => $file->getMimeType(),
            'size' => $file->getSize(),
        ]);

        return back()->with('success', 'File request berhasil diunggah.');
    }

    public function destroyRequestFile(
        AppraisalRequest $appraisalRequest,
        AppraisalRequestFile $file
    )
    {
        abort_unless((int) $file->appraisal_request_id === (int) $appraisalRequest->id, 404);

        if ($file->type === 'contract_signed_pdf') {
            return back()->with('error', 'File kontrak tertandatangani tidak bisa dihapus dari workspace admin Vue.');
        }

        Storage::disk('public')->delete($file->path);
        $file->delete();

        return back()->with('success', 'File request berhasil dihapus.');
    }

    public function moduleShow(string $module): Response
    {
        $definition = $this->moduleDefinitions()[$module] ?? null;

        abort_if($definition === null, 404);

        return inertia('Admin/Modules/Show', [
            'module' => array_merge($definition, [
                'slug' => $module,
                'status_label' => $this->moduleStatusLabel($definition['status']),
            ]),
            'legacyPanelUrl' => url('/legacy-admin'),
        ]);
    }

    private function transformRequestListItem(AppraisalRequest $record): array
    {
        return [
            'id' => $record->id,
            'request_number' => $record->request_number ?? ('REQ-' . $record->id),
            'requester_name' => $record->user?->name ?? '-',
            'client_name' => $record->client_name ?: ($record->user?->name ?? '-'),
            'status_label' => $record->status?->label() ?? '-',
            'status_value' => $record->status?->value ?? null,
            'assets_count' => (int) ($record->assets_count ?? 0),
            'requested_at' => $record->requested_at?->toIso8601String(),
            'show_url' => route('admin.appraisal-requests.show', $record),
            'legacy_url' => $this->legacyAppraisalRequestUrl($record),
        ];
    }

    private function transformRequestTableRow(AppraisalRequest $record): array
    {
        return [
            'id' => $record->id,
            'request_number' => $record->request_number ?? ('REQ-' . $record->id),
            'requester_name' => $record->user?->name ?? '-',
            'client_name' => $record->client_name ?: ($record->user?->name ?? '-'),
            'status_label' => $record->status?->label() ?? '-',
            'status_value' => $record->status?->value ?? null,
            'contract_status_label' => $record->contract_status?->label() ?? '-',
            'contract_status_value' => $record->contract_status?->value ?? null,
            'assets_count' => (int) ($record->assets_count ?? 0),
            'negotiation_rounds_used' => (int) ($record->negotiation_rounds_used ?? 0),
            'fee_total' => (int) ($record->fee_total ?? 0),
            'requested_at' => $record->requested_at?->toIso8601String(),
            'show_url' => route('admin.appraisal-requests.show', $record),
            'legacy_url' => $this->legacyAppraisalRequestUrl($record),
        ];
    }

    private function legacyAppraisalRequestUrl(AppraisalRequest $record): ?string
    {
        try {
            return route('filament.admin.resources.appraisal-requests.view', ['record' => $record]);
        } catch (\Throwable) {
            return null;
        }
    }

    private function formatNegotiationAction(?string $action): string
    {
        return match ($action) {
            'offer_sent' => 'Penawaran dikirim',
            'offer_revised' => 'Counter offer dikirim',
            'counter_request' => 'Pengajuan negosiasi',
            'selected' => 'Fee dipilih',
            'accept_offer' => 'Penawaran diterima',
            'accepted' => 'Penawaran diterima',
            'contract_sign_mock' => 'Tanda tangan kontrak',
            'cancel_request' => 'Permohonan dibatalkan',
            'cancelled' => 'Negosiasi dibatalkan',
            default => Arr::headline((string) $action),
        };
    }

    private function buildLocationMaps(AppraisalRequest $appraisalRequest): array
    {
        $provinceIds = $appraisalRequest->assets->pluck('province_id')->filter()->unique()->values();
        $regencyIds = $appraisalRequest->assets->pluck('regency_id')->filter()->unique()->values();
        $districtIds = $appraisalRequest->assets->pluck('district_id')->filter()->unique()->values();
        $villageIds = $appraisalRequest->assets->pluck('village_id')->filter()->unique()->values();

        return [
            'province' => Province::query()->whereIn('id', $provinceIds)->pluck('name', 'id')->all(),
            'regency' => Regency::query()->whereIn('id', $regencyIds)->pluck('name', 'id')->all(),
            'district' => District::query()->whereIn('id', $districtIds)->pluck('name', 'id')->all(),
            'village' => Village::query()->whereIn('id', $villageIds)->pluck('name', 'id')->all(),
        ];
    }

    private function transformRequestFile(object $file): array
    {
        return [
            'id' => $file->id,
            'type' => (string) $file->type,
            'type_label' => $this->requestFileTypeLabel($file->type),
            'can_delete' => (string) $file->type !== 'contract_signed_pdf',
            'original_name' => $file->original_name ?: basename((string) $file->path),
            'mime' => $file->mime,
            'size' => (int) ($file->size ?? 0),
            'size_label' => $this->formatBytes($file->size),
            'url' => Storage::disk('public')->url($file->path),
            'created_at' => $file->created_at?->toIso8601String(),
        ];
    }

    private function transformAsset(AppraisalAsset $asset, int $order, array $locationMaps): array
    {
        $files = $asset->files->sortByDesc('created_at')->values();

        return [
            'id' => $asset->id,
            'order' => $order,
            'asset_code' => $asset->asset_code,
            'address' => $asset->address ?: 'Alamat belum diisi',
            'asset_type' => $asset->asset_type ?: '-',
            'asset_type_label' => AssetTypeEnum::tryFrom((string) $asset->asset_type)?->label() ?? ($asset->asset_type ?: '-'),
            'peruntukan' => $asset->peruntukan,
            'peruntukan_label' => $this->assetOptionLabel('usage', $asset->peruntukan),
            'title_document_label' => $this->assetOptionLabel('title_document', $asset->title_document),
            'land_shape_label' => $this->assetOptionLabel('land_shape', $asset->land_shape),
            'land_position_label' => $this->assetOptionLabel('land_position', $asset->land_position),
            'land_condition_label' => $this->assetOptionLabel('land_condition', $asset->land_condition),
            'topography_label' => $this->assetOptionLabel('topography', $asset->topography),
            'province_name' => $locationMaps['province'][$asset->province_id] ?? null,
            'regency_name' => $locationMaps['regency'][$asset->regency_id] ?? null,
            'district_name' => $locationMaps['district'][$asset->district_id] ?? null,
            'village_name' => $locationMaps['village'][$asset->village_id] ?? null,
            'maps_link' => $asset->maps_link,
            'coordinates_lat' => $asset->coordinates_lat,
            'coordinates_lng' => $asset->coordinates_lng,
            'land_area' => $asset->land_area,
            'building_area' => $asset->building_area,
            'building_floors' => $asset->building_floors,
            'build_year' => $asset->build_year,
            'renovation_year' => $asset->renovation_year,
            'frontage_width' => $asset->frontage_width,
            'access_road_width' => $asset->access_road_width,
            'land_value_final' => $asset->land_value_final,
            'building_value_final' => $asset->building_value_final,
            'market_value_final' => $asset->market_value_final,
            'estimated_value_low' => $asset->estimated_value_low,
            'estimated_value_high' => $asset->estimated_value_high,
            'edit_url' => route('admin.appraisal-requests.assets.edit', [$asset->appraisal_request_id, $asset]),
            'destroy_url' => route('admin.appraisal-requests.assets.destroy', [$asset->appraisal_request_id, $asset]),
            'documents' => $files
                ->whereIn('type', ['doc_pbb', 'doc_imb', 'doc_certs'])
                ->map(fn ($file) => $this->transformAssetFile($file))
                ->values(),
            'photos' => $files
                ->whereIn('type', ['photo_access_road', 'photo_front', 'photo_interior'])
                ->map(fn ($file) => $this->transformAssetFile($file))
                ->values(),
        ];
    }

    private function transformAssetFile(object $file): array
    {
        return [
            'id' => $file->id,
            'type' => (string) $file->type,
            'type_label' => $this->assetFileTypeLabel($file->type),
            'original_name' => $file->original_name ?: basename((string) $file->path),
            'mime' => $file->mime,
            'size' => (int) ($file->size ?? 0),
            'size_label' => $this->formatBytes($file->size),
            'url' => Storage::disk('public')->url($file->path),
            'created_at' => $file->created_at?->toIso8601String(),
        ];
    }

    private function assetOptionLabel(string $group, ?string $value): ?string
    {
        if (blank($value)) {
            return null;
        }

        $options = match ($group) {
            'usage' => AppraisalAssetFieldOptions::toSelectMap(AppraisalAssetFieldOptions::usageOptions()),
            'title_document' => AppraisalAssetFieldOptions::toSelectMap(AppraisalAssetFieldOptions::titleDocumentOptions()),
            'land_shape' => AppraisalAssetFieldOptions::toSelectMap(AppraisalAssetFieldOptions::landShapeOptions()),
            'land_position' => AppraisalAssetFieldOptions::toSelectMap(AppraisalAssetFieldOptions::landPositionOptions()),
            'land_condition' => AppraisalAssetFieldOptions::toSelectMap(AppraisalAssetFieldOptions::landConditionOptions()),
            'topography' => AppraisalAssetFieldOptions::toSelectMap(AppraisalAssetFieldOptions::topographyOptions()),
            default => [],
        };

        return $options[$value] ?? Arr::headline($value);
    }

    private function requestFileTypeLabel(?string $type): string
    {
        return match ((string) $type) {
            'contract_signed_pdf' => 'PDF Kontrak Ditandatangani',
            'npwp' => 'NPWP',
            'representative' => 'Surat Kuasa',
            'permission' => 'Surat Izin',
            'other_request_document' => 'Lampiran Request',
            default => Arr::headline((string) $type),
        };
    }

    private function assetFileTypeLabel(?string $type): string
    {
        return match ((string) $type) {
            'doc_pbb' => 'PBB',
            'doc_imb' => 'IMB / PBG',
            'doc_certs' => 'Sertifikat',
            'photo_access_road' => 'Foto Akses Jalan',
            'photo_front' => 'Foto Depan',
            'photo_interior' => 'Foto Dalam',
            default => Arr::headline((string) $type),
        };
    }

    private function formatBytes(mixed $bytes): string
    {
        if (! is_numeric($bytes) || (float) $bytes <= 0) {
            return '0 B';
        }

        $number = (float) $bytes;
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $index = (int) floor(log($number, 1024));
        $index = min($index, count($units) - 1);
        $value = $number / (1024 ** $index);

        return sprintf('%s %s', number_format($value, $index === 0 ? 0 : 2), $units[$index]);
    }

    private function moduleCards(): array
    {
        $cards = [];

        foreach ($this->moduleDefinitions() as $slug => $definition) {
            $cards[] = [
                'slug' => $slug,
                'title' => $definition['title'],
                'description' => $definition['description'],
                'resource_count' => count($definition['legacy_resources']),
                'status' => $definition['status'],
                'status_label' => $this->moduleStatusLabel($definition['status']),
                'show_url' => route('admin.modules.show', ['module' => $slug]),
            ];
        }

        return $cards;
    }

    private function blankToNull(mixed $value): mixed
    {
        return is_string($value) && trim($value) === '' ? null : $value;
    }

    private function buildAssetEditorProps(
        Request $request,
        AppraisalRequest $appraisalRequest,
        ?AppraisalAsset $asset = null
    ): array {
        $provinceId = $this->blankToNull($request->query('province_id', $asset?->province_id));
        $regencyId = $this->blankToNull($request->query('regency_id', $asset?->regency_id));
        $districtId = $this->blankToNull($request->query('district_id', $asset?->district_id));

        return [
            'mode' => $asset ? 'edit' : 'create',
            'requestRecord' => [
                'id' => $appraisalRequest->id,
                'request_number' => $appraisalRequest->request_number ?? ('REQ-' . $appraisalRequest->id),
                'show_url' => route('admin.appraisal-requests.show', $appraisalRequest),
                'legacy_url' => $this->legacyAppraisalRequestUrl($appraisalRequest),
            ],
            'record' => $this->assetFormRecord($asset),
            'assetTypeOptions' => [
                ['value' => AssetTypeEnum::TANAH->value, 'label' => AssetTypeEnum::TANAH->label()],
                ['value' => AssetTypeEnum::TANAH_BANGUNAN->value, 'label' => AssetTypeEnum::TANAH_BANGUNAN->label()],
            ],
            'usageOptions' => AppraisalAssetFieldOptions::usageOptions(),
            'titleDocumentOptions' => AppraisalAssetFieldOptions::titleDocumentOptions(),
            'landShapeOptions' => AppraisalAssetFieldOptions::landShapeOptions(),
            'landPositionOptions' => AppraisalAssetFieldOptions::landPositionOptions(),
            'landConditionOptions' => AppraisalAssetFieldOptions::landConditionOptions(),
            'topographyOptions' => AppraisalAssetFieldOptions::topographyOptions(),
            'provinces' => Province::query()->select(['id', 'name'])->orderBy('name')->get()->values(),
            'regencies' => $provinceId
                ? Regency::query()->select(['id', 'name'])->where('province_id', $provinceId)->orderBy('name')->get()->values()
                : [],
            'districts' => $regencyId
                ? District::query()->select(['id', 'name'])->where('regency_id', $regencyId)->orderBy('name')->get()->values()
                : [],
            'villages' => $districtId
                ? Village::query()->select(['id', 'name'])->where('district_id', $districtId)->orderBy('name')->get()->values()
                : [],
            'legacyPanelUrl' => url('/legacy-admin'),
        ];
    }

    private function assetFormRecord(?AppraisalAsset $asset): array
    {
        return [
            'id' => $asset?->id,
            'asset_code' => $asset?->asset_code,
            'asset_type' => $asset?->asset_type,
            'peruntukan' => $asset?->peruntukan,
            'title_document' => $asset?->title_document,
            'land_shape' => $asset?->land_shape,
            'land_position' => $asset?->land_position,
            'land_condition' => $asset?->land_condition,
            'topography' => $asset?->topography,
            'province_id' => $asset?->province_id,
            'regency_id' => $asset?->regency_id,
            'district_id' => $asset?->district_id,
            'village_id' => $asset?->village_id,
            'address' => $asset?->address,
            'maps_link' => $asset?->maps_link,
            'coordinates_lat' => $asset?->coordinates_lat,
            'coordinates_lng' => $asset?->coordinates_lng,
            'land_area' => $asset?->land_area,
            'building_area' => $asset?->building_area,
            'building_floors' => $asset?->building_floors,
            'build_year' => $asset?->build_year,
            'renovation_year' => $asset?->renovation_year,
            'frontage_width' => $asset?->frontage_width,
            'access_road_width' => $asset?->access_road_width,
        ];
    }

    private function assetPayload(array $validated): array
    {
        return [
            'asset_code' => $this->blankToNull($validated['asset_code'] ?? null),
            'asset_type' => $validated['asset_type'],
            'peruntukan' => $this->blankToNull($validated['peruntukan'] ?? null),
            'title_document' => $this->blankToNull($validated['title_document'] ?? null),
            'land_shape' => $this->blankToNull($validated['land_shape'] ?? null),
            'land_position' => $this->blankToNull($validated['land_position'] ?? null),
            'land_condition' => $this->blankToNull($validated['land_condition'] ?? null),
            'topography' => $this->blankToNull($validated['topography'] ?? null),
            'province_id' => $this->blankToNull($validated['province_id'] ?? null),
            'regency_id' => $this->blankToNull($validated['regency_id'] ?? null),
            'district_id' => $this->blankToNull($validated['district_id'] ?? null),
            'village_id' => $this->blankToNull($validated['village_id'] ?? null),
            'address' => $this->blankToNull($validated['address'] ?? null),
            'maps_link' => $this->blankToNull($validated['maps_link'] ?? null),
            'coordinates_lat' => $this->blankToNull($validated['coordinates_lat'] ?? null),
            'coordinates_lng' => $this->blankToNull($validated['coordinates_lng'] ?? null),
            'land_area' => $this->blankToNull($validated['land_area'] ?? null),
            'building_area' => $this->blankToNull($validated['building_area'] ?? null),
            'building_floors' => $this->blankToNull($validated['building_floors'] ?? null),
            'build_year' => $this->blankToNull($validated['build_year'] ?? null),
            'renovation_year' => $this->blankToNull($validated['renovation_year'] ?? null),
            'frontage_width' => $this->blankToNull($validated['frontage_width'] ?? null),
            'access_road_width' => $this->blankToNull($validated['access_road_width'] ?? null),
        ];
    }

    private function ensureAssetBelongsToRequest(AppraisalRequest $appraisalRequest, AppraisalAsset $asset): void
    {
        abort_unless((int) $asset->appraisal_request_id === (int) $appraisalRequest->id, 404);
    }

    private function buildAvailableActions(
        AppraisalRequest $appraisalRequest,
        AppraisalRequestWorkflowService $workflowService
    ): array {
        $actions = [];

        if ($workflowService->canVerifyDocs($appraisalRequest)) {
            $actions[] = [
                'key' => 'verify-docs',
                'label' => 'Verifikasi Dokumen',
                'variant' => 'default',
                'message' => 'Lanjutkan request ini ke tahap menunggu penawaran?',
                'url' => route('admin.appraisal-requests.actions.verify-docs', $appraisalRequest),
            ];
        }

        if ($workflowService->canMarkDocsIncomplete($appraisalRequest)) {
            $actions[] = [
                'key' => 'docs-incomplete',
                'label' => 'Tandai Dokumen Kurang',
                'variant' => 'outline',
                'message' => 'Tandai request ini sebagai dokumen kurang?',
                'url' => route('admin.appraisal-requests.actions.docs-incomplete', $appraisalRequest),
            ];
        }

        if ($workflowService->canMarkContractSigned($appraisalRequest)) {
            $actions[] = [
                'key' => 'contract-signed',
                'label' => 'Kontrak Ditandatangani',
                'variant' => 'default',
                'message' => 'Ubah status request ini menjadi kontrak ditandatangani?',
                'url' => route('admin.appraisal-requests.actions.contract-signed', $appraisalRequest),
            ];
        }

        if ($workflowService->canVerifyPayment($appraisalRequest)) {
            $actions[] = [
                'key' => 'verify-payment',
                'label' => 'Verifikasi Pembayaran',
                'variant' => 'default',
                'message' => 'Pembayaran sudah valid. Lanjutkan request ini ke proses valuasi?',
                'url' => route('admin.appraisal-requests.actions.verify-payment', $appraisalRequest),
            ];
        }

        return $actions;
    }

    private function buildOfferAction(
        AppraisalRequest $appraisalRequest,
        AppraisalRequestWorkflowService $workflowService
    ): ?array {
        if (! $workflowService->canSendOffer($appraisalRequest)) {
            return null;
        }

        $defaults = $workflowService->resolveOfferDefaults($appraisalRequest);
        $statusValue = $appraisalRequest->status?->value ?? $appraisalRequest->status;

        return [
            'label' => $statusValue === AppraisalStatusEnum::WaitingOffer->value
                ? 'Kirim Counter Offer'
                : 'Kirim Penawaran',
            'description' => $statusValue === AppraisalStatusEnum::WaitingOffer->value
                ? 'Gunakan form ini untuk merespons negosiasi user dengan penawaran revisi.'
                : 'Gunakan form ini untuk mengirim penawaran awal ke user.',
            'url' => route('admin.appraisal-requests.actions.send-offer', $appraisalRequest),
            'defaults' => $defaults,
        ];
    }

    private function buildApproveLatestNegotiationAction(
        AppraisalRequest $appraisalRequest,
        AppraisalRequestWorkflowService $workflowService
    ): ?array {
        if (! $workflowService->canApproveLatestNegotiation($appraisalRequest)) {
            return null;
        }

        $latestCounter = $workflowService->latestCounterRequest($appraisalRequest);

        if ($latestCounter === null) {
            return null;
        }

        return [
            'label' => 'Setujui Harapan Fee User',
            'message' => 'Fee akan mengikuti harapan fee terbaru dari user dan counter offer langsung dikirim. Lanjutkan?',
            'url' => route('admin.appraisal-requests.actions.approve-latest-negotiation', $appraisalRequest),
            'expected_fee' => $latestCounter->expected_fee,
            'round' => $latestCounter->round,
            'reason' => $latestCounter->reason,
        ];
    }

    private function buildPaymentVerification(
        AppraisalRequest $appraisalRequest,
        AppraisalRequestWorkflowService $workflowService
    ): ?array {
        $state = $workflowService->paymentVerificationState($appraisalRequest);

        if (! ($state['show'] ?? false)) {
            return null;
        }

        return [
            'ready' => (bool) ($state['ready'] ?? false),
            'message' => $state['message'] ?? null,
            'action_url' => $workflowService->canVerifyPayment($appraisalRequest)
                ? route('admin.appraisal-requests.actions.verify-payment', $appraisalRequest)
                : null,
        ];
    }

    private function requestFileTypeOptions(): array
    {
        return [
            ['value' => 'npwp', 'label' => 'NPWP'],
            ['value' => 'representative', 'label' => 'Surat Kuasa'],
            ['value' => 'permission', 'label' => 'Surat Izin'],
            ['value' => 'other_request_document', 'label' => 'Lampiran Request'],
        ];
    }

    private function moduleStatusLabel(string $status): string
    {
        return match ($status) {
            'in_progress' => 'Sedang dimigrasikan',
            'planned' => 'Belum dimigrasikan',
            'bridge' => 'Butuh jembatan backend',
            default => 'Legacy',
        };
    }

    private function moduleDefinitions(): array
    {
        return [
            'payments' => [
                'title' => 'Keuangan',
                'description' => 'Menggantikan PaymentResource dan OfficeBankAccountResource berikut alur verifikasi pembayaran.',
                'status' => 'bridge',
                'legacy_resources' => [
                    'PaymentResource',
                    'OfficeBankAccountResource',
                ],
                'dependencies' => [
                    'PaymentController masih mengirim database notification dengan builder Filament.',
                    'Verifikasi pembayaran di legacy admin masih menjadi sumber kebenaran untuk beberapa aksi.',
                ],
            ],
            'content' => [
                'title' => 'Konten',
                'description' => 'Migrasi artikel, kategori artikel, dan tag ke halaman Vue dengan editor yang bisa diganti.',
                'status' => 'planned',
                'legacy_resources' => [
                    'ArticleResource',
                    'ArticleCategoryResource',
                    'TagResource',
                ],
                'dependencies' => [
                    'ArticleController publik sudah Inertia, tetapi CMS penulisannya masih Filament.',
                ],
            ],
            'legal-content' => [
                'title' => 'Konten & Legal',
                'description' => 'Dokumen legal, FAQ, feature highlight, testimonial, dan log persetujuan pengguna.',
                'status' => 'planned',
                'legacy_resources' => [
                    'ConsentDocumentResource',
                    'TermsDocumentResource',
                    'PrivacyPolicyResource',
                    'FaqResource',
                    'FeatureResource',
                    'TestimonialResource',
                    'AppraisalUserConsentResource',
                ],
                'dependencies' => [
                    'Ada editor Tiptap khusus Filament pada resource legal tertentu.',
                ],
            ],
            'communications' => [
                'title' => 'Komunikasi',
                'description' => 'Inbox pesan kontak dari landing page dan audit tindak lanjutnya.',
                'status' => 'planned',
                'legacy_resources' => [
                    'ContactMessageResource',
                ],
                'dependencies' => [
                    'LandingController sudah menyimpan pesan, admin inbox masih hanya tersedia di Filament.',
                ],
            ],
            'master-data' => [
                'title' => 'Master Data',
                'description' => 'User terdaftar dan daftar nama lokasi yang dipakai lintas flow penilaian.',
                'status' => 'planned',
                'legacy_resources' => [
                    'UserResource',
                    'ProvinceResource',
                    'RegencyResource',
                    'DistrictResource',
                    'VillageResource',
                ],
                'dependencies' => [
                    'Cluster DaftarNamaLokasi masih murni CRUD Filament.',
                ],
            ],
            'ref-guidelines' => [
                'title' => 'Ref Guidelines',
                'description' => 'Seluruh referensi appraisal, termasuk guideline set, cost element, index, dan page IKK per provinsi.',
                'status' => 'planned',
                'legacy_resources' => [
                    'RefGuidelineSetResource',
                    'BuildingEconomicLifeResource',
                    'ConstructionCostIndexResource',
                    'CostElementResource',
                    'FloorIndexResource',
                    'MappiRcnStandardResource',
                    'ValuationSettingResource',
                    'IkkByProvince Page',
                ],
                'dependencies' => [
                    'Ada custom page Filament dengan repeater dan transaction save mass update.',
                ],
            ],
            'access-control' => [
                'title' => 'Hak Akses',
                'description' => 'Migrasi RoleResource dan ketergantungan ke filament-shield untuk admin policy management.',
                'status' => 'bridge',
                'legacy_resources' => [
                    'RoleResource',
                ],
                'dependencies' => [
                    'User model masih memakai spatie/permission dan konfigurasi super_admin dari filament-shield.',
                    'Policy Role saat ini dihasilkan oleh shield dan belum dipindah ke UI Vue.',
                ],
            ],
        ];
    }
}

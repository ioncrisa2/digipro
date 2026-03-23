<?php

namespace App\Http\Controllers\Admin;

use App\Enums\AppraisalStatusEnum;
use App\Enums\AssetTypeEnum;
use App\Enums\ContractStatusEnum;
use App\Enums\ReportTypeEnum;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreArticleCategoryRequest;
use App\Http\Requests\Admin\StoreArticleRequest;
use App\Http\Requests\Admin\StoreConsentDocumentRequest;
use App\Http\Requests\Admin\StoreConstructionCostIndexRequest;
use App\Http\Requests\Admin\StoreCostElementRequest;
use App\Http\Requests\Admin\StoreFloorIndexRequest;
use App\Http\Requests\Admin\StoreDistrictRequest;
use App\Http\Requests\Admin\StoreFaqRequest;
use App\Http\Requests\Admin\StoreFeatureRequest;
use App\Http\Requests\Admin\StoreLegalDocumentRequest;
use App\Http\Requests\Admin\StoreOfficeBankAccountRequest;
use App\Http\Requests\Admin\StoreAppraisalAssetFileRequest;
use App\Http\Requests\Admin\StoreAppraisalRequestFileRequest;
use App\Http\Requests\Admin\StoreAppraisalOfferRequest;
use App\Http\Requests\Admin\StoreProvinceRequest;
use App\Http\Requests\Admin\StoreRegencyRequest;
use App\Http\Requests\Admin\StoreTagRequest;
use App\Http\Requests\Admin\StoreTestimonialRequest;
use App\Http\Requests\Admin\StoreVillageRequest;
use App\Http\Requests\Admin\StoreRoleRequest;
use App\Http\Requests\Admin\StoreUserRequest;
use App\Http\Requests\Admin\StoreGuidelineSetRequest;
use App\Http\Requests\Admin\StoreMappiRcnStandardRequest;
use App\Http\Requests\Admin\StoreValuationSettingRequest;
use App\Http\Requests\Admin\UpsertAppraisalAssetRequest;
use App\Http\Requests\Admin\UpdatePaymentRequest;
use App\Http\Requests\Admin\UpdateAppraisalRequestBasicRequest;
use App\Models\AppraisalAsset;
use App\Models\AppraisalAssetFile;
use App\Models\AppraisalRequestFile;
use App\Models\AppraisalRequest;
use App\Models\AppraisalUserConsent;
use App\Models\Article;
use App\Models\ArticleCategory;
use App\Models\ConsentDocument;
use App\Models\ConstructionCostIndex;
use App\Models\ContactMessage;
use App\Models\CostElement;
use App\Models\District;
use App\Models\Faq;
use App\Models\Feature;
use App\Models\FloorIndex;
use App\Models\GuidelineSet;
use App\Models\MappiRcnStandard;
use App\Models\OfficeBankAccount;
use App\Models\Payment;
use App\Models\PrivacyPolicy;
use App\Models\Province;
use App\Models\Regency;
use App\Models\Tag;
use App\Models\Testimonial;
use App\Models\TermsDocument;
use App\Models\User;
use App\Models\ValuationSetting;
use App\Models\Village;
use App\Services\Admin\AppraisalContractNumberService;
use App\Services\Admin\AppraisalRequestWorkflowService;
use App\Services\Location\LocationIdGenerator;
use App\Services\Payments\MidtransSnapService;
use App\Support\AppraisalAssetFieldOptions;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Response;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

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
            'assetDocumentTypeOptions' => $this->assetDocumentTypeOptions(),
            'assetPhotoTypeOptions' => $this->assetPhotoTypeOptions(),
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
                'action_value' => (string) $negotiation->action,
                'action_label' => $this->formatNegotiationAction($negotiation->action),
                'action_tone' => $this->negotiationActionTone($negotiation->action),
                'actor_name' => $negotiation->user?->name ?? 'System',
                'round' => $negotiation->round,
                'offered_fee' => $negotiation->offered_fee,
                'expected_fee' => $negotiation->expected_fee,
                'selected_fee' => $negotiation->selected_fee,
                'reason' => $negotiation->reason,
                'created_at' => $negotiation->created_at?->toIso8601String(),
            ])->values(),
            'negotiationActionOptions' => $this->negotiationActionOptions($appraisalRequest),
            'negotiationSummary' => $this->negotiationSummary($appraisalRequest),
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

    public function storeAppraisalAssetFile(
        StoreAppraisalAssetFileRequest $request,
        AppraisalRequest $appraisalRequest,
        AppraisalAsset $asset
    )
    {
        $this->ensureAssetBelongsToRequest($appraisalRequest, $asset);

        $validated = $request->validated();
        $file = $request->file('file');
        $directory = $this->assetFileDirectory($validated['type']);
        $storedPath = $file->storeAs(
            "appraisal-requests/{$appraisalRequest->id}/assets/{$asset->id}/{$directory}",
            now()->format('YmdHis') . '-' . uniqid() . '.' . $file->getClientOriginalExtension(),
            'public'
        );

        $asset->files()->create([
            'type' => $validated['type'],
            'path' => $storedPath,
            'original_name' => $file->getClientOriginalName(),
            'mime' => $file->getMimeType(),
            'size' => $file->getSize(),
        ]);

        return back()->with('success', 'File aset berhasil diunggah.');
    }

    public function destroyAppraisalAssetFile(
        AppraisalRequest $appraisalRequest,
        AppraisalAsset $asset,
        AppraisalAssetFile $file
    )
    {
        $this->ensureAssetBelongsToRequest($appraisalRequest, $asset);
        abort_unless((int) $file->appraisal_asset_id === (int) $asset->id, 404);

        Storage::disk('public')->delete($file->path);
        $file->delete();

        return back()->with('success', 'File aset berhasil dihapus.');
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

    public function usersIndex(Request $request): Response
    {
        $filters = [
            'q' => trim((string) $request->query('q', '')),
            'role' => (string) $request->query('role', 'all'),
            'verified' => (string) $request->query('verified', 'all'),
        ];

        $records = User::query()
            ->with('roles:id,name')
            ->when($filters['q'] !== '', function ($query) use ($filters): void {
                $query->where(function ($innerQuery) use ($filters): void {
                    $innerQuery
                        ->where('name', 'like', '%' . $filters['q'] . '%')
                        ->orWhere('email', 'like', '%' . $filters['q'] . '%');
                });
            })
            ->when(
                $filters['role'] !== 'all',
                fn ($query) => $query->role($filters['role'])
            )
            ->when($filters['verified'] === 'verified', fn ($query) => $query->whereNotNull('email_verified_at'))
            ->when($filters['verified'] === 'unverified', fn ($query) => $query->whereNull('email_verified_at'))
            ->latest('created_at')
            ->paginate(15)
            ->withQueryString();

        $records->through(fn (User $user) => $this->transformUserRow($user));

        return inertia('Admin/Users/Index', [
            'filters' => $filters,
            'roleOptions' => $this->roleSelectOptions(),
            'verifiedOptions' => [
                ['value' => 'all', 'label' => 'Semua Status'],
                ['value' => 'verified', 'label' => 'Verified'],
                ['value' => 'unverified', 'label' => 'Belum Verified'],
            ],
            'summary' => [
                'total' => User::query()->count(),
                'verified' => User::query()->whereNotNull('email_verified_at')->count(),
                'admins' => User::role(['admin', $this->superAdminRoleName()])->count(),
                'reviewers' => User::role('Reviewer')->count(),
            ],
            'records' => $this->paginatedRecordsPayload($records),
            'canCreate' => $this->canManageUsersCreate(),
            'createUrl' => route('admin.master-data.users.create'),
            'legacyPanelUrl' => url('/legacy-admin'),
        ]);
    }

    public function usersCreate(): Response
    {
        abort_unless($this->canManageUsersCreate(), 403);

        return inertia('Admin/Users/Form', [
            'mode' => 'create',
            'record' => [
                'name' => '',
                'email' => '',
                'email_verified_at' => '',
                'roles' => [],
                'legacy_url' => null,
            ],
            'roleOptions' => $this->roleSelectOptions(),
            'indexUrl' => route('admin.master-data.users.index'),
            'submitUrl' => route('admin.master-data.users.store'),
            'legacyPanelUrl' => url('/legacy-admin'),
        ]);
    }

    public function usersStore(StoreUserRequest $request): RedirectResponse
    {
        abort_unless($this->canManageUsersCreate(), 403);

        $validated = $request->validated();

        $user = new User();
        $user->forceFill([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => $validated['password'],
            'email_verified_at' => $validated['email_verified_at'] ?? null,
        ]);
        $user->save();

        $user->syncRoles($validated['roles'] ?? []);

        return redirect()
            ->route('admin.master-data.users.show', $user)
            ->with('success', 'User berhasil ditambahkan.');
    }

    public function usersShow(User $user): Response
    {
        $user->loadMissing('roles:id,name');

        return inertia('Admin/Users/Show', [
            'record' => $this->userShowPayload($user),
            'indexUrl' => route('admin.master-data.users.index'),
            'editUrl' => route('admin.master-data.users.edit', $user),
            'legacyPanelUrl' => url('/legacy-admin'),
        ]);
    }

    public function usersEdit(User $user): Response
    {
        $user->loadMissing('roles:id,name');

        return inertia('Admin/Users/Form', [
            'mode' => 'edit',
            'record' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'email_verified_at' => $user->email_verified_at?->format('Y-m-d\TH:i'),
                'roles' => $user->roles->pluck('name')->values()->all(),
                'legacy_url' => $this->legacyUserUrl($user),
            ],
            'roleOptions' => $this->roleSelectOptions(),
            'indexUrl' => route('admin.master-data.users.index'),
            'submitUrl' => route('admin.master-data.users.update', $user),
            'legacyPanelUrl' => url('/legacy-admin'),
        ]);
    }

    public function usersUpdate(StoreUserRequest $request, User $user): RedirectResponse
    {
        $validated = $request->validated();

        $payload = [
            'name' => $validated['name'],
            'email' => $validated['email'],
            'email_verified_at' => $validated['email_verified_at'] ?? null,
        ];

        if (filled($validated['password'] ?? null)) {
            $payload['password'] = $validated['password'];
        }

        $user->forceFill($payload)->save();
        $user->syncRoles($validated['roles'] ?? []);

        return redirect()
            ->route('admin.master-data.users.show', $user)
            ->with('success', 'User berhasil diperbarui.');
    }

    public function locationIdPreview(Request $request, LocationIdGenerator $generator): JsonResponse
    {
        $validated = $request->validate([
            'type' => ['required', 'in:province,regency,district,village'],
            'province_id' => ['nullable', 'string', 'size:2'],
            'regency_id' => ['nullable', 'string', 'size:4'],
            'district_id' => ['nullable', 'string', 'size:7'],
        ]);

        try {
            $id = DB::transaction(function () use ($validated, $generator) {
                return match ($validated['type']) {
                    'province' => $generator->nextProvinceId(),
                    'regency' => $generator->nextRegencyId((string) ($validated['province_id'] ?? '')),
                    'district' => $generator->nextDistrictId((string) ($validated['regency_id'] ?? '')),
                    'village' => $generator->nextVillageId((string) ($validated['district_id'] ?? '')),
                };
            });
        } catch (\InvalidArgumentException|\RuntimeException $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
            ], 422);
        }

        return response()->json([
            'id' => $id,
        ]);
    }

    public function locationOptions(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'type' => ['required', 'in:provinces,regencies,districts'],
            'province_id' => ['nullable', 'string', 'size:2'],
            'regency_id' => ['nullable', 'string', 'size:4'],
        ]);

        $options = match ($validated['type']) {
            'provinces' => $this->provinceSelectOptions(),
            'regencies' => $this->regencySelectOptionsByProvince($validated['province_id'] ?? null),
            'districts' => $this->districtSelectOptionsByRegency($validated['regency_id'] ?? null),
        };

        return response()->json([
            'options' => $options,
        ]);
    }

    public function rolesIndex(Request $request): Response
    {
        $this->authorizeRoleAbility('view_any_role');

        $filters = [
            'q' => trim((string) $request->query('q', '')),
            'guard' => (string) $request->query('guard', 'all'),
        ];

        $records = Role::query()
            ->withCount('permissions')
            ->when($filters['q'] !== '', function ($query) use ($filters): void {
                $query->where('name', 'like', '%' . $filters['q'] . '%');
            })
            ->when($filters['guard'] !== 'all', fn ($query) => $query->where('guard_name', $filters['guard']))
            ->latest('updated_at')
            ->paginate(15)
            ->withQueryString();

        $records->through(fn (Role $role) => $this->transformRoleRow($role));

        return inertia('Admin/Roles/Index', [
            'filters' => $filters,
            'guardOptions' => $this->roleGuardOptions(),
            'summary' => [
                'total' => Role::query()->count(),
                'web' => Role::query()->where('guard_name', 'web')->count(),
                'permissions' => Permission::query()->count(),
                'super_admins' => User::role($this->superAdminRoleName())->count(),
            ],
            'records' => $this->paginatedRecordsPayload($records),
            'canCreate' => $this->roleAbility('create_role'),
            'canDeleteAny' => $this->roleAbility('delete_any_role') || $this->roleAbility('delete_role'),
            'createUrl' => route('admin.access-control.roles.create'),
            'legacyPanelUrl' => url('/legacy-admin'),
        ]);
    }

    public function rolesCreate(): Response
    {
        $this->authorizeRoleAbility('create_role');

        return inertia('Admin/Roles/Form', [
            'mode' => 'create',
            'record' => [
                'name' => '',
                'guard_name' => 'web',
                'permissions' => [],
                'legacy_url' => null,
            ],
            'permissionGroups' => $this->rolePermissionGroups(),
            'indexUrl' => route('admin.access-control.roles.index'),
            'submitUrl' => route('admin.access-control.roles.store'),
            'legacyPanelUrl' => url('/legacy-admin'),
        ]);
    }

    public function rolesStore(StoreRoleRequest $request): RedirectResponse
    {
        $this->authorizeRoleAbility('create_role');

        $validated = $request->validated();

        $role = Role::query()->create([
            'name' => $validated['name'],
            'guard_name' => $validated['guard_name'] ?: 'web',
        ]);

        $role->syncPermissions($validated['permissions'] ?? []);

        return redirect()
            ->route('admin.access-control.roles.show', $role)
            ->with('success', 'Role berhasil ditambahkan.');
    }

    public function rolesShow(Role $role): Response
    {
        $this->authorizeRoleAbility('view_role');
        $role->loadMissing('permissions:id,name,guard_name');

        return inertia('Admin/Roles/Show', [
            'record' => $this->roleShowPayload($role),
            'canUpdate' => $this->roleAbility('update_role'),
            'canDelete' => $this->roleAbility('delete_role'),
            'indexUrl' => route('admin.access-control.roles.index'),
            'editUrl' => route('admin.access-control.roles.edit', $role),
            'deleteUrl' => route('admin.access-control.roles.destroy', $role),
            'legacyPanelUrl' => url('/legacy-admin'),
        ]);
    }

    public function rolesEdit(Role $role): Response
    {
        $this->authorizeRoleAbility('update_role');
        $role->loadMissing('permissions:id,name,guard_name');

        return inertia('Admin/Roles/Form', [
            'mode' => 'edit',
            'record' => [
                'id' => $role->id,
                'name' => $role->name,
                'guard_name' => $role->guard_name,
                'permissions' => $role->permissions->pluck('name')->values()->all(),
                'legacy_url' => $this->legacyRoleUrl($role),
            ],
            'permissionGroups' => $this->rolePermissionGroups(),
            'indexUrl' => route('admin.access-control.roles.index'),
            'submitUrl' => route('admin.access-control.roles.update', $role),
            'legacyPanelUrl' => url('/legacy-admin'),
        ]);
    }

    public function rolesUpdate(StoreRoleRequest $request, Role $role): RedirectResponse
    {
        $this->authorizeRoleAbility('update_role');

        $validated = $request->validated();

        $role->forceFill([
            'name' => $validated['name'],
            'guard_name' => $validated['guard_name'] ?: 'web',
        ])->save();

        $role->syncPermissions($validated['permissions'] ?? []);

        return redirect()
            ->route('admin.access-control.roles.show', $role)
            ->with('success', 'Role berhasil diperbarui.');
    }

    public function rolesDestroy(Role $role): RedirectResponse
    {
        $this->authorizeRoleAbility('delete_role');

        try {
            $role->delete();
        } catch (QueryException) {
            return redirect()
                ->route('admin.access-control.roles.index')
                ->with('error', 'Role tidak bisa dihapus karena masih dipakai relasi lain.');
        }

        return redirect()
            ->route('admin.access-control.roles.index')
            ->with('success', 'Role berhasil dihapus.');
    }

    public function guidelineSetsIndex(Request $request): Response
    {
        $filters = [
            'q' => trim((string) $request->query('q', '')),
            'status' => (string) $request->query('status', 'all'),
        ];

        $records = GuidelineSet::query()
            ->withCount([
                'constructionCostIndexes',
                'costElements',
                'floorIndexes',
                'mappiRcnStandards',
            ])
            ->when($filters['q'] !== '', function ($query) use ($filters): void {
                $query->where(function ($innerQuery) use ($filters): void {
                    $innerQuery
                        ->where('name', 'like', '%' . $filters['q'] . '%')
                        ->orWhere('description', 'like', '%' . $filters['q'] . '%');
                });
            })
            ->when($filters['status'] === 'active', fn ($query) => $query->where('is_active', true))
            ->when($filters['status'] === 'inactive', fn ($query) => $query->where('is_active', false))
            ->orderByDesc('year')
            ->paginate(15)
            ->withQueryString();

        $records->through(fn (GuidelineSet $guidelineSet) => $this->transformGuidelineSetRow($guidelineSet));

        return inertia('Admin/GuidelineSets/Index', [
            'filters' => $filters,
            'statusOptions' => [
                ['value' => 'all', 'label' => 'Semua Status'],
                ['value' => 'active', 'label' => 'Aktif'],
                ['value' => 'inactive', 'label' => 'Nonaktif'],
            ],
            'summary' => [
                'total' => GuidelineSet::query()->count(),
                'active' => GuidelineSet::query()->where('is_active', true)->count(),
                'valuation_settings' => ValuationSetting::query()->count(),
                'ikk_rows' => \App\Models\ConstructionCostIndex::query()->count(),
            ],
            'records' => $this->paginatedRecordsPayload($records),
            'createUrl' => route('admin.ref-guidelines.guideline-sets.create'),
            'legacyPanelUrl' => url('/legacy-admin'),
        ]);
    }

    public function guidelineSetsCreate(): Response
    {
        return inertia('Admin/GuidelineSets/Form', [
            'mode' => 'create',
            'record' => [
                'name' => '',
                'year' => (int) now()->format('Y'),
                'description' => '',
                'is_active' => false,
                'legacy_url' => null,
            ],
            'submitUrl' => route('admin.ref-guidelines.guideline-sets.store'),
            'indexUrl' => route('admin.ref-guidelines.guideline-sets.index'),
            'legacyPanelUrl' => url('/legacy-admin'),
        ]);
    }

    public function guidelineSetsStore(StoreGuidelineSetRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        DB::transaction(function () use ($validated): void {
            if ((bool) ($validated['is_active'] ?? false)) {
                GuidelineSet::query()->update(['is_active' => false]);
            }

            GuidelineSet::query()->create([
                'name' => $validated['name'],
                'year' => $validated['year'],
                'description' => $validated['description'] ?? null,
                'is_active' => (bool) ($validated['is_active'] ?? false),
            ]);
        });

        return redirect()
            ->route('admin.ref-guidelines.guideline-sets.index')
            ->with('success', 'Guideline set berhasil ditambahkan.');
    }

    public function guidelineSetsEdit(GuidelineSet $guidelineSet): Response
    {
        return inertia('Admin/GuidelineSets/Form', [
            'mode' => 'edit',
            'record' => [
                'id' => $guidelineSet->id,
                'name' => $guidelineSet->name,
                'year' => $guidelineSet->year,
                'description' => $guidelineSet->description,
                'is_active' => (bool) $guidelineSet->is_active,
                'legacy_url' => $this->legacyGuidelineSetUrl($guidelineSet),
            ],
            'submitUrl' => route('admin.ref-guidelines.guideline-sets.update', $guidelineSet),
            'indexUrl' => route('admin.ref-guidelines.guideline-sets.index'),
            'legacyPanelUrl' => url('/legacy-admin'),
        ]);
    }

    public function guidelineSetsUpdate(StoreGuidelineSetRequest $request, GuidelineSet $guidelineSet): RedirectResponse
    {
        $validated = $request->validated();

        DB::transaction(function () use ($validated, $guidelineSet): void {
            if ((bool) ($validated['is_active'] ?? false)) {
                GuidelineSet::query()
                    ->whereKeyNot($guidelineSet->id)
                    ->update(['is_active' => false]);
            }

            $guidelineSet->forceFill([
                'name' => $validated['name'],
                'year' => $validated['year'],
                'description' => $validated['description'] ?? null,
                'is_active' => (bool) ($validated['is_active'] ?? false),
            ])->save();
        });

        return redirect()
            ->route('admin.ref-guidelines.guideline-sets.index')
            ->with('success', 'Guideline set berhasil diperbarui.');
    }

    public function guidelineSetsDestroy(GuidelineSet $guidelineSet): RedirectResponse
    {
        try {
            $guidelineSet->delete();
        } catch (QueryException) {
            return redirect()
                ->route('admin.ref-guidelines.guideline-sets.index')
                ->with('error', 'Guideline set tidak bisa dihapus karena masih dipakai resource referensi lain.');
        }

        return redirect()
            ->route('admin.ref-guidelines.guideline-sets.index')
            ->with('success', 'Guideline set berhasil dihapus.');
    }

    public function valuationSettingsIndex(Request $request): Response
    {
        $filters = [
            'q' => trim((string) $request->query('q', '')),
            'guideline_set_id' => (string) $request->query('guideline_set_id', 'all'),
            'year' => (string) $request->query('year', 'all'),
            'key' => (string) $request->query('key', 'all'),
        ];

        $records = ValuationSetting::query()
            ->with('guidelineSet:id,name,year,is_active')
            ->when($filters['q'] !== '', function ($query) use ($filters): void {
                $query->where(function ($innerQuery) use ($filters): void {
                    $innerQuery
                        ->where('label', 'like', '%' . $filters['q'] . '%')
                        ->orWhere('key', 'like', '%' . $filters['q'] . '%')
                        ->orWhere('notes', 'like', '%' . $filters['q'] . '%');
                });
            })
            ->when(
                $filters['guideline_set_id'] !== 'all',
                fn ($query) => $query->where('guideline_set_id', (int) $filters['guideline_set_id'])
            )
            ->when(
                $filters['year'] !== 'all',
                fn ($query) => $query->where('year', (int) $filters['year'])
            )
            ->when(
                $filters['key'] !== 'all',
                fn ($query) => $query->where('key', $filters['key'])
            )
            ->latest('updated_at')
            ->paginate(15)
            ->withQueryString();

        $records->through(fn (ValuationSetting $valuationSetting) => $this->transformValuationSettingRow($valuationSetting));

        return inertia('Admin/ValuationSettings/Index', [
            'filters' => $filters,
            'guidelineSetOptions' => $this->guidelineSetOptions(includeAll: true),
            'yearOptions' => $this->valuationSettingYearOptions(),
            'keyOptions' => $this->valuationSettingKeyOptions(includeAll: true),
            'summary' => [
                'total' => ValuationSetting::query()->count(),
                'guideline_sets' => ValuationSetting::query()->distinct('guideline_set_id')->count('guideline_set_id'),
                'active_guideline' => ValuationSetting::query()
                    ->whereHas('guidelineSet', fn ($query) => $query->where('is_active', true))
                    ->count(),
            ],
            'records' => $this->paginatedRecordsPayload($records),
            'createUrl' => route('admin.ref-guidelines.valuation-settings.create'),
            'legacyPanelUrl' => url('/legacy-admin'),
        ]);
    }

    public function valuationSettingsCreate(): Response
    {
        $activeGuideline = GuidelineSet::query()->where('is_active', true)->first();

        return inertia('Admin/ValuationSettings/Form', [
            'mode' => 'create',
            'record' => [
                'guideline_set_id' => $activeGuideline?->id,
                'year' => $activeGuideline?->year ?? (int) now()->format('Y'),
                'key' => ValuationSetting::KEY_PPN_PERCENT,
                'label' => ValuationSetting::labelForKey(ValuationSetting::KEY_PPN_PERCENT),
                'value_number' => null,
                'value_text' => '',
                'notes' => '',
                'legacy_url' => null,
            ],
            'guidelineSetOptions' => $this->guidelineSetOptions(),
            'keyOptions' => $this->valuationSettingKeyOptions(),
            'submitUrl' => route('admin.ref-guidelines.valuation-settings.store'),
            'indexUrl' => route('admin.ref-guidelines.valuation-settings.index'),
            'legacyPanelUrl' => url('/legacy-admin'),
        ]);
    }

    public function valuationSettingsStore(StoreValuationSettingRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        ValuationSetting::query()->create([
            'guideline_set_id' => $validated['guideline_set_id'],
            'year' => $validated['year'],
            'key' => $validated['key'],
            'label' => $validated['label'],
            'value_number' => $validated['value_number'],
            'value_text' => $validated['value_text'] ?? null,
            'notes' => $validated['notes'] ?? null,
        ]);

        return redirect()
            ->route('admin.ref-guidelines.valuation-settings.index')
            ->with('success', 'Valuation setting berhasil ditambahkan.');
    }

    public function valuationSettingsEdit(ValuationSetting $valuationSetting): Response
    {
        return inertia('Admin/ValuationSettings/Form', [
            'mode' => 'edit',
            'record' => [
                'id' => $valuationSetting->id,
                'guideline_set_id' => $valuationSetting->guideline_set_id,
                'year' => $valuationSetting->year,
                'key' => $valuationSetting->key,
                'label' => $valuationSetting->label,
                'value_number' => $valuationSetting->value_number,
                'value_text' => $valuationSetting->value_text,
                'notes' => $valuationSetting->notes,
                'legacy_url' => $this->legacyValuationSettingUrl($valuationSetting),
            ],
            'guidelineSetOptions' => $this->guidelineSetOptions(),
            'keyOptions' => $this->valuationSettingKeyOptions(),
            'submitUrl' => route('admin.ref-guidelines.valuation-settings.update', $valuationSetting),
            'indexUrl' => route('admin.ref-guidelines.valuation-settings.index'),
            'legacyPanelUrl' => url('/legacy-admin'),
        ]);
    }

    public function valuationSettingsUpdate(StoreValuationSettingRequest $request, ValuationSetting $valuationSetting): RedirectResponse
    {
        $validated = $request->validated();

        $valuationSetting->forceFill([
            'guideline_set_id' => $validated['guideline_set_id'],
            'year' => $validated['year'],
            'key' => $validated['key'],
            'label' => $validated['label'],
            'value_number' => $validated['value_number'],
            'value_text' => $validated['value_text'] ?? null,
            'notes' => $validated['notes'] ?? null,
        ])->save();

        return redirect()
            ->route('admin.ref-guidelines.valuation-settings.index')
            ->with('success', 'Valuation setting berhasil diperbarui.');
    }

    public function valuationSettingsDestroy(ValuationSetting $valuationSetting): RedirectResponse
    {
        $valuationSetting->delete();

        return redirect()
            ->route('admin.ref-guidelines.valuation-settings.index')
            ->with('success', 'Valuation setting berhasil dihapus.');
    }

    public function constructionCostIndicesIndex(Request $request): Response
    {
        $filters = [
            'q' => trim((string) $request->query('q', '')),
            'guideline_set_id' => (string) $request->query('guideline_set_id', 'all'),
            'year' => (string) $request->query('year', 'all'),
            'province_id' => (string) $request->query('province_id', 'all'),
        ];

        $records = ConstructionCostIndex::query()
            ->with([
                'guidelineSet:id,name,year,is_active',
                'regency:id,name,province_id',
                'regency.province:id,name',
            ])
            ->when($filters['q'] !== '', function ($query) use ($filters): void {
                $query->where(function ($innerQuery) use ($filters): void {
                    $innerQuery
                        ->where('region_name', 'like', '%' . $filters['q'] . '%')
                        ->orWhere('region_code', 'like', '%' . $filters['q'] . '%');
                });
            })
            ->when(
                $filters['guideline_set_id'] !== 'all',
                fn ($query) => $query->where('guideline_set_id', (int) $filters['guideline_set_id'])
            )
            ->when(
                $filters['year'] !== 'all',
                fn ($query) => $query->where('year', (int) $filters['year'])
            )
            ->when(
                $filters['province_id'] !== 'all',
                fn ($query) => $query->whereHas('regency', fn ($regencyQuery) => $regencyQuery->where('province_id', $filters['province_id']))
            )
            ->orderByDesc('year')
            ->orderBy('region_code')
            ->paginate(12)
            ->withQueryString();

        $records->through(fn (ConstructionCostIndex $record) => $this->transformConstructionCostIndexRow($record));

        return inertia('Admin/ConstructionCostIndices/Index', [
            'filters' => $filters,
            'guidelineSetOptions' => $this->guidelineSetOptions(includeAll: true),
            'yearOptions' => $this->constructionCostIndexYearOptions(),
            'provinceOptions' => $this->provinceFilterOptions(includeAll: true),
            'summary' => [
                'total' => ConstructionCostIndex::query()->count(),
                'guideline_sets' => ConstructionCostIndex::query()->distinct('guideline_set_id')->count('guideline_set_id'),
                'provinces' => Regency::query()
                    ->whereIn('id', ConstructionCostIndex::query()->distinct()->pluck('region_code'))
                    ->distinct('province_id')
                    ->count('province_id'),
                'active_guideline' => ConstructionCostIndex::query()
                    ->whereHas('guidelineSet', fn ($query) => $query->where('is_active', true))
                    ->count(),
            ],
            'records' => $this->paginatedRecordsPayload($records),
            'createUrl' => route('admin.ref-guidelines.construction-cost-indices.create'),
            'legacyPanelUrl' => url('/legacy-admin/ref-guidelines/construction-cost-indices'),
        ]);
    }

    public function constructionCostIndicesCreate(): Response
    {
        $activeGuideline = GuidelineSet::query()->where('is_active', true)->first();

        return inertia('Admin/ConstructionCostIndices/Form', [
            'mode' => 'create',
            'record' => [
                'guideline_set_id' => $activeGuideline?->id,
                'year' => $activeGuideline?->year ?? (int) now()->format('Y'),
                'province_id' => '',
                'region_code' => '',
                'ikk_value' => '',
                'legacy_url' => null,
            ],
            'guidelineSetOptions' => $this->guidelineSetOptions(),
            'provinceOptions' => $this->provinceSelectOptions(),
            'regencyOptions' => [],
            'submitUrl' => route('admin.ref-guidelines.construction-cost-indices.store'),
            'indexUrl' => route('admin.ref-guidelines.construction-cost-indices.index'),
            'legacyPanelUrl' => url('/legacy-admin/ref-guidelines/construction-cost-indices'),
        ]);
    }

    public function constructionCostIndicesStore(StoreConstructionCostIndexRequest $request): RedirectResponse
    {
        $validated = $request->validated();
        $regency = Regency::query()->findOrFail($validated['region_code']);

        ConstructionCostIndex::query()->create([
            'guideline_set_id' => $validated['guideline_set_id'],
            'year' => $validated['year'],
            'region_code' => $regency->id,
            'region_name' => $regency->name,
            'ikk_value' => $validated['ikk_value'],
        ]);

        return redirect()
            ->route('admin.ref-guidelines.construction-cost-indices.index')
            ->with('success', 'IKK berhasil ditambahkan.');
    }

    public function constructionCostIndicesEdit(ConstructionCostIndex $constructionCostIndex): Response
    {
        $constructionCostIndex->loadMissing('regency:id,name,province_id');

        return inertia('Admin/ConstructionCostIndices/Form', [
            'mode' => 'edit',
            'record' => [
                'id' => $constructionCostIndex->id,
                'guideline_set_id' => $constructionCostIndex->guideline_set_id,
                'year' => (int) $constructionCostIndex->year,
                'province_id' => (string) ($constructionCostIndex->regency?->province_id ?? ''),
                'region_code' => (string) $constructionCostIndex->region_code,
                'ikk_value' => (float) $constructionCostIndex->ikk_value,
                'legacy_url' => $this->legacyConstructionCostIndexUrl($constructionCostIndex),
            ],
            'guidelineSetOptions' => $this->guidelineSetOptions(),
            'provinceOptions' => $this->provinceSelectOptions(),
            'regencyOptions' => $this->regencySelectOptionsByProvince($constructionCostIndex->regency?->province_id),
            'submitUrl' => route('admin.ref-guidelines.construction-cost-indices.update', $constructionCostIndex),
            'indexUrl' => route('admin.ref-guidelines.construction-cost-indices.index'),
            'legacyPanelUrl' => url('/legacy-admin/ref-guidelines/construction-cost-indices'),
        ]);
    }

    public function constructionCostIndicesUpdate(StoreConstructionCostIndexRequest $request, ConstructionCostIndex $constructionCostIndex): RedirectResponse
    {
        $validated = $request->validated();
        $regency = Regency::query()->findOrFail($validated['region_code']);

        $constructionCostIndex->forceFill([
            'guideline_set_id' => $validated['guideline_set_id'],
            'year' => $validated['year'],
            'region_code' => $regency->id,
            'region_name' => $regency->name,
            'ikk_value' => $validated['ikk_value'],
        ])->save();

        return redirect()
            ->route('admin.ref-guidelines.construction-cost-indices.index')
            ->with('success', 'IKK berhasil diperbarui.');
    }

    public function constructionCostIndicesDestroy(ConstructionCostIndex $constructionCostIndex): RedirectResponse
    {
        try {
            $constructionCostIndex->delete();
        } catch (QueryException) {
            return redirect()
                ->route('admin.ref-guidelines.construction-cost-indices.index')
                ->with('error', 'IKK tidak bisa dihapus karena masih dipakai data appraisal atau reviewer.');
        }

        return redirect()
            ->route('admin.ref-guidelines.construction-cost-indices.index')
            ->with('success', 'IKK berhasil dihapus.');
    }

    public function costElementsIndex(Request $request): Response
    {
        $filters = [
            'q' => trim((string) $request->query('q', '')),
            'guideline_set_id' => (string) $request->query('guideline_set_id', 'all'),
            'year' => (string) $request->query('year', 'all'),
            'base_region' => (string) $request->query('base_region', 'all'),
            'group' => (string) $request->query('group', 'all'),
        ];

        $records = CostElement::query()
            ->with('guidelineSet:id,name,year,is_active')
            ->when($filters['q'] !== '', function ($query) use ($filters): void {
                $query->where(function ($innerQuery) use ($filters): void {
                    $innerQuery
                        ->where('group', 'like', '%' . $filters['q'] . '%')
                        ->orWhere('element_code', 'like', '%' . $filters['q'] . '%')
                        ->orWhere('element_name', 'like', '%' . $filters['q'] . '%')
                        ->orWhere('building_type', 'like', '%' . $filters['q'] . '%')
                        ->orWhere('building_class', 'like', '%' . $filters['q'] . '%')
                        ->orWhere('storey_pattern', 'like', '%' . $filters['q'] . '%');
                });
            })
            ->when(
                $filters['guideline_set_id'] !== 'all',
                fn ($query) => $query->where('guideline_set_id', (int) $filters['guideline_set_id'])
            )
            ->when(
                $filters['year'] !== 'all',
                fn ($query) => $query->where('year', (int) $filters['year'])
            )
            ->when(
                $filters['base_region'] !== 'all',
                fn ($query) => $query->where('base_region', $filters['base_region'])
            )
            ->when(
                $filters['group'] !== 'all',
                fn ($query) => $query->where('group', $filters['group'])
            )
            ->orderByDesc('year')
            ->orderBy('group')
            ->orderBy('element_code')
            ->paginate(12)
            ->withQueryString();

        $records->through(fn (CostElement $record) => $this->transformCostElementRow($record));

        return inertia('Admin/CostElements/Index', [
            'filters' => $filters,
            'guidelineSetOptions' => $this->guidelineSetOptions(includeAll: true),
            'yearOptions' => $this->costElementYearOptions(),
            'baseRegionOptions' => $this->costElementBaseRegionOptions(includeAll: true),
            'groupOptions' => $this->costElementGroupOptions(includeAll: true),
            'summary' => [
                'total' => CostElement::query()->count(),
                'guideline_sets' => CostElement::query()->distinct('guideline_set_id')->count('guideline_set_id'),
                'groups' => CostElement::query()->distinct('group')->count('group'),
                'active_guideline' => CostElement::query()
                    ->whereHas('guidelineSet', fn ($query) => $query->where('is_active', true))
                    ->count(),
            ],
            'records' => $this->paginatedRecordsPayload($records),
            'createUrl' => route('admin.ref-guidelines.cost-elements.create'),
            'legacyPanelUrl' => url('/legacy-admin/ref-guidelines/cost-elements'),
        ]);
    }

    public function costElementsCreate(): Response
    {
        $activeGuideline = GuidelineSet::query()->where('is_active', true)->first();

        return inertia('Admin/CostElements/Form', [
            'mode' => 'create',
            'record' => [
                'guideline_set_id' => $activeGuideline?->id,
                'year' => $activeGuideline?->year ?? (int) now()->format('Y'),
                'base_region' => 'DKI Jakarta',
                'group' => '',
                'element_code' => '',
                'element_name' => '',
                'building_type' => '',
                'building_class' => '',
                'storey_pattern' => '',
                'unit' => 'm2',
                'unit_cost' => '',
                'spec_json' => '',
                'legacy_url' => null,
            ],
            'guidelineSetOptions' => $this->guidelineSetOptions(),
            'formOptions' => $this->costElementFormOptions(),
            'submitUrl' => route('admin.ref-guidelines.cost-elements.store'),
            'indexUrl' => route('admin.ref-guidelines.cost-elements.index'),
            'legacyPanelUrl' => url('/legacy-admin/ref-guidelines/cost-elements'),
        ]);
    }

    public function costElementsStore(StoreCostElementRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        CostElement::query()->create([
            'guideline_set_id' => $validated['guideline_set_id'],
            'year' => $validated['year'],
            'base_region' => $validated['base_region'],
            'group' => $validated['group'],
            'element_code' => $validated['element_code'],
            'element_name' => $validated['element_name'],
            'building_type' => $validated['building_type'] ?? null,
            'building_class' => $validated['building_class'] ?? null,
            'storey_pattern' => $validated['storey_pattern'] ?? null,
            'unit' => $validated['unit'],
            'unit_cost' => $validated['unit_cost'],
            'spec_json' => $validated['spec_json'] ?? null,
        ]);

        return redirect()
            ->route('admin.ref-guidelines.cost-elements.index')
            ->with('success', 'Cost element berhasil ditambahkan.');
    }

    public function costElementsEdit(CostElement $costElement): Response
    {
        return inertia('Admin/CostElements/Form', [
            'mode' => 'edit',
            'record' => [
                'id' => $costElement->id,
                'guideline_set_id' => $costElement->guideline_set_id,
                'year' => (int) $costElement->year,
                'base_region' => $costElement->base_region,
                'group' => $costElement->group,
                'element_code' => $costElement->element_code,
                'element_name' => $costElement->element_name,
                'building_type' => $costElement->building_type,
                'building_class' => $costElement->building_class,
                'storey_pattern' => $costElement->storey_pattern,
                'unit' => $costElement->unit,
                'unit_cost' => (int) $costElement->unit_cost,
                'spec_json' => $costElement->spec_json
                    ? json_encode($costElement->spec_json, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
                    : '',
                'legacy_url' => $this->legacyCostElementUrl($costElement),
            ],
            'guidelineSetOptions' => $this->guidelineSetOptions(),
            'formOptions' => $this->costElementFormOptions(),
            'submitUrl' => route('admin.ref-guidelines.cost-elements.update', $costElement),
            'indexUrl' => route('admin.ref-guidelines.cost-elements.index'),
            'legacyPanelUrl' => url('/legacy-admin/ref-guidelines/cost-elements'),
        ]);
    }

    public function costElementsUpdate(StoreCostElementRequest $request, CostElement $costElement): RedirectResponse
    {
        $validated = $request->validated();

        $costElement->forceFill([
            'guideline_set_id' => $validated['guideline_set_id'],
            'year' => $validated['year'],
            'base_region' => $validated['base_region'],
            'group' => $validated['group'],
            'element_code' => $validated['element_code'],
            'element_name' => $validated['element_name'],
            'building_type' => $validated['building_type'] ?? null,
            'building_class' => $validated['building_class'] ?? null,
            'storey_pattern' => $validated['storey_pattern'] ?? null,
            'unit' => $validated['unit'],
            'unit_cost' => $validated['unit_cost'],
            'spec_json' => $validated['spec_json'] ?? null,
        ])->save();

        return redirect()
            ->route('admin.ref-guidelines.cost-elements.index')
            ->with('success', 'Cost element berhasil diperbarui.');
    }

    public function costElementsDestroy(CostElement $costElement): RedirectResponse
    {
        try {
            $costElement->delete();
        } catch (QueryException) {
            return redirect()
                ->route('admin.ref-guidelines.cost-elements.index')
                ->with('error', 'Cost element tidak bisa dihapus karena masih dipakai perhitungan reviewer.');
        }

        return redirect()
            ->route('admin.ref-guidelines.cost-elements.index')
            ->with('success', 'Cost element berhasil dihapus.');
    }

    public function floorIndicesIndex(Request $request): Response
    {
        $filters = [
            'q' => trim((string) $request->query('q', '')),
            'guideline_set_id' => (string) $request->query('guideline_set_id', 'all'),
            'year' => (string) $request->query('year', 'all'),
            'building_class' => (string) $request->query('building_class', 'all'),
        ];

        $records = FloorIndex::query()
            ->with('guidelineSet:id,name,year,is_active')
            ->when($filters['q'] !== '', function ($query) use ($filters): void {
                $query->where(function ($innerQuery) use ($filters): void {
                    $innerQuery
                        ->where('building_class', 'like', '%' . $filters['q'] . '%')
                        ->orWhere('floor_count', 'like', '%' . $filters['q'] . '%');
                });
            })
            ->when(
                $filters['guideline_set_id'] !== 'all',
                fn ($query) => $query->where('guideline_set_id', (int) $filters['guideline_set_id'])
            )
            ->when(
                $filters['year'] !== 'all',
                fn ($query) => $query->where('year', (int) $filters['year'])
            )
            ->when(
                $filters['building_class'] !== 'all',
                fn ($query) => $query->where('building_class', $filters['building_class'])
            )
            ->orderByDesc('year')
            ->orderBy('building_class')
            ->orderBy('floor_count')
            ->paginate(12)
            ->withQueryString();

        $records->through(fn (FloorIndex $record) => $this->transformFloorIndexRow($record));

        return inertia('Admin/FloorIndices/Index', [
            'filters' => $filters,
            'guidelineSetOptions' => $this->guidelineSetOptions(includeAll: true),
            'yearOptions' => $this->floorIndexYearOptions(includeAll: true),
            'buildingClassOptions' => $this->floorIndexBuildingClassOptions(includeAll: true),
            'summary' => [
                'total' => FloorIndex::query()->count(),
                'guideline_sets' => FloorIndex::query()->distinct('guideline_set_id')->count('guideline_set_id'),
                'classes' => FloorIndex::query()->distinct('building_class')->count('building_class'),
                'active_guideline' => FloorIndex::query()
                    ->whereHas('guidelineSet', fn ($query) => $query->where('is_active', true))
                    ->count(),
            ],
            'records' => $this->paginatedRecordsPayload($records),
            'createUrl' => route('admin.ref-guidelines.floor-indices.create'),
            'legacyPanelUrl' => url('/legacy-admin/ref-guidelines/floor-indices'),
        ]);
    }

    public function floorIndicesCreate(): Response
    {
        $activeGuideline = GuidelineSet::query()->where('is_active', true)->first();

        return inertia('Admin/FloorIndices/Form', [
            'mode' => 'create',
            'record' => [
                'guideline_set_id' => $activeGuideline?->id,
                'year' => $activeGuideline?->year ?? (int) now()->format('Y'),
                'building_class' => 'DEFAULT',
                'floor_count' => '',
                'il_value' => '',
                'legacy_url' => null,
            ],
            'guidelineSetOptions' => $this->guidelineSetOptions(),
            'buildingClassOptions' => $this->floorIndexBuildingClassOptions(),
            'submitUrl' => route('admin.ref-guidelines.floor-indices.store'),
            'indexUrl' => route('admin.ref-guidelines.floor-indices.index'),
            'legacyPanelUrl' => url('/legacy-admin/ref-guidelines/floor-indices'),
        ]);
    }

    public function floorIndicesStore(StoreFloorIndexRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        FloorIndex::query()->create([
            'guideline_set_id' => $validated['guideline_set_id'],
            'year' => $validated['year'],
            'building_class' => $validated['building_class'],
            'floor_count' => $validated['floor_count'],
            'il_value' => $validated['il_value'],
        ]);

        return redirect()
            ->route('admin.ref-guidelines.floor-indices.index')
            ->with('success', 'Floor index berhasil ditambahkan.');
    }

    public function floorIndicesEdit(FloorIndex $floorIndex): Response
    {
        return inertia('Admin/FloorIndices/Form', [
            'mode' => 'edit',
            'record' => [
                'id' => $floorIndex->id,
                'guideline_set_id' => $floorIndex->guideline_set_id,
                'year' => (int) $floorIndex->year,
                'building_class' => $floorIndex->building_class,
                'floor_count' => (int) $floorIndex->floor_count,
                'il_value' => (float) $floorIndex->il_value,
                'legacy_url' => $this->legacyFloorIndexUrl($floorIndex),
            ],
            'guidelineSetOptions' => $this->guidelineSetOptions(),
            'buildingClassOptions' => $this->floorIndexBuildingClassOptions(),
            'submitUrl' => route('admin.ref-guidelines.floor-indices.update', $floorIndex),
            'indexUrl' => route('admin.ref-guidelines.floor-indices.index'),
            'legacyPanelUrl' => url('/legacy-admin/ref-guidelines/floor-indices'),
        ]);
    }

    public function floorIndicesUpdate(StoreFloorIndexRequest $request, FloorIndex $floorIndex): RedirectResponse
    {
        $validated = $request->validated();

        $floorIndex->forceFill([
            'guideline_set_id' => $validated['guideline_set_id'],
            'year' => $validated['year'],
            'building_class' => $validated['building_class'],
            'floor_count' => $validated['floor_count'],
            'il_value' => $validated['il_value'],
        ])->save();

        return redirect()
            ->route('admin.ref-guidelines.floor-indices.index')
            ->with('success', 'Floor index berhasil diperbarui.');
    }

    public function floorIndicesDestroy(FloorIndex $floorIndex): RedirectResponse
    {
        try {
            $floorIndex->delete();
        } catch (QueryException) {
            return redirect()
                ->route('admin.ref-guidelines.floor-indices.index')
                ->with('error', 'Floor index tidak bisa dihapus karena masih dipakai perhitungan reviewer.');
        }

        return redirect()
            ->route('admin.ref-guidelines.floor-indices.index')
            ->with('success', 'Floor index berhasil dihapus.');
    }

    public function mappiRcnStandardsIndex(Request $request): Response
    {
        $filters = [
            'q' => trim((string) $request->query('q', '')),
            'guideline_set_id' => (string) $request->query('guideline_set_id', 'all'),
            'year' => (string) $request->query('year', 'all'),
            'building_type' => (string) $request->query('building_type', 'all'),
            'building_class' => (string) $request->query('building_class', 'all'),
        ];

        $records = MappiRcnStandard::query()
            ->with('guidelineSet:id,name,year,is_active')
            ->when($filters['q'] !== '', function ($query) use ($filters): void {
                $query->where(function ($innerQuery) use ($filters): void {
                    $innerQuery
                        ->where('building_type', 'like', '%' . $filters['q'] . '%')
                        ->orWhere('building_class', 'like', '%' . $filters['q'] . '%')
                        ->orWhere('storey_pattern', 'like', '%' . $filters['q'] . '%')
                        ->orWhere('notes', 'like', '%' . $filters['q'] . '%');
                });
            })
            ->when(
                $filters['guideline_set_id'] !== 'all',
                fn ($query) => $query->where('guideline_set_id', (int) $filters['guideline_set_id'])
            )
            ->when(
                $filters['year'] !== 'all',
                fn ($query) => $query->where('year', (int) $filters['year'])
            )
            ->when(
                $filters['building_type'] !== 'all',
                fn ($query) => $query->where('building_type', $filters['building_type'])
            )
            ->when(
                $filters['building_class'] !== 'all',
                fn ($query) => $query->where('building_class', $filters['building_class'])
            )
            ->orderByDesc('year')
            ->orderBy('building_type')
            ->orderBy('building_class')
            ->paginate(12)
            ->withQueryString();

        $records->through(fn (MappiRcnStandard $record) => $this->transformMappiRcnStandardRow($record));

        return inertia('Admin/MappiRcnStandards/Index', [
            'filters' => $filters,
            'guidelineSetOptions' => $this->guidelineSetOptions(includeAll: true),
            'yearOptions' => $this->mappiRcnYearOptions(includeAll: true),
            'buildingTypeOptions' => $this->mappiRcnBuildingTypeOptions(includeAll: true),
            'buildingClassOptions' => $this->mappiRcnBuildingClassOptions(includeAll: true),
            'summary' => [
                'total' => MappiRcnStandard::query()->count(),
                'guideline_sets' => MappiRcnStandard::query()->distinct('guideline_set_id')->count('guideline_set_id'),
                'building_types' => MappiRcnStandard::query()->distinct('building_type')->count('building_type'),
                'active_guideline' => MappiRcnStandard::query()
                    ->whereHas('guidelineSet', fn ($query) => $query->where('is_active', true))
                    ->count(),
            ],
            'records' => $this->paginatedRecordsPayload($records),
            'createUrl' => route('admin.ref-guidelines.mappi-rcn-standards.create'),
            'legacyPanelUrl' => url('/legacy-admin/ref-guidelines/mappi-rcn-standards'),
        ]);
    }

    public function mappiRcnStandardsCreate(): Response
    {
        $activeGuideline = GuidelineSet::query()->where('is_active', true)->first();

        return inertia('Admin/MappiRcnStandards/Form', [
            'mode' => 'create',
            'record' => [
                'guideline_set_id' => $activeGuideline?->id,
                'year' => $activeGuideline?->year ?? (int) now()->format('Y'),
                'reference_region' => 'DKI Jakarta',
                'building_type' => '',
                'building_class' => '',
                'storey_pattern' => '',
                'rcn_value' => '',
                'notes' => '',
                'legacy_url' => null,
            ],
            'guidelineSetOptions' => $this->guidelineSetOptions(),
            'formOptions' => $this->mappiRcnFormOptions(),
            'submitUrl' => route('admin.ref-guidelines.mappi-rcn-standards.store'),
            'indexUrl' => route('admin.ref-guidelines.mappi-rcn-standards.index'),
            'legacyPanelUrl' => url('/legacy-admin/ref-guidelines/mappi-rcn-standards'),
        ]);
    }

    public function mappiRcnStandardsStore(StoreMappiRcnStandardRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        MappiRcnStandard::query()->create([
            'guideline_set_id' => $validated['guideline_set_id'],
            'year' => $validated['year'],
            'reference_region' => $validated['reference_region'],
            'building_type' => $validated['building_type'],
            'building_class' => $validated['building_class'] ?? null,
            'storey_pattern' => $validated['storey_pattern'] ?? null,
            'rcn_value' => $validated['rcn_value'],
            'notes' => $validated['notes'] ?? null,
        ]);

        return redirect()
            ->route('admin.ref-guidelines.mappi-rcn-standards.index')
            ->with('success', 'MAPPI RCN berhasil ditambahkan.');
    }

    public function mappiRcnStandardsEdit(MappiRcnStandard $mappiRcnStandard): Response
    {
        return inertia('Admin/MappiRcnStandards/Form', [
            'mode' => 'edit',
            'record' => [
                'id' => $mappiRcnStandard->id,
                'guideline_set_id' => $mappiRcnStandard->guideline_set_id,
                'year' => (int) $mappiRcnStandard->year,
                'reference_region' => $mappiRcnStandard->reference_region,
                'building_type' => $mappiRcnStandard->building_type,
                'building_class' => $mappiRcnStandard->building_class,
                'storey_pattern' => $mappiRcnStandard->storey_pattern,
                'rcn_value' => (int) $mappiRcnStandard->rcn_value,
                'notes' => $mappiRcnStandard->notes,
                'legacy_url' => $this->legacyMappiRcnStandardUrl($mappiRcnStandard),
            ],
            'guidelineSetOptions' => $this->guidelineSetOptions(),
            'formOptions' => $this->mappiRcnFormOptions(),
            'submitUrl' => route('admin.ref-guidelines.mappi-rcn-standards.update', $mappiRcnStandard),
            'indexUrl' => route('admin.ref-guidelines.mappi-rcn-standards.index'),
            'legacyPanelUrl' => url('/legacy-admin/ref-guidelines/mappi-rcn-standards'),
        ]);
    }

    public function mappiRcnStandardsUpdate(StoreMappiRcnStandardRequest $request, MappiRcnStandard $mappiRcnStandard): RedirectResponse
    {
        $validated = $request->validated();

        $mappiRcnStandard->forceFill([
            'guideline_set_id' => $validated['guideline_set_id'],
            'year' => $validated['year'],
            'reference_region' => $validated['reference_region'],
            'building_type' => $validated['building_type'],
            'building_class' => $validated['building_class'] ?? null,
            'storey_pattern' => $validated['storey_pattern'] ?? null,
            'rcn_value' => $validated['rcn_value'],
            'notes' => $validated['notes'] ?? null,
        ])->save();

        return redirect()
            ->route('admin.ref-guidelines.mappi-rcn-standards.index')
            ->with('success', 'MAPPI RCN berhasil diperbarui.');
    }

    public function mappiRcnStandardsDestroy(MappiRcnStandard $mappiRcnStandard): RedirectResponse
    {
        try {
            $mappiRcnStandard->delete();
        } catch (QueryException) {
            return redirect()
                ->route('admin.ref-guidelines.mappi-rcn-standards.index')
                ->with('error', 'MAPPI RCN tidak bisa dihapus karena masih dipakai perhitungan reviewer.');
        }

        return redirect()
            ->route('admin.ref-guidelines.mappi-rcn-standards.index')
            ->with('success', 'MAPPI RCN berhasil dihapus.');
    }

    public function provincesIndex(Request $request): Response
    {
        $filters = [
            'q' => trim((string) $request->query('q', '')),
        ];

        $records = Province::query()
            ->withCount('regencies')
            ->when($filters['q'] !== '', function ($query) use ($filters): void {
                $query->where(function ($innerQuery) use ($filters): void {
                    $innerQuery
                        ->where('id', 'like', '%' . $filters['q'] . '%')
                        ->orWhere('name', 'like', '%' . $filters['q'] . '%');
                });
            })
            ->orderBy('name')
            ->paginate(20)
            ->withQueryString();

        $records->through(fn (Province $province) => $this->transformProvinceRow($province));

        return inertia('Admin/Locations/Index', [
            'resource' => $this->locationResourceDefinition('provinces'),
            'filters' => $filters,
            'filterOptions' => [],
            'summaryCards' => [
                ['label' => 'Total Provinsi', 'value' => Province::query()->count()],
                ['label' => 'Total Kabupaten/Kota', 'value' => Regency::query()->count()],
                ['label' => 'Provinsi Dengan Kabupaten/Kota', 'value' => Province::query()->has('regencies')->count()],
            ],
            'records' => $this->paginatedRecordsPayload($records),
            'indexUrl' => route('admin.master-data.provinces.index'),
            'createUrl' => route('admin.master-data.provinces.create'),
            'legacyPanelUrl' => url('/legacy-admin'),
        ]);
    }

    public function provincesCreate(LocationIdGenerator $generator): Response
    {
        return inertia('Admin/Locations/Form', [
            'resource' => $this->locationResourceDefinition('provinces'),
            'mode' => 'create',
            'record' => [
                'id' => $this->locationGeneratedIdPreview('province', [], $generator),
                'name' => '',
                'legacy_url' => null,
            ],
            'selectFields' => [],
            'generator' => $this->locationGeneratorProps('province'),
            'indexUrl' => route('admin.master-data.provinces.index'),
            'submitUrl' => route('admin.master-data.provinces.store'),
            'legacyPanelUrl' => url('/legacy-admin'),
        ]);
    }

    public function provincesStore(StoreProvinceRequest $request, LocationIdGenerator $generator): RedirectResponse
    {
        $validated = $request->validated();

        DB::transaction(function () use ($validated, $generator): void {
            Province::query()->create([
                'id' => $generator->nextProvinceId(),
                'name' => $validated['name'],
            ]);
        });

        return redirect()
            ->route('admin.master-data.provinces.index')
            ->with('success', 'Provinsi berhasil ditambahkan.');
    }

    public function provincesEdit(Province $province): Response
    {
        return inertia('Admin/Locations/Form', [
            'resource' => $this->locationResourceDefinition('provinces'),
            'mode' => 'edit',
            'record' => [
                'id' => $province->id,
                'name' => $province->name,
                'legacy_url' => $this->legacyProvinceUrl($province),
            ],
            'selectFields' => [],
            'generator' => null,
            'indexUrl' => route('admin.master-data.provinces.index'),
            'submitUrl' => route('admin.master-data.provinces.update', $province),
            'legacyPanelUrl' => url('/legacy-admin'),
        ]);
    }

    public function provincesUpdate(StoreProvinceRequest $request, Province $province): RedirectResponse
    {
        $validated = $request->validated();

        $province->forceFill([
            'name' => $validated['name'],
        ])->save();

        return redirect()
            ->route('admin.master-data.provinces.index')
            ->with('success', 'Provinsi berhasil diperbarui.');
    }

    public function provincesDestroy(Province $province): RedirectResponse
    {
        return $this->destroyLocationRecord(
            $province,
            'admin.master-data.provinces.index',
            'Provinsi'
        );
    }

    public function regenciesIndex(Request $request): Response
    {
        $filters = [
            'q' => trim((string) $request->query('q', '')),
            'province_id' => (string) $request->query('province_id', 'all'),
        ];

        $records = Regency::query()
            ->with(['province:id,name'])
            ->withCount('districts')
            ->when($filters['q'] !== '', function ($query) use ($filters): void {
                $query->where(function ($innerQuery) use ($filters): void {
                    $innerQuery
                        ->where('id', 'like', '%' . $filters['q'] . '%')
                        ->orWhere('name', 'like', '%' . $filters['q'] . '%');
                });
            })
            ->when(
                $filters['province_id'] !== 'all',
                fn ($query) => $query->where('province_id', $filters['province_id'])
            )
            ->orderBy('name')
            ->paginate(20)
            ->withQueryString();

        $records->through(fn (Regency $regency) => $this->transformRegencyRow($regency));

        return inertia('Admin/Locations/Index', [
            'resource' => $this->locationResourceDefinition('regencies'),
            'filters' => $filters,
            'filterOptions' => [
                [
                    'key' => 'province_id',
                    'label' => 'Provinsi',
                    'defaultValue' => 'all',
                    'options' => $this->provinceFilterOptions(),
                ],
            ],
            'summaryCards' => [
                ['label' => 'Total Kabupaten/Kota', 'value' => Regency::query()->count()],
                ['label' => 'Provinsi Tercakup', 'value' => Regency::query()->distinct('province_id')->count('province_id')],
                ['label' => 'Total Kecamatan', 'value' => District::query()->count()],
            ],
            'records' => $this->paginatedRecordsPayload($records),
            'indexUrl' => route('admin.master-data.regencies.index'),
            'createUrl' => route('admin.master-data.regencies.create'),
            'legacyPanelUrl' => url('/legacy-admin'),
        ]);
    }

    public function regenciesCreate(Request $request, LocationIdGenerator $generator): Response
    {
        $selectedProvinceId = (string) $request->query('province_id', '');

        return inertia('Admin/Locations/Form', [
            'resource' => $this->locationResourceDefinition('regencies'),
            'mode' => 'create',
            'record' => [
                'id' => $this->locationGeneratedIdPreview('regency', ['province_id' => $selectedProvinceId], $generator),
                'name' => '',
                'province_id' => $selectedProvinceId,
                'legacy_url' => null,
            ],
            'selectFields' => [
                [
                    'key' => 'province_id',
                    'label' => 'Provinsi',
                    'placeholder' => 'Pilih provinsi',
                    'options' => $this->provinceSelectOptions(),
                ],
            ],
            'generator' => $this->locationGeneratorProps('regency', 'province_id'),
            'showIdField' => false,
            'indexUrl' => route('admin.master-data.regencies.index'),
            'submitUrl' => route('admin.master-data.regencies.store'),
            'legacyPanelUrl' => url('/legacy-admin'),
        ]);
    }

    public function regenciesStore(StoreRegencyRequest $request, LocationIdGenerator $generator): RedirectResponse
    {
        $validated = $request->validated();

        DB::transaction(function () use ($validated, $generator): void {
            Regency::query()->create([
                'id' => $generator->nextRegencyId($validated['province_id']),
                'province_id' => $validated['province_id'],
                'name' => $validated['name'],
            ]);
        });

        return redirect()
            ->route('admin.master-data.regencies.index')
            ->with('success', 'Kabupaten/Kota berhasil ditambahkan.');
    }

    public function regenciesEdit(Regency $regency): Response
    {
        $regency->loadMissing('province:id,name');

        return inertia('Admin/Locations/Form', [
            'resource' => $this->locationResourceDefinition('regencies'),
            'mode' => 'edit',
            'record' => [
                'id' => $regency->id,
                'name' => $regency->name,
                'province_id' => $regency->province_id,
                'legacy_url' => $this->legacyRegencyUrl($regency),
            ],
            'selectFields' => [
                [
                    'key' => 'province_id',
                    'label' => 'Provinsi',
                    'placeholder' => 'Pilih provinsi',
                    'options' => $this->provinceSelectOptions(),
                ],
            ],
            'generator' => null,
            'indexUrl' => route('admin.master-data.regencies.index'),
            'submitUrl' => route('admin.master-data.regencies.update', $regency),
            'legacyPanelUrl' => url('/legacy-admin'),
        ]);
    }

    public function regenciesUpdate(StoreRegencyRequest $request, Regency $regency): RedirectResponse
    {
        $validated = $request->validated();

        $regency->forceFill([
            'province_id' => $validated['province_id'],
            'name' => $validated['name'],
        ])->save();

        return redirect()
            ->route('admin.master-data.regencies.index')
            ->with('success', 'Kabupaten/Kota berhasil diperbarui.');
    }

    public function regenciesDestroy(Regency $regency): RedirectResponse
    {
        return $this->destroyLocationRecord(
            $regency,
            'admin.master-data.regencies.index',
            'Kabupaten/Kota'
        );
    }

    public function districtsIndex(Request $request): Response
    {
        $filters = [
            'q' => trim((string) $request->query('q', '')),
            'province_id' => (string) $request->query('province_id', 'all'),
            'regency_id' => (string) $request->query('regency_id', 'all'),
        ];

        $records = District::query()
            ->with(['regency:id,name,province_id', 'regency.province:id,name'])
            ->withCount('villages')
            ->when($filters['q'] !== '', function ($query) use ($filters): void {
                $query->where(function ($innerQuery) use ($filters): void {
                    $innerQuery
                        ->where('id', 'like', '%' . $filters['q'] . '%')
                        ->orWhere('name', 'like', '%' . $filters['q'] . '%');
                });
            })
            ->when(
                $filters['regency_id'] !== 'all',
                fn ($query) => $query->where('regency_id', $filters['regency_id'])
            )
            ->when($filters['province_id'] !== 'all', function ($query) use ($filters): void {
                $query->whereHas('regency', fn ($regencyQuery) => $regencyQuery->where('province_id', $filters['province_id']));
            })
            ->orderBy('name')
            ->paginate(20)
            ->withQueryString();

        $records->through(fn (District $district) => $this->transformDistrictRow($district));

        return inertia('Admin/Locations/Index', [
            'resource' => $this->locationResourceDefinition('districts'),
            'filters' => $filters,
            'filterOptions' => [
                [
                    'key' => 'province_id',
                    'label' => 'Provinsi',
                    'defaultValue' => 'all',
                    'options' => $this->provinceFilterOptions(),
                ],
                [
                    'key' => 'regency_id',
                    'label' => 'Kabupaten/Kota',
                    'defaultValue' => 'all',
                    'options' => $this->regencyFilterOptions(),
                ],
            ],
            'summaryCards' => [
                ['label' => 'Total Kecamatan', 'value' => District::query()->count()],
                ['label' => 'Kabupaten/Kota Tercakup', 'value' => District::query()->distinct('regency_id')->count('regency_id')],
                ['label' => 'Total Kelurahan/Desa', 'value' => Village::query()->count()],
            ],
            'records' => $this->paginatedRecordsPayload($records),
            'indexUrl' => route('admin.master-data.districts.index'),
            'createUrl' => route('admin.master-data.districts.create'),
            'legacyPanelUrl' => url('/legacy-admin'),
        ]);
    }

    public function districtsCreate(Request $request, LocationIdGenerator $generator): Response
    {
        $selectedRegencyId = (string) $request->query('regency_id', '');
        $selectedProvinceId = '';

        if ($selectedRegencyId !== '') {
            $selectedProvinceId = (string) Regency::query()
                ->whereKey($selectedRegencyId)
                ->value('province_id');
        }

        return inertia('Admin/Locations/Form', [
            'resource' => $this->locationResourceDefinition('districts'),
            'mode' => 'create',
            'record' => [
                'id' => $this->locationGeneratedIdPreview('district', ['regency_id' => $selectedRegencyId], $generator),
                'name' => '',
                'province_id' => $selectedProvinceId,
                'regency_id' => $selectedRegencyId,
                'legacy_url' => null,
            ],
            'selectFields' => [
                [
                    'key' => 'province_id',
                    'label' => 'Provinsi',
                    'placeholder' => 'Pilih provinsi',
                    'options' => $this->provinceSelectOptions(),
                ],
                [
                    'key' => 'regency_id',
                    'label' => 'Kabupaten/Kota',
                    'placeholder' => 'Pilih kabupaten/kota',
                    'options' => $this->regencySelectOptionsByProvince($selectedProvinceId),
                    'depends_on' => 'province_id',
                    'endpoint_type' => 'regencies',
                    'parent_param' => 'province_id',
                ],
            ],
            'generator' => $this->locationGeneratorProps('district', 'regency_id'),
            'showIdField' => false,
            'indexUrl' => route('admin.master-data.districts.index'),
            'submitUrl' => route('admin.master-data.districts.store'),
            'legacyPanelUrl' => url('/legacy-admin'),
        ]);
    }

    public function districtsStore(StoreDistrictRequest $request, LocationIdGenerator $generator): RedirectResponse
    {
        $validated = $request->validated();

        DB::transaction(function () use ($validated, $generator): void {
            District::query()->create([
                'id' => $generator->nextDistrictId($validated['regency_id']),
                'regency_id' => $validated['regency_id'],
                'name' => $validated['name'],
            ]);
        });

        return redirect()
            ->route('admin.master-data.districts.index')
            ->with('success', 'Kecamatan berhasil ditambahkan.');
    }

    public function districtsEdit(District $district): Response
    {
        $district->loadMissing(['regency:id,name,province_id', 'regency.province:id,name']);
        $selectedProvinceId = (string) ($district->regency?->province_id ?? '');

        return inertia('Admin/Locations/Form', [
            'resource' => $this->locationResourceDefinition('districts'),
            'mode' => 'edit',
            'record' => [
                'id' => $district->id,
                'name' => $district->name,
                'province_id' => $selectedProvinceId,
                'regency_id' => $district->regency_id,
                'legacy_url' => $this->legacyDistrictUrl($district),
            ],
            'selectFields' => [
                [
                    'key' => 'province_id',
                    'label' => 'Provinsi',
                    'placeholder' => 'Pilih provinsi',
                    'options' => $this->provinceSelectOptions(),
                ],
                [
                    'key' => 'regency_id',
                    'label' => 'Kabupaten/Kota',
                    'placeholder' => 'Pilih kabupaten/kota',
                    'options' => $this->regencySelectOptionsByProvince($selectedProvinceId),
                    'depends_on' => 'province_id',
                    'endpoint_type' => 'regencies',
                    'parent_param' => 'province_id',
                ],
            ],
            'generator' => null,
            'indexUrl' => route('admin.master-data.districts.index'),
            'submitUrl' => route('admin.master-data.districts.update', $district),
            'legacyPanelUrl' => url('/legacy-admin'),
        ]);
    }

    public function districtsUpdate(StoreDistrictRequest $request, District $district): RedirectResponse
    {
        $validated = $request->validated();

        $district->forceFill([
            'regency_id' => $validated['regency_id'],
            'name' => $validated['name'],
        ])->save();

        return redirect()
            ->route('admin.master-data.districts.index')
            ->with('success', 'Kecamatan berhasil diperbarui.');
    }

    public function districtsDestroy(District $district): RedirectResponse
    {
        return $this->destroyLocationRecord(
            $district,
            'admin.master-data.districts.index',
            'Kecamatan'
        );
    }

    public function villagesIndex(Request $request): Response
    {
        $filters = [
            'q' => trim((string) $request->query('q', '')),
            'province_id' => (string) $request->query('province_id', 'all'),
            'regency_id' => (string) $request->query('regency_id', 'all'),
            'district_id' => (string) $request->query('district_id', 'all'),
        ];

        $records = Village::query()
            ->with(['district:id,name,regency_id', 'district.regency:id,name,province_id', 'district.regency.province:id,name'])
            ->when($filters['q'] !== '', function ($query) use ($filters): void {
                $query->where(function ($innerQuery) use ($filters): void {
                    $innerQuery
                        ->where('id', 'like', '%' . $filters['q'] . '%')
                        ->orWhere('name', 'like', '%' . $filters['q'] . '%');
                });
            })
            ->when(
                $filters['district_id'] !== 'all',
                fn ($query) => $query->where('district_id', $filters['district_id'])
            )
            ->when($filters['regency_id'] !== 'all', function ($query) use ($filters): void {
                $query->whereHas('district', fn ($districtQuery) => $districtQuery->where('regency_id', $filters['regency_id']));
            })
            ->when($filters['province_id'] !== 'all', function ($query) use ($filters): void {
                $query->whereHas(
                    'district.regency',
                    fn ($regencyQuery) => $regencyQuery->where('province_id', $filters['province_id'])
                );
            })
            ->orderBy('name')
            ->paginate(20)
            ->withQueryString();

        $records->through(fn (Village $village) => $this->transformVillageRow($village));

        return inertia('Admin/Locations/Index', [
            'resource' => $this->locationResourceDefinition('villages'),
            'filters' => $filters,
            'filterOptions' => [
                [
                    'key' => 'province_id',
                    'label' => 'Provinsi',
                    'defaultValue' => 'all',
                    'options' => $this->provinceFilterOptions(),
                ],
                [
                    'key' => 'regency_id',
                    'label' => 'Kabupaten/Kota',
                    'defaultValue' => 'all',
                    'options' => $this->regencyFilterOptions(),
                ],
                [
                    'key' => 'district_id',
                    'label' => 'Kecamatan',
                    'defaultValue' => 'all',
                    'options' => $this->districtFilterOptions(),
                ],
            ],
            'summaryCards' => [
                ['label' => 'Total Kelurahan/Desa', 'value' => Village::query()->count()],
                ['label' => 'Kecamatan Tercakup', 'value' => Village::query()->distinct('district_id')->count('district_id')],
                ['label' => 'Kabupaten/Kota Tercakup', 'value' => District::query()->has('villages')->distinct('regency_id')->count('regency_id')],
            ],
            'records' => $this->paginatedRecordsPayload($records),
            'indexUrl' => route('admin.master-data.villages.index'),
            'createUrl' => route('admin.master-data.villages.create'),
            'legacyPanelUrl' => url('/legacy-admin'),
        ]);
    }

    public function villagesCreate(Request $request, LocationIdGenerator $generator): Response
    {
        $selectedDistrictId = (string) $request->query('district_id', '');
        $selectedRegencyId = '';
        $selectedProvinceId = '';

        if ($selectedDistrictId !== '') {
            $district = District::query()
                ->with('regency:id,province_id')
                ->find($selectedDistrictId);

            $selectedRegencyId = (string) ($district?->regency_id ?? '');
            $selectedProvinceId = (string) ($district?->regency?->province_id ?? '');
        }

        return inertia('Admin/Locations/Form', [
            'resource' => $this->locationResourceDefinition('villages'),
            'mode' => 'create',
            'record' => [
                'id' => $this->locationGeneratedIdPreview('village', ['district_id' => $selectedDistrictId], $generator),
                'name' => '',
                'province_id' => $selectedProvinceId,
                'regency_id' => $selectedRegencyId,
                'district_id' => $selectedDistrictId,
                'legacy_url' => null,
            ],
            'selectFields' => [
                [
                    'key' => 'province_id',
                    'label' => 'Provinsi',
                    'placeholder' => 'Pilih provinsi',
                    'options' => $this->provinceSelectOptions(),
                ],
                [
                    'key' => 'regency_id',
                    'label' => 'Kabupaten/Kota',
                    'placeholder' => 'Pilih kabupaten/kota',
                    'options' => $this->regencySelectOptionsByProvince($selectedProvinceId),
                    'depends_on' => 'province_id',
                    'endpoint_type' => 'regencies',
                    'parent_param' => 'province_id',
                ],
                [
                    'key' => 'district_id',
                    'label' => 'Kecamatan',
                    'placeholder' => 'Pilih kecamatan',
                    'options' => $this->districtSelectOptionsByRegency($selectedRegencyId),
                    'depends_on' => 'regency_id',
                    'endpoint_type' => 'districts',
                    'parent_param' => 'regency_id',
                ],
            ],
            'generator' => $this->locationGeneratorProps('village', 'district_id'),
            'showIdField' => false,
            'indexUrl' => route('admin.master-data.villages.index'),
            'submitUrl' => route('admin.master-data.villages.store'),
            'legacyPanelUrl' => url('/legacy-admin'),
        ]);
    }

    public function villagesStore(StoreVillageRequest $request, LocationIdGenerator $generator): RedirectResponse
    {
        $validated = $request->validated();

        DB::transaction(function () use ($validated, $generator): void {
            Village::query()->create([
                'id' => $generator->nextVillageId($validated['district_id']),
                'district_id' => $validated['district_id'],
                'name' => $validated['name'],
            ]);
        });

        return redirect()
            ->route('admin.master-data.villages.index')
            ->with('success', 'Kelurahan/Desa berhasil ditambahkan.');
    }

    public function villagesEdit(Village $village): Response
    {
        $village->loadMissing(['district:id,name,regency_id', 'district.regency:id,name,province_id', 'district.regency.province:id,name']);
        $selectedRegencyId = (string) ($village->district?->regency_id ?? '');
        $selectedProvinceId = (string) ($village->district?->regency?->province_id ?? '');

        return inertia('Admin/Locations/Form', [
            'resource' => $this->locationResourceDefinition('villages'),
            'mode' => 'edit',
            'record' => [
                'id' => $village->id,
                'name' => $village->name,
                'province_id' => $selectedProvinceId,
                'regency_id' => $selectedRegencyId,
                'district_id' => $village->district_id,
                'legacy_url' => $this->legacyVillageUrl($village),
            ],
            'selectFields' => [
                [
                    'key' => 'province_id',
                    'label' => 'Provinsi',
                    'placeholder' => 'Pilih provinsi',
                    'options' => $this->provinceSelectOptions(),
                ],
                [
                    'key' => 'regency_id',
                    'label' => 'Kabupaten/Kota',
                    'placeholder' => 'Pilih kabupaten/kota',
                    'options' => $this->regencySelectOptionsByProvince($selectedProvinceId),
                    'depends_on' => 'province_id',
                    'endpoint_type' => 'regencies',
                    'parent_param' => 'province_id',
                ],
                [
                    'key' => 'district_id',
                    'label' => 'Kecamatan',
                    'placeholder' => 'Pilih kecamatan',
                    'options' => $this->districtSelectOptionsByRegency($selectedRegencyId),
                    'depends_on' => 'regency_id',
                    'endpoint_type' => 'districts',
                    'parent_param' => 'regency_id',
                ],
            ],
            'generator' => null,
            'indexUrl' => route('admin.master-data.villages.index'),
            'submitUrl' => route('admin.master-data.villages.update', $village),
            'legacyPanelUrl' => url('/legacy-admin'),
        ]);
    }

    public function villagesUpdate(StoreVillageRequest $request, Village $village): RedirectResponse
    {
        $validated = $request->validated();

        $village->forceFill([
            'district_id' => $validated['district_id'],
            'name' => $validated['name'],
        ])->save();

        return redirect()
            ->route('admin.master-data.villages.index')
            ->with('success', 'Kelurahan/Desa berhasil diperbarui.');
    }

    public function villagesDestroy(Village $village): RedirectResponse
    {
        return $this->destroyLocationRecord(
            $village,
            'admin.master-data.villages.index',
            'Kelurahan/Desa'
        );
    }

    public function paymentsIndex(Request $request, MidtransSnapService $midtrans): Response
    {
        $filters = [
            'q' => trim((string) $request->query('q', '')),
            'status' => (string) $request->query('status', 'all'),
            'method' => (string) $request->query('method', 'all'),
        ];

        $records = Payment::query()
            ->with(['appraisalRequest.user'])
            ->when($filters['q'] !== '', function ($query) use ($filters): void {
                $query->where(function ($innerQuery) use ($filters): void {
                    $innerQuery
                        ->where('external_payment_id', 'like', '%' . $filters['q'] . '%')
                        ->orWhereHas('appraisalRequest', function ($requestQuery) use ($filters): void {
                            $requestQuery
                                ->where('request_number', 'like', '%' . $filters['q'] . '%')
                                ->orWhere('client_name', 'like', '%' . $filters['q'] . '%')
                                ->orWhereHas('user', fn ($userQuery) => $userQuery->where('name', 'like', '%' . $filters['q'] . '%'));
                        });
                });
            })
            ->when($filters['status'] !== 'all', fn ($query) => $query->where('status', $filters['status']))
            ->when($filters['method'] !== 'all', fn ($query) => $query->where('method', $filters['method']))
            ->latest('created_at')
            ->paginate(15)
            ->withQueryString();

        $records->through(fn (Payment $payment) => $this->transformPaymentRow($payment, $midtrans));

        return inertia('Admin/Payments/Index', [
            'filters' => $filters,
            'statusOptions' => [
                ['value' => 'all', 'label' => 'Semua Status'],
                ['value' => 'pending', 'label' => 'Menunggu'],
                ['value' => 'paid', 'label' => 'Dibayar'],
                ['value' => 'failed', 'label' => 'Gagal'],
                ['value' => 'expired', 'label' => 'Kedaluwarsa'],
                ['value' => 'rejected', 'label' => 'Ditolak'],
                ['value' => 'refunded', 'label' => 'Refund'],
            ],
            'methodOptions' => [
                ['value' => 'all', 'label' => 'Semua Metode'],
                ['value' => 'gateway', 'label' => 'Gateway / Midtrans'],
                ['value' => 'manual', 'label' => 'Legacy Manual'],
            ],
            'summary' => [
                'total' => Payment::query()->count(),
                'pending' => Payment::query()->where('status', 'pending')->count(),
                'paid' => Payment::query()->where('status', 'paid')->count(),
                'active_bank_accounts' => OfficeBankAccount::query()->where('is_active', true)->count(),
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
            'officeBankAccountsUrl' => route('admin.finance.office-bank-accounts.index'),
            'legacyPanelUrl' => url('/legacy-admin'),
        ]);
    }

    public function paymentsShow(Payment $payment, MidtransSnapService $midtrans): Response
    {
        $payment->loadMissing(['appraisalRequest.user']);

        $gatewayDetails = $midtrans->gatewayDetailsFromMetadata(is_array($payment->metadata) ? $payment->metadata : []);
        $proofFileUrl = filled($payment->proof_file_path) && Storage::disk('public')->exists($payment->proof_file_path)
            ? Storage::disk('public')->url($payment->proof_file_path)
            : null;

        return inertia('Admin/Payments/Show', [
            'record' => [
                'id' => $payment->id,
                'invoice_number' => $this->paymentInvoiceNumber($payment),
                'amount' => (int) $payment->amount,
                'method' => $payment->method,
                'method_label' => $midtrans->paymentMethodLabel($payment),
                'status' => $payment->status,
                'status_label' => $midtrans->paymentStatusLabel($payment),
                'gateway' => $payment->gateway,
                'external_payment_id' => $payment->external_payment_id,
                'paid_at' => $payment->paid_at?->toIso8601String(),
                'proof_original_name' => $payment->proof_original_name,
                'proof_mime' => $payment->proof_mime,
                'proof_size' => (int) ($payment->proof_size ?? 0),
                'proof_size_label' => $this->formatBytes($payment->proof_size),
                'proof_type' => $payment->proof_type,
                'proof_url' => $proofFileUrl,
                'metadata_lines' => $this->paymentMetadataLines($payment->metadata),
                'request_number' => $payment->appraisalRequest?->request_number ?? ('REQ-' . $payment->appraisal_request_id),
                'requester_name' => $payment->appraisalRequest?->user?->name ?? '-',
                'client_name' => $payment->appraisalRequest?->client_name ?: ($payment->appraisalRequest?->user?->name ?? '-'),
                'request_show_url' => $payment->appraisalRequest
                    ? route('admin.appraisal-requests.show', $payment->appraisalRequest)
                    : null,
                'created_at' => $payment->created_at?->toIso8601String(),
                'updated_at' => $payment->updated_at?->toIso8601String(),
                'legacy_url' => $this->legacyPaymentUrl($payment),
            ],
            'gatewayDetails' => $gatewayDetails,
            'officeBankAccountsUrl' => route('admin.finance.office-bank-accounts.index'),
            'indexUrl' => route('admin.finance.payments.index'),
            'legacyPanelUrl' => url('/legacy-admin'),
        ]);
    }

    public function paymentsEdit(Payment $payment): Response
    {
        $payment->loadMissing(['appraisalRequest.user']);

        return inertia('Admin/Payments/Edit', [
            'record' => [
                'id' => $payment->id,
                'invoice_number' => $this->paymentInvoiceNumber($payment),
                'method' => $payment->method,
                'method_label' => $payment->method === 'gateway' ? 'Midtrans Gateway' : 'Legacy Manual',
                'amount' => (int) $payment->amount,
                'status' => $payment->status,
                'gateway' => $payment->gateway,
                'external_payment_id' => $payment->external_payment_id,
                'paid_at' => $payment->paid_at?->format('Y-m-d\TH:i'),
                'metadata_json' => $this->formatPaymentMetadataJson($payment->metadata),
                'request_number' => $payment->appraisalRequest?->request_number ?? ('REQ-' . $payment->appraisal_request_id),
                'requester_name' => $payment->appraisalRequest?->user?->name ?? '-',
                'client_name' => $payment->appraisalRequest?->client_name ?: ($payment->appraisalRequest?->user?->name ?? '-'),
                'show_url' => route('admin.finance.payments.show', $payment),
                'request_show_url' => $payment->appraisalRequest
                    ? route('admin.appraisal-requests.show', $payment->appraisalRequest)
                    : null,
                'legacy_url' => $this->legacyPaymentUrl($payment),
            ],
            'statusOptions' => [
                ['value' => 'pending', 'label' => 'Menunggu'],
                ['value' => 'paid', 'label' => 'Dibayar'],
                ['value' => 'failed', 'label' => 'Gagal'],
                ['value' => 'expired', 'label' => 'Kedaluwarsa'],
                ['value' => 'rejected', 'label' => 'Ditolak'],
                ['value' => 'refunded', 'label' => 'Refund'],
            ],
            'indexUrl' => route('admin.finance.payments.index'),
            'legacyPanelUrl' => url('/legacy-admin'),
        ]);
    }

    public function paymentsUpdate(UpdatePaymentRequest $request, Payment $payment): RedirectResponse
    {
        $validated = $request->validated();

        $payment->forceFill([
            'amount' => (int) $validated['amount'],
            'status' => $validated['status'],
            'gateway' => $validated['gateway'] ?: 'midtrans',
            'external_payment_id' => $validated['external_payment_id'] ?: null,
            'paid_at' => $validated['paid_at'] ?? null,
            'metadata' => $this->decodePaymentMetadata($validated['metadata_json'] ?? null),
        ])->save();

        return redirect()
            ->route('admin.finance.payments.show', $payment)
            ->with('success', 'Pembayaran berhasil diperbarui.');
    }

    public function officeBankAccountsIndex(Request $request): Response
    {
        $filters = [
            'q' => trim((string) $request->query('q', '')),
            'status' => (string) $request->query('status', 'all'),
        ];

        $records = OfficeBankAccount::query()
            ->when($filters['q'] !== '', function ($query) use ($filters): void {
                $query->where(function ($innerQuery) use ($filters): void {
                    $innerQuery
                        ->where('bank_name', 'like', '%' . $filters['q'] . '%')
                        ->orWhere('account_number', 'like', '%' . $filters['q'] . '%')
                        ->orWhere('account_holder', 'like', '%' . $filters['q'] . '%');
                });
            })
            ->when($filters['status'] === 'active', fn ($query) => $query->where('is_active', true))
            ->when($filters['status'] === 'inactive', fn ($query) => $query->where('is_active', false))
            ->orderBy('sort_order')
            ->orderBy('bank_name')
            ->get()
            ->map(fn (OfficeBankAccount $account) => $this->transformOfficeBankAccountRow($account))
            ->values();

        return inertia('Admin/OfficeBankAccounts/Index', [
            'filters' => $filters,
            'statusOptions' => [
                ['value' => 'all', 'label' => 'Semua Status'],
                ['value' => 'active', 'label' => 'Aktif'],
                ['value' => 'inactive', 'label' => 'Nonaktif'],
            ],
            'summary' => [
                'total' => OfficeBankAccount::query()->count(),
                'active' => OfficeBankAccount::query()->where('is_active', true)->count(),
                'inactive' => OfficeBankAccount::query()->where('is_active', false)->count(),
            ],
            'records' => $records,
            'createUrl' => route('admin.finance.office-bank-accounts.create'),
            'paymentsUrl' => route('admin.finance.payments.index'),
            'legacyPanelUrl' => url('/legacy-admin'),
        ]);
    }

    public function officeBankAccountsCreate(): Response
    {
        return inertia('Admin/OfficeBankAccounts/Form', [
            'mode' => 'create',
            'record' => [
                'bank_name' => '',
                'account_number' => '',
                'account_holder' => '',
                'branch' => '',
                'currency' => 'IDR',
                'notes' => '',
                'is_active' => true,
                'sort_order' => 0,
                'legacy_url' => null,
            ],
            'indexUrl' => route('admin.finance.office-bank-accounts.index'),
            'submitUrl' => route('admin.finance.office-bank-accounts.store'),
            'legacyPanelUrl' => url('/legacy-admin'),
        ]);
    }

    public function officeBankAccountsStore(StoreOfficeBankAccountRequest $request): RedirectResponse
    {
        OfficeBankAccount::query()->create($request->validated());

        return redirect()
            ->route('admin.finance.office-bank-accounts.index')
            ->with('success', 'Rekening kantor berhasil ditambahkan.');
    }

    public function officeBankAccountsEdit(OfficeBankAccount $officeBankAccount): Response
    {
        return inertia('Admin/OfficeBankAccounts/Form', [
            'mode' => 'edit',
            'record' => [
                'id' => $officeBankAccount->id,
                'bank_name' => $officeBankAccount->bank_name,
                'account_number' => $officeBankAccount->account_number,
                'account_holder' => $officeBankAccount->account_holder,
                'branch' => $officeBankAccount->branch,
                'currency' => $officeBankAccount->currency,
                'notes' => $officeBankAccount->notes,
                'is_active' => (bool) $officeBankAccount->is_active,
                'sort_order' => (int) $officeBankAccount->sort_order,
                'legacy_url' => $this->legacyOfficeBankAccountUrl($officeBankAccount),
            ],
            'indexUrl' => route('admin.finance.office-bank-accounts.index'),
            'submitUrl' => route('admin.finance.office-bank-accounts.update', $officeBankAccount),
            'legacyPanelUrl' => url('/legacy-admin'),
        ]);
    }

    public function officeBankAccountsUpdate(
        StoreOfficeBankAccountRequest $request,
        OfficeBankAccount $officeBankAccount
    ): RedirectResponse {
        $officeBankAccount->update($request->validated());

        return redirect()
            ->route('admin.finance.office-bank-accounts.index')
            ->with('success', 'Rekening kantor berhasil diperbarui.');
    }

    public function officeBankAccountsDestroy(OfficeBankAccount $officeBankAccount): RedirectResponse
    {
        $officeBankAccount->delete();

        return redirect()
            ->route('admin.finance.office-bank-accounts.index')
            ->with('success', 'Rekening kantor berhasil dihapus.');
    }

    public function articlesIndex(Request $request): Response
    {
        $filters = [
            'q' => trim((string) $request->query('q', '')),
            'status' => (string) $request->query('status', 'all'),
            'category' => (string) $request->query('category', 'all'),
        ];

        $records = Article::query()
            ->with(['category:id,name', 'tags:id,name'])
            ->when($filters['q'] !== '', function ($query) use ($filters): void {
                $query->where(function ($innerQuery) use ($filters): void {
                    $innerQuery
                        ->where('title', 'like', '%' . $filters['q'] . '%')
                        ->orWhere('slug', 'like', '%' . $filters['q'] . '%')
                        ->orWhere('excerpt', 'like', '%' . $filters['q'] . '%');
                });
            })
            ->when($filters['status'] === 'published', fn ($query) => $query->where('is_published', true))
            ->when($filters['status'] === 'draft', fn ($query) => $query->where('is_published', false))
            ->when($filters['category'] !== 'all', fn ($query) => $query->where('category_id', $filters['category']))
            ->latest('created_at')
            ->paginate(12)
            ->withQueryString();

        $records->through(fn (Article $article) => $this->transformArticleRow($article));

        return inertia('Admin/Articles/Index', [
            'filters' => $filters,
            'statusOptions' => [
                ['value' => 'all', 'label' => 'Semua Status'],
                ['value' => 'published', 'label' => 'Published'],
                ['value' => 'draft', 'label' => 'Draft'],
            ],
            'categoryOptions' => ArticleCategory::query()
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get(['id', 'name'])
                ->map(fn (ArticleCategory $category) => [
                    'value' => (string) $category->id,
                    'label' => $category->name,
                ])
                ->values(),
            'summary' => [
                'total' => Article::query()->count(),
                'published' => Article::query()->where('is_published', true)->count(),
                'draft' => Article::query()->where('is_published', false)->count(),
                'categories' => ArticleCategory::query()->count(),
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
            'createUrl' => route('admin.content.articles.create'),
            'categoriesUrl' => route('admin.content.categories.index'),
            'tagsUrl' => route('admin.content.tags.index'),
            'legacyPanelUrl' => url('/legacy-admin'),
        ]);
    }

    public function articlesCreate(): Response
    {
        return inertia('Admin/Articles/Form', [
            'mode' => 'create',
            'record' => $this->articleFormPayload(new Article()),
            'categoryOptions' => $this->articleCategorySelectOptions(),
            'tagOptions' => $this->tagSelectOptions(),
            'indexUrl' => route('admin.content.articles.index'),
            'submitUrl' => route('admin.content.articles.store'),
            'legacyPanelUrl' => url('/legacy-admin'),
        ]);
    }

    public function articlesStore(StoreArticleRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        $article = new Article();
        $this->persistArticle($article, $validated, $request);

        return redirect()
            ->route('admin.content.articles.index')
            ->with('success', 'Artikel berhasil ditambahkan.');
    }

    public function articlesEdit(Article $article): Response
    {
        $article->loadMissing(['category:id,name', 'tags:id,name']);

        return inertia('Admin/Articles/Form', [
            'mode' => 'edit',
            'record' => $this->articleFormPayload($article),
            'categoryOptions' => $this->articleCategorySelectOptions(),
            'tagOptions' => $this->tagSelectOptions(),
            'indexUrl' => route('admin.content.articles.index'),
            'submitUrl' => route('admin.content.articles.update', $article),
            'legacyPanelUrl' => url('/legacy-admin'),
        ]);
    }

    public function articlesUpdate(StoreArticleRequest $request, Article $article): RedirectResponse
    {
        $validated = $request->validated();
        $this->persistArticle($article, $validated, $request);

        return redirect()
            ->route('admin.content.articles.index')
            ->with('success', 'Artikel berhasil diperbarui.');
    }

    public function articlesDestroy(Article $article): RedirectResponse
    {
        if (filled($article->cover_image_path) && Storage::disk('public')->exists($article->cover_image_path)) {
            Storage::disk('public')->delete($article->cover_image_path);
        }

        $article->tags()->detach();
        $article->delete();

        return redirect()
            ->route('admin.content.articles.index')
            ->with('success', 'Artikel berhasil dihapus.');
    }

    public function articleCategoriesIndex(Request $request): Response
    {
        $filters = [
            'q' => trim((string) $request->query('q', '')),
            'status' => (string) $request->query('status', 'all'),
        ];

        $records = ArticleCategory::query()
            ->withCount('articles')
            ->when($filters['q'] !== '', function ($query) use ($filters): void {
                $query->where(function ($innerQuery) use ($filters): void {
                    $innerQuery
                        ->where('name', 'like', '%' . $filters['q'] . '%')
                        ->orWhere('slug', 'like', '%' . $filters['q'] . '%');
                });
            })
            ->when($filters['status'] === 'active', fn ($query) => $query->where('is_active', true))
            ->when($filters['status'] === 'inactive', fn ($query) => $query->where('is_active', false))
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get()
            ->map(fn (ArticleCategory $category) => $this->transformArticleCategoryRow($category))
            ->values();

        return inertia('Admin/ArticleCategories/Index', [
            'filters' => $filters,
            'statusOptions' => [
                ['value' => 'all', 'label' => 'Semua Status'],
                ['value' => 'active', 'label' => 'Aktif'],
                ['value' => 'inactive', 'label' => 'Nonaktif'],
            ],
            'summary' => [
                'total' => ArticleCategory::query()->count(),
                'active' => ArticleCategory::query()->where('is_active', true)->count(),
                'show_in_nav' => ArticleCategory::query()->where('show_in_nav', true)->count(),
            ],
            'records' => $records,
            'createUrl' => route('admin.content.categories.create'),
            'articlesUrl' => route('admin.content.articles.index'),
            'legacyPanelUrl' => url('/legacy-admin'),
        ]);
    }

    public function articleCategoriesCreate(): Response
    {
        return inertia('Admin/ArticleCategories/Form', [
            'mode' => 'create',
            'record' => [
                'name' => '',
                'slug' => '',
                'description' => '',
                'sort_order' => 0,
                'is_active' => true,
                'show_in_nav' => false,
                'legacy_url' => null,
            ],
            'indexUrl' => route('admin.content.categories.index'),
            'submitUrl' => route('admin.content.categories.store'),
            'legacyPanelUrl' => url('/legacy-admin'),
        ]);
    }

    public function articleCategoriesStore(StoreArticleCategoryRequest $request): RedirectResponse
    {
        ArticleCategory::query()->create($request->validated());

        return redirect()
            ->route('admin.content.categories.index')
            ->with('success', 'Kategori artikel berhasil ditambahkan.');
    }

    public function articleCategoriesEdit(ArticleCategory $articleCategory): Response
    {
        return inertia('Admin/ArticleCategories/Form', [
            'mode' => 'edit',
            'record' => [
                'id' => $articleCategory->id,
                'name' => $articleCategory->name,
                'slug' => $articleCategory->slug,
                'description' => $articleCategory->description,
                'sort_order' => (int) $articleCategory->sort_order,
                'is_active' => (bool) $articleCategory->is_active,
                'show_in_nav' => (bool) $articleCategory->show_in_nav,
                'legacy_url' => $this->legacyArticleCategoryUrl($articleCategory),
            ],
            'indexUrl' => route('admin.content.categories.index'),
            'submitUrl' => route('admin.content.categories.update', $articleCategory),
            'legacyPanelUrl' => url('/legacy-admin'),
        ]);
    }

    public function articleCategoriesUpdate(
        StoreArticleCategoryRequest $request,
        ArticleCategory $articleCategory
    ): RedirectResponse {
        $articleCategory->update($request->validated());

        return redirect()
            ->route('admin.content.categories.index')
            ->with('success', 'Kategori artikel berhasil diperbarui.');
    }

    public function articleCategoriesDestroy(ArticleCategory $articleCategory): RedirectResponse
    {
        $articleCategory->delete();

        return redirect()
            ->route('admin.content.categories.index')
            ->with('success', 'Kategori artikel berhasil dihapus.');
    }

    public function tagsIndex(Request $request): Response
    {
        $filters = [
            'q' => trim((string) $request->query('q', '')),
            'status' => (string) $request->query('status', 'all'),
        ];

        $records = Tag::query()
            ->withCount('articles')
            ->when($filters['q'] !== '', function ($query) use ($filters): void {
                $query->where(function ($innerQuery) use ($filters): void {
                    $innerQuery
                        ->where('name', 'like', '%' . $filters['q'] . '%')
                        ->orWhere('slug', 'like', '%' . $filters['q'] . '%');
                });
            })
            ->when($filters['status'] === 'active', fn ($query) => $query->where('is_active', true))
            ->when($filters['status'] === 'inactive', fn ($query) => $query->where('is_active', false))
            ->orderBy('name')
            ->get()
            ->map(fn (Tag $tag) => $this->transformTagRow($tag))
            ->values();

        return inertia('Admin/Tags/Index', [
            'filters' => $filters,
            'statusOptions' => [
                ['value' => 'all', 'label' => 'Semua Status'],
                ['value' => 'active', 'label' => 'Aktif'],
                ['value' => 'inactive', 'label' => 'Nonaktif'],
            ],
            'summary' => [
                'total' => Tag::query()->count(),
                'active' => Tag::query()->where('is_active', true)->count(),
                'articles' => Tag::query()->withCount('articles')->get()->sum('articles_count'),
            ],
            'records' => $records,
            'createUrl' => route('admin.content.tags.create'),
            'articlesUrl' => route('admin.content.articles.index'),
            'legacyPanelUrl' => url('/legacy-admin'),
        ]);
    }

    public function tagsCreate(): Response
    {
        return inertia('Admin/Tags/Form', [
            'mode' => 'create',
            'record' => [
                'name' => '',
                'slug' => '',
                'is_active' => true,
                'legacy_url' => null,
            ],
            'indexUrl' => route('admin.content.tags.index'),
            'submitUrl' => route('admin.content.tags.store'),
            'legacyPanelUrl' => url('/legacy-admin'),
        ]);
    }

    public function tagsStore(StoreTagRequest $request): RedirectResponse
    {
        Tag::query()->create($request->validated());

        return redirect()
            ->route('admin.content.tags.index')
            ->with('success', 'Tag artikel berhasil ditambahkan.');
    }

    public function tagsEdit(Tag $tag): Response
    {
        return inertia('Admin/Tags/Form', [
            'mode' => 'edit',
            'record' => [
                'id' => $tag->id,
                'name' => $tag->name,
                'slug' => $tag->slug,
                'is_active' => (bool) $tag->is_active,
                'legacy_url' => $this->legacyTagUrl($tag),
            ],
            'indexUrl' => route('admin.content.tags.index'),
            'submitUrl' => route('admin.content.tags.update', $tag),
            'legacyPanelUrl' => url('/legacy-admin'),
        ]);
    }

    public function tagsUpdate(StoreTagRequest $request, Tag $tag): RedirectResponse
    {
        $tag->update($request->validated());

        return redirect()
            ->route('admin.content.tags.index')
            ->with('success', 'Tag artikel berhasil diperbarui.');
    }

    public function tagsDestroy(Tag $tag): RedirectResponse
    {
        $tag->delete();

        return redirect()
            ->route('admin.content.tags.index')
            ->with('success', 'Tag artikel berhasil dihapus.');
    }

    public function faqsIndex(Request $request): Response
    {
        $filters = $this->simpleActiveFilters($request);

        $records = Faq::query()
            ->when($filters['q'] !== '', fn ($query) => $query->where('question', 'like', '%' . $filters['q'] . '%'))
            ->when($filters['status'] === 'active', fn ($query) => $query->where('is_active', true))
            ->when($filters['status'] === 'inactive', fn ($query) => $query->where('is_active', false))
            ->orderBy('sort_order')
            ->get()
            ->map(fn (Faq $faq) => [
                'id' => $faq->id,
                'question' => $faq->question,
                'answer' => $faq->answer,
                'sort_order' => (int) $faq->sort_order,
                'is_active' => (bool) $faq->is_active,
                'edit_url' => route('admin.content.legal.faqs.edit', $faq),
                'destroy_url' => route('admin.content.legal.faqs.destroy', $faq),
                'legacy_url' => $this->legacyFaqUrl($faq),
            ])
            ->values();

        return inertia('Admin/SimpleContent/Index', [
            'resource' => ['key' => 'faqs', 'title' => 'FAQ', 'create_label' => 'Tambah FAQ'],
            'filters' => $filters,
            'statusOptions' => $this->simpleStatusOptions(),
            'summary' => [
                'total' => Faq::query()->count(),
                'active' => Faq::query()->where('is_active', true)->count(),
            ],
            'records' => $records,
            'createUrl' => route('admin.content.legal.faqs.create'),
            'links' => $this->legalModuleLinks(),
            'legacyPanelUrl' => url('/legacy-admin'),
        ]);
    }

    public function faqsCreate(): Response
    {
        return inertia('Admin/SimpleContent/Form', [
            'resource' => ['key' => 'faqs', 'title' => 'FAQ', 'singular' => 'FAQ'],
            'mode' => 'create',
            'record' => [
                'question' => '',
                'answer' => '',
                'sort_order' => 0,
                'is_active' => true,
                'legacy_url' => null,
            ],
            'indexUrl' => route('admin.content.legal.faqs.index'),
            'submitUrl' => route('admin.content.legal.faqs.store'),
            'links' => $this->legalModuleLinks(),
            'legacyPanelUrl' => url('/legacy-admin'),
        ]);
    }

    public function faqsStore(StoreFaqRequest $request): RedirectResponse
    {
        Faq::query()->create($request->validated());

        return redirect()->route('admin.content.legal.faqs.index')->with('success', 'FAQ berhasil ditambahkan.');
    }

    public function faqsEdit(Faq $faq): Response
    {
        return inertia('Admin/SimpleContent/Form', [
            'resource' => ['key' => 'faqs', 'title' => 'FAQ', 'singular' => 'FAQ'],
            'mode' => 'edit',
            'record' => [
                'id' => $faq->id,
                'question' => $faq->question,
                'answer' => $faq->answer,
                'sort_order' => (int) $faq->sort_order,
                'is_active' => (bool) $faq->is_active,
                'legacy_url' => $this->legacyFaqUrl($faq),
            ],
            'indexUrl' => route('admin.content.legal.faqs.index'),
            'submitUrl' => route('admin.content.legal.faqs.update', $faq),
            'links' => $this->legalModuleLinks(),
            'legacyPanelUrl' => url('/legacy-admin'),
        ]);
    }

    public function faqsUpdate(StoreFaqRequest $request, Faq $faq): RedirectResponse
    {
        $faq->update($request->validated());

        return redirect()->route('admin.content.legal.faqs.index')->with('success', 'FAQ berhasil diperbarui.');
    }

    public function faqsDestroy(Faq $faq): RedirectResponse
    {
        $faq->delete();

        return redirect()->route('admin.content.legal.faqs.index')->with('success', 'FAQ berhasil dihapus.');
    }

    public function featuresIndex(Request $request): Response
    {
        $filters = $this->simpleActiveFilters($request);

        $records = Feature::query()
            ->when($filters['q'] !== '', fn ($query) => $query->where('title', 'like', '%' . $filters['q'] . '%'))
            ->when($filters['status'] === 'active', fn ($query) => $query->where('is_active', true))
            ->when($filters['status'] === 'inactive', fn ($query) => $query->where('is_active', false))
            ->orderBy('sort_order')
            ->get()
            ->map(fn (Feature $feature) => [
                'id' => $feature->id,
                'icon' => $feature->icon,
                'title' => $feature->title,
                'description' => $feature->description,
                'sort_order' => (int) $feature->sort_order,
                'is_active' => (bool) $feature->is_active,
                'edit_url' => route('admin.content.legal.features.edit', $feature),
                'destroy_url' => route('admin.content.legal.features.destroy', $feature),
                'legacy_url' => $this->legacyFeatureUrl($feature),
            ])
            ->values();

        return inertia('Admin/SimpleContent/Index', [
            'resource' => ['key' => 'features', 'title' => 'Fitur', 'create_label' => 'Tambah Fitur'],
            'filters' => $filters,
            'statusOptions' => $this->simpleStatusOptions(),
            'summary' => [
                'total' => Feature::query()->count(),
                'active' => Feature::query()->where('is_active', true)->count(),
            ],
            'records' => $records,
            'createUrl' => route('admin.content.legal.features.create'),
            'links' => $this->legalModuleLinks(),
            'legacyPanelUrl' => url('/legacy-admin'),
        ]);
    }

    public function featuresCreate(): Response
    {
        return inertia('Admin/SimpleContent/Form', [
            'resource' => ['key' => 'features', 'title' => 'Fitur', 'singular' => 'Fitur'],
            'mode' => 'create',
            'record' => [
                'icon' => '__none',
                'title' => '',
                'description' => '',
                'sort_order' => 0,
                'is_active' => true,
                'legacy_url' => null,
            ],
            'iconOptions' => $this->featureIconOptions(),
            'indexUrl' => route('admin.content.legal.features.index'),
            'submitUrl' => route('admin.content.legal.features.store'),
            'links' => $this->legalModuleLinks(),
            'legacyPanelUrl' => url('/legacy-admin'),
        ]);
    }

    public function featuresStore(StoreFeatureRequest $request): RedirectResponse
    {
        Feature::query()->create($request->validated());

        return redirect()->route('admin.content.legal.features.index')->with('success', 'Fitur berhasil ditambahkan.');
    }

    public function featuresEdit(Feature $feature): Response
    {
        return inertia('Admin/SimpleContent/Form', [
            'resource' => ['key' => 'features', 'title' => 'Fitur', 'singular' => 'Fitur'],
            'mode' => 'edit',
            'record' => [
                'id' => $feature->id,
                'icon' => $feature->icon ?? '__none',
                'title' => $feature->title,
                'description' => $feature->description,
                'sort_order' => (int) $feature->sort_order,
                'is_active' => (bool) $feature->is_active,
                'legacy_url' => $this->legacyFeatureUrl($feature),
            ],
            'iconOptions' => $this->featureIconOptions(),
            'indexUrl' => route('admin.content.legal.features.index'),
            'submitUrl' => route('admin.content.legal.features.update', $feature),
            'links' => $this->legalModuleLinks(),
            'legacyPanelUrl' => url('/legacy-admin'),
        ]);
    }

    public function featuresUpdate(StoreFeatureRequest $request, Feature $feature): RedirectResponse
    {
        $feature->update($request->validated());

        return redirect()->route('admin.content.legal.features.index')->with('success', 'Fitur berhasil diperbarui.');
    }

    public function featuresDestroy(Feature $feature): RedirectResponse
    {
        $feature->delete();

        return redirect()->route('admin.content.legal.features.index')->with('success', 'Fitur berhasil dihapus.');
    }

    public function testimonialsIndex(Request $request): Response
    {
        $filters = $this->simpleActiveFilters($request);

        $records = Testimonial::query()
            ->when($filters['q'] !== '', fn ($query) => $query->where('name', 'like', '%' . $filters['q'] . '%'))
            ->when($filters['status'] === 'active', fn ($query) => $query->where('is_active', true))
            ->when($filters['status'] === 'inactive', fn ($query) => $query->where('is_active', false))
            ->orderBy('sort_order')
            ->get()
            ->map(fn (Testimonial $testimonial) => [
                'id' => $testimonial->id,
                'name' => $testimonial->name,
                'role' => $testimonial->role,
                'quote' => $testimonial->quote,
                'sort_order' => (int) $testimonial->sort_order,
                'is_active' => (bool) $testimonial->is_active,
                'edit_url' => route('admin.content.legal.testimonials.edit', $testimonial),
                'destroy_url' => route('admin.content.legal.testimonials.destroy', $testimonial),
                'legacy_url' => $this->legacyTestimonialUrl($testimonial),
            ])
            ->values();

        return inertia('Admin/SimpleContent/Index', [
            'resource' => ['key' => 'testimonials', 'title' => 'Testimoni', 'create_label' => 'Tambah Testimoni'],
            'filters' => $filters,
            'statusOptions' => $this->simpleStatusOptions(),
            'summary' => [
                'total' => Testimonial::query()->count(),
                'active' => Testimonial::query()->where('is_active', true)->count(),
            ],
            'records' => $records,
            'createUrl' => route('admin.content.legal.testimonials.create'),
            'links' => $this->legalModuleLinks(),
            'legacyPanelUrl' => url('/legacy-admin'),
        ]);
    }

    public function testimonialsCreate(): Response
    {
        return inertia('Admin/SimpleContent/Form', [
            'resource' => ['key' => 'testimonials', 'title' => 'Testimoni', 'singular' => 'Testimoni'],
            'mode' => 'create',
            'record' => [
                'name' => '',
                'role' => '',
                'quote' => '',
                'sort_order' => 0,
                'is_active' => true,
                'legacy_url' => null,
            ],
            'indexUrl' => route('admin.content.legal.testimonials.index'),
            'submitUrl' => route('admin.content.legal.testimonials.store'),
            'links' => $this->legalModuleLinks(),
            'legacyPanelUrl' => url('/legacy-admin'),
        ]);
    }

    public function testimonialsStore(StoreTestimonialRequest $request): RedirectResponse
    {
        Testimonial::query()->create($request->validated());

        return redirect()->route('admin.content.legal.testimonials.index')->with('success', 'Testimoni berhasil ditambahkan.');
    }

    public function testimonialsEdit(Testimonial $testimonial): Response
    {
        return inertia('Admin/SimpleContent/Form', [
            'resource' => ['key' => 'testimonials', 'title' => 'Testimoni', 'singular' => 'Testimoni'],
            'mode' => 'edit',
            'record' => [
                'id' => $testimonial->id,
                'name' => $testimonial->name,
                'role' => $testimonial->role,
                'quote' => $testimonial->quote,
                'sort_order' => (int) $testimonial->sort_order,
                'is_active' => (bool) $testimonial->is_active,
                'legacy_url' => $this->legacyTestimonialUrl($testimonial),
            ],
            'indexUrl' => route('admin.content.legal.testimonials.index'),
            'submitUrl' => route('admin.content.legal.testimonials.update', $testimonial),
            'links' => $this->legalModuleLinks(),
            'legacyPanelUrl' => url('/legacy-admin'),
        ]);
    }

    public function testimonialsUpdate(StoreTestimonialRequest $request, Testimonial $testimonial): RedirectResponse
    {
        $testimonial->update($request->validated());

        return redirect()->route('admin.content.legal.testimonials.index')->with('success', 'Testimoni berhasil diperbarui.');
    }

    public function testimonialsDestroy(Testimonial $testimonial): RedirectResponse
    {
        $testimonial->delete();

        return redirect()->route('admin.content.legal.testimonials.index')->with('success', 'Testimoni berhasil dihapus.');
    }

    public function termsDocumentsIndex(Request $request): Response
    {
        return $this->legalDocumentsIndex(
            $request,
            TermsDocument::class,
            'Admin/LegalDocuments/Index',
            'admin.content.legal.terms',
            'terms'
        );
    }

    public function termsDocumentsCreate(): Response
    {
        return $this->legalDocumentFormResponse(
            'Admin/LegalDocuments/Form',
            'create',
            $this->legalDocumentFormPayload(new TermsDocument()),
            route('admin.content.legal.terms.index'),
            route('admin.content.legal.terms.store')
        );
    }

    public function termsDocumentsStore(StoreLegalDocumentRequest $request): RedirectResponse
    {
        TermsDocument::query()->create($request->validated());

        return redirect()->route('admin.content.legal.terms.index')->with('success', 'Dokumen terms berhasil ditambahkan.');
    }

    public function termsDocumentsEdit(TermsDocument $termsDocument): Response
    {
        return $this->legalDocumentFormResponse(
            'Admin/LegalDocuments/Form',
            'edit',
            $this->legalDocumentFormPayload($termsDocument, $this->legacyTermsDocumentUrl($termsDocument)),
            route('admin.content.legal.terms.index'),
            route('admin.content.legal.terms.update', $termsDocument)
        );
    }

    public function termsDocumentsUpdate(StoreLegalDocumentRequest $request, TermsDocument $termsDocument): RedirectResponse
    {
        $termsDocument->update($request->validated());

        return redirect()->route('admin.content.legal.terms.index')->with('success', 'Dokumen terms berhasil diperbarui.');
    }

    public function termsDocumentsDestroy(TermsDocument $termsDocument): RedirectResponse
    {
        $termsDocument->delete();

        return redirect()->route('admin.content.legal.terms.index')->with('success', 'Dokumen terms berhasil dihapus.');
    }

    public function privacyPoliciesIndex(Request $request): Response
    {
        return $this->legalDocumentsIndex(
            $request,
            PrivacyPolicy::class,
            'Admin/LegalDocuments/Index',
            'admin.content.legal.privacy',
            'privacy'
        );
    }

    public function privacyPoliciesCreate(): Response
    {
        return $this->legalDocumentFormResponse(
            'Admin/LegalDocuments/Form',
            'create',
            $this->legalDocumentFormPayload(new PrivacyPolicy()),
            route('admin.content.legal.privacy.index'),
            route('admin.content.legal.privacy.store')
        );
    }

    public function privacyPoliciesStore(StoreLegalDocumentRequest $request): RedirectResponse
    {
        PrivacyPolicy::query()->create($request->validated());

        return redirect()->route('admin.content.legal.privacy.index')->with('success', 'Dokumen privacy berhasil ditambahkan.');
    }

    public function privacyPoliciesEdit(PrivacyPolicy $privacyPolicy): Response
    {
        return $this->legalDocumentFormResponse(
            'Admin/LegalDocuments/Form',
            'edit',
            $this->legalDocumentFormPayload($privacyPolicy, $this->legacyPrivacyPolicyUrl($privacyPolicy)),
            route('admin.content.legal.privacy.index'),
            route('admin.content.legal.privacy.update', $privacyPolicy)
        );
    }

    public function privacyPoliciesUpdate(StoreLegalDocumentRequest $request, PrivacyPolicy $privacyPolicy): RedirectResponse
    {
        $privacyPolicy->update($request->validated());

        return redirect()->route('admin.content.legal.privacy.index')->with('success', 'Dokumen privacy berhasil diperbarui.');
    }

    public function privacyPoliciesDestroy(PrivacyPolicy $privacyPolicy): RedirectResponse
    {
        $privacyPolicy->delete();

        return redirect()->route('admin.content.legal.privacy.index')->with('success', 'Dokumen privacy berhasil dihapus.');
    }

    public function consentDocumentsIndex(Request $request): Response
    {
        $filters = [
            'q' => trim((string) $request->query('q', '')),
            'status' => (string) $request->query('status', 'all'),
        ];

        $records = ConsentDocument::query()
            ->when($filters['q'] !== '', function ($query) use ($filters): void {
                $query->where(function ($innerQuery) use ($filters): void {
                    $innerQuery
                        ->where('title', 'like', '%' . $filters['q'] . '%')
                        ->orWhere('code', 'like', '%' . $filters['q'] . '%')
                        ->orWhere('version', 'like', '%' . $filters['q'] . '%');
                });
            })
            ->when($filters['status'] !== 'all', fn ($query) => $query->where('status', $filters['status']))
            ->latest('updated_at')
            ->get()
            ->map(fn (ConsentDocument $document) => [
                'id' => $document->id,
                'code' => $document->code,
                'version' => $document->version,
                'title' => $document->title,
                'status' => $document->status,
                'hash' => $document->hash,
                'published_at' => $document->published_at?->toIso8601String(),
                'sections_count' => count((array) $document->sections),
                'edit_url' => route('admin.content.legal.consent.edit', $document),
                'destroy_url' => route('admin.content.legal.consent.destroy', $document),
                'publish_url' => route('admin.content.legal.consent.publish', $document),
                'can_edit' => $document->status === 'draft',
                'can_delete' => $document->status === 'draft',
                'can_publish' => $document->status === 'draft',
                'legacy_url' => $this->legacyConsentDocumentUrl($document),
            ])
            ->values();

        return inertia('Admin/ConsentDocuments/Index', [
            'filters' => $filters,
            'statusOptions' => [
                ['value' => 'all', 'label' => 'Semua Status'],
                ['value' => 'draft', 'label' => 'Draft'],
                ['value' => 'published', 'label' => 'Published'],
                ['value' => 'archived', 'label' => 'Arsip'],
            ],
            'summary' => [
                'total' => ConsentDocument::query()->count(),
                'draft' => ConsentDocument::query()->where('status', 'draft')->count(),
                'published' => ConsentDocument::query()->where('status', 'published')->count(),
            ],
            'records' => $records,
            'createUrl' => route('admin.content.legal.consent.create'),
            'links' => $this->legalModuleLinks(),
            'legacyPanelUrl' => url('/legacy-admin'),
        ]);
    }

    public function consentDocumentsCreate(): Response
    {
        return inertia('Admin/ConsentDocuments/Form', [
            'mode' => 'create',
            'record' => $this->consentDocumentFormPayload(new ConsentDocument()),
            'indexUrl' => route('admin.content.legal.consent.index'),
            'submitUrl' => route('admin.content.legal.consent.store'),
            'links' => $this->legalModuleLinks(),
            'legacyPanelUrl' => url('/legacy-admin'),
        ]);
    }

    public function consentDocumentsStore(StoreConsentDocumentRequest $request): RedirectResponse
    {
        $document = new ConsentDocument();
        $this->persistConsentDocument($document, $request->validated());

        return redirect()->route('admin.content.legal.consent.index')->with('success', 'Dokumen consent berhasil ditambahkan.');
    }

    public function consentDocumentsEdit(ConsentDocument $consentDocument): Response
    {
        return inertia('Admin/ConsentDocuments/Form', [
            'mode' => 'edit',
            'record' => $this->consentDocumentFormPayload($consentDocument),
            'indexUrl' => route('admin.content.legal.consent.index'),
            'submitUrl' => route('admin.content.legal.consent.update', $consentDocument),
            'links' => $this->legalModuleLinks(),
            'legacyPanelUrl' => url('/legacy-admin'),
        ]);
    }

    public function consentDocumentsUpdate(
        StoreConsentDocumentRequest $request,
        ConsentDocument $consentDocument
    ): RedirectResponse {
        if ($consentDocument->status !== 'draft') {
            return redirect()->route('admin.content.legal.consent.index')->with('error', 'Dokumen published tidak bisa diedit.');
        }

        $this->persistConsentDocument($consentDocument, $request->validated());

        return redirect()->route('admin.content.legal.consent.index')->with('success', 'Dokumen consent berhasil diperbarui.');
    }

    public function consentDocumentsDestroy(ConsentDocument $consentDocument): RedirectResponse
    {
        if ($consentDocument->status !== 'draft') {
            return redirect()->route('admin.content.legal.consent.index')->with('error', 'Dokumen published tidak bisa dihapus.');
        }

        $consentDocument->delete();

        return redirect()->route('admin.content.legal.consent.index')->with('success', 'Dokumen consent berhasil dihapus.');
    }

    public function consentDocumentsPublish(ConsentDocument $consentDocument): RedirectResponse
    {
        if ($consentDocument->status !== 'draft') {
            return redirect()->route('admin.content.legal.consent.index')->with('error', 'Hanya draft yang bisa dipublish.');
        }

        ConsentDocument::query()
            ->forCode($consentDocument->code)
            ->published()
            ->where('id', '!=', $consentDocument->id)
            ->update(['status' => 'archived']);

        $consentDocument->status = 'published';
        $consentDocument->published_at = now();
        $consentDocument->hash = ConsentDocument::computeHash($consentDocument->payloadForHash());
        $consentDocument->updated_by = auth()->id();
        $consentDocument->save();

        return redirect()->route('admin.content.legal.consent.index')->with('success', 'Dokumen consent berhasil dipublish.');
    }

    public function appraisalUserConsentsIndex(Request $request): Response
    {
        $filters = [
            'q' => trim((string) $request->query('q', '')),
            'code' => (string) $request->query('code', 'all'),
        ];

        $records = AppraisalUserConsent::query()
            ->with(['user', 'document'])
            ->when($filters['q'] !== '', function ($query) use ($filters): void {
                $query->where(function ($innerQuery) use ($filters): void {
                    $innerQuery
                        ->where('code', 'like', '%' . $filters['q'] . '%')
                        ->orWhere('version', 'like', '%' . $filters['q'] . '%')
                        ->orWhereHas('user', fn ($userQuery) => $userQuery
                            ->where('name', 'like', '%' . $filters['q'] . '%')
                            ->orWhere('email', 'like', '%' . $filters['q'] . '%'));
                });
            })
            ->when($filters['code'] !== 'all', fn ($query) => $query->where('code', $filters['code']))
            ->latest('accepted_at')
            ->paginate(20)
            ->withQueryString();

        $records->through(fn (AppraisalUserConsent $consent) => [
            'id' => $consent->id,
            'user_name' => $consent->user?->name ?? '-',
            'user_email' => $consent->user?->email ?? '-',
            'document_title' => $consent->document?->title ?? '-',
            'code' => $consent->code,
            'version' => $consent->version,
            'accepted_at' => $consent->accepted_at?->toIso8601String(),
            'ip' => $consent->ip,
            'show_url' => route('admin.content.legal.user-consents.show', $consent),
            'legacy_url' => $this->legacyAppraisalUserConsentUrl($consent),
        ]);

        return inertia('Admin/AppraisalUserConsents/Index', [
            'filters' => $filters,
            'codeOptions' => AppraisalUserConsent::query()
                ->distinct()
                ->orderBy('code')
                ->pluck('code')
                ->filter()
                ->values()
                ->map(fn (string $code) => ['value' => $code, 'label' => $code])
                ->all(),
            'records' => [
                'data' => $records->items(),
                'meta' => [
                    'from' => $records->firstItem(),
                    'to' => $records->lastItem(),
                    'total' => $records->total(),
                    'links' => $records->linkCollection()->toArray(),
                ],
            ],
            'links' => $this->legalModuleLinks(),
            'legacyPanelUrl' => url('/legacy-admin'),
        ]);
    }

    public function appraisalUserConsentsShow(AppraisalUserConsent $appraisalUserConsent): Response
    {
        $appraisalUserConsent->loadMissing(['user', 'document']);

        return inertia('Admin/AppraisalUserConsents/Show', [
            'record' => [
                'id' => $appraisalUserConsent->id,
                'user_name' => $appraisalUserConsent->user?->name ?? '-',
                'user_email' => $appraisalUserConsent->user?->email ?? '-',
                'document_title' => $appraisalUserConsent->document?->title ?? '-',
                'code' => $appraisalUserConsent->code,
                'version' => $appraisalUserConsent->version,
                'hash' => $appraisalUserConsent->hash,
                'accepted_at' => $appraisalUserConsent->accepted_at?->toIso8601String(),
                'ip' => $appraisalUserConsent->ip,
                'user_agent' => $appraisalUserConsent->user_agent,
                'legacy_url' => $this->legacyAppraisalUserConsentUrl($appraisalUserConsent),
            ],
            'indexUrl' => route('admin.content.legal.user-consents.index'),
            'links' => $this->legalModuleLinks(),
            'legacyPanelUrl' => url('/legacy-admin'),
        ]);
    }

    public function contactMessagesIndex(Request $request): Response
    {
        $filters = [
            'q' => trim((string) $request->query('q', '')),
            'status' => (string) $request->query('status', 'all'),
            'unread' => (string) $request->query('unread', 'all'),
            'source' => (string) $request->query('source', 'all'),
        ];

        $records = ContactMessage::query()
            ->with('handledBy:id,name')
            ->when($filters['q'] !== '', function ($query) use ($filters): void {
                $query->where(function ($innerQuery) use ($filters): void {
                    $innerQuery
                        ->where('name', 'like', '%' . $filters['q'] . '%')
                        ->orWhere('email', 'like', '%' . $filters['q'] . '%')
                        ->orWhere('subject', 'like', '%' . $filters['q'] . '%')
                        ->orWhere('message', 'like', '%' . $filters['q'] . '%');
                });
            })
            ->when(
                $filters['status'] !== 'all',
                fn ($query) => $query->where('status', $filters['status'])
            )
            ->when(
                $filters['unread'] === 'yes',
                fn ($query) => $query->whereNull('read_at')
            )
            ->when(
                $filters['source'] !== 'all',
                fn ($query) => $query->where('source', $filters['source'])
            )
            ->latest('created_at')
            ->paginate(20)
            ->withQueryString();

        $records->through(fn (ContactMessage $message) => $this->transformContactMessageRow($message));

        return inertia('Admin/ContactMessages/Index', [
            'filters' => $filters,
            'statusOptions' => $this->contactMessageStatusOptions(withAll: true),
            'sourceOptions' => $this->contactMessageSourceOptions(),
            'unreadOptions' => [
                ['value' => 'all', 'label' => 'Semua'],
                ['value' => 'yes', 'label' => 'Unread'],
            ],
            'summary' => [
                'total' => ContactMessage::query()->count(),
                'new' => ContactMessage::query()->where('status', 'new')->count(),
                'unread' => ContactMessage::query()->whereNull('read_at')->count(),
                'done' => ContactMessage::query()->where('status', 'done')->count(),
            ],
            'records' => $this->paginatedRecordsPayload($records),
            'legacyPanelUrl' => url('/legacy-admin'),
        ]);
    }

    public function contactMessagesShow(ContactMessage $contactMessage): Response
    {
        $contactMessage->loadMissing('handledBy:id,name');

        if (blank($contactMessage->read_at)) {
            $contactMessage->forceFill([
                'read_at' => now(),
                'status' => $contactMessage->status === 'new' ? 'in_progress' : $contactMessage->status,
            ])->save();

            $contactMessage->refresh();
            $contactMessage->loadMissing('handledBy:id,name');
        }

        return inertia('Admin/ContactMessages/Show', [
            'record' => $this->contactMessagePayload($contactMessage),
            'indexUrl' => route('admin.communications.contact-messages.index'),
            'legacyPanelUrl' => url('/legacy-admin'),
        ]);
    }

    public function contactMessagesMarkInProgress(ContactMessage $contactMessage): RedirectResponse
    {
        $contactMessage->forceFill([
            'status' => 'in_progress',
            'read_at' => $contactMessage->read_at ?? now(),
        ])->save();

        return redirect()
            ->route('admin.communications.contact-messages.show', $contactMessage)
            ->with('success', 'Pesan ditandai sedang diproses.');
    }

    public function contactMessagesMarkDone(ContactMessage $contactMessage): RedirectResponse
    {
        $contactMessage->forceFill([
            'status' => 'done',
            'read_at' => $contactMessage->read_at ?? now(),
            'handled_at' => now(),
            'handled_by' => auth()->id(),
        ])->save();

        return redirect()
            ->route('admin.communications.contact-messages.show', $contactMessage)
            ->with('success', 'Pesan ditandai selesai.');
    }

    public function contactMessagesArchive(ContactMessage $contactMessage): RedirectResponse
    {
        $contactMessage->forceFill([
            'status' => 'archived',
        ])->save();

        return redirect()
            ->route('admin.communications.contact-messages.show', $contactMessage)
            ->with('success', 'Pesan berhasil diarsipkan.');
    }

    public function contactMessagesDestroy(ContactMessage $contactMessage): RedirectResponse
    {
        $contactMessage->delete();

        return redirect()
            ->route('admin.communications.contact-messages.index')
            ->with('success', 'Pesan berhasil dihapus.');
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

    private function transformPaymentRow(Payment $payment, MidtransSnapService $midtrans): array
    {
        $payment->loadMissing(['appraisalRequest.user']);
        $requestRecord = $payment->appraisalRequest;
        $gatewayDetails = $midtrans->gatewayDetailsFromMetadata(is_array($payment->metadata) ? $payment->metadata : []);

        return [
            'id' => $payment->id,
            'invoice_number' => $this->paymentInvoiceNumber($payment),
            'request_number' => $requestRecord?->request_number ?? ('REQ-' . $payment->appraisal_request_id),
            'client_name' => $requestRecord?->client_name ?: ($requestRecord?->user?->name ?? '-'),
            'requester_name' => $requestRecord?->user?->name ?? '-',
            'amount' => (int) $payment->amount,
            'method' => $payment->method,
            'method_label' => $midtrans->paymentMethodLabel($payment),
            'status' => $payment->status,
            'status_label' => $midtrans->paymentStatusLabel($payment),
            'gateway' => $payment->gateway,
            'bank_label' => $gatewayDetails['bank'] ?? $gatewayDetails['label'] ?? '-',
            'reference' => $gatewayDetails['reference'] ?? null,
            'external_payment_id' => $payment->external_payment_id,
            'paid_at' => $payment->paid_at?->toIso8601String(),
            'updated_at' => $payment->updated_at?->toIso8601String(),
            'show_url' => route('admin.finance.payments.show', $payment),
            'edit_url' => route('admin.finance.payments.edit', $payment),
            'request_show_url' => $requestRecord ? route('admin.appraisal-requests.show', $requestRecord) : null,
        ];
    }

    private function transformOfficeBankAccountRow(OfficeBankAccount $account): array
    {
        return [
            'id' => $account->id,
            'bank_name' => $account->bank_name,
            'account_number' => $account->account_number,
            'account_holder' => $account->account_holder,
            'branch' => $account->branch,
            'currency' => $account->currency,
            'is_active' => (bool) $account->is_active,
            'sort_order' => (int) $account->sort_order,
            'notes' => $account->notes,
            'updated_at' => $account->updated_at?->toIso8601String(),
            'edit_url' => route('admin.finance.office-bank-accounts.edit', $account),
            'destroy_url' => route('admin.finance.office-bank-accounts.destroy', $account),
            'legacy_url' => $this->legacyOfficeBankAccountUrl($account),
        ];
    }

    private function transformContactMessageRow(ContactMessage $message): array
    {
        $message->loadMissing('handledBy:id,name');

        return [
            'id' => $message->id,
            'name' => $message->name,
            'email' => $message->email,
            'subject' => $message->subject,
            'message_excerpt' => Str::limit($message->message, 90),
            'status' => $message->status,
            'status_label' => $this->contactMessageStatusLabel($message->status),
            'source' => $message->source ?: '-',
            'is_unread' => blank($message->read_at),
            'handled_by_name' => $message->handledBy?->name ?? '-',
            'created_at' => $message->created_at?->toIso8601String(),
            'show_url' => route('admin.communications.contact-messages.show', $message),
            'legacy_url' => $this->legacyContactMessageUrl($message),
        ];
    }

    private function contactMessagePayload(ContactMessage $message): array
    {
        return [
            'id' => $message->id,
            'name' => $message->name,
            'email' => $message->email,
            'subject' => $message->subject,
            'message' => $message->message,
            'status' => $message->status,
            'status_label' => $this->contactMessageStatusLabel($message->status),
            'source' => $message->source ?: '-',
            'ip_address' => $message->ip_address ?: '-',
            'user_agent' => $message->user_agent ?: '-',
            'read_at' => $message->read_at?->toIso8601String(),
            'handled_at' => $message->handled_at?->toIso8601String(),
            'handled_by_name' => $message->handledBy?->name ?? '-',
            'created_at' => $message->created_at?->toIso8601String(),
            'legacy_url' => $this->legacyContactMessageUrl($message),
            'available_actions' => [
                'in_progress' => in_array($message->status, ['new', 'in_progress'], true),
                'done' => $message->status !== 'done',
                'archive' => $message->status !== 'archived',
                'delete' => true,
            ],
        ];
    }

    private function transformArticleRow(Article $article): array
    {
        $article->loadMissing(['category:id,name', 'tags:id,name']);

        return [
            'id' => $article->id,
            'title' => $article->title,
            'slug' => $article->slug,
            'excerpt' => $article->excerpt,
            'cover_url' => filled($article->cover_image_path) ? Storage::disk('public')->url($article->cover_image_path) : null,
            'category_name' => $article->category?->name,
            'tag_names' => $article->tags->pluck('name')->values()->all(),
            'is_published' => (bool) $article->is_published,
            'published_at' => $article->published_at?->toIso8601String(),
            'views' => (int) ($article->views ?? 0),
            'preview_url' => route('articles.show', $article->slug) . '?preview=1',
            'edit_url' => route('admin.content.articles.edit', $article),
            'destroy_url' => route('admin.content.articles.destroy', $article),
            'legacy_url' => $this->legacyArticleUrl($article),
        ];
    }

    private function transformArticleCategoryRow(ArticleCategory $category): array
    {
        return [
            'id' => $category->id,
            'name' => $category->name,
            'slug' => $category->slug,
            'description' => $category->description,
            'sort_order' => (int) $category->sort_order,
            'is_active' => (bool) $category->is_active,
            'show_in_nav' => (bool) $category->show_in_nav,
            'articles_count' => (int) ($category->articles_count ?? 0),
            'updated_at' => $category->updated_at?->toIso8601String(),
            'edit_url' => route('admin.content.categories.edit', $category),
            'destroy_url' => route('admin.content.categories.destroy', $category),
            'legacy_url' => $this->legacyArticleCategoryUrl($category),
        ];
    }

    private function transformTagRow(Tag $tag): array
    {
        return [
            'id' => $tag->id,
            'name' => $tag->name,
            'slug' => $tag->slug,
            'is_active' => (bool) $tag->is_active,
            'articles_count' => (int) ($tag->articles_count ?? 0),
            'updated_at' => $tag->updated_at?->toIso8601String(),
            'edit_url' => route('admin.content.tags.edit', $tag),
            'destroy_url' => route('admin.content.tags.destroy', $tag),
            'legacy_url' => $this->legacyTagUrl($tag),
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

    private function legacyPaymentUrl(Payment $payment): ?string
    {
        try {
            return route('filament.admin.resources.payments.view', ['record' => $payment]);
        } catch (\Throwable) {
            return null;
        }
    }

    private function legacyOfficeBankAccountUrl(OfficeBankAccount $account): ?string
    {
        try {
            return route('filament.admin.resources.office-bank-accounts.view', ['record' => $account]);
        } catch (\Throwable) {
            return null;
        }
    }

    private function legacyArticleUrl(Article $article): ?string
    {
        try {
            return route('filament.admin.resources.articles.edit', ['record' => $article]);
        } catch (\Throwable) {
            return null;
        }
    }

    private function legacyArticleCategoryUrl(ArticleCategory $category): ?string
    {
        try {
            return route('filament.admin.resources.article-categories.edit', ['record' => $category]);
        } catch (\Throwable) {
            return null;
        }
    }

    private function legacyTagUrl(Tag $tag): ?string
    {
        try {
            return route('filament.admin.resources.tags.edit', ['record' => $tag]);
        } catch (\Throwable) {
            return null;
        }
    }

    private function legacyFaqUrl(Faq $faq): ?string
    {
        try {
            return route('filament.admin.resources.faqs.edit', ['record' => $faq]);
        } catch (\Throwable) {
            return null;
        }
    }

    private function legacyFeatureUrl(Feature $feature): ?string
    {
        try {
            return route('filament.admin.resources.features.edit', ['record' => $feature]);
        } catch (\Throwable) {
            return null;
        }
    }

    private function legacyTestimonialUrl(Testimonial $testimonial): ?string
    {
        try {
            return route('filament.admin.resources.testimonials.edit', ['record' => $testimonial]);
        } catch (\Throwable) {
            return null;
        }
    }

    private function legacyTermsDocumentUrl(TermsDocument $document): ?string
    {
        try {
            return route('filament.admin.resources.terms-documents.edit', ['record' => $document]);
        } catch (\Throwable) {
            return null;
        }
    }

    private function legacyPrivacyPolicyUrl(PrivacyPolicy $policy): ?string
    {
        try {
            return route('filament.admin.resources.privacy-policies.edit', ['record' => $policy]);
        } catch (\Throwable) {
            return null;
        }
    }

    private function legacyConsentDocumentUrl(ConsentDocument $document): ?string
    {
        try {
            return route('filament.admin.resources.consent-documents.edit', ['record' => $document]);
        } catch (\Throwable) {
            return null;
        }
    }

    private function legacyAppraisalUserConsentUrl(AppraisalUserConsent $consent): ?string
    {
        try {
            return route('filament.admin.resources.appraisal-user-consents.view', ['record' => $consent]);
        } catch (\Throwable) {
            return null;
        }
    }

    private function legacyProvinceUrl(Province $province): ?string
    {
        try {
            return route('filament.admin.daftar-nama-lokasi.resources.provinces.edit', ['record' => $province]);
        } catch (\Throwable) {
            return null;
        }
    }

    private function legacyRegencyUrl(Regency $regency): ?string
    {
        try {
            return route('filament.admin.daftar-nama-lokasi.resources.regencies.edit', ['record' => $regency]);
        } catch (\Throwable) {
            return null;
        }
    }

    private function legacyDistrictUrl(District $district): ?string
    {
        try {
            return route('filament.admin.daftar-nama-lokasi.resources.districts.edit', ['record' => $district]);
        } catch (\Throwable) {
            return null;
        }
    }

    private function legacyVillageUrl(Village $village): ?string
    {
        try {
            return route('filament.admin.daftar-nama-lokasi.resources.villages.edit', ['record' => $village]);
        } catch (\Throwable) {
            return null;
        }
    }

    private function legacyUserUrl(User $user): ?string
    {
        try {
            return route('filament.admin.resources.users.edit', ['record' => $user]);
        } catch (\Throwable) {
            return null;
        }
    }

    private function legacyRoleUrl(Role $role): ?string
    {
        try {
            return route('filament.admin.resources.shield.roles.edit', ['record' => $role]);
        } catch (\Throwable) {
            return null;
        }
    }

    private function legacyGuidelineSetUrl(GuidelineSet $guidelineSet): ?string
    {
        try {
            return route('filament.admin.ref-guidelines.resources.ref-guideline-sets.edit', ['record' => $guidelineSet]);
        } catch (\Throwable) {
            return null;
        }
    }

    private function legacyValuationSettingUrl(ValuationSetting $valuationSetting): ?string
    {
        try {
            return route('filament.admin.ref-guidelines.resources.valuation-settings.edit', ['record' => $valuationSetting]);
        } catch (\Throwable) {
            return null;
        }
    }

    private function legacyConstructionCostIndexUrl(ConstructionCostIndex $constructionCostIndex): ?string
    {
        try {
            return route('filament.admin.ref-guidelines.resources.construction-cost-indices.edit', ['record' => $constructionCostIndex]);
        } catch (\Throwable) {
            return null;
        }
    }

    private function legacyCostElementUrl(CostElement $costElement): ?string
    {
        try {
            return route('filament.admin.ref-guidelines.resources.cost-elements.edit', ['record' => $costElement]);
        } catch (\Throwable) {
            return null;
        }
    }

    private function legacyFloorIndexUrl(FloorIndex $floorIndex): ?string
    {
        try {
            return route('filament.admin.ref-guidelines.resources.floor-indices.edit', ['record' => $floorIndex]);
        } catch (\Throwable) {
            return null;
        }
    }

    private function legacyMappiRcnStandardUrl(MappiRcnStandard $mappiRcnStandard): ?string
    {
        try {
            return route('filament.admin.ref-guidelines.resources.mappi-rcn-standards.edit', ['record' => $mappiRcnStandard]);
        } catch (\Throwable) {
            return null;
        }
    }

    private function legacyContactMessageUrl(ContactMessage $message): ?string
    {
        try {
            return route('filament.admin.resources.contact-messages.view', ['record' => $message]);
        } catch (\Throwable) {
            return null;
        }
    }

    private function contactMessageStatusOptions(bool $withAll = false): array
    {
        $options = [
            ['value' => 'new', 'label' => 'New'],
            ['value' => 'in_progress', 'label' => 'In Progress'],
            ['value' => 'done', 'label' => 'Done'],
            ['value' => 'archived', 'label' => 'Archived'],
        ];

        if (!$withAll) {
            return $options;
        }

        return [
            ['value' => 'all', 'label' => 'Semua Status'],
            ...$options,
        ];
    }

    private function contactMessageSourceOptions(): array
    {
        return ContactMessage::query()
            ->distinct()
            ->orderBy('source')
            ->pluck('source')
            ->filter()
            ->values()
            ->map(fn (string $source) => [
                'value' => $source,
                'label' => $source,
            ])
            ->all();
    }

    private function contactMessageStatusLabel(?string $status): string
    {
        return match ($status) {
            'new' => 'New',
            'in_progress' => 'In Progress',
            'done' => 'Done',
            'archived' => 'Archived',
            default => '-',
        };
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

    private function negotiationActionTone(?string $action): string
    {
        return match ((string) $action) {
            'counter_request' => 'warning',
            'accept_offer', 'accepted', 'contract_sign_mock' => 'success',
            'cancel_request', 'cancelled' => 'danger',
            'offer_sent', 'offer_revised' => 'info',
            default => 'default',
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
                ->map(fn ($file) => $this->transformAssetFile($asset, $file))
                ->values(),
            'photos' => $files
                ->whereIn('type', ['photo_access_road', 'photo_front', 'photo_interior'])
                ->map(fn ($file) => $this->transformAssetFile($asset, $file))
                ->values(),
        ];
    }

    private function transformAssetFile(AppraisalAsset $asset, object $file): array
    {
        return [
            'id' => $file->id,
            'type' => (string) $file->type,
            'type_label' => $this->assetFileTypeLabel($file->type),
            'can_delete' => true,
            'original_name' => $file->original_name ?: basename((string) $file->path),
            'mime' => $file->mime,
            'size' => (int) ($file->size ?? 0),
            'size_label' => $this->formatBytes($file->size),
            'url' => Storage::disk('public')->url($file->path),
            'destroy_url' => route('admin.appraisal-requests.assets.files.destroy', [$asset->appraisal_request_id, $asset, $file]),
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

    private function articleFormPayload(Article $article): array
    {
        $article->loadMissing(['tags:id,name']);

        return [
            'id' => $article->id,
            'title' => $article->title ?? '',
            'slug' => $article->slug ?? '',
            'excerpt' => $article->excerpt ?? '',
            'content_html' => $article->content_html ?? '',
            'cover_image_path' => $article->cover_image_path,
            'cover_url' => filled($article->cover_image_path) ? Storage::disk('public')->url($article->cover_image_path) : null,
            'meta_title' => $article->meta_title ?? '',
            'meta_description' => $article->meta_description ?? '',
            'category_id' => $article->category_id ? (string) $article->category_id : '__none',
            'tag_ids' => $article->tags->pluck('id')->map(fn ($id) => (string) $id)->values()->all(),
            'is_published' => (bool) ($article->is_published ?? false),
            'published_at' => $article->published_at?->format('Y-m-d\TH:i'),
            'preview_url' => $article->exists ? route('articles.show', $article->slug) . '?preview=1' : null,
            'legacy_url' => $article->exists ? $this->legacyArticleUrl($article) : null,
        ];
    }

    private function articleCategorySelectOptions(): array
    {
        return ArticleCategory::query()
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (ArticleCategory $category) => [
                'value' => (string) $category->id,
                'label' => $category->name,
            ])
            ->values()
            ->all();
    }

    private function tagSelectOptions(): array
    {
        return Tag::query()
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (Tag $tag) => [
                'value' => (string) $tag->id,
                'label' => $tag->name,
            ])
            ->values()
            ->all();
    }

    private function simpleActiveFilters(Request $request): array
    {
        return [
            'q' => trim((string) $request->query('q', '')),
            'status' => (string) $request->query('status', 'all'),
        ];
    }

    private function simpleStatusOptions(): array
    {
        return [
            ['value' => 'all', 'label' => 'Semua Status'],
            ['value' => 'active', 'label' => 'Aktif'],
            ['value' => 'inactive', 'label' => 'Nonaktif'],
        ];
    }

    private function legalModuleLinks(): array
    {
        return [
            ['label' => 'FAQ', 'url' => route('admin.content.legal.faqs.index')],
            ['label' => 'Fitur', 'url' => route('admin.content.legal.features.index')],
            ['label' => 'Testimoni', 'url' => route('admin.content.legal.testimonials.index')],
            ['label' => 'Terms', 'url' => route('admin.content.legal.terms.index')],
            ['label' => 'Privacy', 'url' => route('admin.content.legal.privacy.index')],
            ['label' => 'Consent', 'url' => route('admin.content.legal.consent.index')],
            ['label' => 'Audit Consent', 'url' => route('admin.content.legal.user-consents.index')],
        ];
    }

    private function featureIconOptions(): array
    {
        return [
            ['value' => 'TrendingUp', 'label' => 'TrendingUp'],
            ['value' => 'Zap', 'label' => 'Zap'],
            ['value' => 'ShieldCheck', 'label' => 'ShieldCheck'],
            ['value' => 'Smartphone', 'label' => 'Smartphone'],
            ['value' => 'CheckCircle2', 'label' => 'CheckCircle2'],
            ['value' => 'Star', 'label' => 'Star'],
        ];
    }

    private function legalDocumentsIndex(
        Request $request,
        string $modelClass,
        string $component,
        string $routePrefix,
        string $legacyType
    ): Response {
        $filters = [
            'q' => trim((string) $request->query('q', '')),
            'status' => (string) $request->query('status', 'all'),
        ];

        $records = $modelClass::query()
            ->when($filters['q'] !== '', fn ($query) => $query->where('title', 'like', '%' . $filters['q'] . '%'))
            ->when($filters['status'] === 'active', fn ($query) => $query->where('is_active', true))
            ->when($filters['status'] === 'inactive', fn ($query) => $query->where('is_active', false))
            ->latest('updated_at')
            ->get()
            ->map(function ($document) use ($routePrefix, $legacyType) {
                return [
                    'id' => $document->id,
                    'title' => $document->title,
                    'company' => $document->company,
                    'version' => $document->version,
                    'effective_since' => $document->effective_since?->toDateString(),
                    'is_active' => (bool) $document->is_active,
                    'published_at' => $document->published_at?->toIso8601String(),
                    'edit_url' => route($routePrefix . '.edit', $document),
                    'destroy_url' => route($routePrefix . '.destroy', $document),
                    'legacy_url' => $legacyType === 'terms'
                        ? $this->legacyTermsDocumentUrl($document)
                        : $this->legacyPrivacyPolicyUrl($document),
                ];
            })
            ->values();

        return inertia($component, [
            'resource' => [
                'key' => $legacyType,
                'title' => $legacyType === 'terms' ? 'Terms' : 'Privacy Policy',
                'create_label' => $legacyType === 'terms' ? 'Tambah Terms' : 'Tambah Privacy Policy',
            ],
            'filters' => $filters,
            'statusOptions' => $this->simpleStatusOptions(),
            'summary' => [
                'total' => $modelClass::query()->count(),
                'active' => $modelClass::query()->where('is_active', true)->count(),
            ],
            'records' => $records,
            'createUrl' => route($routePrefix . '.create'),
            'links' => $this->legalModuleLinks(),
            'legacyPanelUrl' => url('/legacy-admin'),
        ]);
    }

    private function legalDocumentFormPayload(object $document, ?string $legacyUrl = null): array
    {
        return [
            'id' => $document->id ?? null,
            'title' => $document->title ?? '',
            'company' => $document->company ?? '',
            'version' => $document->version ?? '',
            'effective_since' => $document->effective_since?->format('Y-m-d'),
            'content_html' => $document->content_html ?? '',
            'is_active' => (bool) ($document->is_active ?? false),
            'published_at' => $document->published_at?->format('Y-m-d\TH:i'),
            'legacy_url' => $legacyUrl,
        ];
    }

    private function legalDocumentFormResponse(
        string $component,
        string $mode,
        array $record,
        string $indexUrl,
        string $submitUrl
    ): Response {
        return inertia($component, [
            'resource' => [
                'key' => str_contains($indexUrl, '/terms') ? 'terms' : 'privacy',
                'title' => str_contains($indexUrl, '/terms') ? 'Terms' : 'Privacy Policy',
                'singular' => str_contains($indexUrl, '/terms') ? 'Terms' : 'Privacy Policy',
            ],
            'mode' => $mode,
            'record' => $record,
            'indexUrl' => $indexUrl,
            'submitUrl' => $submitUrl,
            'links' => $this->legalModuleLinks(),
            'legacyPanelUrl' => url('/legacy-admin'),
        ]);
    }

    private function consentDocumentFormPayload(ConsentDocument $document): array
    {
        return [
            'id' => $document->id,
            'code' => $document->code ?: 'appraisal_request_consent',
            'version' => $document->version ?? '',
            'title' => $document->title ?? '',
            'status' => $document->status ?? 'draft',
            'checkbox_label' => $document->checkbox_label ?: 'Saya telah membaca, memahami, dan menyetujui seluruh Persetujuan dan Disclaimer di atas.',
            'hash' => $document->hash,
            'published_at' => $document->published_at?->toIso8601String(),
            'sections_json' => $this->formatConsentSectionsJson($document->sections),
            'legacy_url' => $document->exists ? $this->legacyConsentDocumentUrl($document) : null,
        ];
    }

    private function persistConsentDocument(ConsentDocument $document, array $validated): void
    {
        $document->code = $validated['code'];
        $document->version = $validated['version'];
        $document->title = $validated['title'];
        $document->status = $validated['status'];
        $document->checkbox_label = $validated['checkbox_label'] ?? null;
        $document->sections = $this->decodeConsentSections($validated['sections_json']);
        $document->hash = $document->exists ? $document->hash : str_repeat('0', 64);
        $document->created_by = $document->exists ? $document->created_by : auth()->id();
        $document->updated_by = auth()->id();
        $document->save();
    }

    private function formatConsentSectionsJson(mixed $sections): string
    {
        if (! is_array($sections) || $sections === []) {
            return json_encode([
                [
                    'heading' => 'Section 1',
                    'lead' => null,
                    'items' => ['Isi persetujuan pertama'],
                ],
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }

        return (string) json_encode($sections, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    private function decodeConsentSections(?string $sectionsJson): array
    {
        $decoded = json_decode((string) $sectionsJson, true);
        if (! is_array($decoded)) {
            return [];
        }

        return collect($decoded)->map(function ($section) {
            $items = collect($section['items'] ?? [])
                ->map(fn ($item) => is_array($item) ? ($item['text'] ?? null) : $item)
                ->filter(fn ($item) => is_string($item) && trim($item) !== '')
                ->values()
                ->all();

            return [
                'heading' => (string) ($section['heading'] ?? ''),
                'lead' => blank($section['lead'] ?? null) ? null : (string) $section['lead'],
                'items' => $items,
            ];
        })->filter(fn ($section) => $section['heading'] !== '' || $section['items'] !== [])
            ->values()
            ->all();
    }

    private function persistArticle(Article $article, array $validated, Request $request): void
    {
        $coverPath = $article->cover_image_path;
        if ($request->hasFile('cover_image')) {
            $newCoverPath = $request->file('cover_image')->store('articles', 'public');

            if (filled($coverPath) && Storage::disk('public')->exists($coverPath)) {
                Storage::disk('public')->delete($coverPath);
            }

            $coverPath = $newCoverPath;
        }

        $isPublished = (bool) ($validated['is_published'] ?? false);
        $publishedAt = $validated['published_at'] ?? null;
        if ($isPublished && blank($publishedAt)) {
            $publishedAt = now();
        }

        if (! $isPublished) {
            $publishedAt = null;
        }

        $article->fill([
            'title' => $validated['title'],
            'slug' => $validated['slug'],
            'excerpt' => $validated['excerpt'] ?? null,
            'content_html' => $validated['content_html'],
            'cover_image_path' => $coverPath,
            'meta_title' => $validated['meta_title'] ?? null,
            'meta_description' => $validated['meta_description'] ?? null,
            'category_id' => $validated['category_id'] ?: null,
            'is_published' => $isPublished,
            'published_at' => $publishedAt,
        ]);
        $article->save();
        $article->tags()->sync($validated['tag_ids'] ?? []);
    }

    private function paymentInvoiceNumber(Payment $payment): string
    {
        $invoice = data_get($payment->metadata, 'invoice_number');

        if (filled($invoice)) {
            return (string) $invoice;
        }

        return 'INV-' . now()->format('Y') . '-' . str_pad((string) $payment->id, 5, '0', STR_PAD_LEFT);
    }

    /**
     * @return array<int, string>
     */
    private function paymentMetadataLines(mixed $metadata): array
    {
        if (! is_array($metadata) || empty($metadata)) {
            return ['-'];
        }

        $lines = [];
        $flatten = function (array $data, string $prefix = '') use (&$flatten, &$lines): void {
            foreach ($data as $key => $value) {
                $path = $prefix === '' ? (string) $key : "{$prefix}.{$key}";

                if (is_array($value)) {
                    $flatten($value, $path);
                    continue;
                }

                $label = ucwords(str_replace(['.', '_'], [' > ', ' '], $path));
                $text = match (true) {
                    $value === null => '-',
                    is_bool($value) => $value ? 'Ya' : 'Tidak',
                    default => (string) $value,
                };

                $lines[] = "{$label}: {$text}";
            }
        };

        $flatten($metadata);

        return empty($lines) ? ['-'] : $lines;
    }

    private function formatPaymentMetadataJson(mixed $metadata): string
    {
        if (! is_array($metadata) || empty($metadata)) {
            return '';
        }

        return (string) json_encode($metadata, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }

    private function decodePaymentMetadata(?string $metadata): ?array
    {
        if (blank($metadata)) {
            return null;
        }

        $decoded = json_decode((string) $metadata, true);

        return is_array($decoded) ? $decoded : null;
    }

    private function locationResourceDefinition(string $key): array
    {
        return match ($key) {
            'provinces' => [
                'key' => 'provinces',
                'title' => 'Provinsi',
                'singular' => 'Provinsi',
                'description' => 'Kelola daftar nama provinsi untuk dipakai lintas flow penilaian.',
                'create_label' => 'Tambah Provinsi',
                'code_label' => 'Kode Provinsi',
            ],
            'regencies' => [
                'key' => 'regencies',
                'title' => 'Kabupaten/Kota',
                'singular' => 'Kabupaten/Kota',
                'description' => 'Kelola daftar kabupaten dan kota per provinsi.',
                'create_label' => 'Tambah Kabupaten/Kota',
                'code_label' => 'Kode Kabupaten/Kota',
            ],
            'districts' => [
                'key' => 'districts',
                'title' => 'Kecamatan',
                'singular' => 'Kecamatan',
                'description' => 'Kelola daftar kecamatan per kabupaten/kota.',
                'create_label' => 'Tambah Kecamatan',
                'code_label' => 'Kode Kecamatan',
            ],
            default => [
                'key' => 'villages',
                'title' => 'Kelurahan/Desa',
                'singular' => 'Kelurahan/Desa',
                'description' => 'Kelola daftar kelurahan dan desa per kecamatan.',
                'create_label' => 'Tambah Kelurahan/Desa',
                'code_label' => 'Kode Kelurahan/Desa',
            ],
        };
    }

    private function locationGeneratorProps(string $type, ?string $parentField = null): array
    {
        return [
            'type' => $type,
            'parent_field' => $parentField,
            'preview_url' => route('admin.master-data.locations.id-preview'),
        ];
    }

    private function locationGeneratedIdPreview(
        string $type,
        array $context,
        LocationIdGenerator $generator
    ): ?string {
        try {
            return DB::transaction(function () use ($type, $context, $generator) {
                return match ($type) {
                    'province' => $generator->nextProvinceId(),
                    'regency' => filled($context['province_id'] ?? null)
                        ? $generator->nextRegencyId((string) $context['province_id'])
                        : null,
                    'district' => filled($context['regency_id'] ?? null)
                        ? $generator->nextDistrictId((string) $context['regency_id'])
                        : null,
                    'village' => filled($context['district_id'] ?? null)
                        ? $generator->nextVillageId((string) $context['district_id'])
                        : null,
                    default => null,
                };
            });
        } catch (\Throwable) {
            return null;
        }
    }

    private function canManageUsersCreate(): bool
    {
        $user = auth()->user();

        return $user !== null && $user->hasRole($this->superAdminRoleName());
    }

    private function superAdminRoleName(): string
    {
        return (string) config('filament-shield.super_admin.name', 'super_admin');
    }

    private function roleSelectOptions(): array
    {
        return Role::query()
            ->where('guard_name', 'web')
            ->orderBy('name')
            ->get(['name'])
            ->map(fn (Role $role) => [
                'value' => $role->name,
                'label' => $role->name,
            ])
            ->values()
            ->all();
    }

    private function transformUserRow(User $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'role_names' => $user->roles->pluck('name')->values()->all(),
            'is_verified' => filled($user->email_verified_at),
            'created_at' => $user->created_at?->toIso8601String(),
            'show_url' => route('admin.master-data.users.show', $user),
            'edit_url' => route('admin.master-data.users.edit', $user),
            'legacy_url' => $this->legacyUserUrl($user),
        ];
    }

    private function userShowPayload(User $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'email_verified_at' => $user->email_verified_at?->toIso8601String(),
            'role_names' => $user->roles->pluck('name')->values()->all(),
            'created_at' => $user->created_at?->toIso8601String(),
            'updated_at' => $user->updated_at?->toIso8601String(),
            'legacy_url' => $this->legacyUserUrl($user),
        ];
    }

    private function roleAbility(string $ability): bool
    {
        $user = auth()->user();

        if ($user === null) {
            return false;
        }

        if ($user->hasRole($this->superAdminRoleName())) {
            return true;
        }

        return $user->can($ability);
    }

    private function authorizeRoleAbility(string $ability): void
    {
        abort_unless($this->roleAbility($ability), 403);
    }

    private function roleGuardOptions(): array
    {
        return Role::query()
            ->distinct()
            ->orderBy('guard_name')
            ->pluck('guard_name')
            ->filter()
            ->values()
            ->map(fn (string $guard) => [
                'value' => $guard,
                'label' => $guard,
            ])
            ->all();
    }

    private function transformRoleRow(Role $role): array
    {
        return [
            'id' => $role->id,
            'name' => $role->name,
            'guard_name' => $role->guard_name,
            'permissions_count' => (int) ($role->permissions_count ?? 0),
            'updated_at' => $role->updated_at?->toIso8601String(),
            'show_url' => route('admin.access-control.roles.show', $role),
            'edit_url' => route('admin.access-control.roles.edit', $role),
            'destroy_url' => route('admin.access-control.roles.destroy', $role),
            'legacy_url' => $this->legacyRoleUrl($role),
            'can_update' => $this->roleAbility('update_role'),
            'can_delete' => $this->roleAbility('delete_role'),
        ];
    }

    private function roleShowPayload(Role $role): array
    {
        return [
            'id' => $role->id,
            'name' => $role->name,
            'guard_name' => $role->guard_name,
            'permissions_count' => $role->permissions->count(),
            'updated_at' => $role->updated_at?->toIso8601String(),
            'permission_groups' => $this->groupPermissions($role->permissions),
            'legacy_url' => $this->legacyRoleUrl($role),
        ];
    }

    private function rolePermissionGroups(): array
    {
        $permissions = Permission::query()
            ->orderBy('name')
            ->get(['id', 'name', 'guard_name']);

        return $this->groupPermissions($permissions, includeSelectionData: true);
    }

    private function groupPermissions(iterable $permissions, bool $includeSelectionData = false): array
    {
        $knownPrefixes = [
            'force_delete_any',
            'force_delete',
            'delete_any',
            'restore_any',
            'view_any',
            'replicate',
            'reorder',
            'restore',
            'delete',
            'create',
            'update',
            'widget',
            'page',
            'view',
        ];

        $grouped = [];

        foreach ($permissions as $permission) {
            $name = is_string($permission) ? $permission : $permission->name;
            $guardName = is_string($permission) ? 'web' : $permission->guard_name;
            $matchedPrefix = 'other';
            $subject = $name;

            foreach ($knownPrefixes as $prefix) {
                $needle = $prefix . '_';
                if (str_starts_with($name, $needle)) {
                    $matchedPrefix = $prefix;
                    $subject = substr($name, strlen($needle));
                    break;
                }
            }

            $subjectKey = $subject;
            $grouped[$subjectKey] ??= [
                'key' => $subjectKey,
                'title' => Str::headline(str_replace('::', ' ', $subject)),
                'permissions' => [],
            ];

            $entry = [
                'name' => $name,
                'label' => Str::headline(str_replace(['::', '_'], [' ', ' '], $matchedPrefix)),
                'guard_name' => $guardName,
            ];

            if ($includeSelectionData) {
                $entry['value'] = $name;
            }

            $grouped[$subjectKey]['permissions'][] = $entry;
        }

        return collect($grouped)
            ->sortBy('title')
            ->map(function (array $group) {
                $group['permissions'] = collect($group['permissions'])
                    ->sortBy('label')
                    ->values()
                    ->all();

                return $group;
            })
            ->values()
            ->all();
    }

    private function transformGuidelineSetRow(GuidelineSet $guidelineSet): array
    {
        return [
            'id' => $guidelineSet->id,
            'name' => $guidelineSet->name,
            'year' => (int) $guidelineSet->year,
            'description' => $guidelineSet->description,
            'is_active' => (bool) $guidelineSet->is_active,
            'construction_cost_indexes_count' => (int) ($guidelineSet->construction_cost_indexes_count ?? 0),
            'cost_elements_count' => (int) ($guidelineSet->cost_elements_count ?? 0),
            'floor_indexes_count' => (int) ($guidelineSet->floor_indexes_count ?? 0),
            'mappi_rcn_standards_count' => (int) ($guidelineSet->mappi_rcn_standards_count ?? 0),
            'updated_at' => $guidelineSet->updated_at?->toIso8601String(),
            'edit_url' => route('admin.ref-guidelines.guideline-sets.edit', $guidelineSet),
            'destroy_url' => route('admin.ref-guidelines.guideline-sets.destroy', $guidelineSet),
            'legacy_url' => $this->legacyGuidelineSetUrl($guidelineSet),
        ];
    }

    private function transformValuationSettingRow(ValuationSetting $valuationSetting): array
    {
        $valuationSetting->loadMissing('guidelineSet:id,name,is_active');

        return [
            'id' => $valuationSetting->id,
            'guideline_set_name' => $valuationSetting->guidelineSet?->name ?? '-',
            'guideline_is_active' => (bool) ($valuationSetting->guidelineSet?->is_active ?? false),
            'year' => (int) $valuationSetting->year,
            'key' => $valuationSetting->key,
            'key_label' => ValuationSetting::labelForKey($valuationSetting->key),
            'label' => $valuationSetting->label,
            'value_number' => (float) ($valuationSetting->value_number ?? 0),
            'value_text' => $valuationSetting->value_text,
            'notes' => $valuationSetting->notes,
            'updated_at' => $valuationSetting->updated_at?->toIso8601String(),
            'edit_url' => route('admin.ref-guidelines.valuation-settings.edit', $valuationSetting),
            'destroy_url' => route('admin.ref-guidelines.valuation-settings.destroy', $valuationSetting),
            'legacy_url' => $this->legacyValuationSettingUrl($valuationSetting),
        ];
    }

    private function transformConstructionCostIndexRow(ConstructionCostIndex $constructionCostIndex): array
    {
        $constructionCostIndex->loadMissing([
            'guidelineSet:id,name,is_active',
            'regency:id,name,province_id',
            'regency.province:id,name',
        ]);

        return [
            'id' => $constructionCostIndex->id,
            'guideline_set_name' => $constructionCostIndex->guidelineSet?->name ?? '-',
            'guideline_is_active' => (bool) ($constructionCostIndex->guidelineSet?->is_active ?? false),
            'year' => (int) $constructionCostIndex->year,
            'province_name' => $constructionCostIndex->regency?->province?->name ?? '-',
            'region_code' => (string) $constructionCostIndex->region_code,
            'region_name' => $constructionCostIndex->region_name,
            'ikk_value' => (float) $constructionCostIndex->ikk_value,
            'updated_at' => $constructionCostIndex->updated_at?->toIso8601String(),
            'edit_url' => route('admin.ref-guidelines.construction-cost-indices.edit', $constructionCostIndex),
            'destroy_url' => route('admin.ref-guidelines.construction-cost-indices.destroy', $constructionCostIndex),
            'legacy_url' => $this->legacyConstructionCostIndexUrl($constructionCostIndex),
        ];
    }

    private function transformCostElementRow(CostElement $costElement): array
    {
        $costElement->loadMissing('guidelineSet:id,name,is_active');

        return [
            'id' => $costElement->id,
            'guideline_set_name' => $costElement->guidelineSet?->name ?? '-',
            'guideline_is_active' => (bool) ($costElement->guidelineSet?->is_active ?? false),
            'year' => (int) $costElement->year,
            'base_region' => $costElement->base_region,
            'group' => $costElement->group,
            'element_code' => $costElement->element_code,
            'element_name' => $costElement->element_name,
            'building_type' => $costElement->building_type,
            'building_class' => $costElement->building_class,
            'storey_pattern' => $costElement->storey_pattern,
            'unit' => $costElement->unit,
            'unit_cost' => (int) $costElement->unit_cost,
            'spec_json' => $costElement->spec_json,
            'updated_at' => $costElement->updated_at?->toIso8601String(),
            'edit_url' => route('admin.ref-guidelines.cost-elements.edit', $costElement),
            'destroy_url' => route('admin.ref-guidelines.cost-elements.destroy', $costElement),
            'legacy_url' => $this->legacyCostElementUrl($costElement),
        ];
    }

    private function transformFloorIndexRow(FloorIndex $floorIndex): array
    {
        $floorIndex->loadMissing('guidelineSet:id,name,is_active');

        return [
            'id' => $floorIndex->id,
            'guideline_set_name' => $floorIndex->guidelineSet?->name ?? '-',
            'guideline_is_active' => (bool) ($floorIndex->guidelineSet?->is_active ?? false),
            'year' => (int) $floorIndex->year,
            'building_class' => $floorIndex->building_class,
            'floor_count' => (int) $floorIndex->floor_count,
            'il_value' => (float) $floorIndex->il_value,
            'updated_at' => $floorIndex->updated_at?->toIso8601String(),
            'edit_url' => route('admin.ref-guidelines.floor-indices.edit', $floorIndex),
            'destroy_url' => route('admin.ref-guidelines.floor-indices.destroy', $floorIndex),
            'legacy_url' => $this->legacyFloorIndexUrl($floorIndex),
        ];
    }

    private function transformMappiRcnStandardRow(MappiRcnStandard $mappiRcnStandard): array
    {
        $mappiRcnStandard->loadMissing('guidelineSet:id,name,is_active');

        return [
            'id' => $mappiRcnStandard->id,
            'guideline_set_name' => $mappiRcnStandard->guidelineSet?->name ?? '-',
            'guideline_is_active' => (bool) ($mappiRcnStandard->guidelineSet?->is_active ?? false),
            'year' => (int) $mappiRcnStandard->year,
            'reference_region' => $mappiRcnStandard->reference_region,
            'building_type' => $mappiRcnStandard->building_type,
            'building_class' => $mappiRcnStandard->building_class,
            'storey_pattern' => $mappiRcnStandard->storey_pattern,
            'rcn_value' => (int) $mappiRcnStandard->rcn_value,
            'notes' => $mappiRcnStandard->notes,
            'updated_at' => $mappiRcnStandard->updated_at?->toIso8601String(),
            'edit_url' => route('admin.ref-guidelines.mappi-rcn-standards.edit', $mappiRcnStandard),
            'destroy_url' => route('admin.ref-guidelines.mappi-rcn-standards.destroy', $mappiRcnStandard),
            'legacy_url' => $this->legacyMappiRcnStandardUrl($mappiRcnStandard),
        ];
    }

    private function guidelineSetOptions(bool $includeAll = false): array
    {
        $options = GuidelineSet::query()
            ->orderByDesc('year')
            ->get(['id', 'name', 'year', 'is_active'])
            ->map(fn (GuidelineSet $guidelineSet) => [
                'value' => (string) $guidelineSet->id,
                'label' => $guidelineSet->name . ' (' . $guidelineSet->year . ')' . ($guidelineSet->is_active ? ' · aktif' : ''),
                'year' => (int) $guidelineSet->year,
            ])
            ->values()
            ->all();

        if (! $includeAll) {
            return $options;
        }

        return [
            ['value' => 'all', 'label' => 'Semua Guideline Set'],
            ...$options,
        ];
    }

    private function valuationSettingKeyOptions(bool $includeAll = false): array
    {
        $options = collect(ValuationSetting::keyOptions())
            ->map(fn (string $label, string $value) => [
                'value' => $value,
                'label' => $label,
            ])
            ->values()
            ->all();

        if (! $includeAll) {
            return $options;
        }

        return [
            ['value' => 'all', 'label' => 'Semua Key'],
            ...$options,
        ];
    }

    private function valuationSettingYearOptions(): array
    {
        return ValuationSetting::query()
            ->distinct()
            ->orderByDesc('year')
            ->pluck('year')
            ->filter()
            ->values()
            ->map(fn (int|string $year) => [
                'value' => (string) $year,
                'label' => (string) $year,
            ])
            ->all();
    }

    private function constructionCostIndexYearOptions(): array
    {
        return ConstructionCostIndex::query()
            ->distinct()
            ->orderByDesc('year')
            ->pluck('year')
            ->map(fn ($year) => [
                'value' => (string) $year,
                'label' => (string) $year,
            ])
            ->values()
            ->all();
    }

    private function costElementYearOptions(): array
    {
        return CostElement::query()
            ->distinct()
            ->orderByDesc('year')
            ->pluck('year')
            ->map(fn ($year) => [
                'value' => (string) $year,
                'label' => (string) $year,
            ])
            ->values()
            ->all();
    }

    private function floorIndexYearOptions(bool $includeAll = false): array
    {
        $options = FloorIndex::query()
            ->distinct()
            ->orderByDesc('year')
            ->pluck('year')
            ->map(fn ($year) => [
                'value' => (string) $year,
                'label' => (string) $year,
            ])
            ->values()
            ->all();

        if (! $includeAll) {
            return $options;
        }

        return [
            ['value' => 'all', 'label' => 'Semua Tahun'],
            ...$options,
        ];
    }

    private function floorIndexBuildingClassOptions(bool $includeAll = false): array
    {
        $options = FloorIndex::query()
            ->whereNotNull('building_class')
            ->where('building_class', '<>', '')
            ->distinct()
            ->orderBy('building_class')
            ->pluck('building_class')
            ->map(fn (string $value) => [
                'value' => $value,
                'label' => $value,
            ])
            ->values()
            ->all();

        if (! $includeAll) {
            return $options;
        }

        return [
            ['value' => 'all', 'label' => 'Semua Class'],
            ...$options,
        ];
    }

    private function mappiRcnYearOptions(bool $includeAll = false): array
    {
        $options = MappiRcnStandard::query()
            ->distinct()
            ->orderByDesc('year')
            ->pluck('year')
            ->map(fn ($year) => [
                'value' => (string) $year,
                'label' => (string) $year,
            ])
            ->values()
            ->all();

        if (! $includeAll) {
            return $options;
        }

        return [
            ['value' => 'all', 'label' => 'Semua Tahun'],
            ...$options,
        ];
    }

    private function mappiRcnBuildingTypeOptions(bool $includeAll = false): array
    {
        $options = MappiRcnStandard::query()
            ->whereNotNull('building_type')
            ->where('building_type', '<>', '')
            ->distinct()
            ->orderBy('building_type')
            ->pluck('building_type')
            ->map(fn (string $value) => [
                'value' => $value,
                'label' => $value,
            ])
            ->values()
            ->all();

        if (! $includeAll) {
            return $options;
        }

        return [
            ['value' => 'all', 'label' => 'Semua Building Type'],
            ...$options,
        ];
    }

    private function mappiRcnBuildingClassOptions(bool $includeAll = false): array
    {
        $options = MappiRcnStandard::query()
            ->whereNotNull('building_class')
            ->where('building_class', '<>', '')
            ->distinct()
            ->orderBy('building_class')
            ->pluck('building_class')
            ->map(fn (string $value) => [
                'value' => $value,
                'label' => $value,
            ])
            ->values()
            ->all();

        if (! $includeAll) {
            return $options;
        }

        return [
            ['value' => 'all', 'label' => 'Semua Building Class'],
            ...$options,
        ];
    }

    private function mappiRcnFormOptions(): array
    {
        return [
            'building_types' => MappiRcnStandard::query()
                ->whereNotNull('building_type')
                ->where('building_type', '<>', '')
                ->distinct()
                ->orderBy('building_type')
                ->pluck('building_type')
                ->values()
                ->all(),
            'building_classes' => MappiRcnStandard::query()
                ->whereNotNull('building_class')
                ->where('building_class', '<>', '')
                ->distinct()
                ->orderBy('building_class')
                ->pluck('building_class')
                ->values()
                ->all(),
            'storey_patterns' => MappiRcnStandard::query()
                ->whereNotNull('storey_pattern')
                ->where('storey_pattern', '<>', '')
                ->distinct()
                ->orderBy('storey_pattern')
                ->pluck('storey_pattern')
                ->values()
                ->all(),
        ];
    }

    private function costElementBaseRegionOptions(bool $includeAll = false): array
    {
        $options = CostElement::query()
            ->whereNotNull('base_region')
            ->where('base_region', '<>', '')
            ->distinct()
            ->orderBy('base_region')
            ->pluck('base_region')
            ->map(fn (string $value) => [
                'value' => $value,
                'label' => $value,
            ])
            ->values()
            ->all();

        if (! $includeAll) {
            return $options;
        }

        return [
            ['value' => 'all', 'label' => 'Semua Base Region'],
            ...$options,
        ];
    }

    private function costElementGroupOptions(bool $includeAll = false): array
    {
        $options = CostElement::query()
            ->whereNotNull('group')
            ->where('group', '<>', '')
            ->distinct()
            ->orderBy('group')
            ->pluck('group')
            ->map(fn (string $value) => [
                'value' => $value,
                'label' => $value,
            ])
            ->values()
            ->all();

        if (! $includeAll) {
            return $options;
        }

        return [
            ['value' => 'all', 'label' => 'Semua Group'],
            ...$options,
        ];
    }

    private function costElementFormOptions(): array
    {
        return [
            'groups' => CostElement::query()
                ->whereNotNull('group')
                ->where('group', '<>', '')
                ->distinct()
                ->orderBy('group')
                ->limit(300)
                ->pluck('group')
                ->values()
                ->all(),
            'element_codes' => CostElement::query()
                ->whereNotNull('element_code')
                ->where('element_code', '<>', '')
                ->distinct()
                ->orderBy('element_code')
                ->limit(500)
                ->pluck('element_code')
                ->values()
                ->all(),
            'element_names' => CostElement::query()
                ->whereNotNull('element_name')
                ->where('element_name', '<>', '')
                ->distinct()
                ->orderBy('element_name')
                ->limit(500)
                ->pluck('element_name')
                ->values()
                ->all(),
            'building_types' => CostElement::query()
                ->whereNotNull('building_type')
                ->where('building_type', '<>', '')
                ->distinct()
                ->orderBy('building_type')
                ->limit(200)
                ->pluck('building_type')
                ->values()
                ->all(),
            'building_classes' => CostElement::query()
                ->whereNotNull('building_class')
                ->where('building_class', '<>', '')
                ->distinct()
                ->orderBy('building_class')
                ->limit(200)
                ->pluck('building_class')
                ->values()
                ->all(),
            'storey_patterns' => CostElement::query()
                ->whereNotNull('storey_pattern')
                ->where('storey_pattern', '<>', '')
                ->distinct()
                ->orderBy('storey_pattern')
                ->limit(200)
                ->pluck('storey_pattern')
                ->values()
                ->all(),
        ];
    }

    private function paginatedRecordsPayload(object $records): array
    {
        return [
            'data' => $records->items(),
            'meta' => [
                'from' => $records->firstItem(),
                'to' => $records->lastItem(),
                'total' => $records->total(),
                'links' => $records->linkCollection()->toArray(),
            ],
        ];
    }

    private function provinceSelectOptions(): array
    {
        return Province::query()
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (Province $province) => [
                'value' => (string) $province->id,
                'label' => $province->name . ' (' . $province->id . ')',
            ])
            ->values()
            ->all();
    }

    private function provinceFilterOptions(bool $includeAll = false): array
    {
        $options = $this->provinceSelectOptions();

        if (! $includeAll) {
            return $options;
        }

        return [
            ['value' => 'all', 'label' => 'Semua Provinsi'],
            ...$options,
        ];
    }

    private function regencySelectOptions(): array
    {
        return Regency::query()
            ->with('province:id,name')
            ->orderBy('name')
            ->get(['id', 'name', 'province_id'])
            ->map(fn (Regency $regency) => [
                'value' => (string) $regency->id,
                'label' => $regency->name . ' - ' . ($regency->province?->name ?? '-') . ' (' . $regency->id . ')',
            ])
            ->values()
            ->all();
    }

    private function regencySelectOptionsByProvince(?string $provinceId): array
    {
        if (blank($provinceId)) {
            return [];
        }

        return Regency::query()
            ->where('province_id', $provinceId)
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (Regency $regency) => [
                'value' => (string) $regency->id,
                'label' => $regency->name . ' (' . $regency->id . ')',
            ])
            ->values()
            ->all();
    }

    private function districtSelectOptions(): array
    {
        return District::query()
            ->with(['regency:id,name,province_id', 'regency.province:id,name'])
            ->orderBy('name')
            ->get(['id', 'name', 'regency_id'])
            ->map(fn (District $district) => [
                'value' => (string) $district->id,
                'label' => $district->name
                    . ' - '
                    . ($district->regency?->name ?? '-')
                    . ' / '
                    . ($district->regency?->province?->name ?? '-')
                    . ' ('
                    . $district->id
                    . ')',
            ])
            ->values()
            ->all();
    }

    private function districtSelectOptionsByRegency(?string $regencyId): array
    {
        if (blank($regencyId)) {
            return [];
        }

        return District::query()
            ->where('regency_id', $regencyId)
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (District $district) => [
                'value' => (string) $district->id,
                'label' => $district->name . ' (' . $district->id . ')',
            ])
            ->values()
            ->all();
    }

    private function regencyFilterOptions(): array
    {
        return Regency::query()
            ->with('province:id,name')
            ->orderBy('name')
            ->get(['id', 'name', 'province_id'])
            ->map(fn (Regency $regency) => [
                'value' => (string) $regency->id,
                'label' => $regency->name . ' - ' . ($regency->province?->name ?? '-'),
            ])
            ->values()
            ->all();
    }

    private function districtFilterOptions(): array
    {
        return District::query()
            ->with(['regency:id,name,province_id', 'regency.province:id,name'])
            ->orderBy('name')
            ->get(['id', 'name', 'regency_id'])
            ->map(fn (District $district) => [
                'value' => (string) $district->id,
                'label' => $district->name
                    . ' - '
                    . ($district->regency?->name ?? '-')
                    . ' / '
                    . ($district->regency?->province?->name ?? '-'),
            ])
            ->values()
            ->all();
    }

    private function transformProvinceRow(Province $province): array
    {
        return [
            'id' => $province->id,
            'code' => $province->id,
            'name' => $province->name,
            'details' => [],
            'stats' => [
                ['label' => 'Kabupaten/Kota', 'value' => (int) ($province->regencies_count ?? 0)],
            ],
            'edit_url' => route('admin.master-data.provinces.edit', $province),
            'destroy_url' => route('admin.master-data.provinces.destroy', $province),
            'legacy_url' => $this->legacyProvinceUrl($province),
        ];
    }

    private function transformRegencyRow(Regency $regency): array
    {
        return [
            'id' => $regency->id,
            'code' => $regency->id,
            'name' => $regency->name,
            'details' => [
                'Provinsi: ' . ($regency->province?->name ?? '-'),
            ],
            'stats' => [
                ['label' => 'Kecamatan', 'value' => (int) ($regency->districts_count ?? 0)],
            ],
            'edit_url' => route('admin.master-data.regencies.edit', $regency),
            'destroy_url' => route('admin.master-data.regencies.destroy', $regency),
            'legacy_url' => $this->legacyRegencyUrl($regency),
        ];
    }

    private function transformDistrictRow(District $district): array
    {
        return [
            'id' => $district->id,
            'code' => $district->id,
            'name' => $district->name,
            'details' => [
                'Kabupaten/Kota: ' . ($district->regency?->name ?? '-'),
                'Provinsi: ' . ($district->regency?->province?->name ?? '-'),
            ],
            'stats' => [
                ['label' => 'Kelurahan/Desa', 'value' => (int) ($district->villages_count ?? 0)],
            ],
            'edit_url' => route('admin.master-data.districts.edit', $district),
            'destroy_url' => route('admin.master-data.districts.destroy', $district),
            'legacy_url' => $this->legacyDistrictUrl($district),
        ];
    }

    private function transformVillageRow(Village $village): array
    {
        return [
            'id' => $village->id,
            'code' => $village->id,
            'name' => $village->name,
            'details' => [
                'Kecamatan: ' . ($village->district?->name ?? '-'),
                'Kabupaten/Kota: ' . ($village->district?->regency?->name ?? '-'),
                'Provinsi: ' . ($village->district?->regency?->province?->name ?? '-'),
            ],
            'stats' => [],
            'edit_url' => route('admin.master-data.villages.edit', $village),
            'destroy_url' => route('admin.master-data.villages.destroy', $village),
            'legacy_url' => $this->legacyVillageUrl($village),
        ];
    }

    private function destroyLocationRecord(Model $record, string $routeName, string $label): RedirectResponse
    {
        try {
            $record->delete();
        } catch (QueryException) {
            return redirect()
                ->route($routeName)
                ->with('error', $label . ' tidak bisa dihapus karena masih dipakai data turunan.');
        }

        return redirect()
            ->route($routeName)
            ->with('success', $label . ' berhasil dihapus.');
    }

    private function negotiationActionOptions(AppraisalRequest $appraisalRequest): array
    {
        return $appraisalRequest->offerNegotiations
            ->pluck('action')
            ->filter()
            ->unique()
            ->values()
            ->map(fn (string $action) => [
                'value' => $action,
                'label' => $this->formatNegotiationAction($action),
            ])
            ->all();
    }

    private function negotiationSummary(AppraisalRequest $appraisalRequest): array
    {
        $entries = $appraisalRequest->offerNegotiations;

        return [
            'total' => $entries->count(),
            'counter_requests' => $entries->where('action', 'counter_request')->count(),
            'offers_sent' => $entries->whereIn('action', ['offer_sent', 'offer_revised'])->count(),
            'accepted' => $entries->whereIn('action', ['accept_offer', 'accepted'])->count(),
            'cancelled' => $entries->whereIn('action', ['cancel_request', 'cancelled'])->count(),
        ];
    }

    private function assetDocumentTypeOptions(): array
    {
        return [
            ['value' => 'doc_pbb', 'label' => 'PBB'],
            ['value' => 'doc_imb', 'label' => 'IMB / PBG'],
            ['value' => 'doc_certs', 'label' => 'Sertifikat'],
        ];
    }

    private function assetPhotoTypeOptions(): array
    {
        return [
            ['value' => 'photo_access_road', 'label' => 'Foto Akses Jalan'],
            ['value' => 'photo_front', 'label' => 'Foto Depan'],
            ['value' => 'photo_interior', 'label' => 'Foto Dalam'],
        ];
    }

    private function assetFileDirectory(string $type): string
    {
        return match ($type) {
            'doc_pbb' => 'documents/pbb',
            'doc_imb' => 'documents/imb',
            'doc_certs' => 'documents/certificate',
            'photo_access_road' => 'photos/access_road',
            'photo_front' => 'photos/front',
            'photo_interior' => 'photos/interior',
            default => 'uploads',
        };
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
                'status' => 'in_progress',
                'legacy_resources' => [
                    'PaymentResource',
                    'OfficeBankAccountResource',
                ],
                'dependencies' => [
                    'List/detail pembayaran, edit pembayaran, dan CRUD rekening kantor sudah tersedia di admin Vue.',
                    'PaymentController masih mengirim database notification dengan builder Filament.',
                ],
            ],
            'content' => [
                'title' => 'Konten',
                'description' => 'Migrasi artikel, kategori artikel, dan tag ke halaman Vue dengan editor yang bisa diganti.',
                'status' => 'in_progress',
                'legacy_resources' => [
                    'ArticleResource',
                    'ArticleCategoryResource',
                    'TagResource',
                ],
                'dependencies' => [
                    'CMS artikel, kategori artikel, dan tag sudah tersedia di admin Vue.',
                    'Editor artikel saat ini memakai HTML textarea sebagai pengganti editor Filament.',
                ],
            ],
            'legal-content' => [
                'title' => 'Konten & Legal',
                'description' => 'Dokumen legal, FAQ, feature highlight, testimonial, dan log persetujuan pengguna.',
                'status' => 'in_progress',
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
                    'FAQ, feature, testimonial, terms, privacy, consent document, dan audit persetujuan pengguna sudah tersedia di admin Vue.',
                    'Editor legal sekarang memakai HTML textarea/JSON textarea sebagai pengganti editor Filament.',
                ],
            ],
            'communications' => [
                'title' => 'Komunikasi',
                'description' => 'Inbox pesan kontak dari landing page dan audit tindak lanjutnya.',
                'status' => 'in_progress',
                'legacy_resources' => [
                    'ContactMessageResource',
                ],
                'dependencies' => [
                    'LandingController sudah menyimpan pesan, inbox contact message list/detail/action sudah tersedia di admin Vue.',
                ],
            ],
            'master-data' => [
                'title' => 'Master Data',
                'description' => 'User terdaftar dan daftar nama lokasi yang dipakai lintas flow penilaian.',
                'status' => 'in_progress',
                'legacy_resources' => [
                    'UserResource',
                    'ProvinceResource',
                    'RegencyResource',
                    'DistrictResource',
                    'VillageResource',
                ],
                'dependencies' => [
                    'Daftar nama lokasi untuk provinsi, kabupaten/kota, kecamatan, dan kelurahan/desa sudah tersedia di admin Vue.',
                    'User management untuk list, detail, edit, dan create terbatas super_admin sudah tersedia di admin Vue.',
                    'Delete user tetap tidak diaktifkan agar parity dengan resource legacy.',
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
                'status' => 'in_progress',
                'legacy_resources' => [
                    'RoleResource',
                ],
                'dependencies' => [
                    'User model masih memakai spatie/permission dan konfigurasi super_admin dari filament-shield.',
                    'Role management list/detail/create/edit/delete sudah tersedia di admin Vue.',
                    'Policy Role masih memakai permission prefix bawaan shield seperti view_any_role dan update_role.',
                ],
            ],
        ];
    }
}

<?php

namespace App\Http\Controllers\Admin;

use App\Enums\AppraisalStatusEnum;
use App\Enums\ContractStatusEnum;
use App\Enums\ReportTypeEnum;
use App\Enums\ValuationObjectiveEnum;
use App\Http\Controllers\Admin\Concerns\InteractsWithAppraisalRequests;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateAppraisalRequestBasicRequest;
use App\Models\AppraisalRequest;
use App\Services\Admin\AppraisalContractNumberService;
use App\Services\Admin\AppraisalRequestRevisionService;
use App\Services\Admin\AppraisalRequestWorkflowService;
use App\Services\AppraisalRevisionFileResolver;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Response;

class AppraisalRequestController extends Controller
{
    use InteractsWithAppraisalRequests;

    public function appraisalRequestsIndex(Request $request): Response
    {
        $filters = [
            'q' => trim((string) $request->query('q', '')),
            'status' => (string) $request->query('status', 'all'),
            'per_page' => (string) $this->adminPerPage($request),
        ];

        $records = AppraisalRequest::query()
            ->with('user')
            ->withCount('assets')
            ->withCount([
                'offerNegotiations as negotiation_rounds_used' => fn ($query) => $query->where('action', 'counter_request'),
            ])
            ->when($filters['q'] !== '', function ($query) use ($filters): void {
                $query->where(function ($innerQuery) use ($filters): void {
                    $innerQuery
                        ->where('request_number', 'like', '%' . $filters['q'] . '%')
                        ->orWhere('client_name', 'like', '%' . $filters['q'] . '%')
                        ->orWhereHas('user', fn ($userQuery) => $userQuery->where('name', 'like', '%' . $filters['q'] . '%'));
                });
            })
            ->when($filters['status'] !== 'all', fn ($query) => $query->where('status', $filters['status']))
            ->latest('requested_at')
            ->paginate($this->adminPerPage($request))
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
            'records' => $this->paginatedRecordsPayload($records),
        ]);
    }

    public function appraisalRequestsShow(
        AppraisalRequest $appraisalRequest,
        AppraisalRequestWorkflowService $workflowService,
        AppraisalRequestRevisionService $revisionService,
        AppraisalRevisionFileResolver $fileResolver
    ): Response {
        $appraisalRequest->load([
            'guidelineSet',
            'user',
            'files',
            'assets.files',
            'payments' => fn ($query) => $query->latest('id'),
            'offerNegotiations' => fn ($query) => $query->with('user')->latest('id'),
            'revisionBatches' => fn ($query) => $query
                ->with([
                    'creator',
                    'items.appraisalAsset',
                    'items.originalRequestFile',
                    'items.originalAssetFile',
                    'items.replacementRequestFile',
                    'items.replacementAssetFile',
                ])
                ->latest('id'),
        ]);
        $approvedRevisionItems = $fileResolver->approvedItemsForRequest($appraisalRequest);
        $activeRequestFiles = $fileResolver->activeRequestFiles($appraisalRequest, $approvedRevisionItems);
        $activeAssetFiles = $fileResolver->activeAssetFilesByRequest($appraisalRequest, $approvedRevisionItems);

        $locationMaps = $this->buildLocationMaps($appraisalRequest);
        $latestCounterRequest = $appraisalRequest->offerNegotiations
            ->first(fn ($entry) => $entry->action === 'counter_request');

        return inertia('Admin/AppraisalRequests/Show', [
            'record' => [
                'id' => $appraisalRequest->id,
                'request_number' => $appraisalRequest->request_number ?? ('REQ-' . $appraisalRequest->id),
                'purpose_label' => $appraisalRequest->purpose?->label() ?? '-',
                'valuation_objective_label' => $appraisalRequest->valuation_objective instanceof ValuationObjectiveEnum
                    ? $appraisalRequest->valuation_objective->label()
                    : '-',
                'status_label' => $appraisalRequest->status?->label() ?? '-',
                'status_value' => $appraisalRequest->status?->value ?? null,
                'contract_status_label' => $appraisalRequest->contract_status?->label() ?? '-',
                'contract_status_value' => $appraisalRequest->contract_status?->value ?? null,
                'report_type_label' => $appraisalRequest->report_type?->label() ?? '-',
                'requested_at' => $appraisalRequest->requested_at?->toIso8601String(),
                'verified_at' => $appraisalRequest->verified_at?->toIso8601String(),
                'client_name' => $appraisalRequest->client_name ?: '-',
                'sertifikat_on_hand_confirmed' => (bool) $appraisalRequest->sertifikat_on_hand_confirmed,
                'certificate_not_encumbered_confirmed' => (bool) $appraisalRequest->certificate_not_encumbered_confirmed,
                'certificate_statements_accepted_at' => $appraisalRequest->certificate_statements_accepted_at?->toIso8601String(),
                'contract_number' => $appraisalRequest->contract_number ?: '-',
                'contract_date' => $appraisalRequest->contract_date?->toIso8601String(),
                'valuation_duration_days' => $appraisalRequest->valuation_duration_days,
                'offer_validity_days' => $appraisalRequest->offer_validity_days,
                'fee_total' => (int) ($appraisalRequest->fee_total ?? 0),
                'latest_expected_fee' => $latestCounterRequest?->expected_fee,
                'latest_negotiation_reason' => $latestCounterRequest?->reason,
                'notes' => $appraisalRequest->notes,
                'user_request_note' => $appraisalRequest->user_request_note,
                'guideline_set' => $appraisalRequest->guidelineSet?->name ?? '-',
            ],
            'marketPreview' => [
                'version' => (int) ($appraisalRequest->market_preview_version ?? 0),
                'published_at' => $appraisalRequest->market_preview_published_at?->toIso8601String(),
                'approved_at' => $appraisalRequest->market_preview_approved_at?->toIso8601String(),
                'appeal_count' => (int) ($appraisalRequest->market_preview_appeal_count ?? 0),
                'appeal_reason' => $appraisalRequest->market_preview_appeal_reason,
                'appeal_submitted_at' => $appraisalRequest->market_preview_appeal_submitted_at?->toIso8601String(),
                'summary' => [
                    'estimated_value_low' => data_get($appraisalRequest->market_preview_snapshot, 'summary.estimated_value_low'),
                    'market_value_final' => data_get($appraisalRequest->market_preview_snapshot, 'summary.market_value_final'),
                    'estimated_value_high' => data_get($appraisalRequest->market_preview_snapshot, 'summary.estimated_value_high'),
                    'assets_count' => data_get($appraisalRequest->market_preview_snapshot, 'summary.assets_count'),
                ],
                'assets' => collect(data_get($appraisalRequest->market_preview_snapshot, 'assets', []))
                    ->map(fn (array $asset) => [
                        'asset_id' => $asset['asset_id'] ?? null,
                        'asset_type_label' => $asset['asset_type_label'] ?? ($asset['asset_type'] ?? '-'),
                        'address' => $asset['address'] ?? '-',
                        'estimated_value_low' => $asset['estimated_value_low'] ?? null,
                        'market_value_final' => $asset['market_value_final'] ?? null,
                        'estimated_value_high' => $asset['estimated_value_high'] ?? null,
                    ])
                    ->values()
                    ->all(),
            ],
            'reportPreparation' => [
                'status' => $appraisalRequest->status?->value ?? null,
                'draft_available' => filled($appraisalRequest->report_draft_pdf_path),
                'draft_generated_at' => $appraisalRequest->report_draft_generated_at?->toIso8601String(),
                'draft_download_url' => ($appraisalRequest->status?->value ?? null) === AppraisalStatusEnum::ReportPreparation->value
                    ? route('admin.appraisal-requests.actions.report-draft', $appraisalRequest)
                    : null,
                'final_upload_url' => ($appraisalRequest->status?->value ?? null) === AppraisalStatusEnum::ReportPreparation->value
                    ? route('admin.appraisal-requests.actions.report-final', $appraisalRequest)
                    : null,
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
            'requestFiles' => $activeRequestFiles
                ->map(fn ($file) => $this->transformRequestFile($file))
                ->values(),
            'assets' => $appraisalRequest->assets
                ->sortBy('id')
                ->values()
                ->map(fn ($asset, $index) => $this->transformAsset(
                    $asset,
                    $index + 1,
                    $locationMaps,
                    collect($activeAssetFiles[$asset->id] ?? [])
                ))
                ->values(),
            'payments' => $appraisalRequest->payments->map(fn ($payment) => [
                'id' => $payment->id,
                'amount' => (int) $payment->amount,
                'method_label' => $payment->method === 'gateway' ? 'Midtrans Gateway' : 'Gateway Legacy',
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
            'revisionWorkspace' => [
                'state' => $revisionService->creationState($appraisalRequest),
                'create_url' => route('admin.appraisal-requests.revision-batches.store', $appraisalRequest),
                'target_options' => $revisionService->buildTargetOptions($appraisalRequest),
                'batches' => $appraisalRequest->revisionBatches
                    ->map(fn ($batch) => $this->transformRevisionBatch($batch, $appraisalRequest))
                    ->values(),
            ],
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
        ]);
    }

    public function appraisalRequestsUpdate(
        UpdateAppraisalRequestBasicRequest $request,
        AppraisalRequest $appraisalRequest,
        AppraisalContractNumberService $contractNumberService
    ): RedirectResponse {
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
            'fee_has_dp' => false,
            'fee_dp_percent' => null,
            'user_request_note' => $this->blankToNull($validated['user_request_note'] ?? null),
            'notes' => $this->blankToNull($validated['notes'] ?? null),
        ]);

        return redirect()
            ->route('admin.appraisal-requests.show', $appraisalRequest)
            ->with('success', 'Informasi dasar request berhasil diperbarui.');
    }

}

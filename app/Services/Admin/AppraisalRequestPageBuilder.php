<?php

namespace App\Services\Admin;

use App\Enums\AppraisalStatusEnum;
use App\Enums\ContractStatusEnum;
use App\Enums\ReportTypeEnum;
use App\Enums\ValuationObjectiveEnum;
use App\Models\AppraisalRequest;
use App\Models\ReportSigner;
use App\Services\AppraisalPhysicalReportSummaryBuilder;
use App\Services\Finance\AppraisalBillingService;
use App\Services\Revisions\AppraisalRevisionFileResolver;
use App\Support\Admin\AppraisalRequestActionResolver;
use App\Support\Admin\AppraisalRequestAdminPresenter;

class AppraisalRequestPageBuilder
{
    public function __construct(
        private readonly AppraisalRequestAdminPresenter $presenter,
        private readonly AppraisalRequestActionResolver $actionResolver,
        private readonly AppraisalRequestRevisionService $revisionService,
        private readonly AppraisalRevisionFileResolver $fileResolver,
        private readonly AppraisalPhysicalReportSummaryBuilder $physicalReportSummaryBuilder,
        private readonly AppraisalBillingService $billingService,
    ) {
    }

    public function buildShowPayload(
        AppraisalRequest $appraisalRequest,
        AppraisalRequestWorkflowService $workflowService,
    ): array {
        $this->loadShowRelations($appraisalRequest);

        $approvedRevisionItems = $this->fileResolver->approvedItemsForRequest($appraisalRequest);
        $activeRequestFiles = $this->fileResolver->activeRequestFiles($appraisalRequest, $approvedRevisionItems);
        $activeAssetFiles = $this->fileResolver->activeAssetFilesByRequest($appraisalRequest, $approvedRevisionItems);
        $locationMaps = $this->presenter->buildLocationMaps($appraisalRequest);
        $latestCounterRequest = $appraisalRequest->offerNegotiations
            ->first(fn ($entry) => $entry->action === 'counter_request');

        return [
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
                'report_format' => $appraisalRequest->report_format,
                'physical_copies_count' => (int) ($appraisalRequest->physical_copies_count ?? 0),
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
                'billing_dpp_amount' => (int) ($appraisalRequest->billing_dpp_amount ?? 0),
                'latest_expected_fee' => $latestCounterRequest?->expected_fee,
                'latest_negotiation_reason' => $latestCounterRequest?->reason,
                'notes' => $appraisalRequest->notes,
                'user_request_note' => $appraisalRequest->user_request_note,
                'guideline_set' => $appraisalRequest->guidelineSet?->name ?? '-',
                'cancelled_at' => $appraisalRequest->cancelled_at?->toIso8601String(),
                'cancelled_by_name' => $appraisalRequest->cancelledBy?->name,
                'cancellation_reason' => $appraisalRequest->cancellation_reason,
                'ringkasan_tagihan' => $this->billingService->summary($appraisalRequest),
                'tagihan_admin_url' => route('admin.finance.billings.show', $appraisalRequest),
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
                    'estimated_value_high' => data_get($appraisalRequest->market_preview_snapshot, 'summary.estimated_value_high'),
                    'assets_count' => data_get($appraisalRequest->market_preview_snapshot, 'summary.assets_count'),
                ],
                'assets' => collect(data_get($appraisalRequest->market_preview_snapshot, 'assets', []))
                    ->map(fn (array $asset) => [
                        'asset_id' => $asset['asset_id'] ?? null,
                        'asset_type_label' => $asset['asset_type_label'] ?? ($asset['asset_type'] ?? '-'),
                        'address' => $asset['address'] ?? '-',
                        'estimated_value_low' => $asset['estimated_value_low'] ?? null,
                        'estimated_value_high' => $asset['estimated_value_high'] ?? null,
                    ])
                    ->values()
                    ->all(),
            ],
            'reportPreparation' => [
                'status' => $appraisalRequest->status?->value ?? null,
                'draft_available' => filled($appraisalRequest->report_draft_pdf_path),
                'draft_generated_at' => $appraisalRequest->report_draft_generated_at?->toIso8601String(),
                'configuration_url' => ($appraisalRequest->status?->value ?? null) === AppraisalStatusEnum::ReportPreparation->value
                    ? route('admin.appraisal-requests.actions.report-configuration', $appraisalRequest)
                    : null,
                'draft_download_url' => ($appraisalRequest->status?->value ?? null) === AppraisalStatusEnum::ReportPreparation->value
                    ? route('admin.appraisal-requests.actions.report-draft', $appraisalRequest)
                    : null,
                'final_upload_url' => ($appraisalRequest->status?->value ?? null) === AppraisalStatusEnum::ReportPreparation->value
                    ? route('admin.appraisal-requests.actions.report-final', $appraisalRequest)
                    : null,
                'valuation_date' => $appraisalRequest->market_preview_published_at?->toDateString(),
                'selected_review_signer_id' => $appraisalRequest->report_reviewer_signer_id,
                'selected_public_appraiser_signer_id' => $appraisalRequest->report_public_appraiser_signer_id,
                'signer_snapshot' => $appraisalRequest->report_signer_snapshot,
                'signer_options' => $this->signerOptions(),
            ],
            'requester' => [
                'id' => $appraisalRequest->user?->id,
                'name' => $appraisalRequest->user?->name ?? '-',
                'email' => $appraisalRequest->user?->email ?? '-',
                'phone_number' => $appraisalRequest->user?->phone_number ?? '-',
                'whatsapp_number' => $appraisalRequest->user?->whatsapp_number ?? '-',
            ],
            'physicalReport' => array_merge(
                $this->physicalReportSummaryBuilder->build($appraisalRequest),
                [
                    'update_url' => route('admin.appraisal-requests.actions.physical-report.update', $appraisalRequest),
                    'workspace' => $workflowService->physicalReportState($appraisalRequest),
                ],
            ),
            'availableActions' => $this->actionResolver->buildAvailableActions($appraisalRequest, $workflowService),
            'offerAction' => $this->actionResolver->buildOfferAction($appraisalRequest, $workflowService),
            'approveLatestNegotiationAction' => $this->actionResolver->buildApproveLatestNegotiationAction($appraisalRequest, $workflowService),
            'paymentVerification' => $this->actionResolver->buildPaymentVerification($appraisalRequest, $workflowService),
            'requestFiles' => $activeRequestFiles
                ->map(fn ($file) => $this->presenter->requestFile($file))
                ->values(),
            'assets' => $appraisalRequest->assets
                ->sortBy('id')
                ->values()
                ->map(fn ($asset, $index) => $this->presenter->asset(
                    $asset,
                    $index + 1,
                    $locationMaps,
                    collect($activeAssetFiles[$asset->id] ?? []),
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
                'action_label' => $this->presenter->formatNegotiationAction($negotiation->action),
                'action_tone' => $this->presenter->negotiationActionTone($negotiation->action),
                'actor_name' => $negotiation->user?->name ?? 'System',
                'round' => $negotiation->round,
                'offered_fee' => $negotiation->offered_fee,
                'expected_fee' => $negotiation->expected_fee,
                'selected_fee' => $negotiation->selected_fee,
                'reason' => $negotiation->reason,
                'created_at' => $negotiation->created_at?->toIso8601String(),
            ])->values(),
            'negotiationActionOptions' => $this->presenter->negotiationActionOptions($appraisalRequest),
            'negotiationSummary' => $this->presenter->negotiationSummary($appraisalRequest),
            'revisionWorkspace' => [
                'state' => $this->revisionService->creationState($appraisalRequest),
                'create_url' => route('admin.appraisal-requests.revision-batches.store', $appraisalRequest),
                'field_correction_url' => route('admin.appraisal-requests.field-corrections.store', $appraisalRequest),
                'target_options' => $this->revisionService->buildTargetOptions($appraisalRequest),
                'batches' => $appraisalRequest->revisionBatches
                    ->map(fn ($batch) => $this->presenter->revisionBatch($batch, $appraisalRequest))
                    ->values(),
            ],
        ];
    }

    public function buildEditPayload(AppraisalRequest $appraisalRequest): array
    {
        return [
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
                'billing_dpp_amount' => $appraisalRequest->billing_dpp_amount
                    ?? $this->billingService->deriveFromGross((int) ($appraisalRequest->fee_total ?? 0))['billing_dpp_amount'],
                'user_request_note' => $appraisalRequest->user_request_note,
                'notes' => $appraisalRequest->notes,
                'ringkasan_tagihan' => $this->billingService->summary($appraisalRequest),
            ],
            'contractStatusOptions' => array_map(
                fn (ContractStatusEnum $status) => [
                    'value' => $status->value,
                    'label' => $status->label(),
                ],
                ContractStatusEnum::cases(),
            ),
            'reportTypeOptions' => array_map(
                fn (ReportTypeEnum $type) => [
                    'value' => $type->value,
                    'label' => $type->label(),
                ],
                ReportTypeEnum::cases(),
            ),
        ];
    }

    private function loadShowRelations(AppraisalRequest $appraisalRequest): void
    {
        $appraisalRequest->load([
            'guidelineSet',
            'user:id,name,email,phone_number,whatsapp_number',
            'cancelledBy:id,name',
            'physicalReportPrintedBy:id,name',
            'reportReviewerSigner',
            'reportPublicAppraiserSigner',
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
    }

    private function signerOptions(): array
    {
        return [
            'reviewers' => $this->signerOptionsByRole('reviewer'),
            'public_appraisers' => $this->signerOptionsByRole('public_appraiser'),
        ];
    }

    private function signerOptionsByRole(string $role): array
    {
        return ReportSigner::query()
            ->where('role', $role)
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'position_title', 'title_suffix', 'certification_number'])
            ->map(fn (ReportSigner $signer) => [
                'value' => $signer->id,
                'label' => $signer->name,
                'description' => implode(' | ', array_filter([
                    $signer->position_title,
                    $signer->certification_number,
                ])),
            ])
            ->values()
            ->all();
    }
}

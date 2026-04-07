<?php

namespace App\Services\Customer\Payloads;

use App\Enums\AppraisalStatusEnum;
use App\Enums\AssetTypeEnum;
use App\Enums\ContractStatusEnum;
use App\Enums\ReportTypeEnum;
use App\Enums\ValuationObjectiveEnum;
use App\Models\AppraisalRequest;
use App\Services\Payments\MidtransSnapService;
use App\Services\Revisions\AppraisalRequestRevisionSubmissionService;
use Illuminate\Support\Facades\Storage;

class CustomerAppraisalShowBuilder
{
    public function __construct(
        private readonly AppraisalPayloadFormatter $formatter,
        private readonly AppraisalDocumentCatalogBuilder $documentCatalogBuilder,
        private readonly AppraisalContractDocumentBuilder $contractDocumentBuilder,
        private readonly AppraisalStatusTimelineBuilder $statusTimelineBuilder,
        private readonly AppraisalPreviewStateBuilder $previewStateBuilder,
        private readonly MidtransSnapService $midtransSnapService,
        private readonly AppraisalRequestRevisionSubmissionService $revisionSubmissionService,
    ) {
    }

    public function build(int $userId, int $id): array
    {
        $record = AppraisalRequest::query()
            ->where('user_id', $userId)
            ->withCount([
                'assets',
                'offerNegotiations as negotiation_rounds_used' => fn ($query) => $query->where('action', 'counter_request'),
            ])
            ->with([
                'user:id,name,email',
                'cancelledBy:id,name',
                'assets:id,appraisal_request_id,asset_type,peruntukan,title_document,land_shape,land_position,land_condition,topography,frontage_width,access_road_width,land_area,building_area,building_floors,build_year,renovation_year,address,coordinates_lat,coordinates_lng,province_id,regency_id,district_id,village_id',
                'assets.files:id,appraisal_asset_id,type,path,original_name,mime,size,created_at',
                'offerNegotiations:id,appraisal_request_id,user_id,action,round,offered_fee,expected_fee,selected_fee,reason,meta,created_at',
                'offerNegotiations.user:id,name',
                'payments:id,appraisal_request_id,amount,method,gateway,external_payment_id,status,paid_at,metadata,updated_at,created_at',
            ])
            ->findOrFail($id);

        $reportTypeValue = $this->formatter->enumBackedValue(ReportTypeEnum::class, $record->report_type);
        $statusValue = $this->formatter->enumBackedValue(AppraisalStatusEnum::class, $record->status);
        $contractStatusValue = $this->formatter->enumBackedValue(ContractStatusEnum::class, $record->contract_status);
        $valuationObjectiveValue = $this->formatter->enumBackedValue(ValuationObjectiveEnum::class, $record->valuation_objective);

        $assets = $record->assets->map(function ($asset) {
            $typeValue = $this->formatter->enumBackedValue(AssetTypeEnum::class, $asset->asset_type);

            return [
                'id' => $asset->id,
                'type' => $typeValue,
                'type_label' => $this->formatter->enumLabel(AssetTypeEnum::class, $asset->asset_type)
                    ?? $this->formatter->assetTypeLegacyLabel($typeValue),
                'land_area' => $asset->land_area,
                'building_area' => $asset->building_area,
                'building_floors' => $asset->building_floors,
                'build_year' => $asset->build_year,
                'renovation_year' => $asset->renovation_year,
                'estimated_value_low' => $asset->estimated_value_low,
                'market_value_final' => $asset->market_value_final,
                'estimated_value_high' => $asset->estimated_value_high,
                'peruntukan' => $asset->peruntukan,
                'title_document' => $asset->title_document,
                'land_shape' => $asset->land_shape,
                'land_position' => $asset->land_position,
                'land_condition' => $asset->land_condition,
                'topography' => $asset->topography,
                'frontage_width' => $asset->frontage_width,
                'access_road_width' => $asset->access_road_width,
                'address' => $asset->address,
                'coordinates' => [
                    'lat' => $asset->coordinates_lat,
                    'lng' => $asset->coordinates_lng,
                ],
                'province_id' => $asset->province_id,
                'regency_id' => $asset->regency_id,
                'district_id' => $asset->district_id,
                'village_id' => $asset->village_id,
            ];
        })->values();

        $collections = $this->documentCatalogBuilder->resolveCollections($record);
        $documents = collect($collections['documents']);
        $requestFiles = collect($collections['request_files']);
        $firstAddress = $collections['first_asset_address'];

        $offerNegotiations = $record->offerNegotiations
            ->sortBy('id')
            ->values()
            ->map(function ($negotiation) {
                return [
                    'id' => $negotiation->id,
                    'action' => $negotiation->action,
                    'round' => $negotiation->round,
                    'offered_fee' => $negotiation->offered_fee,
                    'expected_fee' => $negotiation->expected_fee,
                    'selected_fee' => $negotiation->selected_fee,
                    'reason' => $negotiation->reason,
                    'created_at' => $negotiation->created_at?->toDateTimeString(),
                    'user_name' => $negotiation->user?->name,
                    'meta' => $negotiation->meta,
                ];
            });

        $offerFeeOptions = $offerNegotiations
            ->flatMap(function ($negotiation) {
                return array_filter([
                    $negotiation['offered_fee'],
                    $negotiation['selected_fee'],
                ], fn ($fee) => is_numeric($fee));
            })
            ->map(fn ($fee) => (int) $fee)
            ->values();

        if ($record->fee_total !== null) {
            $offerFeeOptions->push((int) $record->fee_total);
        }

        $offerFeeOptions = $offerFeeOptions
            ->unique()
            ->sort()
            ->values()
            ->map(fn (int $fee) => [
                'id' => 'fee-' . $fee,
                'fee_total' => $fee,
            ]);

        $reportPdfUrl = null;
        if ($record->report_pdf_path && Storage::disk('public')->exists($record->report_pdf_path)) {
            $reportPdfUrl = Storage::disk('public')->url($record->report_pdf_path);
        }

        $contractDocument = $this->contractDocumentBuilder->build($record);
        $statusTimeline = $this->statusTimelineBuilder->build($record);
        $latestPayment = $record->payments->sortByDesc('id')->first();
        $revisionSummary = $this->revisionSubmissionService->buildSummary($record);
        $previewState = $this->previewStateBuilder->build($record);
        $paymentStatus = $latestPayment?->status;
        $paymentStatusLabel = $this->midtransSnapService->paymentStatusLabel($latestPayment);
        $invoiceNumber = data_get($latestPayment?->metadata, 'invoice_number');

        if (! filled($invoiceNumber) && $latestPayment) {
            $invoiceNumber = 'INV-' . now()->format('Y') . '-' . str_pad((string) $latestPayment->id, 5, '0', STR_PAD_LEFT);
        }

        return [
            'request' => [
                'id' => $record->id,
                'request_number' => $record->request_number ?? ('REQ-' . $record->id),
                'report_type' => $reportTypeValue,
                'report_type_label' => $this->formatter->enumLabel(ReportTypeEnum::class, $record->report_type)
                    ?? $this->formatter->headlineOrDashValue($reportTypeValue),
                'status' => $statusValue,
                'status_label' => $this->formatter->enumLabel(AppraisalStatusEnum::class, $record->status)
                    ?? $this->formatter->headlineOrDashValue($statusValue),
                'requested_at' => optional($record->requested_at)->toDateTimeString(),
                'verified_at' => optional($record->verified_at)->toDateTimeString(),
                'client_name' => $record->client_name,
                'client_address' => $record->client_address,
                'client_spk_number' => $record->client_spk_number,
                'valuation_objective' => $valuationObjectiveValue,
                'valuation_objective_label' => $this->formatter->enumLabel(ValuationObjectiveEnum::class, $record->valuation_objective)
                    ?? $this->formatter->headlineOrDashValue($valuationObjectiveValue),
                'sertifikat_on_hand_confirmed' => (bool) $record->sertifikat_on_hand_confirmed,
                'certificate_not_encumbered_confirmed' => (bool) $record->certificate_not_encumbered_confirmed,
                'certificate_statements_accepted_at' => optional($record->certificate_statements_accepted_at)->toDateTimeString(),
                'contract_number' => $record->contract_number,
                'contract_date' => optional($record->contract_date)->toDateString(),
                'contract_status' => $contractStatusValue,
                'contract_status_label' => $this->formatter->enumLabel(ContractStatusEnum::class, $record->contract_status)
                    ?? $this->formatter->headlineOrDashValue($contractStatusValue),
                'fee_total' => $record->fee_total,
                'report_format' => $record->report_format,
                'physical_copies_count' => (int) ($record->physical_copies_count ?? 0),
                'assets_count' => (int) ($record->assets_count ?? 0),
                'negotiation_rounds_used' => (int) ($record->negotiation_rounds_used ?? 0),
                'first_asset_address' => $firstAddress,
                'offer_negotiations' => $offerNegotiations,
                'offer_fee_options' => $offerFeeOptions,
                'assets' => $assets,
                'documents' => $documents,
                'request_files' => $requestFiles,
                'report_generated_at' => optional($record->report_generated_at)->toDateTimeString(),
                'report_pdf_path' => $record->report_pdf_path,
                'report_pdf_url' => $reportPdfUrl,
                'contract_document' => $contractDocument,
                'cancelled_at' => optional($record->cancelled_at)->toDateTimeString(),
                'cancelled_by_name' => $record->cancelledBy?->name,
                'cancellation_reason' => $record->cancellation_reason,
                'preview_state' => $previewState,
                'preview_summary' => $previewState['summary'],
                'preview_page_url' => $previewState['page_url'],
                'appeal_remaining' => $previewState['appeal_remaining'],
                'latest_preview_version' => $previewState['version'],
                'status_timeline' => $statusTimeline,
                'revision_summary' => $revisionSummary,
                'payment_summary' => [
                    'id' => $latestPayment?->id,
                    'status' => $paymentStatus,
                    'status_label' => $paymentStatusLabel,
                    'is_paid' => $paymentStatus === 'paid',
                    'invoice_number' => $invoiceNumber,
                    'paid_at' => optional($latestPayment?->paid_at)->toDateTimeString(),
                ],
            ],
        ];
    }
}

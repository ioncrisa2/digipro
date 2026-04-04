<?php

namespace App\Services\Customer;

use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Storage;
use App\Support\EnumPresenter;
use App\Support\AppraisalAssetFieldOptions;
use App\Services\Payments\MidtransSnapService;
use App\Services\Revisions\AppraisalRequestRevisionSubmissionService;
use App\Services\Revisions\AppraisalRevisionFileResolver;
use App\Models\Regency;
use App\Models\Village;
use App\Models\District;
use App\Models\Province;
use App\Models\AppraisalAsset;
use App\Models\ConsentDocument;
use App\Models\AppraisalRequest;
use App\Models\AppraisalUserConsent;
use App\Enums\AssetTypeEnum;
use App\Enums\ReportTypeEnum;
use App\Enums\ContractStatusEnum;
use App\Enums\AppraisalStatusEnum;
use App\Enums\ValuationObjectiveEnum;

/**
 * Builds appraisal UI payloads and handles consent flows for users.
 */
class AppraisalService
{
    use EnumPresenter;

    public function buildIndexPayload(int $userId, string $q, string $status, int $perPage = 10): array
    {
        $base = AppraisalRequest::query()->where('user_id', $userId);

        $pendingStatuses = array_filter([
            AppraisalStatusEnum::Draft->value ?? null,
            AppraisalStatusEnum::Submitted->value ?? null,
            AppraisalStatusEnum::DocsIncomplete->value ?? null,
            AppraisalStatusEnum::Verified->value ?? null,
            AppraisalStatusEnum::WaitingOffer->value ?? null,
            AppraisalStatusEnum::OfferSent->value ?? null,
            AppraisalStatusEnum::WaitingSignature->value ?? null,
        ]);

        $inProgressStatuses = array_filter([
            AppraisalStatusEnum::ContractSigned->value ?? null,
            AppraisalStatusEnum::ValuationOnProgress->value ?? null,
            AppraisalStatusEnum::ValuationCompleted->value ?? null,
            AppraisalStatusEnum::PreviewReady->value ?? null,
            AppraisalStatusEnum::ReportPreparation->value ?? null,
            AppraisalStatusEnum::ReportReady->value ?? null,
        ]);

        $completedStatuses = array_filter([AppraisalStatusEnum::Completed->value ?? null]);

        $rejectedStatuses = array_filter([AppraisalStatusEnum::Cancelled->value ?? null]);

        $stats = [
            'total' => (clone $base)->count(),
            'pending' => $pendingStatuses
                ? (clone $base)->whereIn('status', $pendingStatuses)->count()
                : (clone $base)->where('status', 'pending')->count(),
            'in_progress' => $inProgressStatuses
                ? (clone $base)->whereIn('status', $inProgressStatuses)->count()
                : (clone $base)->where('status', 'in_progress')->count(),
            'completed' => $completedStatuses
                ? (clone $base)->whereIn('status', $completedStatuses)->count()
                : (clone $base)->where('status', 'completed')->count(),
            'rejected' => $rejectedStatuses
                ? (clone $base)->whereIn('status', $rejectedStatuses)->count()
                : (clone $base)->where('status', 'rejected')->count(),
        ];

        $statsCards = [
            [
                'key' => 'total',
                'label' => 'Total Permohonan',
                'value' => $stats['total'],
            ],
            [
                'key' => 'pending',
                'label' => 'Menunggu Proses',
                'value' => $stats['pending'],
            ],
            [
                'key' => 'in_progress',
                'label' => 'Sedang Diproses',
                'value' => $stats['in_progress'],
            ],
            [
                'key' => 'completed',
                'label' => 'Selesai',
                'value' => $stats['completed'],
            ],
        ];

        $query = AppraisalRequest::query()
            ->where('user_id', $userId)
            ->withCount('assets')
            ->selectSub(
                AppraisalAsset::select('address')
                    ->whereColumn('appraisal_assets.appraisal_request_id', 'appraisal_requests.id')
                    ->orderBy('id')
                    ->limit(1),
                'first_asset_address'
            );

        if ($q !== '') {
            $query->where(function ($s) use ($q) {
                $s->where('request_number', 'like', "%{$q}%")
                    ->orWhere('client_name', 'like', "%{$q}%")
                    ->orWhere('id', $q);
            });
        }

        if ($status !== '' && $status !== 'all') {
            $query->where('status', $status);
        }

        $appraisals = $query
            ->latest('requested_at')
            ->paginate($perPage)
            ->withQueryString()
            ->through(function ($r) {
                $reportTypeEnum = $this->asEnum(ReportTypeEnum::class, $r->report_type);
                $statusEnum = $this->asEnum(AppraisalStatusEnum::class, $r->status);

                $reportTypeValue = $reportTypeEnum?->value ?? $this->enumValue($r->report_type);
                $statusValue = $statusEnum?->value ?? $this->enumValue($r->status);

                return [
                    'id' => $r->id,
                    'request_number' => $r->request_number ?? ('REQ-' . $r->id),

                    'report_type' => $reportTypeValue,
                    'report_type_label' => $reportTypeEnum?->label() ?? $this->headlineOrDash($reportTypeValue),

                    'assets_count' => (int) $r->assets_count,

                    'status' => $statusValue,
                    'status_label' => $statusEnum?->label() ?? $this->headlineOrDash($statusValue),

                    'requested_at' => optional($r->requested_at)->toDateString(),

                    'location' => $r->first_asset_address
                        ? Str::limit($r->first_asset_address, 48)
                        : '-',

                    'report_format' => $r->report_format,
                    'physical_copies_count' => (int) ($r->physical_copies_count ?? 0),
                ];
            });

        return [
            'appraisals' => $appraisals,
            'stats' => $stats,
            'statsCards' => $statsCards,
        ];
    }

    public function buildCreatePayload(?int $provinceId, ?int $regencyId, ?int $districtId, bool $needsConsent, ?array $consentData): array
    {
        $maxFileUploads = (int) ini_get('max_file_uploads');

        return [
            'provinces' => Province::select('id', 'name')->orderBy('name')->get(),
            'regencies' => $provinceId
                ? Regency::select('id', 'name')->where('province_id', $provinceId)->orderBy('name')->get()
                : [],
            'districts' => $regencyId
                ? District::select('id', 'name')->where('regency_id', $regencyId)->orderBy('name')->get()
                : [],
            'villages' => $districtId
                ? Village::select('id', 'name')->where('district_id', $districtId)->orderBy('name')->get()
                : [],
            'assetTypeOptions' => collect(AssetTypeEnum::cases())
                ->map(fn (AssetTypeEnum $case) => [
                    'value' => $case->value,
                    'label' => $case->label(),
                ])
                ->values()
                ->toArray(),
            'usageOptions' => AppraisalAssetFieldOptions::usageOptions(),
            'titleDocumentOptions' => AppraisalAssetFieldOptions::titleDocumentOptions(),
            'landShapeOptions' => AppraisalAssetFieldOptions::landShapeOptions(),
            'landPositionOptions' => AppraisalAssetFieldOptions::landPositionOptions(),
            'landConditionOptions' => AppraisalAssetFieldOptions::landConditionOptions(),
            'topographyOptions' => AppraisalAssetFieldOptions::topographyOptions(),
            'needsConsent' => $needsConsent,
            'consentData' => $consentData,
            'representativeLetterNotice' => [
                'title' => 'Surat Representatif DigiPro',
                'description' => 'Setelah request dilanjutkan dan kontrak ditandatangani, DigiPro akan menyiapkan surat representatif berdasarkan data permohonan dan dokumen yang Anda kirim.',
            ],
            'valuationObjective' => [
                'value' => ValuationObjectiveEnum::KajianNilaiPasarRange->value,
                'label' => ValuationObjectiveEnum::KajianNilaiPasarRange->label(),
            ],
            'uploadLimits' => [
                'maxFileUploads' => $maxFileUploads > 0 ? $maxFileUploads : null,
                'uploadMaxFilesize' => ini_get('upload_max_filesize'),
                'postMaxSize' => ini_get('post_max_size'),
            ],
        ];
    }

    public function buildDocumentsIndexPayload(int $userId): array
    {
        $records = AppraisalRequest::query()
            ->where('user_id', $userId)
            ->with([
                'user:id,name,email',
                'assets:id,appraisal_request_id,asset_type,address',
                'assets.files:id,appraisal_asset_id,type,path,original_name,mime,size,created_at',
                'files:id,appraisal_request_id,type,path,original_name,mime,size,created_at',
                'payments:id,appraisal_request_id,amount,method,gateway,external_payment_id,status,paid_at,metadata,updated_at,created_at',
            ])
            ->latest('requested_at')
            ->get();

        $reports = $records->map(function (AppraisalRequest $record): array {
            $collections = $this->resolveDocumentCollections($record);
            $reportTypeEnum = $this->asEnum(ReportTypeEnum::class, $record->report_type);
            $statusEnum = $this->asEnum(AppraisalStatusEnum::class, $record->status);
            $statusValue = $statusEnum?->value ?? $this->enumValue($record->status);
            $summary = $this->buildDocumentSummary($collections);

            return [
                'id' => $record->id,
                'request_number' => $record->request_number ?? ('REQ-' . $record->id),
                'client' => $record->client_name ?: ($record->user?->name ?? '-'),
                'report_type' => $reportTypeEnum?->label() ?? $this->headlineOrDash($record->report_type),
                'status' => $statusEnum?->label() ?? $this->headlineOrDash($statusValue),
                'status_key' => $statusValue,
                'address' => $collections['first_asset_address'],
                'updated_at' => optional($record->updated_at)->toDateString(),
                'customer_documents_count' => $summary['customer_documents_count'],
                'customer_photos_count' => $summary['customer_photos_count'],
                'system_documents_count' => $summary['system_documents_count'],
                'ready_contract' => $summary['ready_contract'],
                'ready_report' => $summary['ready_report'],
                'ready_invoice' => $summary['ready_invoice'],
                'ready_legal_documents' => $summary['ready_legal_documents'],
                'total_documents_count' => $summary['total_documents_count'],
            ];
        })->values()->all();

        return [
            'reports' => $reports,
        ];
    }

    public function buildDocumentsShowPayload(int $userId, int $id): array
    {
        $record = AppraisalRequest::query()
            ->where('user_id', $userId)
            ->with([
                'user:id,name,email',
                'assets:id,appraisal_request_id,asset_type,address',
                'assets.files:id,appraisal_asset_id,type,path,original_name,mime,size,created_at',
                'files:id,appraisal_request_id,type,path,original_name,mime,size,created_at',
                'payments:id,appraisal_request_id,amount,method,gateway,external_payment_id,status,paid_at,metadata,updated_at,created_at',
            ])
            ->findOrFail($id);

        $collections = $this->resolveDocumentCollections($record);
        $reportTypeEnum = $this->asEnum(ReportTypeEnum::class, $record->report_type);
        $statusEnum = $this->asEnum(AppraisalStatusEnum::class, $record->status);
        $summary = $this->buildDocumentSummary($collections);

        return [
            'report' => [
                'id' => $record->id,
                'request_number' => $record->request_number ?? ('REQ-' . $record->id),
                'client' => $record->client_name ?: ($record->user?->name ?? '-'),
                'report_type' => $reportTypeEnum?->label() ?? $this->headlineOrDash($record->report_type),
                'status' => $statusEnum?->label() ?? $this->headlineOrDash($record->status),
                'status_key' => $statusEnum?->value ?? $this->enumValue($record->status),
                'address' => $collections['first_asset_address'],
                'updated_at' => optional($record->updated_at)->toDateTimeString(),
                'summary' => $summary,
                'request_upload_documents' => $collections['request_upload_documents'],
                'asset_sections' => $collections['asset_sections'],
                'system_documents' => $collections['system_documents'],
                'legal_documents' => $collections['legal_documents'],
                'billing_documents' => $collections['billing_documents'],
            ],
        ];
    }

    public function buildShowPayload(int $userId, int $id): array
    {
        $r = AppraisalRequest::query()
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

        $reportTypeEnum = $this->asEnum(ReportTypeEnum::class, $r->report_type);
        $statusEnum = $this->asEnum(AppraisalStatusEnum::class, $r->status);
        $contractStatusEnum = $this->asEnum(ContractStatusEnum::class, $r->contract_status);

        $reportTypeValue = $reportTypeEnum?->value ?? $this->enumValue($r->report_type);
        $statusValue = $statusEnum?->value ?? $this->enumValue($r->status);
        $contractStatusValue = $contractStatusEnum?->value ?? $this->enumValue($r->contract_status);
        $valuationObjectiveEnum = $this->asEnum(ValuationObjectiveEnum::class, $r->valuation_objective);
        $valuationObjectiveValue = $valuationObjectiveEnum?->value ?? (is_string($r->valuation_objective) ? $r->valuation_objective : null);

        $assets = $r->assets->map(function ($a) {
            $typeValue = $this->enumValue($a->asset_type);
            $typeEnum = $this->asEnum(AssetTypeEnum::class, $a->asset_type);

            return [
                'id' => $a->id,
                'type' => $typeValue,
                'type_label' => $typeEnum?->label() ?? $this->assetTypeLegacyLabel($typeValue),
                'land_area' => $a->land_area,
                'building_area' => $a->building_area,
                'building_floors' => $a->building_floors,
                'build_year' => $a->build_year,
                'renovation_year' => $a->renovation_year,
                'estimated_value_low' => $a->estimated_value_low,
                'market_value_final' => $a->market_value_final,
                'estimated_value_high' => $a->estimated_value_high,
                'peruntukan' => $a->peruntukan,
                'title_document' => $a->title_document,
                'land_shape' => $a->land_shape,
                'land_position' => $a->land_position,
                'land_condition' => $a->land_condition,
                'topography' => $a->topography,
                'frontage_width' => $a->frontage_width,
                'access_road_width' => $a->access_road_width,
                'address' => $a->address,
                'coordinates' => [
                    'lat' => $a->coordinates_lat,
                    'lng' => $a->coordinates_lng,
                ],
                'province_id' => $a->province_id,
                'regency_id' => $a->regency_id,
                'district_id' => $a->district_id,
                'village_id' => $a->village_id,
            ];
        })->values();

        $collections = $this->resolveDocumentCollections($r);
        $documents = collect($collections['documents']);
        $requestFiles = collect($collections['request_files']);
        $firstAddress = $collections['first_asset_address'];

        $offerNegotiations = $r->offerNegotiations
            ->sortBy('id')
            ->values()
            ->map(function ($n) {
                return [
                    'id' => $n->id,
                    'action' => $n->action,
                    'round' => $n->round,
                    'offered_fee' => $n->offered_fee,
                    'expected_fee' => $n->expected_fee,
                    'selected_fee' => $n->selected_fee,
                    'reason' => $n->reason,
                    'created_at' => $n->created_at?->toDateTimeString(),
                    'user_name' => $n->user?->name,
                    'meta' => $n->meta,
                ];
            });

        $offerFeeOptions = $offerNegotiations
            ->flatMap(function ($n) {
                return array_filter([
                    $n['offered_fee'],
                    $n['selected_fee'],
                ], fn ($fee) => is_numeric($fee));
            })
            ->map(fn ($fee) => (int) $fee)
            ->values();

        if ($r->fee_total !== null) {
            $offerFeeOptions->push((int) $r->fee_total);
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
        if ($r->report_pdf_path && Storage::disk('public')->exists($r->report_pdf_path)) {
            $reportPdfUrl = Storage::disk('public')->url($r->report_pdf_path);
        }

        $contractDocument = $this->buildContractDocumentPayload($r);
        $statusTimeline = $this->buildStatusTimeline($r);
        $latestPayment = $r->payments->sortByDesc('id')->first();
        $revisionSummary = app(AppraisalRequestRevisionSubmissionService::class)->buildSummary($r);
        $previewState = $this->buildPreviewStatePayload($r);
        $paymentStatus = $latestPayment?->status;
        $paymentStatusLabel = app(MidtransSnapService::class)->paymentStatusLabel($latestPayment);
        $invoiceNumber = data_get($latestPayment?->metadata, 'invoice_number');
        if (! filled($invoiceNumber) && $latestPayment) {
            $invoiceNumber = 'INV-' . now()->format('Y') . '-' . str_pad((string) $latestPayment->id, 5, '0', STR_PAD_LEFT);
        }

        return [
            'request' => [
                'id' => $r->id,
                'request_number' => $r->request_number ?? ('REQ-' . $r->id),

                'report_type' => $reportTypeValue,
                'report_type_label' => $reportTypeEnum?->label() ?? $this->headlineOrDash($reportTypeValue),

                'status' => $statusValue,
                'status_label' => $statusEnum?->label() ?? $this->headlineOrDash($statusValue),

                'requested_at' => optional($r->requested_at)->toDateTimeString(),
                'verified_at' => optional($r->verified_at)->toDateTimeString(),

                'client_name' => $r->client_name,
                'client_address' => $r->client_address,
                'client_spk_number' => $r->client_spk_number,
                'valuation_objective' => $valuationObjectiveValue,
                'valuation_objective_label' => $valuationObjectiveEnum?->label() ?? $this->headlineOrDash($valuationObjectiveValue),
                'sertifikat_on_hand_confirmed' => (bool) $r->sertifikat_on_hand_confirmed,
                'certificate_not_encumbered_confirmed' => (bool) $r->certificate_not_encumbered_confirmed,
                'certificate_statements_accepted_at' => optional($r->certificate_statements_accepted_at)->toDateTimeString(),

                'contract_number' => $r->contract_number,
                'contract_date' => optional($r->contract_date)->toDateString(),
                'contract_status' => $contractStatusValue,
                'contract_status_label' => $contractStatusEnum?->label() ?? $this->headlineOrDash($contractStatusValue),

                'fee_total' => $r->fee_total,

                'report_format' => $r->report_format,
                'physical_copies_count' => (int) ($r->physical_copies_count ?? 0),

                'assets_count' => (int) ($r->assets_count ?? 0),
                'negotiation_rounds_used' => (int) ($r->negotiation_rounds_used ?? 0),
                'first_asset_address' => $firstAddress,
                'offer_negotiations' => $offerNegotiations,
                'offer_fee_options' => $offerFeeOptions,

                'assets' => $assets,
                'documents' => $documents,
                'request_files' => $requestFiles,

                'report_generated_at' => optional($r->report_generated_at)->toDateTimeString(),
                'report_pdf_path' => $r->report_pdf_path,
                'report_pdf_url' => $reportPdfUrl,
                'contract_document' => $contractDocument,
                'cancelled_at' => optional($r->cancelled_at)->toDateTimeString(),
                'cancelled_by_name' => $r->cancelledBy?->name,
                'cancellation_reason' => $r->cancellation_reason,
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

    public function buildMarketPreviewPayload(int $userId, int $id): array
    {
        $record = AppraisalRequest::query()
            ->where('user_id', $userId)
            ->with([
                'user:id,name,email',
                'assets:id,appraisal_request_id,asset_type,address,land_area,building_area,estimated_value_low,estimated_value_high,market_value_final',
            ])
            ->findOrFail($id);

        $previewState = $this->buildPreviewStatePayload($record);
        $status = $record->status?->value ?? (string) $record->status;

        if ($status !== AppraisalStatusEnum::PreviewReady->value) {
            abort(404);
        }

        return [
            'preview' => [
                'id' => $record->id,
                'request_number' => $record->request_number ?? ('REQ-' . $record->id),
                'client_name' => $record->client_name ?: ($record->user?->name ?? '-'),
                'status' => $record->status?->value ?? (string) $record->status,
                'status_label' => $record->status?->label() ?? (string) $record->status,
                'report_type_label' => $record->report_type?->label() ?? '-',
                'version' => $previewState['version'],
                'published_at' => $previewState['published_at'],
                'summary' => $previewState['summary'],
                'assets' => $previewState['assets'],
                'can_approve' => true,
                'can_appeal' => $previewState['appeal_remaining'] > 0,
                'appeal_remaining' => $previewState['appeal_remaining'],
                'appeal_reason' => $record->market_preview_appeal_reason,
                'approve_url' => route('appraisal.market-preview.approve', ['id' => $record->id]),
                'appeal_url' => route('appraisal.market-preview.appeal', ['id' => $record->id]),
            ],
        ];
    }

    public function buildContractDocumentPayload(AppraisalRequest $record): array
    {
        $record->loadMissing([
            'user:id,name,email',
            'assets:id,appraisal_request_id,asset_type,land_area,building_area,address',
            'assets.files:id,appraisal_asset_id,type,original_name',
            'offerNegotiations:id,appraisal_request_id,user_id,action,meta,created_at',
            'offerNegotiations.user:id,name,email',
        ]);

        $assetRows = $record->assets
            ->values()
            ->map(function (AppraisalAsset $asset, int $index): array {
                $docLabels = $asset->files
                    ->filter(fn ($file) => ! $this->isPhotoFileType($file->type))
                    ->map(fn ($file) => $this->contractDocumentTypeLabel($file->type))
                    ->filter()
                    ->unique()
                    ->values()
                    ->all();

                return [
                    'no' => $index + 1,
                    'label' => $this->assetTypeLabelForContract($this->enumValue($asset->asset_type)),
                    'address' => $asset->address ?: '-',
                    'main_documents' => empty($docLabels) ? '-' : implode(', ', $docLabels),
                    'area_basis' => $this->assetAreaBasisForContract($asset),
                    'note' => $this->assetNoteForContract($asset),
                ];
            })
            ->all();

        $assetCount = count($assetRows);
        $totalFee = (int) ($record->fee_total ?? 0);
        $feePerAsset = $assetCount > 0 ? (int) round($totalFee / $assetCount) : $totalFee;

        $acceptedAt = optional(
            $record->offerNegotiations
                ->where('action', 'accept_offer')
                ->sortByDesc('created_at')
                ->first()
        )->created_at;

        $signatureLog = $record->offerNegotiations
            ->where('action', 'contract_sign_mock')
            ->sortByDesc('id')
            ->first();

        $signatureMeta = is_array($signatureLog?->meta) ? $signatureLog->meta : [];
        $signedAt = $signatureMeta['signed_at'] ?? ($signatureLog?->created_at?->toDateTimeString());
        $signedPdfPath = is_string($signatureMeta['signed_pdf_path'] ?? null)
            ? $signatureMeta['signed_pdf_path']
            : null;

        $signedPdfUrl = null;
        if ($signedPdfPath && Storage::disk('public')->exists($signedPdfPath)) {
            $signedPdfUrl = Storage::disk('public')->url($signedPdfPath);
        }

        return [
            'title' => 'PENAWARAN LAYANAN ESTIMASI RENTANG HARGA PROPERTI',
            'subtitle' => '(Tanpa Inspeksi Lapangan - Non-Reliance)',
            'valuation_objective_label' => $record->valuation_objective?->label() ?? 'Kajian Nilai Pasar dalam Bentuk Range',
            'agr_no' => $record->contract_number ?: '-',
            'date' => optional($record->contract_date)->toDateString() ?: now()->toDateString(),
            'user_name' => $record->user?->name ?: ($record->client_name ?: '-'),
            'request_id' => $record->request_number ?: ('REQ-' . $record->id),
            'user_identifier' => $record->user?->email ?: '-',
            'assets' => $assetRows,
            'asset_count' => $assetCount,
            'fee_per_asset' => $feePerAsset,
            'total_fee' => $totalFee,
            'tax_note' => 'Menyesuaikan ketentuan perpajakan yang berlaku.',
            'payment_methods' => 'Pembayaran online melalui Midtrans Snap (VA, QRIS, dan e-wallet yang tersedia).',
            'included_scope' => [
                'Telaah dokumen/foto yang diunggah pengguna',
                'Pemilihan pembanding dari Bank Data DigiPro',
                'Perhitungan rentang estimasi (P25-P75) dan indikator confidence',
            ],
            'excluded_scope' => [
                'Inspeksi lapangan dan pengukuran fisik',
                'Verifikasi legalitas menyeluruh di luar dokumen yang diunggah',
                'Penerbitan laporan penilaian dengan nilai tunggal/final',
            ],
            'output_text' => 'Hasil estimasi ditampilkan pada halaman DigiPro dan tersedia untuk diunduh dalam format PDF.',
            'sla_text' => 'Estimasi waktu penyelesaian umumnya beberapa jam sejak data minimum dinyatakan lengkap oleh sistem, dengan batas waktu maksimum 1-24 jam.',
            'statement_text' => 'Dokumen penawaran dan hasil layanan DigiPro bersifat informasi umum. DigiPro tidak melakukan inspeksi lapangan. Hasil layanan berupa estimasi rentang, bukan nilai final, dan tidak dimaksudkan untuk digunakan sebagai dasar penjaminan/agunan, kredit, transaksi mengikat, perpajakan, pelaporan keuangan, maupun tujuan penilaian profesional.',
            'official_contact' => config('app.name') . ' User Portal',
            'accepted_at' => $acceptedAt?->toDateTimeString() ?: '-',
            'consent_id' => 'CONSENT-' . $record->id,
            'disclaimer_footer' => 'Dokumen ini bersifat informasi umum dan non-reliance (tanpa inspeksi lapangan).',
            'signature' => [
                'is_signed' => (bool) $signatureLog,
                'signed_at' => $signedAt ?: '-',
                'signed_by_name' => $signatureMeta['signed_by_name'] ?? ($signatureLog?->user?->name ?: '-'),
                'signed_by_email' => $signatureMeta['signed_by_email'] ?? ($signatureLog?->user?->email ?: '-'),
                'signature_id' => $signatureMeta['signature_id'] ?? '-',
                'method' => $signatureMeta['method'] ?? ($signatureLog ? 'clickwrap' : '-'),
                'provider' => $signatureMeta['provider'] ?? ($signatureLog ? 'mock' : '-'),
                'document_hash' => $signatureMeta['document_hash'] ?? '-',
                'signed_pdf_path' => $signedPdfPath,
                'signed_pdf_url' => $signedPdfUrl,
            ],
        ];
    }

    public function buildRepresentativeLetterPayload(AppraisalRequest $record, array $signatureMeta = []): array
    {
        $record->loadMissing([
            'user:id,name,email',
            'assets:id,appraisal_request_id,asset_type,address,land_area,building_area,title_document',
        ]);

        $assetSummaries = $record->assets
            ->values()
            ->map(function (AppraisalAsset $asset, int $index): array {
                return [
                    'no' => $index + 1,
                    'type_label' => $this->assetTypeLabelForContract($this->enumValue($asset->asset_type)),
                    'address' => $asset->address ?: '-',
                    'title_document' => $asset->title_document ?: '-',
                    'land_area' => is_numeric($asset->land_area) ? number_format((float) $asset->land_area, 2, ',', '.') . ' m2' : '-',
                    'building_area' => is_numeric($asset->building_area) ? number_format((float) $asset->building_area, 2, ',', '.') . ' m2' : '-',
                ];
            })
            ->all();

        return [
            'title' => 'SURAT REPRESENTATIF',
            'subtitle' => 'Pernyataan pengguna atas dokumen dan informasi permohonan penilaian digital',
            'request_number' => $record->request_number ?? ('REQ-' . $record->id),
            'contract_number' => $record->contract_number ?: '-',
            'valuation_objective_label' => $record->valuation_objective?->label() ?? 'Kajian Nilai Pasar dalam Bentuk Range',
            'date' => now()->translatedFormat('d F Y'),
            'requester_name' => $record->user?->name ?? '-',
            'requester_email' => $record->user?->email ?? '-',
            'client_name' => $record->client_name ?: ($record->user?->name ?? '-'),
            'asset_summaries' => $assetSummaries,
            'statement_items' => [
                'Seluruh data, pernyataan, foto, dan dokumen yang saya unggah ke DigiPro adalah benar, lengkap, dan sesuai kondisi objek yang sebenarnya pada saat permohonan dibuat.',
                'Saya menyatakan dokumen kepemilikan utama tersedia/on hand dan tidak sedang dijaminkan pada saat permohonan diajukan melalui platform DigiPro.',
                'Saya memahami bahwa hasil layanan dan dokumen turunannya disusun berdasarkan informasi yang saya berikan melalui proses digital DigiPro. Jika di kemudian hari terdapat ketidaksesuaian atau informasi yang tidak benar dari pihak saya, maka tanggung jawab atas akibat yang timbul berada pada pihak saya.',
                'Saya memberikan pembebasan tanggung jawab kepada DigiPro dan tim operasionalnya atas kerugian, tuntutan, atau sengketa yang timbul akibat data atau dokumen yang saya sampaikan tidak benar, tidak lengkap, atau berubah tanpa pemberitahuan.',
                'Saya memahami bahwa surat ini dan dokumen yang dihasilkan DigiPro digunakan hanya dalam konteks proses permohonan penilaian digital sesuai ketentuan layanan yang berlaku di platform.',
            ],
            'signature' => [
                'signed_at' => $signatureMeta['signed_at'] ?? '-',
                'signed_by_name' => $signatureMeta['signed_by_name'] ?? ($record->user?->name ?? '-'),
                'signed_by_email' => $signatureMeta['signed_by_email'] ?? ($record->user?->email ?? '-'),
                'signature_id' => $signatureMeta['signature_id'] ?? '-',
                'document_hash' => $signatureMeta['document_hash'] ?? '-',
            ],
        ];
    }

    public function acceptConsent(Request $request): void
    {
        $doc = ConsentDocument::query()
            ->published()
            ->forCode('appraisal_request_consent')
            ->orderByDesc('published_at')
            ->firstOrFail();

        $request->session()->put('appraisal_consent.accepted', true);
        $request->session()->put('appraisal_consent.document_id', $doc->id);
        $request->session()->put('appraisal_consent.code', $doc->code);
        $request->session()->put('appraisal_consent.version', $doc->version);
        $request->session()->put('appraisal_consent.hash', $doc->hash);

        AppraisalUserConsent::create([
            'user_id' => auth()->id(),
            'consent_document_id' => $doc->id,
            'code' => $doc->code,
            'version' => $doc->version,
            'hash' => $doc->hash,
            'accepted_at' => now(),
            'ip' => $request->ip(),
            'user_agent' => substr((string) $request->userAgent(), 0, 255),
        ]);
    }

    public function declineConsent(Request $request): void
    {
        $request->session()->forget([
            'appraisal_consent',
            'appraisal_consent_accepted',
            'appraisal_consent_document_id',
            'appraisal_consent_version',
            'appraisal_consent_hash',
        ]);
    }

    public function hasAcceptedLatestConsent(Request $request): bool
    {
        $props = $this->buildConsentProps();

        $v = Session::get('appraisal_consent.version');
        $h = Session::get('appraisal_consent.hash');

        if ($v === null || $h === null) {
            $legacyV = Session::get('appraisal_consent_version');
            $legacyH = Session::get('appraisal_consent_hash');
            if ($legacyV !== null && $legacyH !== null) {
                $v = $legacyV;
                $h = $legacyH;
                Session::put('appraisal_consent.version', $legacyV);
                Session::put('appraisal_consent.hash', $legacyH);
            }
        }
        if ($v === $props['version'] && $h === $props['hash']) {
            return true;
        }

        $accepted = DB::table('appraisal_user_consents')
            ->where('user_id', $request->user()->id)
            ->where('version', $props['version'])
            ->where('hash', $props['hash'])
            ->exists();

        if ($accepted) {
            Session::put('appraisal_consent.version', $props['version']);
            Session::put('appraisal_consent.hash', $props['hash']);
        }

        return $accepted;
    }

    public function buildConsentProps(): array
    {
        $doc = ConsentDocument::query()
            ->published()
            ->forCode('appraisal_request_consent')
            ->orderByDesc('published_at')
            ->firstOrFail();

        return [
            'document_id' => $doc->id,
            'version' => $doc->version,
            'hash' => $doc->hash,
            'title' => $doc->title,
            'sections' => $doc->sections,
            'checkbox_label' => $doc->checkbox_label
                ?? 'Saya telah membaca, memahami, dan menyetujui seluruh Persetujuan dan Disclaimer di atas.',
        ];
    }

    private function assetTypeLegacyLabel(?string $type): string
    {
        return match ($type) {
            'house' => 'Rumah Tinggal',
            'land' => 'Tanah Kosong',
            'shophouse' => 'Ruko / Rukan',
            'warehouse' => 'Gudang / Pabrik',
            default => $this->headlineOrDash($type),
        };
    }

    private function assetTypeLabelForContract(?string $type): string
    {
        $enum = $this->asEnum(AssetTypeEnum::class, $type);
        if ($enum) {
            return $enum->label() ?? $this->assetTypeLegacyLabel($type);
        }

        return $this->assetTypeLegacyLabel($type);
    }

    private function isPhotoFileType(?string $type): bool
    {
        $type = strtolower((string) $type);
        return str_starts_with($type, 'photo_') || $type === 'photos';
    }

    private function contractDocumentTypeLabel(?string $type): string
    {
        return match ((string) $type) {
            'agreement_pdf' => 'Agreement DigiPro',
            'contract_pdf' => 'Kontrak',
            'contract_signed_pdf' => 'PDF Kontrak Ditandatangani',
            'doc_certs' => 'Sertifikat',
            'doc_pbb' => 'PBB',
            'doc_imb' => 'IMB/PBG',
            'disclaimer_pdf' => 'Disclaimer DigiPro',
            'doc_old_report' => 'Laporan Lama',
            'invoice_pdf' => 'Invoice Pembayaran',
            'npwp' => 'NPWP',
            'representative' => 'Surat Kuasa',
            'representative_letter_pdf' => 'Surat Representatif DigiPro',
            'permission' => 'Surat Izin',
            default => $this->headlineOrDash((string) $type),
        };
    }

    private function resolveDocumentCollections(AppraisalRequest $record): array
    {
        $record->loadMissing([
            'user:id,name,email',
            'assets:id,appraisal_request_id,asset_type,address',
            'assets.files:id,appraisal_asset_id,type,path,original_name,mime,size,created_at',
            'files:id,appraisal_request_id,type,path,original_name,mime,size,created_at',
            'payments:id,appraisal_request_id,amount,method,gateway,external_payment_id,status,paid_at,metadata,updated_at,created_at',
        ]);

        $fileResolver = app(AppraisalRevisionFileResolver::class);
        $approvedRevisionItems = $fileResolver->approvedItemsForRequest($record);
        $activeAssetFiles = $fileResolver->activeAssetFilesByRequest($record, $approvedRevisionItems);
        $activeRequestFiles = $fileResolver->activeRequestFiles($record, $approvedRevisionItems);
        $latestPayment = $record->payments->sortByDesc('id')->first();

        $documents = $record->assets
            ->flatMap(function ($asset) use ($activeAssetFiles) {
                return collect($activeAssetFiles[$asset->id] ?? [])->map(function ($file) use ($asset) {
                    return $this->mapStoredFilePayload($file, (int) $asset->id, (string) $asset->asset_type);
                });
            })
            ->values()
            ->all();

        $requestFiles = $activeRequestFiles
            ->map(fn ($file) => $this->mapStoredFilePayload($file))
            ->values()
            ->all();

        $requestUploadDocuments = array_values(array_filter(
            $requestFiles,
            fn ($file) => in_array((string) $file['type'], $this->customerRequestFileTypes(), true)
        ));

        $assetSections = $record->assets
            ->sortBy('id')
            ->values()
            ->map(function ($asset, $index) use ($documents): array {
                $assetDocs = array_values(array_filter($documents, fn ($file) => (int) ($file['asset_id'] ?? 0) === (int) $asset->id));

                return [
                    'id' => $asset->id,
                    'title' => 'Aset #' . ($index + 1) . ' - ' . ($this->assetTypeLegacyLabel($this->enumValue($asset->asset_type)) ?: 'Aset'),
                    'address' => $asset->address ?: '-',
                    'documents' => array_values(array_filter($assetDocs, fn ($file) => ! $this->isPhotoFileType($file['type'] ?? null))),
                    'photos' => array_values(array_filter($assetDocs, fn ($file) => $this->isPhotoFileType($file['type'] ?? null))),
                ];
            })
            ->all();

        $systemDocuments = $this->buildSystemDocumentEntries($record, $requestFiles, $latestPayment);
        $legalDocuments = array_values(array_filter(
            $requestFiles,
            fn ($file) => in_array((string) $file['type'], $this->legalFinalRequestFileTypes(), true)
        ));
        $billingDocuments = $this->buildBillingDocumentEntries($record, $latestPayment);

        return [
            'documents' => $documents,
            'request_files' => $requestFiles,
            'request_upload_documents' => $requestUploadDocuments,
            'asset_sections' => $assetSections,
            'system_documents' => $systemDocuments,
            'legal_documents' => $legalDocuments,
            'billing_documents' => $billingDocuments,
            'first_asset_address' => $record->assets->sortBy('id')->first()?->address ?: '-',
            'latest_payment' => $latestPayment,
        ];
    }

    private function buildDocumentSummary(array $collections): array
    {
        $requestUploadsCount = count($collections['request_upload_documents'] ?? []);
        $assetSections = collect($collections['asset_sections'] ?? []);
        $assetDocumentsCount = $assetSections->sum(fn ($section) => count($section['documents'] ?? []));
        $assetPhotosCount = $assetSections->sum(fn ($section) => count($section['photos'] ?? []));
        $systemDocuments = $collections['system_documents'] ?? [];
        $legalDocuments = $collections['legal_documents'] ?? [];
        $billingDocuments = $collections['billing_documents'] ?? [];
        $systemDocumentsCount = count($systemDocuments) + count($legalDocuments) + count($billingDocuments);

        return [
            'customer_documents_count' => $requestUploadsCount + $assetDocumentsCount,
            'customer_photos_count' => $assetPhotosCount,
            'system_documents_count' => $systemDocumentsCount,
            'ready_contract' => collect($systemDocuments)->contains(fn ($item) => ($item['type'] ?? null) === 'contract_pdf'),
            'ready_report' => collect($systemDocuments)->contains(fn ($item) => ($item['type'] ?? null) === 'report_pdf'),
            'ready_invoice' => count($billingDocuments) > 0,
            'ready_legal_documents' => count($legalDocuments) === count($this->legalFinalRequestFileTypes()),
            'total_documents_count' => $requestUploadsCount + $assetDocumentsCount + $assetPhotosCount + $systemDocumentsCount,
        ];
    }

    private function mapStoredFilePayload(object $file, ?int $assetId = null, ?string $assetType = null): array
    {
        $url = null;
        if ($file->path && Storage::disk('public')->exists($file->path)) {
            $url = Storage::disk('public')->url($file->path);
        }

        return [
            'id' => $file->id,
            'type' => (string) $file->type,
            'label' => $this->contractDocumentTypeLabel($file->type),
            'original_name' => $file->original_name ?: basename((string) $file->path),
            'mime' => $file->mime,
            'size' => (int) ($file->size ?? 0),
            'created_at' => $file->created_at?->toDateTimeString(),
            'url' => $url,
            'path' => $file->path,
            'asset_id' => $assetId,
            'asset_type' => $assetType,
        ];
    }

    private function buildSystemDocumentEntries(AppraisalRequest $record, array $requestFiles, mixed $latestPayment): array
    {
        $status = $record->status?->value ?? (string) $record->status;
        $entries = [];
        $signedContract = collect($requestFiles)->firstWhere('type', 'contract_signed_pdf');

        if ($this->isContractAccessibleStatus($status)) {
            $entries[] = [
                'id' => 'contract-' . $record->id,
                'type' => 'contract_pdf',
                'label' => 'Kontrak',
                'original_name' => $signedContract['original_name'] ?? ('Kontrak-' . ($record->request_number ?? $record->id) . '.pdf'),
                'mime' => 'application/pdf',
                'size' => (int) ($signedContract['size'] ?? 0),
                'created_at' => $signedContract['created_at'] ?? optional($record->contract_date)->toDateString(),
                'url' => route('appraisal.contract.pdf', ['id' => $record->id]),
                'path' => $signedContract['path'] ?? null,
                'asset_id' => null,
                'asset_type' => null,
            ];
        }

        if (
            $record->report_generated_at
            && $record->report_pdf_path
            && Storage::disk('public')->exists($record->report_pdf_path)
        ) {
            $entries[] = [
                'id' => 'report-' . $record->id,
                'type' => 'report_pdf',
                'label' => 'Laporan Kajian Pasar',
                'original_name' => basename((string) $record->report_pdf_path),
                'mime' => 'application/pdf',
                'size' => (int) ($record->report_pdf_size ?? 0),
                'created_at' => optional($record->report_generated_at)->toDateTimeString(),
                'url' => Storage::disk('public')->url($record->report_pdf_path),
                'path' => $record->report_pdf_path,
                'asset_id' => null,
                'asset_type' => null,
            ];
        }

        return $entries;
    }

    private function buildBillingDocumentEntries(AppraisalRequest $record, mixed $latestPayment): array
    {
        if (! $latestPayment || $latestPayment->status !== 'paid') {
            return [];
        }

        $invoiceNumber = data_get($latestPayment->metadata, 'invoice_number');
        if (! filled($invoiceNumber)) {
            $invoiceNumber = 'INV-' . now()->format('Y') . '-' . str_pad((string) $latestPayment->id, 5, '0', STR_PAD_LEFT);
        }

        return [[
            'id' => 'invoice-' . $record->id,
            'type' => 'invoice_pdf',
            'label' => 'Invoice Pembayaran',
            'original_name' => $invoiceNumber . '.pdf',
            'mime' => 'application/pdf',
            'size' => 0,
            'created_at' => optional($latestPayment->paid_at)->toDateTimeString(),
            'url' => route('appraisal.invoice.pdf', ['id' => $record->id]),
            'path' => null,
            'asset_id' => null,
            'asset_type' => null,
        ]];
    }

    private function customerRequestFileTypes(): array
    {
        return ['npwp', 'representative', 'permission', 'other_request_document'];
    }

    private function legalFinalRequestFileTypes(): array
    {
        return ['agreement_pdf', 'disclaimer_pdf', 'representative_letter_pdf'];
    }

    private function isContractAccessibleStatus(string $status): bool
    {
        return in_array($status, [
            AppraisalStatusEnum::WaitingSignature->value,
            AppraisalStatusEnum::ContractSigned->value,
            AppraisalStatusEnum::ValuationOnProgress->value,
            AppraisalStatusEnum::ValuationCompleted->value,
            AppraisalStatusEnum::PreviewReady->value,
            AppraisalStatusEnum::ReportPreparation->value,
            AppraisalStatusEnum::ReportReady->value,
            AppraisalStatusEnum::Completed->value,
        ], true);
    }

    private function buildPreviewStatePayload(AppraisalRequest $record): array
    {
        $snapshot = is_array($record->market_preview_snapshot) ? $record->market_preview_snapshot : null;
        $assets = collect($snapshot['assets'] ?? [])->map(function (array $asset): array {
            return [
                'asset_id' => $asset['asset_id'] ?? null,
                'asset_type' => $asset['asset_type'] ?? null,
                'asset_type_label' => $asset['asset_type_label'] ?? $this->assetTypeLegacyLabel($asset['asset_type'] ?? null),
                'address' => $asset['address'] ?? '-',
                'land_area' => $asset['land_area'] ?? null,
                'building_area' => $asset['building_area'] ?? null,
                'estimated_value_low' => $asset['estimated_value_low'] ?? null,
                'market_value_final' => $asset['market_value_final'] ?? null,
                'estimated_value_high' => $asset['estimated_value_high'] ?? null,
            ];
        })->values()->all();

        return [
            'has_preview' => $snapshot !== null,
            'status' => $record->status?->value ?? (string) $record->status,
            'version' => (int) ($record->market_preview_version ?? ($snapshot['version'] ?? 0)),
            'published_at' => optional($record->market_preview_published_at)->toDateTimeString()
                ?: ($snapshot['published_at'] ?? null),
            'approved_at' => optional($record->market_preview_approved_at)->toDateTimeString(),
            'appeal_count' => (int) ($record->market_preview_appeal_count ?? 0),
            'appeal_reason' => $record->market_preview_appeal_reason,
            'appeal_submitted_at' => optional($record->market_preview_appeal_submitted_at)->toDateTimeString(),
            'appeal_remaining' => max(0, 1 - (int) ($record->market_preview_appeal_count ?? 0)),
            'summary' => [
                'estimated_value_low' => $snapshot['summary']['estimated_value_low'] ?? null,
                'market_value_final' => $snapshot['summary']['market_value_final'] ?? null,
                'estimated_value_high' => $snapshot['summary']['estimated_value_high'] ?? null,
                'assets_count' => $snapshot['summary']['assets_count'] ?? count($assets),
            ],
            'assets' => $assets,
            'page_url' => $snapshot !== null
                ? route('appraisal.market-preview.page', ['id' => $record->id])
                : null,
        ];
    }

    private function assetAreaBasisForContract(AppraisalAsset $asset): string
    {
        $landArea = is_numeric($asset->land_area) ? (float) $asset->land_area : null;
        $buildingArea = is_numeric($asset->building_area) ? (float) $asset->building_area : null;

        if ($landArea === null && $buildingArea === null) {
            return '-';
        }

        if ($landArea !== null && $buildingArea !== null) {
            return sprintf('DOC - LT %.2f m2 | LB %.2f m2', $landArea, $buildingArea);
        }

        if ($landArea !== null) {
            return sprintf('DOC - LT %.2f m2', $landArea);
        }

        return sprintf('DOC - LB %.2f m2', $buildingArea);
    }

    private function assetNoteForContract(AppraisalAsset $asset): string
    {
        $hasBuilding = is_numeric($asset->building_area) && (float) $asset->building_area > 0;
        return $hasBuilding ? 'Tanah dan bangunan' : 'Tanah/lahan';
    }

    private function buildStatusTimeline(AppraisalRequest $record): array
    {
        $entries = [];
        $requestNumber = $record->request_number ?? ('REQ-' . $record->id);

        $append = function (
            string $key,
            string $title,
            string $description,
            mixed $at,
            string $type = 'default'
        ) use (&$entries): void {
            $time = $this->timelineDateTimeString($at);
            if ($time === null) {
                return;
            }

            $entries[] = [
                'key' => $key,
                'title' => $title,
                'description' => $description,
                'at' => $time,
                'type' => $type,
            ];
        };

        $append(
            'request_submitted',
            'Permohonan Dikirim',
            "Permohonan {$requestNumber} berhasil dikirim.",
            $record->requested_at ?? $record->created_at,
            'submitted'
        );

        if ($record->verified_at) {
            $append(
                'docs_verified',
                'Dokumen Diverifikasi',
                'Dokumen awal telah diverifikasi oleh admin.',
                $record->verified_at,
                'success'
            );
        }

        $record->offerNegotiations
            ->sortBy('created_at')
            ->each(function ($item) use ($append): void {
                $action = (string) $item->action;
                $offeredFee = is_numeric($item->offered_fee) ? $this->formatRupiah((int) $item->offered_fee) : null;
                $expectedFee = is_numeric($item->expected_fee) ? $this->formatRupiah((int) $item->expected_fee) : null;

                if ($action === 'offer_sent') {
                    $append(
                        "offer_sent_{$item->id}",
                        'Penawaran Dikirim',
                        $offeredFee ? "Admin mengirim penawaran fee {$offeredFee}." : 'Admin mengirim penawaran.',
                        $item->created_at,
                        'offer'
                    );
                    return;
                }

                if ($action === 'offer_revised') {
                    $append(
                        "offer_revised_{$item->id}",
                        'Penawaran Direvisi',
                        $offeredFee ? "Admin mengirim revisi penawaran fee {$offeredFee}." : 'Admin mengirim revisi penawaran.',
                        $item->created_at,
                        'offer'
                    );
                    return;
                }

                if ($action === 'counter_request') {
                    $append(
                        "counter_request_{$item->id}",
                        'Negosiasi Diajukan',
                        $expectedFee
                            ? "Anda mengajukan negosiasi fee {$expectedFee}."
                            : 'Anda mengajukan negosiasi penawaran.',
                        $item->created_at,
                        'warning'
                    );
                    return;
                }

                if ($action === 'accept_offer') {
                    $selectedFee = is_numeric($item->selected_fee)
                        ? $this->formatRupiah((int) $item->selected_fee)
                        : $offeredFee;

                    $append(
                        "accept_offer_{$item->id}",
                        'Penawaran Disetujui',
                        $selectedFee ? "Anda menyetujui penawaran fee {$selectedFee}." : 'Anda menyetujui penawaran.',
                        $item->created_at,
                        'success'
                    );
                    return;
                }

                if ($action === 'accepted') {
                    $selectedFee = is_numeric($item->selected_fee)
                        ? $this->formatRupiah((int) $item->selected_fee)
                        : $expectedFee;

                    $append(
                        "accepted_{$item->id}",
                        'Negosiasi Disetujui Admin',
                        $selectedFee
                            ? "Admin menyetujui fee hasil negosiasi sebesar {$selectedFee} dan request masuk ke tahap tanda tangan kontrak."
                            : 'Admin menyetujui hasil negosiasi dan request masuk ke tahap tanda tangan kontrak.',
                        $item->created_at,
                        'success'
                    );
                    return;
                }

                if ($action === 'contract_sign_mock') {
                    $append(
                        "contract_signed_{$item->id}",
                        'Kontrak Ditandatangani',
                        'Kontrak telah ditandatangani secara digital.',
                        $item->created_at,
                        'success'
                    );
                    return;
                }

                if ($action === 'cancel_request') {
                    $append(
                        "request_cancelled_{$item->id}",
                        'Permohonan Dibatalkan',
                        'Permohonan dibatalkan oleh user.',
                        $item->created_at,
                        'danger'
                    );
                    return;
                }

                if ($action === 'cancelled' && data_get($item->meta, 'flow') === 'admin_request_cancelled') {
                    $description = 'Permohonan dibatalkan oleh sistem.';
                    if (filled($item->reason)) {
                        $description .= ' Alasan: ' . $item->reason;
                    }

                    $append(
                        "admin_request_cancelled_{$item->id}",
                        'Permohonan Dibatalkan Sistem',
                        $description,
                        $item->created_at,
                        'danger'
                    );
                }
            });

        $latestPayment = $record->payments->sortByDesc('id')->first();
        if ($latestPayment) {
            if ($latestPayment->method === 'gateway' && $latestPayment->status === 'pending') {
                $append(
                    'payment_session_created',
                    'Sesi Pembayaran Dibuat',
                    'Session pembayaran Midtrans sudah dibuat dan menunggu penyelesaian pembayaran.',
                    data_get($latestPayment->metadata, 'checkout.created_at') ?? $latestPayment->created_at,
                    'payment'
                );
            }

            if ($latestPayment->status === 'paid') {
                $append(
                    'payment_verified',
                    'Pembayaran Terkonfirmasi',
                    'Pembayaran berhasil dikonfirmasi Midtrans. Proses penilaian dimulai.',
                    $latestPayment->paid_at ?? $latestPayment->updated_at,
                    'success'
                );
            }

            if ($latestPayment->status === 'rejected') {
                $append(
                    'payment_rejected',
                    'Pembayaran Ditolak',
                    'Transaksi pembayaran ditolak oleh gateway atau tidak dapat diproses.',
                    data_get($latestPayment->metadata, 'admin_rejected_at') ?? $latestPayment->updated_at,
                    'danger'
                );
            }

            if ($latestPayment->status === 'failed') {
                $append(
                    'payment_failed',
                    'Pembayaran Gagal',
                    'Transaksi pembayaran tidak berhasil diproses. Anda dapat membuat sesi pembayaran baru.',
                    data_get($latestPayment->metadata, 'last_webhook_received_at') ?? $latestPayment->updated_at,
                    'danger'
                );
            }

            if ($latestPayment->status === 'expired') {
                $append(
                    'payment_expired',
                    'Pembayaran Kedaluwarsa',
                    'Sesi pembayaran telah kedaluwarsa. Anda dapat membuat sesi pembayaran baru.',
                    data_get($latestPayment->metadata, 'last_expired_at')
                        ?? data_get($latestPayment->metadata, 'gateway_details.expiry_time')
                        ?? $latestPayment->updated_at,
                    'warning'
                );
            }
        }

        if ($record->report_generated_at) {
            $append(
                'report_ready',
                'Laporan Siap',
                'Laporan kajian pasar sudah tersedia untuk diunduh.',
                $record->report_generated_at,
                'success'
            );
        }

        if ($record->market_preview_published_at) {
            $append(
                'market_preview_published',
                'Preview Kajian Dipublikasikan',
                'Customer dapat meninjau hasil kajian pasar dalam bentuk range sebelum laporan final disiapkan.',
                $record->market_preview_published_at,
                'info'
            );
        }

        if ($record->market_preview_appeal_submitted_at) {
            $append(
                'market_preview_appeal',
                'Banding Diajukan',
                'Customer menggunakan kesempatan banding dan meminta reviewer memperbarui hasil preview.',
                $record->market_preview_appeal_submitted_at,
                'warning'
            );
        }

        if ($record->market_preview_approved_at) {
            $append(
                'market_preview_approved',
                'Preview Disetujui Customer',
                'Customer menyetujui preview hasil kajian pasar dan request masuk ke tahap persiapan laporan final.',
                $record->market_preview_approved_at,
                'success'
            );
        }

        if ($record->report_draft_generated_at) {
            $append(
                'report_preparation',
                'Admin Menyiapkan Laporan Final',
                'Draft laporan sudah disiapkan. Admin akan melengkapi QR/barcode P2PK/ELSA dan tanda tangan sebelum upload final.',
                $record->report_draft_generated_at,
                'info'
            );
        }

        if (($record->status?->value ?? $record->status) === AppraisalStatusEnum::Completed->value) {
            $append(
                'request_completed',
                'Permohonan Selesai',
                'Seluruh proses penilaian telah selesai.',
                $record->updated_at,
                'success'
            );
        }

        if (($record->status?->value ?? $record->status) === AppraisalStatusEnum::Cancelled->value) {
            $description = 'Permohonan berada pada status dibatalkan.';
            if (filled($record->cancellation_reason)) {
                $description .= ' Alasan: ' . $record->cancellation_reason;
            }

            $append(
                'request_cancelled_status',
                'Status Dibatalkan',
                $description,
                $record->cancelled_at ?? $record->updated_at,
                'danger'
            );
        }

        usort($entries, function (array $a, array $b): int {
            $left = strtotime((string) ($a['at'] ?? '')) ?: 0;
            $right = strtotime((string) ($b['at'] ?? '')) ?: 0;
            return $left <=> $right;
        });

        return array_values($entries);
    }

    private function timelineDateTimeString(mixed $value): ?string
    {
        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d H:i:s');
        }

        if (is_string($value) && trim($value) !== '') {
            $ts = strtotime($value);
            return $ts ? date('Y-m-d H:i:s', $ts) : trim($value);
        }

        return null;
    }

    private function formatRupiah(int $amount): string
    {
        return 'Rp ' . number_format($amount, 0, ',', '.');
    }
}

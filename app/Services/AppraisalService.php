<?php

namespace App\Services;

use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Storage;
use App\Support\EnumPresenter;
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

/**
 * Builds appraisal UI payloads and handles consent flows for users.
 */
class AppraisalService
{
    use EnumPresenter;

    public function buildIndexPayload(int $userId, string $q, string $status): array
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
            ->paginate(10)
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
            'needsConsent' => $needsConsent,
            'consentData' => $consentData,
            'uploadLimits' => [
                'maxFileUploads' => $maxFileUploads > 0 ? $maxFileUploads : null,
                'uploadMaxFilesize' => ini_get('upload_max_filesize'),
                'postMaxSize' => ini_get('post_max_size'),
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
                'assets:id,appraisal_request_id,asset_type,land_area,building_area,building_floors,renovation_year,address,coordinates_lat,coordinates_lng,province_id,regency_id,district_id,village_id',
                'assets.files:id,appraisal_asset_id,type,path,original_name,mime,size,created_at',
                'offerNegotiations:id,appraisal_request_id,user_id,action,round,offered_fee,expected_fee,selected_fee,reason,meta,created_at',
                'offerNegotiations.user:id,name',
                'payments:id,appraisal_request_id,status,paid_at,proof_file_path,proof_original_name,metadata,updated_at,created_at',
            ])
            ->findOrFail($id);

        $reportTypeEnum = $this->asEnum(ReportTypeEnum::class, $r->report_type);
        $statusEnum = $this->asEnum(AppraisalStatusEnum::class, $r->status);
        $contractStatusEnum = $this->asEnum(ContractStatusEnum::class, $r->contract_status);

        $reportTypeValue = $reportTypeEnum?->value ?? $this->enumValue($r->report_type);
        $statusValue = $statusEnum?->value ?? $this->enumValue($r->status);
        $contractStatusValue = $contractStatusEnum?->value ?? $this->enumValue($r->contract_status);

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
                'renovation_year' => $a->renovation_year,
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

        $documents = $r->assets
            ->flatMap(function ($asset) {
                return $asset->files->map(function ($f) use ($asset) {
                    $url = null;
                    if ($f->path && Storage::disk('public')->exists($f->path)) {
                        $url = Storage::disk('public')->url($f->path);
                    }

                    return [
                        'id' => $f->id,
                        'type' => $f->type,
                        'original_name' => $f->original_name,
                        'mime' => $f->mime,
                        'size' => $f->size,
                        'created_at' => $f->created_at?->toDateTimeString(),
                        'url' => $url,
                        'path' => $f->path,
                        'asset_id' => $asset->id,
                        'asset_type' => $asset->asset_type,
                    ];
                });
            })
            ->values();

        $firstAddress = $assets->first()['address'] ?? '-';

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
        $paymentStatus = $latestPayment?->status;
        $paymentStatusLabel = match ($paymentStatus) {
            'paid' => 'Dibayar',
            'failed' => 'Gagal',
            'rejected' => 'Ditolak',
            'refunded' => 'Refund',
            default => $latestPayment && filled($latestPayment->proof_file_path)
                ? 'Menunggu Verifikasi'
                : 'Menunggu Pembayaran',
        };
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

                'report_generated_at' => optional($r->report_generated_at)->toDateTimeString(),
                'report_pdf_path' => $r->report_pdf_path,
                'report_pdf_url' => $reportPdfUrl,
                'contract_document' => $contractDocument,
                'status_timeline' => $statusTimeline,
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
            'payment_methods' => 'Transfer bank ke rekening resmi DigiPro.',
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
            'doc_certs' => 'Sertifikat',
            'doc_pbb' => 'PBB',
            'doc_imb' => 'IMB/PBG',
            'doc_old_report' => 'Laporan Lama',
            'npwp' => 'NPWP',
            'representative' => 'Surat Kuasa',
            'permission' => 'Surat Izin',
            default => $this->headlineOrDash((string) $type),
        };
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
                }
            });

        $latestPayment = $record->payments->sortByDesc('id')->first();
        if ($latestPayment) {
            $paymentSubmittedAt = data_get($latestPayment->metadata, 'submitted_at') ?? $latestPayment->updated_at;
            if (filled($latestPayment->proof_file_path)) {
                $append(
                    'payment_submitted',
                    'Bukti Pembayaran Diunggah',
                    'Bukti pembayaran berhasil diunggah dan menunggu verifikasi admin.',
                    $paymentSubmittedAt,
                    'payment'
                );
            }

            if ($latestPayment->status === 'paid') {
                $append(
                    'payment_verified',
                    'Pembayaran Terkonfirmasi',
                    'Pembayaran telah dikonfirmasi admin. Proses penilaian dimulai.',
                    $latestPayment->paid_at ?? $latestPayment->updated_at,
                    'success'
                );
            }

            if ($latestPayment->status === 'rejected') {
                $reason = trim((string) data_get($latestPayment->metadata, 'admin_rejected_reason', ''));
                $append(
                    'payment_rejected',
                    'Bukti Pembayaran Ditolak',
                    $reason !== ''
                        ? "Admin menolak bukti pembayaran. Alasan: {$reason}"
                        : 'Admin menolak bukti pembayaran. Silakan unggah ulang bukti yang valid.',
                    data_get($latestPayment->metadata, 'admin_rejected_at') ?? $latestPayment->updated_at,
                    'danger'
                );
            }
        }

        if ($record->report_generated_at) {
            $append(
                'report_ready',
                'Laporan Siap',
                'Laporan penilaian sudah tersedia untuk diunduh.',
                $record->report_generated_at,
                'success'
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
            $append(
                'request_cancelled_status',
                'Status Dibatalkan',
                'Permohonan berada pada status dibatalkan.',
                $record->updated_at,
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

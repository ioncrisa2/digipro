<?php

namespace App\Services\Customer\Payloads;

use App\Models\AppraisalAsset;
use App\Models\AppraisalRequest;
use Illuminate\Support\Facades\Storage;

class AppraisalContractDocumentBuilder
{
    public function __construct(
        private readonly AppraisalPayloadFormatter $formatter,
    ) {
    }

    public function build(AppraisalRequest $record): array
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
                    ->filter(fn ($file) => ! $this->formatter->isPhotoFileType($file->type))
                    ->map(fn ($file) => $this->formatter->contractDocumentTypeLabel($file->type))
                    ->filter()
                    ->unique()
                    ->values()
                    ->all();

                return [
                    'no' => $index + 1,
                    'label' => $this->formatter->assetTypeLabelForContract($this->formatter->enumBackedValue(\App\Enums\AssetTypeEnum::class, $asset->asset_type)),
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
}

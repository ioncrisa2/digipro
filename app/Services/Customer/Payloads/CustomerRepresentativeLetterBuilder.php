<?php

namespace App\Services\Customer\Payloads;

use App\Models\AppraisalAsset;
use App\Models\AppraisalRequest;

class CustomerRepresentativeLetterBuilder
{
    public function __construct(
        private readonly AppraisalPayloadFormatter $formatter,
    ) {
    }

    public function build(AppraisalRequest $record, array $signatureMeta = []): array
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
                    'type_label' => $this->formatter->assetTypeLabelForContract($this->formatter->enumBackedValue(\App\Enums\AssetTypeEnum::class, $asset->asset_type)),
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
}

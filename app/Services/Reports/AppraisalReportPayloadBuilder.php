<?php

namespace App\Services\Reports;

use App\Enums\AssetTypeEnum;
use App\Models\AppraisalAsset;
use App\Models\AppraisalRequest;
use App\Services\Revisions\AppraisalRevisionFileResolver;
use App\Support\AppraisalAssetFieldOptions;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class AppraisalReportPayloadBuilder
{
    public function __construct(
        private readonly AppraisalRevisionFileResolver $fileResolver
    ) {
    }

    public function build(AppraisalRequest $record): array
    {
        $record->loadMissing([
            'user:id,name,email',
            'guidelineSet:id,name,year',
            'assets',
            'assets.files',
            'files',
        ]);

        $snapshot = is_array($record->market_preview_snapshot) ? $record->market_preview_snapshot : [];
        $snapshotAssets = collect($snapshot['assets'] ?? [])
            ->keyBy(fn (array $asset) => (int) ($asset['asset_id'] ?? 0));
        $approvedItems = $this->fileResolver->approvedItemsForRequest($record);
        $activeAssetFiles = $this->fileResolver->activeAssetFilesByRequest($record, $approvedItems);
        $activeRequestFiles = $this->fileResolver->activeRequestFiles($record, $approvedItems);

        $assets = $record->assets
            ->sortBy('id')
            ->values()
            ->map(function (AppraisalAsset $asset, int $index) use ($snapshotAssets, $activeAssetFiles): array {
                $snapshotAsset = $snapshotAssets->get((int) $asset->id, []);
                $files = collect($activeAssetFiles[$asset->id] ?? []);
                $photoFiles = $files->whereIn('type', ['photo_access_road', 'photo_front', 'photo_interior'])->values();
                $documentFiles = $files->whereIn('type', ['doc_pbb', 'doc_imb', 'doc_certs'])->values();
                $hasBuilding = is_numeric($asset->building_area) && (float) $asset->building_area > 0;
                $assetTypeEnum = AssetTypeEnum::tryFrom((string) $asset->asset_type);

                return [
                    'no' => $index + 1,
                    'asset_id' => $asset->id,
                    'asset_type_label' => $assetTypeEnum?->label() ?? Str::headline((string) $asset->asset_type),
                    'asset_narrative_label' => $hasBuilding ? 'Tanah dan Bangunan' : 'Tanah',
                    'address' => $asset->address ?: '-',
                    'maps_link' => $asset->maps_link,
                    'coordinates' => collect([
                        $asset->coordinates_lat !== null ? 'Lat ' . $asset->coordinates_lat : null,
                        $asset->coordinates_lng !== null ? 'Lng ' . $asset->coordinates_lng : null,
                    ])->filter()->implode(' · '),
                    'usage_label' => $this->optionLabel('usage', $asset->peruntukan),
                    'land_characteristics' => array_values(array_filter([
                        $this->attributeRow('Jenis Hak / Sertifikat', $this->optionLabel('title_document', $asset->title_document)),
                        $this->attributeRow('Nomor Sertifikat', $asset->certificate_number),
                        $this->attributeRow('Pemegang Hak', $asset->certificate_holder_name),
                        $this->attributeRow('Tanggal Terbit', optional($asset->certificate_issued_at)?->format('d-m-Y')),
                        $this->attributeRow('Tanggal Buku Tanah', optional($asset->land_book_date)?->format('d-m-Y')),
                        $this->attributeRow('Luas Menurut Dokumen', $this->formatArea($asset->document_land_area)),
                        $this->attributeRow('Peruntukan', $this->optionLabel('usage', $asset->peruntukan)),
                        $this->attributeRow('Bentuk Tanah', $this->optionLabel('land_shape', $asset->land_shape)),
                        $this->attributeRow('Posisi Tanah', $this->optionLabel('land_position', $asset->land_position)),
                        $this->attributeRow('Kondisi Tanah', $this->optionLabel('land_condition', $asset->land_condition)),
                        $this->attributeRow('Topografi', $this->optionLabel('topography', $asset->topography)),
                        $this->attributeRow('Lebar Muka', $this->formatMeter($asset->frontage_width)),
                        $this->attributeRow('Lebar Akses Jalan', $this->formatMeter($asset->access_road_width)),
                    ])),
                    'building_characteristics' => $hasBuilding
                        ? array_values(array_filter([
                            $this->attributeRow('Luas Bangunan', $this->formatArea($asset->building_area)),
                            $this->attributeRow('Jumlah Lantai', $asset->building_floors),
                            $this->attributeRow('Tahun Bangun', $asset->build_year),
                            $this->attributeRow('Tahun Renovasi', $asset->renovation_year),
                        ]))
                        : [],
                    'legal_notes' => $asset->legal_notes,
                    'valuation' => [
                        'estimated_value_low' => $snapshotAsset['estimated_value_low'] ?? $asset->estimated_value_low,
                        'estimated_value_high' => $snapshotAsset['estimated_value_high'] ?? $asset->estimated_value_high,
                    ],
                    'land_area' => $asset->land_area,
                    'building_area' => $asset->building_area,
                    'supporting_documents' => $documentFiles
                        ->map(fn ($file) => [
                            'label' => $this->assetDocumentLabel((string) $file->type),
                            'original_name' => $file->original_name ?: basename((string) $file->path),
                            'created_at' => optional($file->created_at)->format('d-m-Y H:i'),
                        ])
                        ->values()
                        ->all(),
                    'photos' => $photoFiles
                        ->map(fn ($file) => [
                            'label' => $this->assetDocumentLabel((string) $file->type),
                            'image_data_uri' => $this->toImageDataUri((string) $file->path, (string) ($file->mime ?? '')),
                        ])
                        ->filter(fn (array $photo) => filled($photo['image_data_uri']))
                        ->values()
                        ->all(),
                ];
            })
            ->all();

        $summary = [
            'estimated_value_low' => (int) data_get($snapshot, 'summary.estimated_value_low', collect($assets)->sum(fn (array $asset) => (int) data_get($asset, 'valuation.estimated_value_low', 0))),
            'estimated_value_high' => (int) data_get($snapshot, 'summary.estimated_value_high', collect($assets)->sum(fn (array $asset) => (int) data_get($asset, 'valuation.estimated_value_high', 0))),
            'assets_count' => count($assets),
        ];

        $firstAsset = $assets[0] ?? null;
        $signerSnapshot = is_array($record->report_signer_snapshot) ? $record->report_signer_snapshot : [];

        return [
            'title' => 'LAPORAN KAJIAN PASAR PROPERTI DALAM BENTUK RANGE',
            'subtitle' => 'Estimasi rentang harga properti berbasis data, dokumen, foto, dan kajian pasar digital DigiPro by KJPP HJAR',
            'request_number' => $record->request_number ?? ('REQ-' . $record->id),
            'client_name' => $record->client_name ?: ($record->user?->name ?? '-'),
            'prepared_for' => $record->client_name ?: ($record->user?->name ?? '-'),
            'contract_number' => $record->contract_number ?: '-',
            'report_type_label' => $record->report_type?->label() ?? '-',
            'valuation_objective_label' => $record->valuation_objective?->label() ?? 'Kajian Nilai Pasar dalam Bentuk Range',
            'guideline_name' => $record->guidelineSet?->name ?? '-',
            'generated_at' => now()->toDateTimeString(),
            'preview_version' => (int) ($record->market_preview_version ?? 1),
            'request_date' => optional($record->requested_at)->format('d F Y'),
            'valuation_date' => optional($record->market_preview_published_at)->format('d F Y')
                ?? optional($record->updated_at)->format('d F Y')
                ?? now()->format('d F Y'),
            'property_summary' => [
                'primary_asset_type' => $firstAsset['asset_narrative_label'] ?? 'Properti',
                'primary_address' => $firstAsset['address'] ?? '-',
                'asset_count' => count($assets),
            ],
            'summary' => $summary,
            'cover_range_text' => $this->rangeText($summary['estimated_value_low'], $summary['estimated_value_high']),
            'scope_points' => [
                'Kajian dilakukan terhadap data objek, foto, dokumen legalitas, dan informasi digital yang tersedia di sistem DigiPro by KJPP HJAR.',
                'Pendekatan kajian menggunakan data pembanding pasar yang relevan untuk menghasilkan estimasi bawah dan estimasi atas.',
                'Laporan ini disusun untuk tujuan kajian pasar dalam bentuk range dan bukan opini nilai tunggal penilaian formal.',
            ],
            'assumptions' => [
                'Kebenaran data, foto, dan dokumen yang diunggah menjadi tanggung jawab pihak yang menyerahkan data.',
                'Kajian pasar ini tidak dimaksudkan sebagai dasar tunggal untuk agunan, perpajakan, laporan keuangan, atau transaksi yang memerlukan penilaian formal.',
                'Perubahan kondisi pasar setelah tanggal penilaian dapat memengaruhi rentang hasil kajian.',
            ],
            'statement_points' => [
                'Laporan disusun secara independen berdasarkan data yang tersedia pada saat kajian dilakukan.',
                'Tidak terdapat konflik kepentingan terhadap objek properti yang dikaji dalam lingkup layanan DigiPro by KJPP HJAR ini.',
                'Rentang hasil kajian ditampilkan pada level request dan per aset untuk membantu pengguna memahami posisi estimasi pasar saat ini.',
            ],
            'methodology_points' => [
                'Identifikasi karakteristik objek properti dan dokumen pendukung.',
                'Pemilihan dan penyesuaian pembanding pasar yang relevan.',
                'Penyusunan estimasi bawah dan estimasi atas berdasarkan hasil kajian pasar.',
            ],
            'request_supporting_documents' => $activeRequestFiles
                ->map(fn ($file) => [
                    'label' => $this->requestDocumentLabel((string) $file->type),
                    'original_name' => $file->original_name ?: basename((string) $file->path),
                    'created_at' => optional($file->created_at)->format('d-m-Y H:i'),
                ])
                ->values()
                ->all(),
            'assets' => $assets,
            'signers' => [
                'reviewer' => $signerSnapshot['reviewer'] ?? null,
                'public_appraiser' => $signerSnapshot['public_appraiser'] ?? null,
            ],
        ];
    }

    private function attributeRow(string $label, mixed $value): ?array
    {
        if (blank($value) && $value !== 0 && $value !== '0') {
            return null;
        }

        return [
            'label' => $label,
            'value' => (string) $value,
        ];
    }

    private function formatArea(mixed $value): ?string
    {
        if (! is_numeric($value)) {
            return null;
        }

        return number_format((float) $value, 2, ',', '.') . ' m2';
    }

    private function formatMeter(mixed $value): ?string
    {
        if (! is_numeric($value)) {
            return null;
        }

        return number_format((float) $value, 2, ',', '.') . ' m';
    }

    private function optionLabel(string $group, ?string $value): ?string
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

        return $options[$value] ?? Str::headline($value);
    }

    private function requestDocumentLabel(string $type): string
    {
        return match ($type) {
            'npwp' => 'NPWP',
            'representative' => 'Surat Kuasa',
            'permission' => 'Surat Izin',
            'other_request_document' => 'Lampiran Request',
            default => Str::headline($type),
        };
    }

    private function assetDocumentLabel(string $type): string
    {
        return match ($type) {
            'doc_pbb' => 'PBB',
            'doc_imb' => 'IMB / PBG',
            'doc_certs' => 'Sertifikat',
            'photo_access_road' => 'Foto Akses Jalan',
            'photo_front' => 'Foto Depan',
            'photo_interior' => 'Foto Dalam',
            default => Str::headline($type),
        };
    }

    private function toImageDataUri(string $path, string $mime): ?string
    {
        if ($path === '' || ! Storage::disk('public')->exists($path)) {
            return null;
        }

        $normalizedMime = $mime !== '' ? $mime : Storage::disk('public')->mimeType($path);
        if (! is_string($normalizedMime) || ! str_starts_with($normalizedMime, 'image/')) {
            return null;
        }

        $content = Storage::disk('public')->get($path);

        return 'data:' . $normalizedMime . ';base64,' . base64_encode($content);
    }

    private function rangeText(int $low, int $high): string
    {
        return 'Estimasi rentang nilai pasar berada pada kisaran Rp '
            . number_format($low, 0, ',', '.')
            . ' sampai dengan Rp '
            . number_format($high, 0, ',', '.')
            . '.';
    }
}

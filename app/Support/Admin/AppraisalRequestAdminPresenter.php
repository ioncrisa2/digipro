<?php

namespace App\Support\Admin;

use App\Enums\AssetTypeEnum;
use App\Models\AppraisalAsset;
use App\Models\AppraisalRequest;
use App\Models\District;
use App\Models\Province;
use App\Models\Regency;
use App\Models\Village;
use App\Support\AppraisalAssetFieldOptions;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class AppraisalRequestAdminPresenter
{
    public function requestTableRow(AppraisalRequest $record): array
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
        ];
    }

    public function formatNegotiationAction(?string $action): string
    {
        return match ($action) {
            'offer_sent' => 'Penawaran dikirim',
            'offer_revised' => 'Counter offer dikirim',
            'counter_request' => 'Pengajuan negosiasi',
            'selected' => 'Fee dipilih',
            'accept_offer', 'accepted' => 'Penawaran diterima',
            'contract_sign_mock' => 'Tanda tangan kontrak',
            'cancel_request' => 'Permohonan dibatalkan',
            'cancelled' => 'Permohonan dibatalkan sistem',
            default => Str::headline((string) $action),
        };
    }

    public function negotiationActionTone(?string $action): string
    {
        return match ((string) $action) {
            'counter_request' => 'warning',
            'accept_offer', 'accepted', 'contract_sign_mock' => 'success',
            'cancel_request', 'cancelled' => 'danger',
            'offer_sent', 'offer_revised' => 'info',
            default => 'default',
        };
    }

    public function buildLocationMaps(AppraisalRequest $appraisalRequest): array
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

    public function requestFile(object $file): array
    {
        return [
            'id' => $file->id,
            'type' => (string) $file->type,
            'type_label' => $this->requestFileTypeLabel($file->type),
            'original_name' => $file->original_name ?: basename((string) $file->path),
            'mime' => $file->mime,
            'size' => (int) ($file->size ?? 0),
            'size_label' => $this->formatBytes($file->size),
            'url' => Storage::disk('public')->url($file->path),
            'created_at' => $file->created_at?->toIso8601String(),
        ];
    }

    public function asset(
        AppraisalAsset $asset,
        int $order,
        array $locationMaps,
        ?Collection $activeFiles = null
    ): array {
        $files = ($activeFiles ?? $asset->files)->sortByDesc('created_at')->values();

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
            'certificate_number' => $asset->certificate_number,
            'certificate_holder_name' => $asset->certificate_holder_name,
            'certificate_issued_at' => optional($asset->certificate_issued_at)?->toDateString(),
            'land_book_date' => optional($asset->land_book_date)?->toDateString(),
            'document_land_area' => $asset->document_land_area,
            'legal_notes' => $asset->legal_notes,
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
            'documents' => $files
                ->whereIn('type', ['doc_pbb', 'doc_imb', 'doc_certs'])
                ->map(fn ($file) => $this->assetFile($file))
                ->values(),
            'photos' => $files
                ->whereIn('type', ['photo_access_road', 'photo_front', 'photo_interior'])
                ->map(fn ($file) => $this->assetFile($file))
                ->values(),
        ];
    }

    public function revisionBatch(object $batch, AppraisalRequest $appraisalRequest): array
    {
        $assetOrderMap = $appraisalRequest->assets
            ->sortBy('id')
            ->values()
            ->pluck('id')
            ->flip()
            ->map(fn ($index) => $index + 1)
            ->all();

        return [
            'id' => $batch->id,
            'status' => (string) $batch->status,
            'status_label' => $this->revisionBatchStatusLabel($batch->status),
            'created_at' => $batch->created_at?->toIso8601String(),
            'admin_note' => $batch->admin_note,
            'creator_name' => $batch->creator?->name ?? 'Admin',
            'items' => $batch->items
                ->map(fn ($item) => $this->revisionItem($item, $assetOrderMap, (int) $appraisalRequest->id))
                ->values(),
        ];
    }

    public function negotiationActionOptions(AppraisalRequest $appraisalRequest): array
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

    public function negotiationSummary(AppraisalRequest $appraisalRequest): array
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

    private function assetFile(object $file): array
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

    private function revisionItem(object $item, array $assetOrderMap, int $appraisalRequestId): array
    {
        $originalFile = $item->originalRequestFile ?? $item->originalAssetFile;
        $replacementFile = $item->replacementRequestFile ?? $item->replacementAssetFile;
        $assetOrder = $item->appraisalAsset?->id ? ($assetOrderMap[$item->appraisalAsset->id] ?? null) : null;
        $scopeLabel = match ((string) $item->item_type) {
            'request_file' => 'Dokumen Request',
            'asset_document' => 'Dokumen Aset',
            'asset_photo' => 'Foto Aset',
            'asset_field' => 'Data Aset',
            'request_field' => 'Data Request',
            default => Str::headline((string) $item->item_type),
        };
        $targetLabel = $scopeLabel . ': ' . match ((string) $item->item_type) {
            'request_file' => $this->requestFileTypeLabel($item->requested_file_type),
            'asset_document', 'asset_photo' => $this->assetFileTypeLabel($item->requested_file_type),
            'asset_field', 'request_field' => $this->revisionFieldLabel((string) ($item->requested_field_key ?: $item->requested_file_type)),
            default => Str::headline((string) $item->requested_file_type),
        };

        if ($assetOrder !== null) {
            $targetLabel = sprintf('Aset #%d - %s', $assetOrder, $targetLabel);
        }

        return [
            'id' => $item->id,
            'status' => (string) $item->status,
            'status_label' => $this->revisionItemStatusLabel($item->status),
            'item_type' => (string) $item->item_type,
            'requested_file_type' => (string) $item->requested_file_type,
            'target_key' => $this->revisionItemTargetKey($item),
            'target_label' => $targetLabel,
            'asset_address' => $item->appraisalAsset?->address,
            'issue_note' => $item->issue_note,
            'review_note' => $item->review_note,
            'reviewed_at' => $item->reviewed_at?->toIso8601String(),
            'can_approve' => (string) $item->status === 'reuploaded',
            'can_reject' => (string) $item->status === 'reuploaded',
            'field' => in_array((string) $item->item_type, ['asset_field', 'request_field'], true) ? [
                'key' => (string) ($item->requested_field_key ?: $item->requested_file_type),
                'label' => $this->revisionFieldLabel((string) ($item->requested_field_key ?: $item->requested_file_type)),
                'original_value' => $item->original_value,
                'replacement_value' => $item->replacement_value,
            ] : null,
            'approve_url' => (string) $item->status === 'reuploaded'
                ? route('admin.appraisal-requests.revision-items.approve', [
                    'appraisalRequest' => $appraisalRequestId,
                    'revisionItem' => $item->id,
                ])
                : null,
            'reject_url' => (string) $item->status === 'reuploaded'
                ? route('admin.appraisal-requests.revision-items.reject', [
                    'appraisalRequest' => $appraisalRequestId,
                    'revisionItem' => $item->id,
                ])
                : null,
            'original_file' => $this->revisionAttachment($originalFile, $item->originalRequestFile !== null),
            'replacement_file' => $this->revisionAttachment($replacementFile, $item->replacementRequestFile !== null),
        ];
    }

    private function revisionAttachment(?object $file, bool $isRequestFile): ?array
    {
        if ($file === null) {
            return null;
        }

        return [
            'id' => $file->id,
            'original_name' => $file->original_name ?: basename((string) $file->path),
            'url' => Storage::disk('public')->url($file->path),
            'type_label' => $isRequestFile
                ? $this->requestFileTypeLabel($file->type)
                : $this->assetFileTypeLabel($file->type),
            'mime' => $file->mime,
            'size' => (int) ($file->size ?? 0),
            'created_at' => $file->created_at?->toIso8601String(),
        ];
    }

    private function revisionItemTargetKey(object $item): string
    {
        if ($item->original_request_file_id) {
            return "request_file:existing:{$item->original_request_file_id}";
        }

        if ($item->original_asset_file_id) {
            return "{$item->item_type}:existing:{$item->original_asset_file_id}";
        }

        if (in_array((string) $item->item_type, ['asset_field', 'request_field'], true)) {
            if ($item->appraisal_asset_id) {
                return "{$item->item_type}:{$item->appraisal_asset_id}:{$item->requested_field_key}";
            }

            return "{$item->item_type}:{$item->requested_field_key}";
        }

        if ($item->appraisal_asset_id) {
            return "{$item->item_type}:missing:{$item->appraisal_asset_id}:{$item->requested_file_type}";
        }

        return "request_file:missing:{$item->requested_file_type}";
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

        return $options[$value] ?? Str::headline($value);
    }

    private function requestFileTypeLabel(?string $type): string
    {
        return match ((string) $type) {
            'agreement_pdf' => 'Agreement DigiPro',
            'contract_signed_pdf' => 'PDF Kontrak Ditandatangani',
            'disclaimer_pdf' => 'Disclaimer DigiPro',
            'npwp' => 'NPWP',
            'representative' => 'Surat Kuasa',
            'representative_letter_pdf' => 'Surat Representatif DigiPro',
            'permission' => 'Surat Izin',
            'other_request_document' => 'Lampiran Request',
            default => Str::headline((string) $type),
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
            default => Str::headline((string) $type),
        };
    }

    private function revisionBatchStatusLabel(?string $status): string
    {
        return match ((string) $status) {
            'open' => 'Terbuka',
            'submitted' => 'Dikirim Ulang Customer',
            'reviewed' => 'Selesai Direview',
            'cancelled' => 'Dibatalkan',
            default => Str::headline((string) $status),
        };
    }

    private function revisionItemStatusLabel(?string $status): string
    {
        return match ((string) $status) {
            'pending' => 'Menunggu Upload Ulang',
            'reuploaded' => 'Sudah Upload Ulang',
            'approved' => 'Disetujui',
            'rejected' => 'Perlu Revisi Lagi',
            default => Str::headline((string) $status),
        };
    }

    private function revisionFieldLabel(string $fieldKey): string
    {
        return match ($fieldKey) {
            'title_document' => 'Jenis Dokumen Tanah',
            'address' => 'Alamat Lengkap',
            'maps_link' => 'Link Google Maps',
            'coordinates_lat' => 'Latitude',
            'coordinates_lng' => 'Longitude',
            'land_area' => 'Luas Tanah (m2)',
            'building_area' => 'Luas Bangunan (m2)',
            'building_floors' => 'Jumlah Lantai',
            'build_year' => 'Tahun Bangun',
            'renovation_year' => 'Tahun Renovasi',
            default => Str::headline($fieldKey),
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
}

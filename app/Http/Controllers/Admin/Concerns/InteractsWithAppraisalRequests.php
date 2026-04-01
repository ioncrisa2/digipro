<?php

namespace App\Http\Controllers\Admin\Concerns;

use App\Enums\AppraisalStatusEnum;
use App\Enums\AssetTypeEnum;
use App\Models\AppraisalAsset;
use App\Models\AppraisalRequest;
use App\Models\District;
use App\Models\Province;
use App\Models\Regency;
use App\Models\Village;
use App\Services\Admin\AppraisalRequestWorkflowService;
use App\Support\AppraisalAssetFieldOptions;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Storage;

trait InteractsWithAppraisalRequests
{
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
        ];
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
            default => Str::headline((string) $action),
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
            'original_name' => $file->original_name ?: basename((string) $file->path),
            'mime' => $file->mime,
            'size' => (int) ($file->size ?? 0),
            'size_label' => $this->formatBytes($file->size),
            'url' => Storage::disk('public')->url($file->path),
            'created_at' => $file->created_at?->toIso8601String(),
        ];
    }

    private function transformAsset(
        AppraisalAsset $asset,
        int $order,
        array $locationMaps,
        ?Collection $activeFiles = null
    ): array
    {
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
            'original_name' => $file->original_name ?: basename((string) $file->path),
            'mime' => $file->mime,
            'size' => (int) ($file->size ?? 0),
            'size_label' => $this->formatBytes($file->size),
            'url' => Storage::disk('public')->url($file->path),
            'created_at' => $file->created_at?->toIso8601String(),
        ];
    }

    private function transformRevisionBatch(object $batch, AppraisalRequest $appraisalRequest): array
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
                ->map(fn ($item) => $this->transformRevisionItem($item, $assetOrderMap, (int) $appraisalRequest->id))
                ->values(),
        ];
    }

    private function transformRevisionItem(object $item, array $assetOrderMap, int $appraisalRequestId): array
    {
        $originalFile = $item->originalRequestFile ?? $item->originalAssetFile;
        $replacementFile = $item->replacementRequestFile ?? $item->replacementAssetFile;
        $assetOrder = $item->appraisalAsset?->id ? ($assetOrderMap[$item->appraisalAsset->id] ?? null) : null;
        $scopeLabel = match ((string) $item->item_type) {
            'request_file' => 'Dokumen Request',
            'asset_document' => 'Dokumen Aset',
            'asset_photo' => 'Foto Aset',
            default => Str::headline((string) $item->item_type),
        };
        $targetLabel = $scopeLabel . ': ' . match ((string) $item->item_type) {
            'request_file' => $this->requestFileTypeLabel($item->requested_file_type),
            'asset_document', 'asset_photo' => $this->assetFileTypeLabel($item->requested_file_type),
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
            'original_file' => $originalFile ? [
                'id' => $originalFile->id,
                'original_name' => $originalFile->original_name ?: basename((string) $originalFile->path),
                'url' => Storage::disk('public')->url($originalFile->path),
                'type_label' => $item->originalRequestFile
                    ? $this->requestFileTypeLabel($originalFile->type)
                    : $this->assetFileTypeLabel($originalFile->type),
                'mime' => $originalFile->mime,
                'size' => (int) ($originalFile->size ?? 0),
                'created_at' => $originalFile->created_at?->toIso8601String(),
            ] : null,
            'replacement_file' => $replacementFile ? [
                'id' => $replacementFile->id,
                'original_name' => $replacementFile->original_name ?: basename((string) $replacementFile->path),
                'url' => Storage::disk('public')->url($replacementFile->path),
                'type_label' => $item->replacementRequestFile
                    ? $this->requestFileTypeLabel($replacementFile->type)
                    : $this->assetFileTypeLabel($replacementFile->type),
                'mime' => $replacementFile->mime,
                'size' => (int) ($replacementFile->size ?? 0),
                'created_at' => $replacementFile->created_at?->toIso8601String(),
            ] : null,
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
            'certificate_number' => $asset?->certificate_number,
            'certificate_holder_name' => $asset?->certificate_holder_name,
            'certificate_issued_at' => $asset?->certificate_issued_at?->toDateString(),
            'land_book_date' => $asset?->land_book_date?->toDateString(),
            'document_land_area' => $asset?->document_land_area,
            'legal_notes' => $asset?->legal_notes,
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
            'certificate_number' => $this->blankToNull($validated['certificate_number'] ?? null),
            'certificate_holder_name' => $this->blankToNull($validated['certificate_holder_name'] ?? null),
            'certificate_issued_at' => $this->blankToNull($validated['certificate_issued_at'] ?? null),
            'land_book_date' => $this->blankToNull($validated['land_book_date'] ?? null),
            'document_land_area' => $this->blankToNull($validated['document_land_area'] ?? null),
            'legal_notes' => $this->blankToNull($validated['legal_notes'] ?? null),
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

        $verifyDocsState = $workflowService->verifyDocsState($appraisalRequest);

        if ($verifyDocsState['show'] ?? false) {
            $actions[] = [
                'key' => 'verify-docs',
                'label' => 'Verifikasi Dokumen',
                'variant' => 'default',
                'message' => 'Lanjutkan request ini ke tahap menunggu penawaran?',
                'url' => route('admin.appraisal-requests.actions.verify-docs', $appraisalRequest),
                'disabled' => ! ($verifyDocsState['ready'] ?? false),
                'disabled_reason' => $verifyDocsState['message'] ?? null,
            ];
        }

        if ($workflowService->canMarkContractSigned($appraisalRequest)) {
            $actions[] = [
                'key' => 'contract-signed',
                'label' => 'Kontrak Ditandatangani',
                'variant' => 'default',
                'message' => 'Ubah status request ini menjadi kontrak ditandatangani?',
                'url' => route('admin.appraisal-requests.actions.contract-signed', $appraisalRequest),
                'disabled' => false,
                'disabled_reason' => null,
            ];
        }

        if ($workflowService->canVerifyPayment($appraisalRequest)) {
            $actions[] = [
                'key' => 'verify-payment',
                'label' => 'Verifikasi Pembayaran',
                'variant' => 'default',
                'message' => 'Pembayaran sudah valid. Lanjutkan request ini ke proses valuasi?',
                'url' => route('admin.appraisal-requests.actions.verify-payment', $appraisalRequest),
                'disabled' => false,
                'disabled_reason' => null,
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
            'message' => 'Fee akan mengikuti harapan fee terbaru dari user dan hasilnya langsung final ke tahap tanda tangan kontrak. Lanjutkan?',
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

    protected function paginatedRecordsPayload(object $records): array
    {
        return [
            'data' => $records->items(),
            'meta' => [
                'from' => $records->firstItem(),
                'to' => $records->lastItem(),
                'total' => $records->total(),
                'current_page' => $records->currentPage(),
                'last_page' => $records->lastPage(),
                'per_page' => $records->perPage(),
                'links' => $records->linkCollection()->toArray(),
            ],
        ];
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

}

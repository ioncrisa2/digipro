<?php

namespace App\Services\Reviewer;

use App\Enums\AppraisalStatusEnum;
use App\Enums\AssetTypeEnum;
use App\Services\Reviewer\AdjustmentWorkbenchService;
use App\Services\Revisions\AppraisalRevisionFileResolver;
use App\Models\AppraisalAsset;
use App\Models\AppraisalAssetComparable;
use App\Models\AppraisalRequest;
use App\Support\ReviewerBtbCatalog;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ReviewerWorkspaceService
{
    public function __construct(
        private readonly AppraisalRevisionFileResolver $fileResolver
    ) {
    }

    public function reviewerStatuses(): array
    {
        return [
            AppraisalStatusEnum::ContractSigned->value,
            AppraisalStatusEnum::ValuationOnProgress->value,
            AppraisalStatusEnum::ValuationCompleted->value,
        ];
    }

    public function reviewStatusOptions(): array
    {
        return [
            ['value' => 'all', 'label' => 'Semua Status'],
            ['value' => AppraisalStatusEnum::ContractSigned->value, 'label' => AppraisalStatusEnum::ContractSigned->label()],
            ['value' => AppraisalStatusEnum::ValuationOnProgress->value, 'label' => AppraisalStatusEnum::ValuationOnProgress->label()],
            ['value' => AppraisalStatusEnum::ValuationCompleted->value, 'label' => AppraisalStatusEnum::ValuationCompleted->label()],
        ];
    }

    public function reviewQueueOptions(array $summary = []): array
    {
        return [
            [
                'value' => 'all',
                'label' => 'Semua Queue',
                'description' => 'Lihat seluruh permohonan aktif reviewer.',
                'count' => (int) ($summary['total'] ?? 0),
                'status' => 'all',
            ],
            [
                'value' => 'ready_review',
                'label' => 'Siap Review',
                'description' => 'Kontrak selesai dan review bisa dimulai.',
                'count' => (int) ($summary['siap_review'] ?? 0),
                'status' => AppraisalStatusEnum::ContractSigned->value,
            ],
            [
                'value' => 'in_progress',
                'label' => 'Sedang Review',
                'description' => 'Valuasi sedang berjalan dan perlu dilanjutkan.',
                'count' => (int) ($summary['sedang_review'] ?? 0),
                'status' => AppraisalStatusEnum::ValuationOnProgress->value,
            ],
            [
                'value' => 'ready_preview',
                'label' => 'Siap Preview',
                'description' => 'Hasil review siap dikirim ke customer.',
                'count' => (int) ($summary['siap_preview'] ?? 0),
                'status' => AppraisalStatusEnum::ValuationCompleted->value,
            ],
        ];
    }

    public function resolveReviewStatusFilter(array $filters): string
    {
        $status = (string) ($filters['status'] ?? 'all');
        if ($status !== '' && $status !== 'all') {
            return $status;
        }

        return match ((string) ($filters['queue'] ?? 'all')) {
            'ready_review' => AppraisalStatusEnum::ContractSigned->value,
            'in_progress' => AppraisalStatusEnum::ValuationOnProgress->value,
            'ready_preview' => AppraisalStatusEnum::ValuationCompleted->value,
            default => 'all',
        };
    }

    public function statusPayload(mixed $status): array
    {
        $value = $this->enumValue($status) ?? '-';
        $enum = AppraisalStatusEnum::tryFrom($value);

        return [
            'value' => $value,
            'label' => $enum?->label() ?? Str::headline($value),
        ];
    }

    public function serializeReviewListItem(AppraisalRequest $record): array
    {
        $statusValue = $this->enumValue($record->status);

        return [
            'id' => $record->id,
            'request_number' => $record->request_number ?? ('REQ-' . $record->id),
            'client_name' => $record->client_name ?: ($record->user?->name ?? '-'),
            'assets_count' => (int) ($record->assets_count ?? 0),
            'status' => $this->statusPayload($record->status),
            'contract_number' => $record->contract_number,
            'requested_at' => optional($record->requested_at)?->toDateTimeString(),
            'detail_url' => route('reviewer.reviews.show', $record),
            'next_action' => $this->nextReviewAction($statusValue, $record),
        ];
    }

    public function serializeAssetListItem(AppraisalAsset $asset): array
    {
        $assetType = $this->enumValue($asset->asset_type);
        $hasBtb = $this->assetHasBtb($asset);

        return [
            'id' => $asset->id,
            'request_number' => $asset->request?->request_number ?? '-',
            'request_status' => $this->statusPayload($asset->request?->status),
            'address' => $asset->address ?? '-',
            'asset_type' => [
                'value' => $assetType,
                'label' => AssetTypeEnum::tryFrom($assetType ?? '')?->label() ?? ($assetType ?? '-'),
            ],
            'land_area' => $asset->land_area,
            'building_area' => $asset->building_area,
            'comparables_count' => (int) ($asset->comparables_count ?? 0),
            'selected_comparables_count' => (int) ($asset->selected_comparables_count ?? 0),
            'estimated_value_low' => $asset->estimated_value_low,
            'estimated_value_high' => $asset->estimated_value_high,
            'market_value_final' => $asset->market_value_final,
            'detail_url' => route('reviewer.assets.show', $asset),
            'adjustment_url' => route('reviewer.assets.adjustment', $asset),
            'land_adjustment_url' => route('reviewer.assets.adjustment', $asset),
            'has_btb' => $hasBtb,
            'btb_url' => $hasBtb ? route('reviewer.assets.btb', $asset) : null,
        ];
    }

    public function serializeAssetDetail(AppraisalAsset $asset, bool $includeComparables = false, bool $includeFiles = false): array
    {
        $assetType = $this->enumValue($asset->asset_type);
        $hasBtb = $this->assetHasBtb($asset);

        $payload = [
            'id' => $asset->id,
            'request_number' => $asset->request?->request_number ?? '-',
            'request_status' => $this->statusPayload($asset->request?->status),
            'requested_at' => optional($asset->request?->requested_at)?->toDateTimeString(),
            'address' => $asset->address,
            'asset_type' => [
                'value' => $assetType,
                'label' => AssetTypeEnum::tryFrom($assetType ?? '')?->label() ?? ($assetType ?? '-'),
            ],
            'land_area' => $asset->land_area,
            'building_area' => $asset->building_area,
            'building_floors' => $asset->building_floors,
            'build_year' => $asset->build_year,
            'renovation_year' => $asset->renovation_year,
            'maps_link' => $asset->maps_link,
            'coordinates' => [
                'lat' => $asset->coordinates_lat,
                'lng' => $asset->coordinates_lng,
            ],
            'general_data' => [
                'peruntukan' => $asset->peruntukan,
                'title_document' => $asset->title_document,
                'land_shape' => $asset->land_shape,
                'land_position' => $asset->land_position,
                'land_condition' => $asset->land_condition,
                'topography' => $asset->topography,
                'frontage_width' => $asset->frontage_width,
                'access_road_width' => $asset->access_road_width,
                'build_year' => $asset->build_year,
            ],
            'values' => [
                'land_value_final' => $asset->land_value_final,
                'building_value_final' => $asset->building_value_final,
                'market_value_final' => $asset->market_value_final,
                'estimated_value_low' => $asset->estimated_value_low,
                'estimated_value_high' => $asset->estimated_value_high,
            ],
            'detail_url' => route('reviewer.assets.show', $asset),
            'adjustment_url' => route('reviewer.assets.adjustment', $asset),
            'land_adjustment_url' => route('reviewer.assets.adjustment', $asset),
            'has_btb' => $hasBtb,
            'btb_url' => $hasBtb ? route('reviewer.assets.btb', $asset) : null,
            'general_data_update_url' => route('reviewer.api.assets.general-data', $asset),
            'comparables_search_url' => route('reviewer.api.assets.comparables.search', $asset),
            'comparables_sync_url' => route('reviewer.api.assets.comparables.sync', $asset),
        ];

        if ($includeComparables) {
            $payload['comparables'] = $asset->comparables
                ->map(fn (AppraisalAssetComparable $comparable): array => $this->serializeComparableListItem($comparable))
                ->values();
        }

        if ($includeFiles) {
            $payload['files'] = $this->fileResolver
                ->activeAssetFiles($asset)
                ->map(fn ($file): array => $this->serializeAssetFile($file, $asset))
                ->values();
        }

        return $payload;
    }

    public function serializeComparableListItem(AppraisalAssetComparable $comparable): array
    {
        return [
            'id' => $comparable->id,
            'appraisal_asset_id' => $comparable->appraisal_asset_id,
            'request_number' => $comparable->asset?->request?->request_number ?? '-',
            'asset_address' => $comparable->asset?->address ?? '-',
            'asset_detail_url' => route('reviewer.assets.show', $comparable->appraisal_asset_id),
            'external_id' => (string) $comparable->external_id,
            'image_url' => $comparable->image_url,
            'is_selected' => (bool) $comparable->is_selected,
            'manual_rank' => $comparable->manual_rank,
            'rank' => $comparable->rank,
            'score' => $comparable->score,
            'distance_meters' => $comparable->distance_meters,
            'raw_land_area' => $comparable->raw_land_area,
            'raw_building_area' => $comparable->raw_building_area,
            'raw_price' => $comparable->raw_price,
            'raw_peruntukan' => $comparable->raw_peruntukan,
            'raw_data_date' => $comparable->raw_data_date,
            'adjusted_unit_value' => $comparable->adjusted_unit_value,
            'indication_value' => $comparable->indication_value,
            'total_adjustment_percent' => $comparable->total_adjustment_percent,
            'land_adjustments_count' => (int) ($comparable->land_adjustments_count ?? 0),
            'detail_url' => route('reviewer.comparables.show', $comparable),
            'adjustment_url' => route('reviewer.assets.adjustment', $comparable->appraisal_asset_id),
            'update_url' => route('reviewer.api.comparables.update', $comparable),
        ];
    }

    public function serializeComparableDetail(AppraisalAssetComparable $comparable): array
    {
        return [
            ...$this->serializeComparableListItem($comparable),
            'snapshot_json' => $comparable->snapshot_json,
            'asset' => $comparable->asset ? $this->serializeAssetDetail($comparable->asset) : null,
            'land_adjustments' => $comparable->landAdjustments->map(fn ($adjustment): array => [
                'id' => $adjustment->id,
                'factor_name' => $adjustment->factor?->name ?? '-',
                'factor_code' => $adjustment->factor?->code ?? '-',
                'subject_value' => $adjustment->subject_value,
                'comparable_value' => $adjustment->comparable_value,
                'adjustment_percent' => $adjustment->adjustment_percent,
                'adjustment_amount' => $adjustment->adjustment_amount,
                'note' => $adjustment->note,
            ])->values(),
        ];
    }

    public function serializeAssetFile(mixed $file, AppraisalAsset $asset): array
    {
        $url = null;
        if ($file->path && Storage::disk('public')->exists($file->path)) {
            $url = $this->normalizePublicUrl(Storage::disk('public')->url($file->path));
        }

        return [
            'id' => $file->id,
            'asset_id' => $asset->id,
            'asset_address' => $asset->address,
            'type' => $file->type,
            'original_name' => $file->original_name,
            'mime' => $file->mime,
            'size' => $file->size,
            'created_at' => optional($file->created_at)?->toDateTimeString(),
            'url' => $url,
        ];
    }

    public function serializeActiveAssetFiles(AppraisalAsset $asset): Collection
    {
        return $this->fileResolver
            ->activeAssetFiles($asset)
            ->map(fn ($file): array => $this->serializeAssetFile($file, $asset))
            ->values();
    }

    public function paymentStatusLabel(?string $status): string
    {
        return match ($status) {
            'paid' => 'Dibayar',
            'failed' => 'Gagal',
            'rejected' => 'Ditolak',
            'refunded' => 'Refund',
            'pending' => 'Menunggu Verifikasi',
            default => '-',
        };
    }

    public function makeAdjustmentWorkbench(int $assetId): AdjustmentWorkbenchService
    {
        /** @var AdjustmentWorkbenchService $workbench */
        $workbench = app(AdjustmentWorkbenchService::class);
        $workbench->mount($assetId);

        return $workbench;
    }

    public function assetHasBtb(AppraisalAsset $asset): bool
    {
        return (float) ($asset->building_area ?? 0) > 0;
    }

    private function enumValue(mixed $value): ?string
    {
        if ($value instanceof \BackedEnum) {
            return (string) $value->value;
        }

        if (is_string($value) && $value !== '') {
            return $value;
        }

        return null;
    }

    private function normalizePublicUrl(?string $url): ?string
    {
        if (! $url) {
            return null;
        }

        $appUrl = (string) config('app.url', '');
        $appScheme = parse_url($appUrl, PHP_URL_SCHEME);

        if ($appScheme !== 'https') {
            return $url;
        }

        return preg_replace('/^http:\/\//i', 'https://', $url) ?: $url;
    }

    /**
     * @return array{label:string,description:string,url:string}
     */
    private function nextReviewAction(?string $status, AppraisalRequest $record): array
    {
        return match ($status) {
            AppraisalStatusEnum::ContractSigned->value => [
                'label' => 'Mulai Review',
                'description' => 'Buka detail request lalu mulai proses valuasi.',
                'url' => route('reviewer.reviews.show', $record),
            ],
            AppraisalStatusEnum::ValuationOnProgress->value => [
                'label' => 'Lanjutkan Review',
                'description' => 'Teruskan adjustment dan finalisasi nilai aset.',
                'url' => route('reviewer.reviews.show', $record),
            ],
            AppraisalStatusEnum::ValuationCompleted->value => [
                'label' => 'Kirim Preview',
                'description' => 'Cek detail akhir sebelum preview dikirim ke customer.',
                'url' => route('reviewer.reviews.show', $record),
            ],
            default => [
                'label' => 'Buka Detail',
                'description' => 'Lihat detail request.',
                'url' => route('reviewer.reviews.show', $record),
            ],
        };
    }
}

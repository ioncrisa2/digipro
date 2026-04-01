<?php

namespace App\Services;

use App\Enums\AppraisalStatusEnum;
use App\Models\AppraisalAsset;
use App\Models\AppraisalRequest;
use RuntimeException;

class AppraisalMarketPreviewService
{
    public function __construct(
        private readonly AppraisalReportPdfService $reportPdfService
    ) {
    }

    public function publishPreview(AppraisalRequest $record): array
    {
        $record->loadMissing(['assets', 'user:id,name,email']);

        $assets = $record->assets
            ->sortBy('id')
            ->values();

        if ($assets->isEmpty()) {
            throw new RuntimeException('Request belum memiliki aset untuk dipreview.');
        }

        $invalidAsset = $assets->first(fn (AppraisalAsset $asset) => ! $this->hasCompleteRange($asset));
        if ($invalidAsset) {
            throw new RuntimeException('Masih ada aset yang belum memiliki range hasil kajian pasar lengkap.');
        }

        $version = max(1, (int) ($record->market_preview_version ?? 0) + 1);
        $snapshot = $this->buildSnapshot($record, $assets->all(), $version);

        $this->reportPdfService->deleteDraft($record);

        $record->update([
            'status' => AppraisalStatusEnum::PreviewReady,
            'market_preview_snapshot' => $snapshot,
            'market_preview_version' => $version,
            'market_preview_published_at' => now(),
            'market_preview_approved_at' => null,
        ]);

        return $snapshot;
    }

    public function approvePreview(AppraisalRequest $record): void
    {
        $this->ensurePreviewReady($record);

        if (! is_array($record->market_preview_snapshot) || empty($record->market_preview_snapshot['assets'])) {
            throw new RuntimeException('Snapshot preview tidak tersedia.');
        }

        $this->reportPdfService->generateDraft($record);

        $record->update([
            'market_preview_approved_at' => now(),
            'status' => AppraisalStatusEnum::ReportPreparation,
        ]);
    }

    public function submitAppeal(AppraisalRequest $record, string $reason): void
    {
        $this->ensurePreviewReady($record);

        if ((int) ($record->market_preview_appeal_count ?? 0) >= 1) {
            throw new RuntimeException('Kesempatan banding untuk request ini sudah digunakan.');
        }

        $this->reportPdfService->deleteDraft($record);

        $record->update([
            'status' => AppraisalStatusEnum::ValuationOnProgress,
            'market_preview_appeal_count' => 1,
            'market_preview_appeal_reason' => trim($reason),
            'market_preview_appeal_submitted_at' => now(),
            'market_preview_approved_at' => null,
        ]);
    }

    public function buildSnapshot(AppraisalRequest $record, array $assets, int $version): array
    {
        $assetRows = collect($assets)
            ->map(function (AppraisalAsset $asset): array {
                $assetType = is_object($asset->asset_type) && method_exists($asset->asset_type, 'value')
                    ? $asset->asset_type->value
                    : (string) $asset->asset_type;
                $assetTypeLabel = is_object($asset->asset_type) && method_exists($asset->asset_type, 'label')
                    ? $asset->asset_type->label()
                    : (string) $asset->asset_type;

                return [
                    'asset_id' => $asset->id,
                    'asset_type' => $assetType,
                    'asset_type_label' => $assetTypeLabel,
                    'address' => $asset->address ?: '-',
                    'land_area' => $asset->land_area,
                    'building_area' => $asset->building_area,
                    'estimated_value_low' => (int) $asset->estimated_value_low,
                    'market_value_final' => (int) $asset->market_value_final,
                    'estimated_value_high' => (int) $asset->estimated_value_high,
                ];
            })
            ->values();

        return [
            'version' => $version,
            'published_at' => now()->toDateTimeString(),
            'request' => [
                'id' => $record->id,
                'request_number' => $record->request_number ?? ('REQ-' . $record->id),
                'client_name' => $record->client_name ?: ($record->user?->name ?? '-'),
                'report_type' => $record->report_type?->label() ?? '-',
            ],
            'summary' => [
                'estimated_value_low' => $assetRows->sum('estimated_value_low'),
                'market_value_final' => $assetRows->sum('market_value_final'),
                'estimated_value_high' => $assetRows->sum('estimated_value_high'),
                'assets_count' => $assetRows->count(),
            ],
            'assets' => $assetRows->all(),
        ];
    }

    public function ensurePreviewReady(AppraisalRequest $record): void
    {
        $status = $record->status?->value ?? (string) $record->status;

        if ($status !== AppraisalStatusEnum::PreviewReady->value) {
            throw new RuntimeException('Preview hasil kajian pasar belum tersedia pada status saat ini.');
        }
    }

    private function hasCompleteRange(AppraisalAsset $asset): bool
    {
        return is_numeric($asset->estimated_value_low)
            && is_numeric($asset->market_value_final)
            && is_numeric($asset->estimated_value_high);
    }
}

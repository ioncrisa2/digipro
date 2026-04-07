<?php

namespace App\Services\Customer\Payloads;

use App\Models\AppraisalRequest;

class AppraisalPreviewStateBuilder
{
    public function __construct(
        private readonly AppraisalPayloadFormatter $formatter,
    ) {
    }

    public function build(AppraisalRequest $record): array
    {
        $snapshot = is_array($record->market_preview_snapshot) ? $record->market_preview_snapshot : null;
        $assets = collect($snapshot['assets'] ?? [])->map(function (array $asset): array {
            return [
                'asset_id' => $asset['asset_id'] ?? null,
                'asset_type' => $asset['asset_type'] ?? null,
                'asset_type_label' => $asset['asset_type_label']
                    ?? $this->formatter->assetTypeLegacyLabel($asset['asset_type'] ?? null),
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
}

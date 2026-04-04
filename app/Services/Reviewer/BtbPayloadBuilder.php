<?php

namespace App\Services\Reviewer;

use App\Models\AppraisalAsset;
use App\Support\ReviewerBtbCatalog;
use App\Support\ReviewerValueCaster;
use Illuminate\Support\Arr;
use Throwable;

class BtbPayloadBuilder
{
    public function __construct(
        private readonly BtbWorksheetEngine $btbEngine,
    ) {
    }

    public function build(AppraisalAsset $asset, array $input = []): array
    {
        $asset->loadMissing(['request.guidelineSet', 'ikkRef', 'buildingValuation']);

        $usage = $asset->peruntukan;
        $hasBuilding = (float) ($asset->building_area ?? 0) > 0;

        if (! $hasBuilding) {
            return [
                'enabled' => false,
                'reason' => 'Aset ini belum memiliki data bangunan untuk worksheet BTB.',
                'templates' => [],
                'input' => [],
                'state' => null,
            ];
        }

        $guidelineSetId = (int) ($asset->request?->guideline_set_id ?: 0);
        $guidelineYear = (int) ($asset->request?->guidelineSet?->year ?: now()->year);
        $savedInput = $this->savedBtbInput($asset);
        $mergedInput = array_replace_recursive($savedInput, $input);
        $defaultTemplateKey = ReviewerBtbCatalog::defaultTemplateForUsage($usage);
        $templateKey = ReviewerValueCaster::nonEmptyString(Arr::get($mergedInput, 'template_key')) ?? $defaultTemplateKey;
        $template = $templateKey ? ReviewerBtbCatalog::template($templateKey) : null;
        $buildingClass = ReviewerValueCaster::nonEmptyString(Arr::get($mergedInput, 'building_class'))
            ?? ($template['mappi_building_class'] ?? null);
        $floorCount = ReviewerValueCaster::nullableInt(Arr::get($mergedInput, 'floor_count'))
            ?? $asset->building_floors
            ?? ($template['default_floor_count'] ?? null);
        $buildingArea = ReviewerValueCaster::nullableFloat(Arr::get($mergedInput, 'building_area')) ?? $asset->building_area;
        $landArea = ReviewerValueCaster::nullableFloat(Arr::get($mergedInput, 'land_area')) ?? $asset->land_area;
        $buildYear = ReviewerValueCaster::nullableInt(Arr::get($mergedInput, 'build_year')) ?? $asset->build_year;
        $renovationYear = ReviewerValueCaster::nullableInt(Arr::get($mergedInput, 'renovation_year')) ?? $asset->renovation_year ?? $buildYear;

        $engineInput = [
            'guideline_set_id' => $guidelineSetId,
            'year' => $guidelineYear,
            'usage' => $usage,
            'template_key' => $templateKey,
            'building_class' => $buildingClass,
            'floor_count' => $floorCount,
            'building_area' => $buildingArea,
            'land_area' => $landArea,
            'build_year' => $buildYear,
            'renovation_year' => $renovationYear,
            'design_finish_addition_percent' => ReviewerValueCaster::nullableFloat(Arr::get($mergedInput, 'design_finish_addition_percent')) ?? 0.0,
            'maintenance_adjustment_percent' => ReviewerValueCaster::nullableFloat(Arr::get($mergedInput, 'maintenance_adjustment_percent')) ?? 0.0,
            'incurable_depreciation_percent' => ReviewerValueCaster::nullableFloat(Arr::get($mergedInput, 'incurable_depreciation_percent')) ?? 0.0,
            'functional_obsolescence_percent' => ReviewerValueCaster::nullableFloat(Arr::get($mergedInput, 'functional_obsolescence_percent')) ?? 0.0,
            'economic_obsolescence_percent' => ReviewerValueCaster::nullableFloat(Arr::get($mergedInput, 'economic_obsolescence_percent')) ?? 0.0,
            'market_value' => ReviewerValueCaster::nullableFloat(Arr::get($mergedInput, 'market_value')) ?? $asset->market_value_final,
            'region_code' => $asset->regency_id,
            'ikk_value' => $asset->ikk_value_used,
            'subject_overrides' => (array) Arr::get($mergedInput, 'subject_overrides', []),
        ];

        try {
            $state = $guidelineSetId > 0 ? $this->btbEngine->build($engineInput) : null;
        } catch (Throwable) {
            $state = null;
        }

        $savedValuation = $asset->buildingValuation
            ? [
                'id' => $asset->buildingValuation->id,
                'worksheet_template' => $asset->buildingValuation->worksheet_template,
                'updated_at' => optional($asset->buildingValuation->updated_at)?->toDateTimeString(),
                'notes' => $asset->buildingValuation->notes,
                'cost_items_count' => $asset->buildingValuation->costItems()->count(),
            ]
            : null;

        return [
            'enabled' => true,
            'reason' => null,
            'mode' => 'worksheet',
            'note' => 'Worksheet BTB reviewer mengikuti baseline workbook 2025 dan dapat disimpan ke valuation record.',
            'saved_valuation' => $savedValuation,
            'templates' => collect(ReviewerBtbCatalog::candidateTemplatesForUsage($usage))
                ->map(fn (string $key): array => [
                    'value' => $key,
                    'label' => ReviewerBtbCatalog::template($key)['label'] ?? $key,
                ])
                ->values()
                ->all(),
            'input' => $state['input_snapshot'] ?? [
                'template_key' => $templateKey,
                'building_class' => $buildingClass,
                'floor_count' => $floorCount,
                'building_area' => $buildingArea,
                'land_area' => $landArea,
                'market_value' => $engineInput['market_value'],
                'build_year' => $buildYear,
                'renovation_year' => $renovationYear,
                'design_finish_addition_percent' => $engineInput['design_finish_addition_percent'],
                'maintenance_adjustment_percent' => $engineInput['maintenance_adjustment_percent'],
                'incurable_depreciation_percent' => $engineInput['incurable_depreciation_percent'],
                'functional_obsolescence_percent' => $engineInput['functional_obsolescence_percent'],
                'economic_obsolescence_percent' => $engineInput['economic_obsolescence_percent'],
                'subject_overrides' => $engineInput['subject_overrides'],
            ],
            'state' => $state,
        ];
    }

    private function savedBtbInput(AppraisalAsset $asset): array
    {
        $state = (array) ($asset->buildingValuation?->calculation_json ?? []);

        if ($state === []) {
            return [];
        }

        $inputSnapshot = (array) data_get($state, 'input_snapshot', []);

        if ($inputSnapshot !== []) {
            return $inputSnapshot;
        }

        $subjectOverrides = [];

        foreach ((array) data_get($state, 'worksheet.hard_cost_lines', []) as $line) {
            foreach ((array) data_get($line, 'items', []) as $item) {
                $itemKey = data_get($item, 'item_key');

                if (! is_string($itemKey) || $itemKey === '') {
                    continue;
                }

                $subjectOverrides[$itemKey] = [
                    'subject_material_spec' => data_get($item, 'subject_material_spec'),
                    'subject_unit_cost' => data_get($item, 'subject_unit_cost'),
                    'subject_volume_percent' => data_get($item, 'subject_volume_percent'),
                    'other_adjustment_factor' => data_get($item, 'other_adjustment_factor'),
                ];
            }
        }

        return [
            'template_key' => data_get($state, 'context.template_key'),
            'building_class' => data_get($state, 'context.building_class'),
            'floor_count' => data_get($state, 'context.floor_count'),
            'building_area' => data_get($state, 'context.building_area'),
            'land_area' => data_get($state, 'context.land_area'),
            'market_value' => data_get($state, 'context.market_value'),
            'build_year' => data_get($state, 'context.build_year'),
            'renovation_year' => data_get($state, 'context.renovation_year'),
            'design_finish_addition_percent' => ReviewerValueCaster::percentInputFromState(data_get($state, 'worksheet.design_finish_addition_percent')),
            'maintenance_adjustment_percent' => ReviewerValueCaster::percentInputFromState(data_get($state, 'depreciation.maintenance_adjustment_percent')),
            'incurable_depreciation_percent' => ReviewerValueCaster::percentInputFromState(data_get($state, 'depreciation.incurable_depreciation_percent')),
            'functional_obsolescence_percent' => ReviewerValueCaster::percentInputFromState(data_get($state, 'depreciation.functional_obsolescence_percent')),
            'economic_obsolescence_percent' => ReviewerValueCaster::percentInputFromState(data_get($state, 'depreciation.economic_obsolescence_percent')),
            'subject_overrides' => $subjectOverrides,
        ];
    }
}

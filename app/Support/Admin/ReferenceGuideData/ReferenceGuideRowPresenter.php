<?php

namespace App\Support\Admin\ReferenceGuideData;

use App\Models\ConstructionCostIndex;
use App\Models\CostElement;
use App\Models\FloorIndex;
use App\Models\MappiRcnStandard;

class ReferenceGuideRowPresenter
{
    public function constructionCostIndex(ConstructionCostIndex $record, string $editUrl, string $destroyUrl): array
    {
        $record->loadMissing([
            'guidelineSet:id,name,is_active',
            'regency:id,name,province_id',
            'regency.province:id,name',
        ]);

        return [
            'id' => $record->id,
            'guideline_set_name' => $record->guidelineSet?->name ?? '-',
            'guideline_is_active' => (bool) ($record->guidelineSet?->is_active ?? false),
            'year' => (int) $record->year,
            'province_name' => $record->regency?->province?->name ?? '-',
            'region_code' => (string) $record->region_code,
            'region_name' => $record->region_name,
            'ikk_value' => (float) $record->ikk_value,
            'updated_at' => $record->updated_at?->toIso8601String(),
            'edit_url' => $editUrl,
            'destroy_url' => $destroyUrl,
        ];
    }

    public function costElement(CostElement $record, string $editUrl, string $destroyUrl): array
    {
        $record->loadMissing('guidelineSet:id,name,is_active');

        return [
            'id' => $record->id,
            'guideline_set_name' => $record->guidelineSet?->name ?? '-',
            'guideline_is_active' => (bool) ($record->guidelineSet?->is_active ?? false),
            'year' => (int) $record->year,
            'base_region' => $record->base_region,
            'group' => $record->group,
            'element_code' => $record->element_code,
            'element_name' => $record->element_name,
            'building_type' => $record->building_type,
            'building_class' => $record->building_class,
            'storey_pattern' => $record->storey_pattern,
            'unit' => $record->unit,
            'unit_cost' => (int) $record->unit_cost,
            'spec_json' => $record->spec_json,
            'updated_at' => $record->updated_at?->toIso8601String(),
            'edit_url' => $editUrl,
            'destroy_url' => $destroyUrl,
        ];
    }

    public function floorIndex(FloorIndex $record, string $editUrl, string $destroyUrl): array
    {
        $record->loadMissing('guidelineSet:id,name,is_active');

        return [
            'id' => $record->id,
            'guideline_set_name' => $record->guidelineSet?->name ?? '-',
            'guideline_is_active' => (bool) ($record->guidelineSet?->is_active ?? false),
            'year' => (int) $record->year,
            'building_class' => $record->building_class,
            'floor_count' => (int) $record->floor_count,
            'il_value' => (float) $record->il_value,
            'updated_at' => $record->updated_at?->toIso8601String(),
            'edit_url' => $editUrl,
            'destroy_url' => $destroyUrl,
        ];
    }

    public function mappiRcnStandard(MappiRcnStandard $record, string $editUrl, string $destroyUrl): array
    {
        $record->loadMissing('guidelineSet:id,name,is_active');

        return [
            'id' => $record->id,
            'guideline_set_name' => $record->guidelineSet?->name ?? '-',
            'guideline_is_active' => (bool) ($record->guidelineSet?->is_active ?? false),
            'year' => (int) $record->year,
            'reference_region' => $record->reference_region,
            'building_type' => $record->building_type,
            'building_class' => $record->building_class,
            'storey_pattern' => $record->storey_pattern,
            'rcn_value' => (int) $record->rcn_value,
            'notes' => $record->notes,
            'updated_at' => $record->updated_at?->toIso8601String(),
            'edit_url' => $editUrl,
            'destroy_url' => $destroyUrl,
        ];
    }
}

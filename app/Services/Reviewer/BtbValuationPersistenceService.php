<?php

namespace App\Services\Reviewer;

use App\Models\AppraisalAsset;
use App\Models\BuildingCostItem;
use App\Models\BuildingEconomicLife;
use App\Models\BuildingValuation;
use App\Models\CostElement;
use App\Models\FloorIndex;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class BtbValuationPersistenceService
{
    public function persist(AppraisalAsset $asset, array $state): array
    {
        return DB::transaction(function () use ($asset, $state): array {
            $asset->refresh();
            $valuation = $asset->buildingValuation()->firstOrNew();

            $valuation->fill($this->valuationAttributes($asset, $state));
            $valuation->save();

            $valuation->costItems()->delete();
            $this->persistCostItems($valuation, $state);

            $buildingValue = (int) (data_get($state, 'depreciation.depreciated_brb_total') ?? 0);
            $this->syncAssetTotals($asset, $buildingValue);

            return [
                'valuation_id' => $valuation->id,
                'building_value_final' => $buildingValue > 0 ? $buildingValue : null,
                'asset' => $asset->fresh(),
            ];
        });
    }

    private function valuationAttributes(AppraisalAsset $asset, array $state): array
    {
        $context = (array) data_get($state, 'context', []);
        $reference = (array) data_get($state, 'reference', []);
        $worksheet = (array) data_get($state, 'worksheet', []);
        $depreciation = (array) data_get($state, 'depreciation', []);
        $summary = (array) data_get($state, 'summary', []);

        return [
            'appraisal_asset_id' => $asset->id,
            'guideline_set_id' => $context['guideline_set_id'] ?? null,
            'building_name' => $asset->address,
            'worksheet_template' => $context['template_key'] ?? null,
            'building_type' => $context['building_type'] ?? null,
            'building_class' => $context['building_class'] ?? null,
            'floor_count' => $context['floor_count'] ?? null,
            'valuation_year' => $context['year'] ?? null,
            'gross_floor_area' => $context['building_area'] ?? null,
            'ikk_region_code' => $context['region_code'] ?? null,
            'ikk_region_label' => $asset->ikkRef?->region_name,
            'ikk_value' => $reference['ikk_value'] ?? null,
            'base_rcn_unit_cost' => $summary['base_rcn_unit_cost'] ?? null,
            'effective_age' => isset($depreciation['effective_age'])
                ? (int) round((float) $depreciation['effective_age'])
                : null,
            'economic_life' => $reference['economic_life'] ?? null,
            'economic_life_ref_id' => $this->resolveEconomicLifeRefId(
                $context['guideline_set_id'] ?? null,
                $context['year'] ?? null,
                $context['building_type'] ?? null,
                $context['building_class'] ?? null,
                $context['floor_count'] ?? null,
            ),
            'material_quality_adjustment' => null,
            'depreciation_percent' => isset($depreciation['total_depreciation_percent'])
                ? round((float) $depreciation['total_depreciation_percent'] * 100, 2)
                : null,
            'maintenance_adjustment_factor' => $depreciation['maintenance_adjustment_percent'] ?? null,
            'final_adjustment_factor' => $depreciation['remaining_value_factor'] ?? $depreciation['final_adjustment_factor'] ?? null,
            'il_ref_id' => $this->resolveFloorIndexRefId(
                $context['guideline_set_id'] ?? null,
                $context['year'] ?? null,
                $context['building_class'] ?? null,
                $context['floor_count'] ?? null,
            ),
            'il_value' => $reference['floor_index_value'] ?? null,
            'hard_cost_total' => $worksheet['hard_cost_total_ikk_floor_index'] ?? $worksheet['hard_cost_total'] ?? null,
            'soft_cost_total' => $worksheet['soft_cost_total'] ?? null,
            'site_improvement_total' => $worksheet['site_improvement_total'] ?? null,
            'total_rcn' => $worksheet['total_brb'] ?? $worksheet['total_rcn'] ?? null,
            'total_depreciated_value' => $depreciation['depreciated_brb_total'] ?? null,
            'residual_land_value' => $summary['residual_land_value'] ?? null,
            'residual_land_value_per_sqm' => $summary['residual_land_value_per_sqm'] ?? null,
            'calculation_json' => $state,
            'notes' => 'Disimpan dari worksheet BTB reviewer.',
        ];
    }

    private function persistCostItems(BuildingValuation $valuation, array $state): void
    {
        $rows = [];
        $rowOrder = 1;
        $guidelineSetId = (int) ($valuation->guideline_set_id ?? 0);
        $valuationYear = (int) ($valuation->valuation_year ?? 0);
        $buildingType = $valuation->building_type;
        $buildingClass = $valuation->building_class;

        foreach ((array) data_get($state, 'worksheet.hard_cost_lines', []) as $line) {
            foreach ((array) ($line['items'] ?? []) as $item) {
                $rows[] = [
                    'row_order' => $rowOrder++,
                    'section_name' => $line['section'] ?? 'hard_cost',
                    'is_subtotal' => false,
                    'cost_element_id' => $this->resolveCostElementId($guidelineSetId, $valuationYear, $buildingType, $buildingClass, $item),
                    'element_code' => $item['element_code'] ?? null,
                    'element_name' => $item['element_name'] ?? ($line['label'] ?? '-'),
                    'unit' => $item['unit'] ?? null,
                    'quantity' => 1,
                    'ref_unit_cost' => $item['model_unit_cost'] ?? null,
                    'ikk_value_used' => data_get($state, 'reference.ikk_value'),
                    'adjusted_unit_cost' => $item['subject_unit_cost'] ?? null,
                    'model_material_spec' => $item['model_material_spec'] ?? null,
                    'subject_material_spec' => $item['subject_material_spec'] ?? null,
                    'model_volume_percent' => $item['model_volume_percent'] ?? null,
                    'subject_volume_percent' => $item['subject_volume_percent'] ?? null,
                    'other_adjustment_factor' => $item['other_adjustment_factor'] ?? null,
                    'direct_cost_result' => $item['direct_cost_result'] ?? null,
                    'line_total' => $item['direct_cost_result'] ?? null,
                    'source_sheet' => $item['source_sheet'] ?? null,
                    'source_cell' => $item['source_cell'] ?? null,
                    'meta_json' => [
                        'line_code' => $line['line_code'] ?? null,
                        'type' => 'element',
                    ],
                ];
            }

            $rows[] = [
                'row_order' => $rowOrder++,
                'section_name' => $line['section'] ?? 'hard_cost',
                'is_subtotal' => true,
                'cost_element_id' => null,
                'element_code' => $line['line_code'] ?? null,
                'element_name' => ($line['label'] ?? 'Subtotal') . ' - Subtotal',
                'unit' => null,
                'quantity' => null,
                'ref_unit_cost' => null,
                'ikk_value_used' => data_get($state, 'reference.ikk_value'),
                'adjusted_unit_cost' => null,
                'model_material_spec' => null,
                'subject_material_spec' => null,
                'model_volume_percent' => null,
                'subject_volume_percent' => null,
                'other_adjustment_factor' => null,
                'direct_cost_result' => $line['subtotal'] ?? null,
                'line_total' => $line['subtotal'] ?? null,
                'source_sheet' => null,
                'source_cell' => null,
                'meta_json' => [
                    'line_code' => $line['line_code'] ?? null,
                    'type' => 'subtotal',
                ],
            ];
        }

        if (data_get($state, 'worksheet.design_finish_addition_amount') !== null) {
            $rows[] = [
                'row_order' => $rowOrder++,
                'section_name' => 'hard_cost',
                'is_subtotal' => true,
                'cost_element_id' => null,
                'element_code' => 'design_finish_addition',
                'element_name' => 'Nilai Tambah Desain / Finishing',
                'unit' => null,
                'quantity' => null,
                'ref_unit_cost' => null,
                'ikk_value_used' => data_get($state, 'reference.ikk_value'),
                'adjusted_unit_cost' => null,
                'model_material_spec' => null,
                'subject_material_spec' => null,
                'model_volume_percent' => null,
                'subject_volume_percent' => data_get($state, 'worksheet.design_finish_addition_percent'),
                'other_adjustment_factor' => null,
                'direct_cost_result' => data_get($state, 'worksheet.design_finish_addition_amount'),
                'line_total' => data_get($state, 'worksheet.design_finish_addition_amount'),
                'source_sheet' => null,
                'source_cell' => null,
                'meta_json' => [
                    'line_code' => 'design_finish_addition',
                    'type' => 'design_finish_addition',
                ],
            ];
        }

        foreach ((array) data_get($state, 'worksheet.indirect_cost_lines', []) as $line) {
            $rows[] = [
                'row_order' => $rowOrder++,
                'section_name' => $line['section'] ?? 'indirect_cost',
                'is_subtotal' => false,
                'cost_element_id' => null,
                'element_code' => $line['line_code'] ?? null,
                'element_name' => $line['label'] ?? ($line['line_code'] ?? 'Indirect Cost'),
                'unit' => null,
                'quantity' => null,
                'ref_unit_cost' => null,
                'ikk_value_used' => data_get($state, 'reference.ikk_value'),
                'adjusted_unit_cost' => null,
                'model_material_spec' => null,
                'subject_material_spec' => null,
                'model_volume_percent' => null,
                'subject_volume_percent' => null,
                'other_adjustment_factor' => $line['factor'] ?? null,
                'direct_cost_result' => $line['value'] ?? null,
                'line_total' => $line['value'] ?? null,
                'source_sheet' => null,
                'source_cell' => null,
                'meta_json' => [
                    'line_code' => $line['line_code'] ?? null,
                    'type' => 'indirect_cost',
                ],
            ];
        }

        $depreciationRows = [
            'curable_physical_percent' => 'Kemunduran Fisik (Curable)',
            'maintenance_adjustment_percent' => 'Penyesuaian Perawatan',
            'incurable_depreciation_percent' => 'Kemunduran Incurable',
            'functional_obsolescence_percent' => 'Keusangan Fungsi',
            'economic_obsolescence_percent' => 'Keusangan Ekonomis',
            'total_depreciation_percent' => 'Total Penyusutan',
            'depreciation_amount_per_sqm' => 'DEPRESIASI (Rp/m2)',
            'depreciated_brb_per_sqm' => 'BRB Terdepresiasi (Rp/m2)',
            'depreciated_brb_total' => 'BRB Terdepresiasi Total',
        ];

        foreach ($depreciationRows as $key => $label) {
            $value = data_get($state, "depreciation.{$key}");
            if ($value === null) {
                continue;
            }

            $rows[] = [
                'row_order' => $rowOrder++,
                'section_name' => 'depreciation',
                'is_subtotal' => true,
                'cost_element_id' => null,
                'element_code' => $key,
                'element_name' => $label,
                'unit' => null,
                'quantity' => null,
                'ref_unit_cost' => null,
                'ikk_value_used' => data_get($state, 'reference.ikk_value'),
                'adjusted_unit_cost' => null,
                'model_material_spec' => null,
                'subject_material_spec' => null,
                'model_volume_percent' => null,
                'subject_volume_percent' => null,
                'other_adjustment_factor' => null,
                'direct_cost_result' => $value,
                'line_total' => $value,
                'source_sheet' => null,
                'source_cell' => null,
                'meta_json' => [
                    'line_code' => $key,
                    'type' => 'depreciation',
                ],
            ];
        }

        foreach ($rows as $row) {
            $valuation->costItems()->create($row);
        }
    }

    private function syncAssetTotals(AppraisalAsset $asset, int $newBuildingValue): void
    {
        $previousBuildingValue = (int) ($asset->building_value_final ?? 0);

        $landLow = $this->extractLandComponent($asset->estimated_value_low, $previousBuildingValue);
        $landHigh = $this->extractLandComponent($asset->estimated_value_high, $previousBuildingValue);
        $landMid = $this->extractLandComponent($asset->market_value_final, $previousBuildingValue);

        if ($landMid === null && $landLow !== null && $landHigh !== null) {
            $landMid = (int) round(($landLow + $landHigh) / 2);
        }

        $asset->update([
            'building_value_final' => $newBuildingValue > 0 ? $newBuildingValue : null,
            'estimated_value_low' => $landLow !== null ? $landLow + $newBuildingValue : null,
            'estimated_value_high' => $landHigh !== null ? $landHigh + $newBuildingValue : null,
            'market_value_final' => $landMid !== null ? $landMid + $newBuildingValue : null,
        ]);
    }

    private function extractLandComponent(?int $storedValue, int $previousBuildingValue): ?int
    {
        if ($storedValue === null) {
            return null;
        }

        return max(0, $storedValue - max(0, $previousBuildingValue));
    }

    private function resolveFloorIndexRefId(mixed $guidelineSetId, mixed $year, mixed $buildingClass, mixed $floorCount): ?int
    {
        if (! is_numeric($guidelineSetId) || ! is_numeric($year) || ! is_numeric($floorCount) || ! is_string($buildingClass) || trim($buildingClass) === '') {
            return null;
        }

        return FloorIndex::query()
            ->where('guideline_set_id', (int) $guidelineSetId)
            ->where('year', (int) $year)
            ->whereRaw('LOWER(building_class) = ?', [strtolower(trim($buildingClass))])
            ->where('floor_count', (int) $floorCount)
            ->value('id');
    }

    private function resolveEconomicLifeRefId(mixed $guidelineSetId, mixed $year, mixed $buildingType, mixed $buildingClass, mixed $floorCount): ?int
    {
        if (! is_numeric($guidelineSetId) || ! is_numeric($year)) {
            return null;
        }

        $query = BuildingEconomicLife::query()
            ->where('guideline_item_id', (int) $guidelineSetId)
            ->where('year', (int) $year);

        if (is_string($buildingType) && trim($buildingType) !== '') {
            $query->whereRaw('LOWER(building_type) = ?', [strtolower(trim($buildingType))]);
        }

        if (is_string($buildingClass) && trim($buildingClass) !== '') {
            $query->whereRaw('LOWER(building_class) = ?', [strtolower(trim($buildingClass))]);
        }

        if (is_numeric($floorCount)) {
            $query->where(function ($scope) use ($floorCount) {
                $scope
                    ->where(function ($q) use ($floorCount) {
                        $q->whereNotNull('storey_min')
                            ->whereNotNull('storey_max')
                            ->where('storey_min', '<=', (int) $floorCount)
                            ->where('storey_max', '>=', (int) $floorCount);
                    })
                    ->orWhere(function ($q) {
                        $q->whereNull('storey_min')
                            ->whereNull('storey_max');
                    });
            });
        }

        return $query->orderByDesc('storey_min')->value('id');
    }

    private function resolveCostElementId(int $guidelineSetId, int $year, ?string $buildingType, ?string $buildingClass, array $item): ?int
    {
        if ($guidelineSetId <= 0 || $year <= 0 || empty($item['element_code'])) {
            return null;
        }

        $query = CostElement::query()
            ->where('guideline_set_id', $guidelineSetId)
            ->where('year', $year)
            ->where('element_code', $item['element_code']);

        if (is_string($buildingType) && $buildingType !== '') {
            $query->whereRaw('LOWER(building_type) = ?', [strtolower($buildingType)]);
        }

        if (is_string($buildingClass) && $buildingClass !== '') {
            $query->whereRaw('LOWER(building_class) = ?', [strtolower($buildingClass)]);
        }

        return $query->value('id');
    }
}

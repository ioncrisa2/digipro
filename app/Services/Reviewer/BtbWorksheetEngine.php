<?php

namespace App\Services\Reviewer;

use App\Models\BuildingEconomicLife;
use App\Models\ConstructionCostIndex;
use App\Models\CostElement;
use App\Models\FloorIndex;
use App\Models\MappiRcnStandard;
use App\Models\ValuationSetting;
use App\Support\ReviewerBtbCatalog;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use InvalidArgumentException;

class BtbWorksheetEngine
{
    public function build(array $input): array
    {
        $usage = $this->stringOrNull($input['usage'] ?? null);
        $buildingClass = $this->stringOrNull($input['building_class'] ?? null);
        $templateKey = $this->resolveTemplateKey($input, $usage, $buildingClass);
        $template = ReviewerBtbCatalog::template($templateKey);

        if ($template === null) {
            throw new InvalidArgumentException('Template BTB tidak ditemukan.');
        }

        $guidelineSetId = (int) ($input['guideline_set_id'] ?? 0);
        $year = (int) ($input['year'] ?? 0);

        if ($guidelineSetId <= 0 || $year <= 0) {
            throw new InvalidArgumentException('guideline_set_id dan year wajib diisi untuk engine BTB.');
        }

        $buildingType = $this->stringOrNull($input['building_type'] ?? null) ?? $template['mappi_building_type'] ?? null;
        $buildingClass = $buildingClass ?? ($template['mappi_building_class'] ?? null);
        $floorCount = $this->nullableInt($input['floor_count'] ?? null) ?? ($template['default_floor_count'] ?? null);
        $referenceRegion = $this->stringOrNull($input['reference_region'] ?? null) ?? ($template['reference_region'] ?? 'DKI Jakarta');
        $storeyPattern = $this->stringOrNull($input['storey_pattern'] ?? null) ?? ($template['storey_pattern'] ?? null);
        $regionCode = $this->stringOrNull($input['region_code'] ?? null);
        $buildingArea = $this->nullableFloat($input['building_area'] ?? null);
        $landArea = $this->nullableFloat($input['land_area'] ?? null);
        $marketValue = $this->nullableFloat($input['market_value'] ?? null);
        $materialQualityAdjustment = $this->nullableFloat($input['material_quality_adjustment'] ?? null) ?? 1.0;
        $maintenanceAdjustmentFactor = $this->nullableFloat($input['maintenance_adjustment_factor'] ?? null) ?? 0.0;
        $effectiveAge = $this->nullableFloat($input['effective_age'] ?? null);

        $context = [
            'template_key' => $templateKey,
            'template' => $template,
            'usage' => $usage,
            'guideline_set_id' => $guidelineSetId,
            'year' => $year,
            'building_type' => $buildingType,
            'building_class' => $buildingClass,
            'floor_count' => $floorCount,
            'storey_pattern' => $storeyPattern,
            'reference_region' => $referenceRegion,
            'region_code' => $regionCode,
            'building_area' => $buildingArea,
            'land_area' => $landArea,
            'market_value' => $marketValue,
        ];

        $reference = [
            'ikk_value' => $this->nullableFloat($input['ikk_value'] ?? null) ?? $this->ikkValue($guidelineSetId, $year, $regionCode),
            'floor_index_value' => $this->nullableFloat($input['floor_index_value'] ?? null) ?? $this->floorIndexValue($guidelineSetId, $year, $buildingClass, $floorCount),
            'economic_life' => $this->nullableInt($input['economic_life'] ?? null) ?? $this->economicLifeValue($guidelineSetId, $year, $buildingType, $buildingClass, $floorCount),
            'ppn_percent' => $this->nullableFloat($input['ppn_percent'] ?? null) ?? $this->ppnPercent($guidelineSetId, $year),
            'base_rcn_unit_cost' => $this->nullableFloat($input['base_rcn_unit_cost'] ?? null) ?? $this->baseRcnFromStandardReference(
                $guidelineSetId,
                $year,
                $buildingType,
                $buildingClass,
                $floorCount,
                $storeyPattern
            ),
        ];

        $subjectOverrides = (array) ($input['subject_overrides'] ?? []);
        $hardCostLines = [];
        $hardCostTotal = 0.0;

        foreach (ReviewerBtbCatalog::sections()['hard_cost']['line_codes'] ?? [] as $lineCode) {
            $definition = ReviewerBtbCatalog::lineItems()[$lineCode] ?? null;

            if (! $definition || ($definition['type'] ?? null) !== 'element') {
                continue;
            }

            $line = $this->buildElementLine(
                lineCode: $lineCode,
                lineDefinition: $definition,
                guidelineSetId: $guidelineSetId,
                year: $year,
                referenceRegion: $referenceRegion,
                buildingType: $buildingType,
                buildingClass: $buildingClass,
                floorCount: $floorCount,
                preferredStoreyPattern: $storeyPattern,
                subjectOverrides: $subjectOverrides,
            );

            $hardCostLines[] = $line;
            $hardCostTotal += (float) ($line['subtotal'] ?? 0.0);
        }

        $ikkFactor = $reference['ikk_value'] ?? 1.0;
        $floorIndexFactor = $reference['floor_index_value'] ?? 1.0;

        $hardCostAdjustedIkk = $hardCostTotal * $ikkFactor;
        $hardCostAdjustedIkkFloor = $hardCostAdjustedIkk * $floorIndexFactor;

        $indirectFactors = (array) config('reviewer_btb.indirect_cost_factors', []);
        $indirectCostLines = [
            $this->factorLine('professional_fee', $hardCostAdjustedIkkFloor, (float) ($indirectFactors['professional_fee'] ?? 0.0)),
            $this->factorLine('permit_cost', $hardCostAdjustedIkkFloor, (float) ($indirectFactors['permit_cost'] ?? 0.0)),
            $this->factorLine('contractor_profit', $hardCostAdjustedIkkFloor, (float) ($indirectFactors['contractor_profit'] ?? 0.0)),
        ];
        $softCostTotal = array_sum(array_map(fn (array $line): float => (float) $line['value'], $indirectCostLines));

        $siteImprovementTotal = $this->nullableFloat($input['site_improvement_total'] ?? null) ?? 0.0;
        $totalRcnBeforeVat = $hardCostAdjustedIkkFloor + $softCostTotal + $siteImprovementTotal;
        $ppnAmount = $totalRcnBeforeVat * (($reference['ppn_percent'] ?? 0.0) / 100);
        $totalRcn = $totalRcnBeforeVat + $ppnAmount;

        $rcnAdjustedByMaterial = $totalRcn * $materialQualityAdjustment;
        $economicLife = $reference['economic_life'];
        $remainingFactor = ($effectiveAge !== null && $economicLife !== null && $economicLife > 0)
            ? $this->clamp(1 - ($effectiveAge / $economicLife), 0.0, 1.0)
            : null;
        $finalAdjustmentFactor = $remainingFactor !== null
            ? $this->clamp($remainingFactor + $maintenanceAdjustmentFactor, 0.0, 1.0)
            : null;
        $depreciationAmountPerSqm = ($finalAdjustmentFactor !== null && $rcnAdjustedByMaterial > 0)
            ? $rcnAdjustedByMaterial * (1 - $finalAdjustmentFactor)
            : null;
        $depreciatedBrbPerSqm = ($finalAdjustmentFactor !== null)
            ? $rcnAdjustedByMaterial * $finalAdjustmentFactor
            : null;
        $depreciatedBrbTotal = ($depreciatedBrbPerSqm !== null && $buildingArea !== null)
            ? $depreciatedBrbPerSqm * $buildingArea
            : null;
        $residualLandValue = ($marketValue !== null && $depreciatedBrbTotal !== null)
            ? $marketValue - $depreciatedBrbTotal
            : null;
        $residualLandValuePerSqm = ($residualLandValue !== null && $landArea !== null && $landArea > 0)
            ? $residualLandValue / $landArea
            : null;

        return [
            'context' => $context,
            'reference' => $reference,
            'worksheet' => [
                'hard_cost_lines' => $hardCostLines,
                'hard_cost_total' => $this->roundMoney($hardCostTotal),
                'hard_cost_total_ikk' => $this->roundMoney($hardCostAdjustedIkk),
                'hard_cost_total_ikk_floor_index' => $this->roundMoney($hardCostAdjustedIkkFloor),
                'indirect_cost_lines' => $indirectCostLines,
                'soft_cost_total' => $this->roundMoney($softCostTotal),
                'site_improvement_total' => $this->roundMoney($siteImprovementTotal),
                'total_rcn_before_vat' => $this->roundMoney($totalRcnBeforeVat),
                'ppn_amount' => $this->roundMoney($ppnAmount),
                'total_rcn' => $this->roundMoney($totalRcn),
            ],
            'depreciation' => [
                'material_quality_adjustment' => $materialQualityAdjustment,
                'maintenance_adjustment_factor' => $maintenanceAdjustmentFactor,
                'effective_age' => $effectiveAge,
                'economic_life' => $economicLife,
                'remaining_factor' => $remainingFactor,
                'final_adjustment_factor' => $finalAdjustmentFactor,
                'depreciation_amount_per_sqm' => $this->roundNullableMoney($depreciationAmountPerSqm),
                'depreciated_brb_per_sqm' => $this->roundNullableMoney($depreciatedBrbPerSqm),
                'depreciated_brb_total' => $this->roundNullableMoney($depreciatedBrbTotal),
            ],
            'summary' => [
                'base_rcn_unit_cost' => $this->roundNullableMoney($reference['base_rcn_unit_cost']),
                'residual_land_value' => $this->roundNullableMoney($residualLandValue),
                'residual_land_value_per_sqm' => $this->roundNullableMoney($residualLandValuePerSqm),
            ],
        ];
    }

    private function resolveTemplateKey(array $input, ?string $usage, ?string $buildingClass): string
    {
        $explicit = $this->stringOrNull($input['template_key'] ?? null);

        if ($explicit !== null) {
            return $explicit;
        }

        $resolved = ReviewerBtbCatalog::resolveTemplateKey($usage, $buildingClass);

        if ($resolved === null) {
            throw new InvalidArgumentException('Aset ini tidak memerlukan template BTB atau mapping template belum tersedia.');
        }

        return $resolved;
    }

    private function buildElementLine(
        string $lineCode,
        array $lineDefinition,
        int $guidelineSetId,
        int $year,
        string $referenceRegion,
        ?string $buildingType,
        ?string $buildingClass,
        ?int $floorCount,
        ?string $preferredStoreyPattern,
        array $subjectOverrides,
    ): array {
        $rows = $this->costElementRows(
            guidelineSetId: $guidelineSetId,
            year: $year,
            referenceRegion: $referenceRegion,
            buildingType: $buildingType,
            buildingClass: $buildingClass,
            floorCount: $floorCount,
            preferredStoreyPattern: $preferredStoreyPattern,
            groupName: (string) ($lineDefinition['reference_group'] ?? ''),
        );

        if ($rows === []) {
            return [
                'line_code' => $lineCode,
                'label' => $lineDefinition['label'] ?? $lineCode,
                'section' => $lineDefinition['section'] ?? null,
                'type' => $lineDefinition['type'] ?? 'element',
                'items' => [],
                'subtotal' => 0,
            ];
        }

        $items = [];
        $subtotal = 0.0;
        $fallbackVolume = count($rows) === 1 ? 1.0 : 0.0;

        foreach ($rows as $index => $row) {
            $spec = is_array($row->spec_json) ? $row->spec_json : [];
            $modelMaterialSpec = $this->stringOrNull($spec['material_spec'] ?? null)
                ?? $this->stringOrNull($spec['label'] ?? null)
                ?? $row->element_name;

            $itemKey = $this->itemKey($lineCode, $modelMaterialSpec, $index);
            $override = (array) ($subjectOverrides[$itemKey] ?? []);
            $modelVolumePercent = $this->nullableFloat($spec['default_volume_percent'] ?? $spec['volume_percent'] ?? null) ?? $fallbackVolume;
            $subjectVolumePercent = $this->nullableFloat($override['subject_volume_percent'] ?? null) ?? $modelVolumePercent;
            $subjectUnitCost = $this->nullableFloat($override['subject_unit_cost'] ?? null) ?? (float) $row->unit_cost;
            $otherAdjustmentFactor = $this->nullableFloat($override['other_adjustment_factor'] ?? null) ?? 1.0;
            $directCostResult = $subjectUnitCost * $subjectVolumePercent * $otherAdjustmentFactor;

            $item = [
                'item_key' => $itemKey,
                'element_code' => $row->element_code,
                'element_name' => $row->element_name,
                'unit' => $row->unit,
                'model_material_spec' => $modelMaterialSpec,
                'model_unit_cost' => (int) $row->unit_cost,
                'model_volume_percent' => $modelVolumePercent,
                'model_line_total' => $this->roundMoney(((float) $row->unit_cost) * $modelVolumePercent),
                'subject_material_spec' => $this->stringOrNull($override['subject_material_spec'] ?? null) ?? $modelMaterialSpec,
                'subject_unit_cost' => $this->roundMoney($subjectUnitCost),
                'subject_volume_percent' => $subjectVolumePercent,
                'other_adjustment_factor' => $otherAdjustmentFactor,
                'direct_cost_result' => $this->roundMoney($directCostResult),
                'source_sheet' => $spec['source_sheet'] ?? 'BUT_Print',
                'source_cell' => $spec['source_cell'] ?? null,
            ];

            $items[] = $item;
            $subtotal += $directCostResult;
        }

        return [
            'line_code' => $lineCode,
            'label' => $lineDefinition['label'] ?? $lineCode,
            'section' => $lineDefinition['section'] ?? null,
            'type' => $lineDefinition['type'] ?? 'element',
            'items' => $items,
            'subtotal' => $this->roundMoney($subtotal),
        ];
    }

    private function factorLine(string $lineCode, float $baseValue, float $factor): array
    {
        $definition = ReviewerBtbCatalog::lineItems()[$lineCode] ?? [];

        return [
            'line_code' => $lineCode,
            'label' => $definition['label'] ?? $lineCode,
            'section' => $definition['section'] ?? 'indirect_cost',
            'factor' => $factor,
            'value' => $this->roundMoney($baseValue * $factor),
        ];
    }

    /**
     * @return array<int, CostElement>
     */
    private function costElementRows(
        int $guidelineSetId,
        int $year,
        string $referenceRegion,
        ?string $buildingType,
        ?string $buildingClass,
        ?int $floorCount,
        ?string $preferredStoreyPattern,
        string $groupName,
    ): array {
        $typeKey = strtolower(trim((string) $buildingType));
        $classKey = strtolower(trim((string) $buildingClass));
        $candidates = [];

        if ($typeKey !== '' && $classKey !== '') {
            $candidates[] = ['type' => 'exact', 'type_value' => $typeKey, 'class' => 'exact', 'class_value' => $classKey];
            $candidates[] = ['type' => 'exact', 'type_value' => $typeKey, 'class' => 'blank', 'class_value' => null];
        } elseif ($typeKey !== '') {
            $candidates[] = ['type' => 'exact', 'type_value' => $typeKey, 'class' => 'blank', 'class_value' => null];
            $candidates[] = ['type' => 'exact', 'type_value' => $typeKey, 'class' => 'any', 'class_value' => null];
        } elseif ($classKey !== '') {
            $candidates[] = ['type' => 'blank', 'type_value' => null, 'class' => 'exact', 'class_value' => $classKey];
            $candidates[] = ['type' => 'any', 'type_value' => null, 'class' => 'exact', 'class_value' => $classKey];
        }

        $candidates[] = ['type' => 'blank', 'type_value' => null, 'class' => 'blank', 'class_value' => null];

        foreach ($candidates as $candidate) {
            $query = CostElement::query()
                ->where('guideline_set_id', $guidelineSetId)
                ->where('year', $year)
                ->where('base_region', $referenceRegion)
                ->whereRaw('LOWER(`group`) = ?', [strtolower($groupName)]);

            $this->applyDimensionFilter($query, 'building_type', $candidate['type'], $candidate['type_value']);
            $this->applyDimensionFilter($query, 'building_class', $candidate['class'], $candidate['class_value']);

            $rows = $query
                ->get()
                ->filter(fn (CostElement $row): bool => $this->storeyPatternMatches($row->storey_pattern, $floorCount, $preferredStoreyPattern))
                ->sortBy([
                    fn (CostElement $row) => (int) data_get($row->spec_json, 'line_order', 999),
                    fn (CostElement $row) => strtolower((string) data_get($row->spec_json, 'material_spec', $row->element_name)),
                ])
                ->values()
                ->all();

            if ($rows !== []) {
                return $rows;
            }
        }

        return [];
    }

    private function baseRcnFromStandardReference(
        int $guidelineSetId,
        int $year,
        ?string $buildingType,
        ?string $buildingClass,
        ?int $floorCount,
        ?string $preferredStoreyPattern,
    ): ?float {
        $typeKey = strtolower(trim((string) $buildingType));
        $classKey = strtolower(trim((string) $buildingClass));
        $candidates = [];

        if ($typeKey !== '' && $classKey !== '') {
            $candidates[] = ['type' => 'exact', 'type_value' => $typeKey, 'class' => 'exact', 'class_value' => $classKey];
            $candidates[] = ['type' => 'exact', 'type_value' => $typeKey, 'class' => 'blank', 'class_value' => null];
        } elseif ($typeKey !== '') {
            $candidates[] = ['type' => 'exact', 'type_value' => $typeKey, 'class' => 'blank', 'class_value' => null];
            $candidates[] = ['type' => 'exact', 'type_value' => $typeKey, 'class' => 'any', 'class_value' => null];
        } elseif ($classKey !== '') {
            $candidates[] = ['type' => 'blank', 'type_value' => null, 'class' => 'exact', 'class_value' => $classKey];
            $candidates[] = ['type' => 'any', 'type_value' => null, 'class' => 'exact', 'class_value' => $classKey];
        }

        $candidates[] = ['type' => 'blank', 'type_value' => null, 'class' => 'blank', 'class_value' => null];

        foreach ($candidates as $candidate) {
            $query = MappiRcnStandard::query()
                ->where('guideline_set_id', $guidelineSetId)
                ->where('year', $year);

            $this->applyDimensionFilter($query, 'building_type', $candidate['type'], $candidate['type_value']);
            $this->applyDimensionFilter($query, 'building_class', $candidate['class'], $candidate['class_value']);

            $record = $query
                ->get()
                ->first(fn (MappiRcnStandard $row): bool => $this->storeyPatternMatches($row->storey_pattern, $floorCount, $preferredStoreyPattern));

            if ($record && $record->rcn_value > 0) {
                return (float) $record->rcn_value;
            }
        }

        return null;
    }

    private function floorIndexValue(int $guidelineSetId, int $year, ?string $buildingClass, ?int $floorCount): ?float
    {
        if ($buildingClass === null || $floorCount === null) {
            return null;
        }

        return FloorIndex::query()
            ->where('guideline_set_id', $guidelineSetId)
            ->where('year', $year)
            ->whereRaw('LOWER(building_class) = ?', [strtolower($buildingClass)])
            ->where('floor_count', $floorCount)
            ->value('il_value');
    }

    private function economicLifeValue(int $guidelineSetId, int $year, ?string $buildingType, ?string $buildingClass, ?int $floorCount): ?int
    {
        $query = BuildingEconomicLife::query()
            ->where('guideline_item_id', $guidelineSetId)
            ->where('year', $year);

        if ($buildingType !== null && $buildingType !== '') {
            $query->whereRaw('LOWER(building_type) = ?', [strtolower($buildingType)]);
        }

        if ($buildingClass !== null && $buildingClass !== '') {
            $query->whereRaw('LOWER(building_class) = ?', [strtolower($buildingClass)]);
        }

        if ($floorCount !== null) {
            $query->where(function (Builder $scope) use ($floorCount) {
                $scope
                    ->where(function (Builder $q) use ($floorCount) {
                        $q->whereNotNull('storey_min')
                            ->whereNotNull('storey_max')
                            ->where('storey_min', '<=', $floorCount)
                            ->where('storey_max', '>=', $floorCount);
                    })
                    ->orWhere(function (Builder $q) use ($floorCount) {
                        $q->whereNull('storey_min')
                            ->whereNull('storey_max');
                    });
            });
        }

        return $query->orderByDesc('storey_min')->value('economic_life');
    }

    private function ikkValue(int $guidelineSetId, int $year, ?string $regionCode): ?float
    {
        if ($regionCode === null || $regionCode === '') {
            return null;
        }

        return ConstructionCostIndex::query()
            ->where('guideline_set_id', $guidelineSetId)
            ->where('year', $year)
            ->where('region_code', $regionCode)
            ->value('ikk_value');
    }

    private function ppnPercent(int $guidelineSetId, int $year): float
    {
        return (float) (ValuationSetting::query()
            ->where('guideline_set_id', $guidelineSetId)
            ->where('year', $year)
            ->where('key', ValuationSetting::KEY_PPN_PERCENT)
            ->value('value_number') ?? 11.0);
    }

    private function applyDimensionFilter(Builder $query, string $column, string $mode, ?string $value): void
    {
        if ($mode === 'any') {
            return;
        }

        if ($mode === 'blank') {
            $query->where(function (Builder $scope) use ($column) {
                $scope->whereNull($column)->orWhere($column, '');
            });

            return;
        }

        if ($mode === 'exact' && $value !== null && $value !== '') {
            $query->whereRaw("LOWER({$column}) = ?", [$value]);
        }
    }

    private function storeyPatternMatches(?string $pattern, ?int $floorCount, ?string $preferredPattern): bool
    {
        $text = strtolower(trim((string) $pattern));
        $preferred = strtolower(trim((string) $preferredPattern));

        if ($preferred !== '' && $text !== '' && $preferred === $text) {
            return true;
        }

        if ($text === '' || $text === '-') {
            return true;
        }

        if ($floorCount === null || $floorCount <= 0) {
            return false;
        }

        preg_match_all('/\d+/', $text, $matches);
        $numbers = array_map('intval', $matches[0] ?? []);

        if (count($numbers) === 1) {
            return $floorCount === $numbers[0];
        }

        if (count($numbers) === 2 && str_contains($text, '-')) {
            return $floorCount >= min($numbers) && $floorCount <= max($numbers);
        }

        return in_array($floorCount, $numbers, true);
    }

    private function itemKey(string $lineCode, string $materialSpec, int $index): string
    {
        return $lineCode . ':' . Str::slug($materialSpec !== '' ? $materialSpec : (string) $index);
    }

    private function stringOrNull(mixed $value): ?string
    {
        $text = trim((string) $value);

        return $text === '' ? null : $text;
    }

    private function nullableInt(mixed $value): ?int
    {
        return is_numeric($value) ? (int) $value : null;
    }

    private function nullableFloat(mixed $value): ?float
    {
        return is_numeric($value) ? (float) $value : null;
    }

    private function roundMoney(float $value): int
    {
        return (int) round($value);
    }

    private function roundNullableMoney(?float $value): ?int
    {
        return $value !== null ? $this->roundMoney($value) : null;
    }

    private function clamp(float $value, float $min, float $max): float
    {
        if ($value < $min) {
            return $min;
        }

        if ($value > $max) {
            return $max;
        }

        return $value;
    }
}

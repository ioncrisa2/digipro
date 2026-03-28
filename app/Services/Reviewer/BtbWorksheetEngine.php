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
        $buildYear = $this->nullableInt($input['build_year'] ?? null);
        $renovationYear = $this->nullableInt($input['renovation_year'] ?? null) ?? $buildYear;
        $warnings = [];
        $designFinishAdditionPercent = $this->normalizePercent($input['design_finish_addition_percent'] ?? null) ?? 0.0;
        $maintenanceAdjustmentPercent = $this->normalizePercent($input['maintenance_adjustment_percent'] ?? null) ?? 0.0;
        $incurableDepreciationPercent = $this->normalizePercent($input['incurable_depreciation_percent'] ?? null) ?? 0.0;
        $functionalObsolescencePercent = $this->normalizePercent($input['functional_obsolescence_percent'] ?? null) ?? 0.0;
        $economicObsolescencePercent = $this->normalizePercent($input['economic_obsolescence_percent'] ?? null) ?? 0.0;
        $effectiveAge = null;

        if ($buildYear !== null) {
            if ($buildYear > $year) {
                $warnings[] = "Tahun dibangun {$buildYear} melebihi tahun penilaian {$year}; sistem menormalkan ke {$year}.";
                $buildYear = $year;
            }

            $renovationYear ??= $buildYear;

            if ($renovationYear !== null && $renovationYear < $buildYear) {
                $warnings[] = "Tahun renovasi {$renovationYear} lebih kecil dari tahun dibangun {$buildYear}; sistem menormalkan ke {$buildYear}.";
                $renovationYear = $buildYear;
            }

            if ($renovationYear !== null && $renovationYear > $year) {
                $warnings[] = "Tahun renovasi {$renovationYear} melebihi tahun penilaian {$year}; sistem menormalkan ke {$year}.";
                $renovationYear = $year;
            }

            $effectiveAge = max(0.0, $year - (($buildYear + $renovationYear) / 2));
        } else {
            $warnings[] = 'Tahun dibangun belum diisi; umur efektif dan depresiasi fisik tidak bisa dihitung penuh.';
        }

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
            'build_year' => $buildYear,
            'renovation_year' => $renovationYear,
        ];

        $reference = [
            'ikk_value' => $this->nullableFloat($input['ikk_value'] ?? null) ?? $this->ikkValue($guidelineSetId, $year, $regionCode),
            'floor_index_value' => $this->nullableFloat($input['floor_index_value'] ?? null) ?? $this->floorIndexValue($guidelineSetId, $year, $buildingClass, $floorCount),
            'economic_life' => $this->nullableInt($input['economic_life'] ?? null) ?? $this->economicLifeValue($guidelineSetId, $year, $usage, $templateKey, $buildingType, $buildingClass, $floorCount),
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
        $missingReferenceLines = [];

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

            if (($line['items'] ?? []) === []) {
                $missingReferenceLines[] = $line['label'] ?? $lineCode;
            }
        }

        if ($regionCode === null) {
            $warnings[] = 'Region code aset belum tersedia; IKK tidak bisa dipetakan otomatis.';
        }

        if ($reference['ikk_value'] === null) {
            $warnings[] = 'Referensi IKK belum ditemukan untuk guideline dan wilayah aset ini.';
        }

        if ($reference['floor_index_value'] === null) {
            $warnings[] = 'Referensi indeks lantai belum ditemukan untuk kelas bangunan dan jumlah lantai ini.';
        }

        if ($reference['economic_life'] === null) {
            $warnings[] = 'Referensi umur ekonomis belum ditemukan; depresiasi fisik tidak dapat dihitung penuh.';
        }

        if ($missingReferenceLines !== []) {
            $warnings[] = 'Referensi cost element belum lengkap untuk: ' . implode(', ', $missingReferenceLines) . '.';
        }

        if ($marketValue === null) {
            $warnings[] = 'Market value aset belum diisi; residual land value belum bisa dihitung.';
        }

        if ($landArea === null || $landArea <= 0) {
            $warnings[] = 'Luas tanah belum valid; residual land value per m2 belum bisa dihitung.';
        }

        $ikkFactor = $reference['ikk_value'] ?? 1.0;
        $floorIndexFactor = $reference['floor_index_value'] ?? 1.0;

        $hardCostAdjustedIkk = $hardCostTotal * $ikkFactor;
        $hardCostAdjustedIkkFloor = $hardCostAdjustedIkk * $floorIndexFactor;
        $designFinishAdditionAmount = $hardCostAdjustedIkkFloor * $designFinishAdditionPercent;
        $directCostBase = $hardCostAdjustedIkkFloor + $designFinishAdditionAmount;

        $indirectFactors = (array) config('reviewer_btb.indirect_cost_factors', []);
        $indirectCostLines = [
            $this->factorLine('professional_fee', $directCostBase, (float) ($indirectFactors['professional_fee'] ?? 0.0)),
            $this->factorLine('permit_cost', $directCostBase, (float) ($indirectFactors['permit_cost'] ?? 0.0)),
            $this->factorLine('contractor_profit', $directCostBase, (float) ($indirectFactors['contractor_profit'] ?? 0.0)),
        ];
        $softCostTotal = array_sum(array_map(fn (array $line): float => (float) $line['value'], $indirectCostLines));

        $siteImprovementTotal = $this->nullableFloat($input['site_improvement_total'] ?? null) ?? 0.0;
        $totalRcnBeforeVat = $directCostBase + $softCostTotal + $siteImprovementTotal;
        $ppnAmount = $totalRcnBeforeVat * (($reference['ppn_percent'] ?? 0.0) / 100);
        $totalRcn = $totalRcnBeforeVat + $ppnAmount;
        $totalBrb = $buildingArea !== null ? $totalRcn * $buildingArea : null;

        $economicLife = $reference['economic_life'];
        $curablePhysicalPercent = ($effectiveAge !== null && $economicLife !== null && $economicLife > 0)
            ? $this->clamp($effectiveAge / $economicLife, 0.0, 1.0)
            : null;
        $totalDepreciationPercent = $curablePhysicalPercent !== null
            ? $this->clamp(
                $curablePhysicalPercent
                - $maintenanceAdjustmentPercent
                + $incurableDepreciationPercent
                + ($functionalObsolescencePercent * $curablePhysicalPercent)
                + ($economicObsolescencePercent * $curablePhysicalPercent),
                0.0,
                1.0
            )
            : null;
        $remainingFactor = $totalDepreciationPercent !== null
            ? $this->clamp(1 - $totalDepreciationPercent, 0.0, 1.0)
            : null;
        $finalAdjustmentFactor = $remainingFactor;
        $depreciationAmountPerSqm = ($totalDepreciationPercent !== null && $totalRcn > 0)
            ? $totalRcn * $totalDepreciationPercent
            : null;
        $depreciatedBrbPerSqm = ($remainingFactor !== null)
            ? $totalRcn * $remainingFactor
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
        $conditionLabel = $remainingFactor !== null ? $this->conditionLabel($remainingFactor) : null;
        $audit = [
            'normalization_notes' => [
                'Tahun penilaian worksheet mengikuti guideline year, bukan formula YEAR(#REF!) pada workbook.',
                'PPN object-side distandardkan dari ValuationSetting guideline aktif.',
                'Indeks lantai memakai referensi sistem ref_floor_index, bukan referensi silang cell workbook.',
            ],
            'formula_labels' => [
                'hard_cost_adjustment' => 'Hard cost x IKK x indeks lantai.',
                'design_finish' => 'Nilai tambah desain / finishing dihitung dari hard cost setelah IKK dan indeks lantai.',
                'indirect_cost' => 'Biaya tak langsung = professional fee 3% + permit 1.5% + contractor profit 10%.',
                'depreciation' => 'Total penyusutan = curable - perawatan + incurable + fungsi x curable + ekonomis x curable.',
                'residual' => 'Residual land value = market value aset - BRB terdepresiasi total.',
            ],
            'reference_checks' => [
                $this->referenceCheck('IKK', $reference['ikk_value']),
                $this->referenceCheck('Indeks lantai', $reference['floor_index_value']),
                $this->referenceCheck('Umur ekonomis', $reference['economic_life']),
                $this->referenceCheck('PPN guideline', $reference['ppn_percent']),
            ],
            'trace' => [
                'hard_cost_groups' => array_map(fn (array $line): array => [
                    'line_code' => $line['line_code'] ?? null,
                    'label' => $line['label'] ?? null,
                    'subtotal' => $line['subtotal'] ?? null,
                    'source_refs' => collect((array) ($line['items'] ?? []))
                        ->map(fn (array $item): string => trim((string) (($item['source_sheet'] ?? '-') . (($item['source_cell'] ?? null) ? '!' . $item['source_cell'] : ''))))
                        ->filter()
                        ->unique()
                        ->values()
                        ->all(),
                ], $hardCostLines),
                'indirect_costs' => array_map(fn (array $line): array => [
                    'label' => $line['label'] ?? null,
                    'percentage' => $line['percentage'] ?? null,
                    'value' => $line['value'] ?? null,
                ], $indirectCostLines),
            ],
            'outputs' => [
                'hard_cost_total_ikk_floor_index' => $this->roundMoney($hardCostAdjustedIkkFloor),
                'soft_cost_total' => $this->roundMoney($softCostTotal),
                'total_brb_per_sqm' => $this->roundMoney($totalRcn),
                'depreciated_brb_total' => $this->roundNullableMoney($depreciatedBrbTotal),
                'residual_land_value' => $this->roundNullableMoney($residualLandValue),
            ],
        ];

        return [
            'input_snapshot' => [
                'template_key' => $templateKey,
                'building_class' => $buildingClass,
                'floor_count' => $floorCount,
                'building_area' => $buildingArea,
                'land_area' => $landArea,
                'market_value' => $marketValue,
                'build_year' => $buildYear,
                'renovation_year' => $renovationYear,
                'design_finish_addition_percent' => $this->toPercentInput($designFinishAdditionPercent),
                'maintenance_adjustment_percent' => $this->toPercentInput($maintenanceAdjustmentPercent),
                'incurable_depreciation_percent' => $this->toPercentInput($incurableDepreciationPercent),
                'functional_obsolescence_percent' => $this->toPercentInput($functionalObsolescencePercent),
                'economic_obsolescence_percent' => $this->toPercentInput($economicObsolescencePercent),
                'subject_overrides' => $subjectOverrides,
            ],
            'context' => $context,
            'reference' => $reference,
            'warnings' => array_values(array_unique($warnings)),
            'audit' => $audit,
            'worksheet' => [
                'hard_cost_lines' => $hardCostLines,
                'hard_cost_total' => $this->roundMoney($hardCostTotal),
                'hard_cost_total_ikk' => $this->roundMoney($hardCostAdjustedIkk),
                'hard_cost_total_ikk_floor_index' => $this->roundMoney($hardCostAdjustedIkkFloor),
                'design_finish_addition_percent' => $designFinishAdditionPercent,
                'design_finish_addition_amount' => $this->roundMoney($designFinishAdditionAmount),
                'hard_cost_total_with_design_finish' => $this->roundMoney($directCostBase),
                'indirect_cost_lines' => $indirectCostLines,
                'soft_cost_total' => $this->roundMoney($softCostTotal),
                'site_improvement_total' => $this->roundMoney($siteImprovementTotal),
                'total_rcn_before_vat' => $this->roundMoney($totalRcnBeforeVat),
                'ppn_amount' => $this->roundMoney($ppnAmount),
                'total_rcn' => $this->roundMoney($totalRcn),
                'total_brb_per_sqm' => $this->roundMoney($totalRcn),
                'total_brb' => $this->roundNullableMoney($totalBrb),
            ],
            'depreciation' => [
                'build_year' => $buildYear,
                'renovation_year' => $renovationYear,
                'effective_age' => $effectiveAge,
                'economic_life' => $economicLife,
                'curable_physical_percent' => $curablePhysicalPercent,
                'maintenance_adjustment_percent' => $maintenanceAdjustmentPercent,
                'incurable_depreciation_percent' => $incurableDepreciationPercent,
                'functional_obsolescence_percent' => $functionalObsolescencePercent,
                'economic_obsolescence_percent' => $economicObsolescencePercent,
                'total_depreciation_percent' => $totalDepreciationPercent,
                'remaining_factor' => $remainingFactor,
                'remaining_value_factor' => $remainingFactor,
                'final_adjustment_factor' => $finalAdjustmentFactor,
                'condition_label' => $conditionLabel,
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
        $materialOptions = collect($rows)
            ->map(function (CostElement $row): array {
                $spec = is_array($row->spec_json) ? $row->spec_json : [];

                return [
                    'value' => (string) ($spec['material_spec'] ?? $row->element_name),
                    'label' => (string) ($spec['material_spec'] ?? $row->element_name),
                    'unit_cost' => (int) $row->unit_cost,
                    'source_sheet' => $spec['source_sheet'] ?? 'BUT_Print',
                    'source_cell' => $spec['source_cell'] ?? null,
                ];
            })
            ->unique('value')
            ->values()
            ->all();

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
                'material_options' => $materialOptions,
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
            'base_value' => $this->roundMoney($baseValue),
            'percentage' => $factor,
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

    private function economicLifeValue(
        int $guidelineSetId,
        int $year,
        ?string $usage,
        string $templateKey,
        ?string $buildingType,
        ?string $buildingClass,
        ?int $floorCount
    ): ?int
    {
        foreach ($this->economicLifeLookupCandidates($usage, $templateKey, $buildingType, $buildingClass) as $candidate) {
            $query = BuildingEconomicLife::query()
                ->where('guideline_item_id', $guidelineSetId)
                ->where('year', $year);

            if (($candidate['building_type'] ?? null) !== null && $candidate['building_type'] !== '') {
                $query->whereRaw('LOWER(building_type) = ?', [strtolower($candidate['building_type'])]);
            }

            if (($candidate['building_class'] ?? null) !== null && $candidate['building_class'] !== '') {
                $query->whereRaw('LOWER(building_class) = ?', [strtolower($candidate['building_class'])]);
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
                            $q->whereNotNull('storey_min')
                                ->whereNull('storey_max')
                                ->where('storey_min', '<=', $floorCount);
                        })
                        ->orWhere(function (Builder $q) use ($floorCount) {
                            $q->whereNull('storey_min')
                                ->whereNotNull('storey_max')
                                ->where('storey_max', '>=', $floorCount);
                        })
                        ->orWhere(function (Builder $q) {
                            $q->whereNull('storey_min')
                                ->whereNull('storey_max');
                        });
                });
            }

            $value = $query->orderByDesc('storey_min')->value('economic_life');

            if ($value !== null) {
                return (int) $value;
            }
        }

        return null;
    }

    /**
     * BEL yang diinput admin saat ini memakai label bisnis yang tidak selalu sama
     * dengan label MAPPI yang dipakai engine BTB. Fallback ini menjaga lookup tetap
     * jalan tanpa memaksa data existing di-refactor ulang dulu.
     *
     * @return array<int, array{building_type:?string, building_class:?string}>
     */
    private function economicLifeLookupCandidates(
        ?string $usage,
        string $templateKey,
        ?string $buildingType,
        ?string $buildingClass
    ): array {
        $candidates = [
            [
                'building_type' => $buildingType,
                'building_class' => $buildingClass,
            ],
            [
                'building_type' => $buildingType,
                'building_class' => null,
            ],
        ];

        $usageKey = strtolower(trim((string) $usage));

        if ($usageKey === 'rumah_tinggal' || str_starts_with($templateKey, 'rumah_')) {
            $candidates[] = ['building_type' => 'RUMAH TINGGAL', 'building_class' => $buildingClass];
            $candidates[] = ['building_type' => 'RUMAH TINGGAL', 'building_class' => null];
        }

        if ($usageKey === 'kantor') {
            $candidates[] = ['building_type' => 'KANTOR', 'building_class' => null];
        }

        if (in_array($usageKey, ['ruko', 'kios'], true) || $templateKey === 'low_rise_building') {
            $candidates[] = ['building_type' => 'PUSAT PERBELANJAAN', 'building_class' => 'RUKO / RUKAN'];
            $candidates[] = ['building_type' => 'PUSAT PERBELANJAAN', 'building_class' => null];
            $candidates[] = ['building_type' => 'RUKO', 'building_class' => null];
        }

        $unique = [];

        foreach ($candidates as $candidate) {
            $key = strtolower((string) ($candidate['building_type'] ?? '')) . '|' . strtolower((string) ($candidate['building_class'] ?? ''));
            $unique[$key] = $candidate;
        }

        return array_values($unique);
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

    private function normalizePercent(mixed $value): ?float
    {
        if (! is_numeric($value)) {
            return null;
        }

        $number = (float) $value;

        if (abs($number) > 1) {
            $number /= 100;
        }

        return $this->clamp($number, 0.0, 1.0);
    }

    private function toPercentInput(float $value): float
    {
        return round($value * 100, 4);
    }

    private function referenceCheck(string $label, mixed $value): array
    {
        return [
            'label' => $label,
            'value' => $value,
            'status' => $value === null ? 'missing' : 'ready',
        ];
    }

    private function conditionLabel(float $remainingFactor): string
    {
        $remainingPercent = $remainingFactor * 100;

        return match (true) {
            $remainingPercent > 84 => 'Sangat Baik / Very Good',
            $remainingPercent > 64 => 'Baik / Good',
            $remainingPercent > 29 => 'Cukup / Fair',
            $remainingPercent > 9 => 'Buruk / Poor',
            default => 'Scrap',
        };
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

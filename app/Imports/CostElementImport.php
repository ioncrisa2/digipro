<?php

namespace App\Imports;

use App\Models\CostElement;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\SkipsEmptyRows;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

class CostElementImport extends BaseSpreadsheetImport implements ToCollection, WithHeadingRow, SkipsEmptyRows
{
    public function __construct(
        protected int $guidelineSetId,
        protected int $year,
        protected string $baseRegion,
    ) {}

    public function collection(Collection $rows): void
    {
        DB::transaction(function () use ($rows): void {
            foreach ($rows as $row) {
                $group = $this->normalizeNullableString($row['group'] ?? null);
                $elementCode = $this->normalizeNullableString($row['element_code'] ?? null);
                $elementName = $this->normalizeNullableString($row['element_name'] ?? null);
                $unitCostRaw = $row['unit_cost'] ?? null;

                if (! $group || ! $elementCode || ! $elementName || $unitCostRaw === null || $unitCostRaw === '') {
                    $this->skipped++;
                    continue;
                }

                $nameUpper = mb_strtoupper($elementName);
                if (str_contains($nameUpper, 'RASIO') || str_contains((string) $unitCostRaw, '%')) {
                    $this->skipped++;
                    continue;
                }

                $key = [
                    'guideline_set_id' => $this->guidelineSetId,
                    'year' => $this->year,
                    'base_region' => $this->baseRegion,
                    'building_type' => $this->normalizeNullableString($row['building_type'] ?? null),
                    'building_class' => $this->normalizeNullableString($row['building_class'] ?? null),
                    'storey_pattern' => $this->normalizeNullableString($row['storey_pattern'] ?? null),
                    'element_code' => $elementCode,
                ];

                $specJson = $row['spec_json'] ?? null;
                if (is_array($specJson)) {
                    // keep as-is
                } elseif (is_string($specJson) && trim($specJson) !== '') {
                    $decoded = json_decode($specJson, true);
                    $specJson = is_array($decoded) ? $decoded : null;
                } else {
                    $specJson = null;
                }

                $existing = CostElement::query()->where($key)->first();

                CostElement::query()->updateOrCreate(
                    $key,
                    [
                        'group' => $group,
                        'element_name' => $elementName,
                        'unit' => $this->normalizeNullableString($row['unit'] ?? null) ?? 'm2',
                        'unit_cost' => (int) round((float) $unitCostRaw),
                        'spec_json' => $specJson,
                    ]
                );

                if ($existing) {
                    $this->updated++;
                } else {
                    $this->inserted++;
                }
            }
        });
    }
}

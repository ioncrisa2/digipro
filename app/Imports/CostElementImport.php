<?php

namespace App\Imports;

use App\Models\CostElement;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\SkipsEmptyRows;

class CostElementImport implements ToCollection, WithHeadingRow, SkipsEmptyRows
{
     public int $processed = 0;
    public int $skipped = 0;

    public function __construct(
        protected int $guidelineSetId,
        protected int $year,
        protected string $baseRegion,
    ) {}

    public function collection(Collection $rows): void
    {
        DB::transaction(function () use ($rows) {
            foreach ($rows as $row) {
                $norm = fn ($v) => isset($v) && trim((string) $v) !== '' ? (string) $v : null;

                $group       = $norm($row['group'] ?? null);
                $elementCode = $norm($row['element_code'] ?? null);
                $elementName = $norm($row['element_name'] ?? null);
                $unitCostRaw = $row['unit_cost'] ?? null;

                // wajib: hanya yang ada harga
                if (! $group || ! $elementCode || ! $elementName || $unitCostRaw === null || $unitCostRaw === '') {
                    $this->skipped++;
                    continue;
                }

                // skip baris rasio jika masih ikut masuk file
                $nameUpper = mb_strtoupper($elementName);
                if (str_contains($nameUpper, 'RASIO') || str_contains((string) $unitCostRaw, '%')) {
                    $this->skipped++;
                    continue;
                }

                $key = [
                    'guideline_set_id' => $this->guidelineSetId,
                    'year'             => $this->year,
                    'base_region'      => $this->baseRegion,
                    'building_type'    => $norm($row['building_type'] ?? null),
                    'building_class'   => $norm($row['building_class'] ?? null),
                    'storey_pattern'   => $norm($row['storey_pattern'] ?? null),
                    'element_code'     => $elementCode,
                ];

                $specJson = $row['spec_json'] ?? null;
                if (is_array($specJson)) {
                    // ok
                } elseif (is_string($specJson) && trim($specJson) !== '') {
                    $decoded = json_decode($specJson, true);
                    $specJson = is_array($decoded) ? $decoded : null;
                } else {
                    $specJson = null;
                }

                CostElement::query()->updateOrCreate(
                    $key,
                    [
                        'group'        => $group,
                        'element_name' => $elementName,
                        'unit'         => $norm($row['unit'] ?? null) ?? 'm2',
                        'unit_cost'    => (int) $unitCostRaw,
                        'spec_json'    => $specJson,

                        // kolom ratio tetap “kosong”
                        'value_type'   => 'cost',
                        'ratio_value'  => null,
                        'ratio_unit'   => '%',
                    ]
                );

                $this->processed++;
            }
        });
    }
}

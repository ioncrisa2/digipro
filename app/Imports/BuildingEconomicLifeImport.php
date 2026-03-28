<?php

namespace App\Imports;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\SkipsEmptyRows;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

class BuildingEconomicLifeImport implements ToCollection, WithHeadingRow, SkipsEmptyRows
{
    public int $processed = 0;
    public int $skipped = 0;

    public function __construct(
        protected int $guidelineItemId,
        protected int $year,
    ) {}

    public function collection(Collection $rows): void
    {
        DB::transaction(function () use ($rows): void {
            $now = now();

            foreach ($rows as $row) {
                $norm = fn ($v) => isset($v) && trim((string) $v) !== '' ? trim((string) $v) : null;

                $guidelineItemId = (int) (($row['guideline_item_id'] ?? null) ?: $this->guidelineItemId);
                $year = (int) (($row['year'] ?? null) ?: $this->year);
                $category = $norm($row['category'] ?? null);
                $economicLifeRaw = $row['economic_life'] ?? null;

                if ($guidelineItemId <= 0 || $year <= 0 || ! $category || $economicLifeRaw === null || $economicLifeRaw === '') {
                    $this->skipped++;
                    continue;
                }

                $payload = [
                    'guideline_item_id' => $guidelineItemId,
                    'year' => $year,
                    'category' => $category,
                    'sub_category' => $norm($row['sub_category'] ?? null),
                    'building_type' => $norm($row['building_type'] ?? null),
                    'building_class' => $norm($row['building_class'] ?? null),
                    'storey_min' => is_numeric($row['storey_min'] ?? null) ? (int) $row['storey_min'] : null,
                    'storey_max' => is_numeric($row['storey_max'] ?? null) ? (int) $row['storey_max'] : null,
                ];

                if (
                    $payload['storey_min'] !== null
                    && $payload['storey_max'] !== null
                    && $payload['storey_max'] < $payload['storey_min']
                ) {
                    $this->skipped++;
                    continue;
                }

                DB::table('ref_building_economic_life')->updateOrInsert(
                    $payload,
                    [
                        'economic_life' => (int) $economicLifeRaw,
                        'updated_at' => $now,
                        'created_at' => $now,
                    ]
                );

                $this->processed++;
            }
        });
    }
}

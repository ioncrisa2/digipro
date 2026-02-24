<?php

namespace App\Imports;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\SkipsEmptyRows;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

class FloorIndexImport implements ToCollection, WithHeadingRow, SkipsEmptyRows
{
    public int $processed = 0;
    public int $skipped = 0;

    public function __construct(
        protected int $guidelineSetId,
        protected int $year,
    ) {}

    public function collection(Collection $rows): void
    {
        DB::transaction(function() use ($rows){

            $now = now();

            foreach ($rows as $row) {
                $norm = fn ($v) => isset($v) && trim((string) $v) !== '' ? (string) $v : null;

                $buildingClass = $norm($row['building_class'] ?? null);
                $floorCountRaw = $row['floor_count'] ?? null;
                $ilValueRaw    = $row['il_value'] ?? null;

                if (! $buildingClass || $floorCountRaw === null || $floorCountRaw === '' || $ilValueRaw === null || $ilValueRaw === '') {
                    $this->skipped++;
                    continue;
                }

                DB::table('ref_floor_index')->updateOrInsert(
                    [
                        'guideline_set_id' => $this->guidelineSetId,
                        'year'             => $this->year,
                        'building_class'   => $buildingClass,
                        'floor_count'      => (int) $floorCountRaw,
                    ],
                    [
                        'il_value'    => (float) $ilValueRaw,
                        'updated_at'  => $now,
                        'created_at'  => $now,
                    ]
                );

                $this->processed++;
            }

        });
    }
}

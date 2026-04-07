<?php

namespace App\Imports;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\SkipsEmptyRows;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

class FloorIndexImport extends BaseSpreadsheetImport implements ToCollection, WithHeadingRow, SkipsEmptyRows
{
    public function __construct(
        protected int $guidelineSetId,
        protected int $year,
    ) {}

    public function collection(Collection $rows): void
    {
        DB::transaction(function() use ($rows){
            $now = now();

            foreach ($rows as $row) {
                $buildingClass = $this->normalizeNullableString($row['building_class'] ?? null);
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

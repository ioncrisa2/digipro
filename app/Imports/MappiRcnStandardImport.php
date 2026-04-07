<?php

namespace App\Imports;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\SkipsEmptyRows;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithChunkReading;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

class MappiRcnStandardImport extends BaseSpreadsheetImport implements ToCollection, WithHeadingRow, WithChunkReading, SkipsEmptyRows
{
    public function __construct(
        public int $guidelineSetId,
        public int $year,
        public string $referenceRegion = 'DKI Jakarta',
    ) {}

    public function collection(Collection $rows)
    {
        if ($rows->isEmpty()) {
            return;
        }

        $now = now();

        foreach ($rows as $row) {
            $buildingType = $this->normalizeNullableString(
                $row['building_type'] ?? $row['tipe_bangunan'] ?? $row['jenis_bangunan'] ?? null
            );
            $buildingClass = $this->normalizeNullableString(
                $row['building_class'] ?? $row['kelas_bangunan'] ?? $row['klas_bangunan'] ?? null
            );
            $storeyPattern = $this->normalizeNullableString(
                $row['storey_pattern'] ?? $row['pola_lantai'] ?? $row['jumlah_lantai'] ?? null
            );
            $rcnRaw = $row['rcn_value'] ?? $row['nilai_rcn'] ?? $row['total_biaya_pembangunan_baru'] ?? null;

            if ($buildingType === null || $rcnRaw === null || $rcnRaw === '') {
                $this->skipped++;
                continue;
            }

            $rcnValue = $this->parseIntegerCurrency($rcnRaw);

            $existingId = DB::table('ref_mappi_rcn_standards')
                ->where('guideline_set_id', $this->guidelineSetId)
                ->where('year', $this->year)
                ->where('reference_region', $this->referenceRegion)
                ->where('building_type', $buildingType)
                ->where(function ($query) use ($buildingClass) {
                    if ($buildingClass === null) {
                        $query->whereNull('building_class')->orWhere('building_class', '');
                        return;
                    }

                    $query->where('building_class', $buildingClass);
                })
                ->where(function ($query) use ($storeyPattern) {
                    if ($storeyPattern === null) {
                        $query->whereNull('storey_pattern')->orWhere('storey_pattern', '');
                        return;
                    }

                    $query->where('storey_pattern', $storeyPattern);
                })
                ->value('id');

            $payload = [
                'guideline_set_id' => $this->guidelineSetId,
                'year' => $this->year,
                'reference_region' => $this->referenceRegion,
                'building_type' => $buildingType,
                'building_class' => $buildingClass,
                'storey_pattern' => $storeyPattern,
                'rcn_value' => $rcnValue,
                'notes' => $this->normalizeNullableString($row['notes'] ?? $row['catatan'] ?? null),
                'updated_at' => $now,
            ];

            if ($existingId) {
                DB::table('ref_mappi_rcn_standards')
                    ->where('id', $existingId)
                    ->update($payload);
                $this->updated++;
            } else {
                DB::table('ref_mappi_rcn_standards')->insert($payload + [
                    'created_at' => $now,
                ]);
                $this->inserted++;
            }
        }
    }

    public function chunkSize(): int
    {
        return 500;
    }
}

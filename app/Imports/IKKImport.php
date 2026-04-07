<?php

namespace App\Imports;

use App\Models\Regency;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithChunkReading;
use Maatwebsite\Excel\Concerns\SkipsEmptyRows;

class IKKImport extends BaseSpreadsheetImport implements ToCollection, WithHeadingRow, WithChunkReading, SkipsEmptyRows
{
    public function __construct(
        public int $guidelineSetId,
        public int $year,
        public bool $skipProvinceRows = true, // skip kode xxxx00 + baris "PROV."
        public bool $requireRegency = true    // hanya impor kalau kodenya ada di regencies
    ) {}

    public function collection(Collection $rows)
    {
        if ($rows->isEmpty()) return;

        // ambil semua kode dalam 1 chunk
        $codes = $rows->map(fn ($r) => trim((string) ($r['kode'] ?? $r['code'] ?? '')))
            ->filter()
            ->unique()
            ->values()
            ->all();

        // map nama regency untuk ringkas & konsisten
        $regencyNames = Regency::query()
            ->whereIn('id', $codes)
            ->pluck('name', 'id'); // [ '1202' => 'Kab. Mandailing Natal', ... ]

        // existing data (untuk hitung inserted/updated)
        $existing = DB::table('ref_construction_cost_index')
            ->where('guideline_set_id', $this->guidelineSetId)
            ->where('year', $this->year)
            ->whereIn('region_code', $codes)
            ->pluck('id', 'region_code'); // [ '1202' => 123, ... ]

        $now = now();

        foreach ($rows as $row) {
            $code = trim((string) ($row['kode'] ?? $row['code'] ?? ''));
            if ($code === '') { $this->skipped++; continue; }

            // skip baris provinsi (1100, 1200) bila diminta
            $nameFromFile = trim((string) ($row['nama_provinsi_kota_kabupaten'] ?? $row['nama'] ?? $row['region_name'] ?? ''));
            $upperName = strtoupper($nameFromFile);

            if ($this->skipProvinceRows) {
                if (str_ends_with($code, '00')) { $this->skipped++; continue; }
                if ($upperName !== '' && str_starts_with($upperName, 'PROV')) { $this->skipped++; continue; }
            }

            $ikkRaw = $row['ikk_mappi'] ?? $row['ikk'] ?? $row['ikk_value'] ?? null;
            if ($ikkRaw === null || $ikkRaw === '') { $this->skipped++; continue; }

            $ikk = $this->parseDecimal($ikkRaw);

            // wajib ada di regencies?
            $regencyName = $regencyNames[$code] ?? null;
            if ($this->requireRegency && blank($regencyName)) {
                $this->skipped++;
                continue;
            }

            $regionName = $regencyName ?: ($nameFromFile ?: $code);
            $isUpdate = isset($existing[$code]);

            if ($isUpdate) {
                DB::table('ref_construction_cost_index')
                    ->where('id', $existing[$code])
                    ->update([
                        'region_name' => $regionName,
                        'ikk_value' => $ikk,
                        'updated_at' => $now,
                    ]);
                $this->updated++;
            } else {
                DB::table('ref_construction_cost_index')->insert([
                    'guideline_set_id' => $this->guidelineSetId,
                    'year' => $this->year,
                    'region_code' => $code,
                    'region_name' => $regionName,
                    'ikk_value' => $ikk,
                    'created_at' => $now,
                    'updated_at' => $now,
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

<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;

class IkkExport extends BaseMappedQueryExport implements ShouldAutoSize, WithColumnFormatting
{
    protected function columns(): array
    {
        return [
            'region_code',
            'region_name',
            'ikk_value',
        ];
    }

    protected function headingsList(): array
    {
        return ['kode', 'nama_provinsi_kota_kabupaten', 'ikk_mappi'];
    }

    protected function mapRow(mixed $row): array
    {
        return [
            $row->region_code,
            $row->region_name,
            $row->ikk_value,
        ];
    }

    public function columnFormats(): array
    {
        return [
            'C' => '0.000', // Custom format untuk 4 desimal
        ];
    }
}

<?php

namespace App\Exports;

use Illuminate\Database\Eloquent\Builder;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;

class IkkExport implements FromQuery, WithHeadings, WithMapping, ShouldAutoSize, WithColumnFormatting
{
    public function __construct(protected Builder $query) {}

    public function query()
    {
        return $this->query->select([
            'region_code',
            'region_name',
            'ikk_value',
        ]);
    }

    public function headings(): array
    {
        return ['kode', 'nama_provinsi_kota_kabupaten', 'ikk_mappi'];
    }

    public function map($row): array
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

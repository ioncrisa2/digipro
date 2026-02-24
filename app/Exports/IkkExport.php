<?php

namespace App\Exports;

use App\Models\ConstructionCostIndex;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;

class IkkExport implements FromQuery, WithHeadings, WithMapping, ShouldAutoSize, WithColumnFormatting
{

    public function __construct(
        public int $guidelineSetId,
        public int $year,
    ) {}

    public function query()
    {
        return ConstructionCostIndex::query()
            ->where('guideline_set_id', $this->guidelineSetId)
            ->where('year', $this->year)
            ->orderBy('region_code');
    }

    public function headings(): array
    {
        // format sama seperti template import kamu
        return ['KODE', 'NAMA PROVINSI / KOTA / KABUPATEN', 'IKK-MAPPI'];
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

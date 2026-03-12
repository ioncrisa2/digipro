<?php

namespace App\Exports;

use App\Models\MappiRcnStandard;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class MappiRcnStandardExport implements FromQuery, WithHeadings, WithMapping, ShouldAutoSize
{
    public function __construct(
        public int $guidelineSetId,
        public int $year,
    ) {}

    public function query()
    {
        return MappiRcnStandard::query()
            ->where('guideline_set_id', $this->guidelineSetId)
            ->where('year', $this->year)
            ->orderBy('building_type')
            ->orderBy('building_class')
            ->orderBy('storey_pattern');
    }

    public function headings(): array
    {
        return [
            'BUILDING_TYPE',
            'BUILDING_CLASS',
            'STOREY_PATTERN',
            'RCN_VALUE',
            'NOTES',
        ];
    }

    public function map($row): array
    {
        return [
            $row->building_type,
            $row->building_class,
            $row->storey_pattern,
            $row->rcn_value,
            $row->notes,
        ];
    }
}

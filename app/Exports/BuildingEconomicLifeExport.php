<?php

namespace App\Exports;

use App\Models\BuildingEconomicLife;
use Illuminate\Database\Eloquent\Builder;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class BuildingEconomicLifeExport implements FromQuery, WithHeadings, WithMapping, ShouldAutoSize
{
    public function __construct(protected Builder $query) {}

    public function query()
    {
        return $this->query->select([
            'category',
            'sub_category',
            'building_type',
            'building_class',
            'storey_min',
            'storey_max',
            'economic_life',
        ]);
    }

    public function headings(): array
    {
        return [
            'category',
            'sub_category',
            'building_type',
            'building_class',
            'storey_min',
            'storey_max',
            'economic_life',
        ];
    }

    public function map($row): array
    {
        /** @var BuildingEconomicLife $row */
        return [
            $row->category,
            $row->sub_category,
            $row->building_type,
            $row->building_class,
            $row->storey_min,
            $row->storey_max,
            (int) $row->economic_life,
        ];
    }
}

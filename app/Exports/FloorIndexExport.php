<?php

namespace App\Exports;

use Illuminate\Database\Eloquent\Builder;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class FloorIndexExport implements FromQuery, WithHeadings, WithMapping
{
    public function __construct(protected Builder $query) {}

    public function query()
    {
        return $this->query->select([
            'building_class',
            'floor_count',
            'il_value',
        ]);
    }

    public function headings(): array
    {
        return [
            'building_class',
            'floor_count',
            'il_value',
        ];
    }

    public function map($row): array
    {
        return [
            $row->building_class,
            (int) $row->floor_count,
            $row->il_value,
        ];
    }
}

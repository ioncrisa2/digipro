<?php

namespace App\Exports;

use App\Models\CostElement;
use Illuminate\Database\Eloquent\Builder;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class CostElementExport implements FromQuery, WithHeadings, WithMapping
{
    public function __construct(protected Builder $query) {}

    public function query()
    {
        return $this->query->select([
            'group',
            'element_code',
            'element_name',
            'building_type',
            'building_class',
            'storey_pattern',
            'unit',
            'unit_cost',
            'spec_json',
        ]);
    }

    public function headings(): array
    {
        return [
            'group',
            'element_code',
            'element_name',
            'building_type',
            'building_class',
            'storey_pattern',
            'unit',
            'unit_cost',
            'spec_json',
        ];
    }

    public function map($row): array
    {
        /** @var CostElement $row */
        return [
            $row->group,
            $row->element_code,
            $row->element_name,
            $row->building_type,
            $row->building_class,
            $row->storey_pattern,
            $row->unit,
            $row->unit_cost,
            $row->spec_json ? json_encode($row->spec_json, JSON_UNESCAPED_UNICODE) : null,
        ];
    }
}

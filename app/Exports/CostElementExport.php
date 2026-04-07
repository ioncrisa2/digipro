<?php

namespace App\Exports;

use App\Models\CostElement;

class CostElementExport extends BaseMappedQueryExport
{
    protected function columns(): array
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

    protected function mapRow(mixed $row): array
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

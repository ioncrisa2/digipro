<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\ShouldAutoSize;

class MappiRcnStandardExport extends BaseMappedQueryExport implements ShouldAutoSize
{
    protected function columns(): array
    {
        return [
            'building_type',
            'building_class',
            'storey_pattern',
            'rcn_value',
            'notes',
        ];
    }

    protected function mapRow(mixed $row): array
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

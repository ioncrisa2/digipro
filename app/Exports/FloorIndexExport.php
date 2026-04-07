<?php

namespace App\Exports;

class FloorIndexExport extends BaseMappedQueryExport
{
    protected function columns(): array
    {
        return [
            'building_class',
            'floor_count',
            'il_value',
        ];
    }

    protected function mapRow(mixed $row): array
    {
        return [
            $row->building_class,
            (int) $row->floor_count,
            $row->il_value,
        ];
    }
}

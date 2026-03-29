<?php

namespace App\Exports;

use App\Models\GuidelineSet;
use Illuminate\Database\Eloquent\Builder;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class GuidelineSetExport implements FromQuery, WithHeadings, WithMapping
{
    public function __construct(protected Builder $query) {}

    public function query()
    {
        return $this->query->select([
            'name',
            'year',
            'description',
            'is_active',
            'updated_at',
        ]);
    }

    public function headings(): array
    {
        return [
            'name',
            'year',
            'description',
            'is_active',
            'updated_at',
        ];
    }

    public function map($row): array
    {
        /** @var GuidelineSet $row */
        return [
            $row->name,
            (int) $row->year,
            $row->description,
            $row->is_active ? 1 : 0,
            $row->updated_at?->toDateTimeString(),
        ];
    }
}

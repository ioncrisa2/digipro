<?php

namespace App\Exports;

use Illuminate\Database\Eloquent\Builder;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

abstract class BaseMappedQueryExport implements FromQuery, WithHeadings, WithMapping
{
    public function __construct(
        protected Builder $query,
    ) {
    }

    public function query()
    {
        return $this->query->select($this->columns());
    }

    public function headings(): array
    {
        return $this->headingsList();
    }

    public function map($row): array
    {
        return $this->mapRow($row);
    }

    protected function headingsList(): array
    {
        return $this->columns();
    }

    abstract protected function columns(): array;

    abstract protected function mapRow(mixed $row): array;
}

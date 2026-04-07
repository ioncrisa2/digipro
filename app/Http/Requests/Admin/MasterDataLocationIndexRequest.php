<?php

namespace App\Http\Requests\Admin;

class MasterDataLocationIndexRequest extends AdminOrReviewerFormRequest
{
    public function rules(): array
    {
        return [
            'q' => ['nullable', 'string', 'max:100'],
            'province_id' => ['nullable', 'string', 'max:20'],
            'regency_id' => ['nullable', 'string', 'max:20'],
            'district_id' => ['nullable', 'string', 'max:20'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }

    public function filters(array $keys = ['q'], bool $withPerPage = true): array
    {
        return $this->filtersFromQuery([
            'q' => '',
            'province_id' => 'all',
            'regency_id' => 'all',
            'district_id' => 'all',
        ], $keys, $withPerPage);
    }

    public function selectedProvinceId(): string
    {
        return $this->queryStringFilter('province_id');
    }

    public function selectedRegencyId(): string
    {
        return $this->queryStringFilter('regency_id');
    }

    public function selectedDistrictId(): string
    {
        return $this->queryStringFilter('district_id');
    }
}

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
        $source = [
            'q' => trim((string) $this->query('q', '')),
            'province_id' => (string) $this->query('province_id', 'all'),
            'regency_id' => (string) $this->query('regency_id', 'all'),
            'district_id' => (string) $this->query('district_id', 'all'),
        ];

        $filters = [];

        foreach ($keys as $key) {
            $filters[$key] = $source[$key];
        }

        if ($withPerPage) {
            $filters['per_page'] = (string) $this->resolvePerPage();
        }

        return $filters;
    }

    public function perPage(): int
    {
        return $this->resolvePerPage();
    }

    public function selectedProvinceId(): string
    {
        return trim((string) $this->query('province_id', ''));
    }

    public function selectedRegencyId(): string
    {
        return trim((string) $this->query('regency_id', ''));
    }

    public function selectedDistrictId(): string
    {
        return trim((string) $this->query('district_id', ''));
    }
}

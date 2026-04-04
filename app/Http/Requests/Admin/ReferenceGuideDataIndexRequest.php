<?php

namespace App\Http\Requests\Admin;

class ReferenceGuideDataIndexRequest extends AdminOrReviewerFormRequest
{
    public function rules(): array
    {
        return [
            'q' => ['nullable', 'string', 'max:100'],
            'guideline_set_id' => ['nullable', 'string', 'max:20'],
            'guideline_item_id' => ['nullable', 'string', 'max:20'],
            'year' => ['nullable', 'string', 'max:20'],
            'province_id' => ['nullable', 'string', 'max:20'],
            'base_region' => ['nullable', 'string', 'max:255'],
            'group' => ['nullable', 'string', 'max:100'],
            'building_class' => ['nullable', 'string', 'max:100'],
            'building_type' => ['nullable', 'string', 'max:100'],
            'category' => ['nullable', 'string', 'max:100'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }

    public function filters(array $keys, bool $withPerPage = true): array
    {
        $source = [
            'q' => trim((string) $this->query('q', '')),
            'guideline_set_id' => (string) $this->query('guideline_set_id', 'all'),
            'guideline_item_id' => (string) $this->query('guideline_item_id', 'all'),
            'year' => (string) $this->query('year', 'all'),
            'province_id' => (string) $this->query('province_id', 'all'),
            'base_region' => (string) $this->query('base_region', 'all'),
            'group' => (string) $this->query('group', 'all'),
            'building_class' => (string) $this->query('building_class', 'all'),
            'building_type' => (string) $this->query('building_type', 'all'),
            'category' => (string) $this->query('category', 'all'),
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
}

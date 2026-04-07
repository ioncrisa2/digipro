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
        return $this->filtersFromQuery([
            'q' => '',
            'guideline_set_id' => 'all',
            'guideline_item_id' => 'all',
            'year' => 'all',
            'province_id' => 'all',
            'base_region' => 'all',
            'group' => 'all',
            'building_class' => 'all',
            'building_type' => 'all',
            'category' => 'all',
        ], $keys, $withPerPage);
    }
}

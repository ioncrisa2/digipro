<?php

namespace App\Http\Requests\Admin;

class ValuationSettingIndexRequest extends AdminOrReviewerFormRequest
{
    public function rules(): array
    {
        return [
            'q' => ['nullable', 'string', 'max:100'],
            'guideline_set_id' => ['nullable', 'string', 'max:20'],
            'year' => ['nullable', 'string', 'max:10'],
            'key' => ['nullable', 'string', 'max:100'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }

    public function filters(): array
    {
        return $this->filtersFromQuery([
            'q' => '',
            'guideline_set_id' => 'all',
            'year' => 'all',
            'key' => 'all',
        ]);
    }
}

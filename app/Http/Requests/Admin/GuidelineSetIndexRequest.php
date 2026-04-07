<?php

namespace App\Http\Requests\Admin;

class GuidelineSetIndexRequest extends AdminOrReviewerFormRequest
{
    public function rules(): array
    {
        return [
            'q' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', 'string', 'max:20'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }

    public function filters(bool $withPerPage = true): array
    {
        return $this->filtersFromQuery([
            'q' => '',
            'status' => 'all',
        ], withPerPage: $withPerPage);
    }
}

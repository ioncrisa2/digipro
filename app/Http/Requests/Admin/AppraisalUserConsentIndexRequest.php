<?php

namespace App\Http\Requests\Admin;

class AppraisalUserConsentIndexRequest extends AdminFormRequest
{
    public function rules(): array
    {
        return [
            'q' => ['nullable', 'string', 'max:100'],
            'code' => ['nullable', 'string', 'max:100'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }

    public function filters(): array
    {
        return $this->filtersFromQuery([
            'q' => '',
            'code' => 'all',
        ]);
    }
}

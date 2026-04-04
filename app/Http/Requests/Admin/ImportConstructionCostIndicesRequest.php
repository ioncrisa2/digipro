<?php

namespace App\Http\Requests\Admin;

use Illuminate\Validation\Rule;

class ImportConstructionCostIndicesRequest extends AdminOrReviewerFormRequest
{
    public function rules(): array
    {
        return [
            'guideline_set_id' => ['required', 'integer', Rule::exists('ref_guideline_sets', 'id')],
            'year' => ['required', 'integer', 'min:2000', 'max:2100'],
            'file' => ['required', 'file', 'mimes:xlsx,xls,csv'],
            'skip_province_rows' => ['nullable', 'boolean'],
            'require_regency' => ['nullable', 'boolean'],
        ];
    }
}

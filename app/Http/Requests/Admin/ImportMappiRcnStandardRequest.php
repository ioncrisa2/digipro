<?php

namespace App\Http\Requests\Admin;

use Illuminate\Validation\Rule;

class ImportMappiRcnStandardRequest extends AdminOrReviewerFormRequest
{
    public function rules(): array
    {
        return [
            'guideline_set_id' => ['required', 'integer', Rule::exists('ref_guideline_sets', 'id')],
            'year' => ['required', 'integer', 'min:2000', 'max:2100'],
            'reference_region' => ['required', 'string', 'max:255'],
            'file' => ['required', 'file', 'mimes:xlsx,xls,csv'],
        ];
    }
}

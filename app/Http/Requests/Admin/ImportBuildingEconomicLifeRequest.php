<?php

namespace App\Http\Requests\Admin;

use Illuminate\Validation\Rule;

class ImportBuildingEconomicLifeRequest extends AdminOrReviewerFormRequest
{
    public function rules(): array
    {
        return [
            'guideline_item_id' => ['required', 'integer', Rule::exists('ref_guideline_sets', 'id')],
            'year' => ['required', 'integer', 'min:2000', 'max:2100'],
            'file' => ['required', 'file', 'mimes:xlsx,xls,csv'],
        ];
    }
}

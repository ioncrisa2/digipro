<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreConstructionCostIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $record = $this->route('constructionCostIndex');

        return [
            'guideline_set_id' => ['required', 'integer', 'exists:ref_guideline_sets,id'],
            'year' => ['required', 'integer', 'min:2000', 'max:2100'],
            'region_code' => [
                'required',
                'string',
                'exists:regencies,id',
                Rule::unique('ref_construction_cost_index', 'region_code')
                    ->where('guideline_set_id', (int) $this->input('guideline_set_id'))
                    ->where('year', (int) $this->input('year'))
                    ->ignore($record?->id),
            ],
            'ikk_value' => ['required', 'numeric', 'min:0'],
        ];
    }
}

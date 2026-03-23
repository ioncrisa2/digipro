<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreBuildingEconomicLifeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $record = $this->route('buildingEconomicLife');

        return [
            'guideline_item_id' => ['required', 'integer', 'exists:ref_guideline_sets,id'],
            'year' => ['required', 'integer', 'min:2000', 'max:2100'],
            'category' => ['required', 'string', 'max:255'],
            'sub_category' => ['nullable', 'string', 'max:255'],
            'building_type' => ['nullable', 'string', 'max:255'],
            'building_class' => ['nullable', 'string', 'max:255'],
            'storey_min' => ['nullable', 'integer', 'min:0', 'max:200'],
            'storey_max' => ['nullable', 'integer', 'min:0', 'max:200', 'gte:storey_min'],
            'economic_life' => [
                'required',
                'integer',
                'min:1',
                Rule::unique('ref_building_economic_life', 'economic_life')
                    ->where('guideline_item_id', (int) $this->input('guideline_item_id'))
                    ->where('year', (int) $this->input('year'))
                    ->where('category', (string) $this->input('category'))
                    ->where('sub_category', $this->input('sub_category'))
                    ->where('building_type', $this->input('building_type'))
                    ->where('building_class', $this->input('building_class'))
                    ->where('storey_min', $this->input('storey_min'))
                    ->where('storey_max', $this->input('storey_max'))
                    ->ignore($record?->id),
            ],
        ];
    }
}

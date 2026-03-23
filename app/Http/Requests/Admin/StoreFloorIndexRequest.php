<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreFloorIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $record = $this->route('floorIndex');

        return [
            'guideline_set_id' => ['required', 'integer', 'exists:ref_guideline_sets,id'],
            'year' => ['required', 'integer', 'min:2000', 'max:2100'],
            'building_class' => ['required', 'string', 'max:255'],
            'floor_count' => [
                'required',
                'integer',
                'min:1',
                'max:200',
                Rule::unique('ref_floor_index', 'floor_count')
                    ->where('guideline_set_id', (int) $this->input('guideline_set_id'))
                    ->where('year', (int) $this->input('year'))
                    ->where('building_class', (string) $this->input('building_class'))
                    ->ignore($record?->id),
            ],
            'il_value' => ['required', 'numeric', 'min:0'],
        ];
    }
}

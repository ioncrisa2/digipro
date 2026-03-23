<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCostElementRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $specJson = $this->input('spec_json');

        if (is_string($specJson)) {
            $trimmed = trim($specJson);

            if ($trimmed === '') {
                $this->merge(['spec_json' => null]);

                return;
            }

            $decoded = json_decode($trimmed, true);

            if (json_last_error() === JSON_ERROR_NONE) {
                $this->merge(['spec_json' => $decoded]);
            }
        }
    }

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $record = $this->route('costElement');

        return [
            'guideline_set_id' => ['required', 'integer', 'exists:ref_guideline_sets,id'],
            'year' => ['required', 'integer', 'min:2000', 'max:2100'],
            'base_region' => ['required', 'string', 'max:255'],
            'group' => ['required', 'string', 'max:255'],
            'element_code' => [
                'required',
                'string',
                'max:255',
                Rule::unique('ref_cost_elements', 'element_code')
                    ->where('guideline_set_id', (int) $this->input('guideline_set_id'))
                    ->where('year', (int) $this->input('year'))
                    ->where('base_region', (string) $this->input('base_region'))
                    ->where('building_type', $this->input('building_type'))
                    ->where('building_class', $this->input('building_class'))
                    ->where('storey_pattern', $this->input('storey_pattern'))
                    ->ignore($record?->id),
            ],
            'element_name' => ['required', 'string', 'max:255'],
            'building_type' => ['nullable', 'string', 'max:255'],
            'building_class' => ['nullable', 'string', 'max:255'],
            'storey_pattern' => ['nullable', 'string', 'max:255'],
            'unit' => ['required', 'string', 'max:50'],
            'unit_cost' => ['required', 'integer', 'min:0'],
            'spec_json' => ['nullable', 'array'],
        ];
    }
}

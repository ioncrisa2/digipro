<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreMappiRcnStandardRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $record = $this->route('mappiRcnStandard');

        return [
            'guideline_set_id' => ['required', 'integer', 'exists:ref_guideline_sets,id'],
            'year' => ['required', 'integer', 'min:2000', 'max:2100'],
            'reference_region' => ['required', 'string', 'max:255'],
            'building_type' => ['required', 'string', 'max:255'],
            'building_class' => ['nullable', 'string', 'max:255'],
            'storey_pattern' => [
                'nullable',
                'string',
                'max:255',
                Rule::unique('ref_mappi_rcn_standards', 'storey_pattern')
                    ->where('guideline_set_id', (int) $this->input('guideline_set_id'))
                    ->where('year', (int) $this->input('year'))
                    ->where('reference_region', (string) $this->input('reference_region'))
                    ->where('building_type', (string) $this->input('building_type'))
                    ->where('building_class', $this->input('building_class'))
                    ->ignore($record?->id),
            ],
            'rcn_value' => ['required', 'integer', 'min:0'],
            'notes' => ['nullable', 'string'],
        ];
    }
}

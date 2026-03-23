<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreValuationSettingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $record = $this->route('valuationSetting');

        return [
            'guideline_set_id' => ['required', 'integer', 'exists:ref_guideline_sets,id'],
            'year' => ['required', 'integer', 'min:2000', 'max:2100'],
            'key' => [
                'required',
                'string',
                'max:255',
                Rule::unique('ref_valuation_settings', 'key')
                    ->where('guideline_set_id', (int) $this->input('guideline_set_id'))
                    ->where('year', (int) $this->input('year'))
                    ->ignore($record?->id),
            ],
            'label' => ['required', 'string', 'max:255'],
            'value_number' => ['required', 'numeric', 'min:0'],
            'value_text' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],
        ];
    }
}

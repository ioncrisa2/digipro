<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class SaveIkkByProvinceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'guideline_set_id' => ['required', 'integer', 'exists:ref_guideline_sets,id'],
            'year' => ['required', 'integer', 'min:2000', 'max:2100'],
            'province_id' => ['required', 'string', 'size:2', 'exists:provinces,id'],
            'items' => ['array'],
            'items.*.region_code' => ['required', 'string', 'size:4'],
            'items.*.regency_name' => ['required', 'string'],
            'items.*.ikk_value' => ['required', 'numeric', 'min:0'],
        ];
    }
}

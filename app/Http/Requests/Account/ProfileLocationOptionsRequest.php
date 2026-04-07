<?php

namespace App\Http\Requests\Account;

use Illuminate\Foundation\Http\FormRequest;

class ProfileLocationOptionsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'type' => ['required', 'in:provinces,regencies,districts,villages'],
            'province_id' => ['nullable', 'string', 'size:2'],
            'regency_id' => ['nullable', 'string', 'size:4'],
            'district_id' => ['nullable', 'string', 'size:7'],
        ];
    }
}

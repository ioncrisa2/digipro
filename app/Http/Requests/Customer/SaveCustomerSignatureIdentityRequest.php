<?php

namespace App\Http\Requests\Customer;

class SaveCustomerSignatureIdentityRequest extends CustomerFormRequest
{
    public function rules(): array
    {
        return [
            'peruri_email' => ['required', 'email', 'max:255'],
            'peruri_phone' => ['required', 'string', 'max:32'],
            'nik' => ['required', 'digits:16'],
            'reference_province_id' => ['required', 'integer', 'min:1'],
            'reference_city_id' => ['required', 'integer', 'min:1'],
            'address' => ['required', 'string', 'max:500'],
        ];
    }
}

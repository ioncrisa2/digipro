<?php

namespace App\Http\Requests\Customer;

use Illuminate\Validation\Rule;

class SaveCustomerSignatureIdentityRequest extends CustomerFormRequest
{
    public function rules(): array
    {
        $hasStoredKtpPhoto = (bool) $this->user()?->signatureProfile?->ktp_photo_path;
        $isWna = $this->boolean('is_wna');

        return [
            'is_wna' => ['required', 'boolean'],
            'peruri_email' => ['required', 'email', 'max:255'],
            'peruri_phone' => ['required', 'string', 'max:32'],
            'nik' => $isWna
                ? ['required', 'string', 'min:6', 'max:32', 'regex:/^[A-Za-z0-9]+$/']
                : ['required', 'digits:16'],
            'reference_province_id' => ['required', 'integer', 'min:1'],
            'reference_city_id' => ['required', 'integer', 'min:1'],
            'gender' => ['required', Rule::in(['M', 'F'])],
            'place_of_birth' => ['required', 'string', 'max:120'],
            'date_of_birth' => ['required', 'date_format:Y-m-d', 'before:today'],
            'address' => ['required', 'string', 'max:500'],
            'ktp_photo' => [$hasStoredKtpPhoto ? 'nullable' : 'required', 'file', 'image', 'mimes:jpg,jpeg,png', 'max:5120'],
        ];
    }
}

<?php

namespace App\Http\Requests\Customer;

class SubmitCustomerSignatureSpecimenRequest extends CustomerFormRequest
{
    public function rules(): array
    {
        return [
            'signature_image' => ['required', 'file', 'image', 'mimes:png,jpg,jpeg', 'max:5120'],
        ];
    }
}

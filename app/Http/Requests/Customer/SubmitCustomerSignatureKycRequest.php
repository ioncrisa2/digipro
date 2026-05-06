<?php

namespace App\Http\Requests\Customer;

class SubmitCustomerSignatureKycRequest extends CustomerFormRequest
{
    public function rules(): array
    {
        return [
            'kyc_video' => ['required', 'file', 'mimes:mp4,mov,avi,webm,mkv', 'max:51200'],
        ];
    }
}

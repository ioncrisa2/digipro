<?php

namespace App\Http\Requests\Account;

class VerifyCurrentPasswordRequest extends AccountAccessRequest
{
    public function rules(): array
    {
        return [
            'current_password' => ['required'],
        ];
    }
}

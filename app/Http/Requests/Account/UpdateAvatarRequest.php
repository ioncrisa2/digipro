<?php

namespace App\Http\Requests\Account;

class UpdateAvatarRequest extends AccountAccessRequest
{
    public function rules(): array
    {
        return [
            'avatar' => ['required', 'image', 'max:2048'],
        ];
    }
}

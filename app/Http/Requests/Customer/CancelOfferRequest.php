<?php

namespace App\Http\Requests\Customer;

class CancelOfferRequest extends CustomerFormRequest
{
    public function rules(): array
    {
        return [
            'reason' => ['nullable', 'string', 'max:2000'],
        ];
    }
}

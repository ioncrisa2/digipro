<?php

namespace App\Http\Requests\Customer;

class SelectOfferRequest extends CustomerFormRequest
{
    public function rules(): array
    {
        return [
            'selected_fee' => ['required', 'integer', 'min:0'],
            'reason' => ['nullable', 'string', 'max:2000'],
        ];
    }
}

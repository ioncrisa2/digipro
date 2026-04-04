<?php

namespace App\Http\Requests\Customer;

class SubmitOfferNegotiationRequest extends CustomerFormRequest
{
    public function rules(): array
    {
        return [
            'reason' => ['required', 'string', 'max:2000'],
            'expected_fee' => ['nullable', 'integer', 'min:0'],
        ];
    }
}

<?php

namespace App\Http\Requests\Customer;

class StoreCustomerAppraisalCancellationRequest extends CustomerFormRequest
{
    public function rules(): array
    {
        return [
            'reason' => ['required', 'string', 'max:2000'],
        ];
    }
}

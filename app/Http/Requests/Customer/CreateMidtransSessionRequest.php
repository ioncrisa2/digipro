<?php

namespace App\Http\Requests\Customer;

class CreateMidtransSessionRequest extends CustomerFormRequest
{
    public function rules(): array
    {
        return [
            'force_new_attempt' => ['nullable', 'boolean'],
        ];
    }

    public function forceNewAttempt(): bool
    {
        return $this->boolean('force_new_attempt');
    }
}

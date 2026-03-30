<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class StoreAppraisalOfferRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'fee_total' => ['required', 'integer', 'min:1'],
            'contract_sequence' => ['required', 'integer', 'min:1'],
            'offer_validity_days' => ['nullable', 'integer', 'min:1'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $normalize = static fn (mixed $value): mixed => blank($value) ? null : $value;

        $this->merge([
            'fee_total' => $normalize($this->input('fee_total')),
            'contract_sequence' => $normalize($this->input('contract_sequence')),
            'offer_validity_days' => $normalize($this->input('offer_validity_days')),
        ]);
    }
}

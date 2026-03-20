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
            'fee_has_dp' => ['nullable', 'boolean'],
            'fee_dp_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'contract_sequence' => ['required', 'integer', 'min:1'],
            'offer_validity_days' => ['nullable', 'integer', 'min:1'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $normalize = static fn (mixed $value): mixed => blank($value) ? null : $value;

        $this->merge([
            'fee_total' => $normalize($this->input('fee_total')),
            'fee_has_dp' => $this->boolean('fee_has_dp'),
            'fee_dp_percent' => $normalize($this->input('fee_dp_percent')),
            'contract_sequence' => $normalize($this->input('contract_sequence')),
            'offer_validity_days' => $normalize($this->input('offer_validity_days')),
        ]);
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            if ($this->boolean('fee_has_dp') && blank($this->input('fee_dp_percent'))) {
                $validator->errors()->add('fee_dp_percent', 'Persentase DP wajib diisi saat skema DP aktif.');
            }
        });
    }
}

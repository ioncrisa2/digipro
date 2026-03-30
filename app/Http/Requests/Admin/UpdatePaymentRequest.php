<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        $payment = $this->route('payment');

        if (! ($user?->hasAdminAccess() ?? false)) {
            return false;
        }

        if ($payment === null) {
            return true;
        }

        return in_array($payment->status, ['pending', 'failed', 'expired', 'rejected', 'refunded'], true);
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'gateway' => $this->filled('gateway') ? strtolower((string) $this->input('gateway')) : null,
            'paid_at' => blank($this->input('paid_at')) ? null : $this->input('paid_at'),
            'metadata_json' => blank($this->input('metadata_json')) ? null : $this->input('metadata_json'),
        ]);
    }

    public function rules(): array
    {
        return [
            'amount' => ['required', 'integer', 'min:1'],
            'status' => ['required', Rule::in(['pending', 'paid', 'failed', 'expired', 'rejected', 'refunded'])],
            'gateway' => ['nullable', 'string', 'max:60'],
            'external_payment_id' => ['nullable', 'string', 'max:120'],
            'paid_at' => ['nullable', 'date'],
            'metadata_json' => ['nullable', 'json'],
        ];
    }
}

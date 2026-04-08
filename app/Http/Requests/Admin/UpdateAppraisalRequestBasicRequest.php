<?php

namespace App\Http\Requests\Admin;

use App\Enums\ContractStatusEnum;
use App\Enums\ReportTypeEnum;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateAppraisalRequestBasicRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->hasAdminAccess();
    }

    public function rules(): array
    {
        return [
            'client_name' => ['nullable', 'string', 'max:255'],
            'report_type' => ['nullable', Rule::enum(ReportTypeEnum::class)],
            'contract_sequence' => ['nullable', 'integer', 'min:1'],
            'contract_date' => ['nullable', 'date'],
            'contract_status' => ['nullable', Rule::enum(ContractStatusEnum::class)],
            'valuation_duration_days' => ['nullable', 'integer', 'min:1'],
            'offer_validity_days' => ['nullable', 'integer', 'min:1'],
            'billing_dpp_amount' => ['nullable', 'integer', 'min:1'],
            'user_request_note' => ['nullable', 'string'],
            'notes' => ['nullable', 'string'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $normalize = fn ($value) => is_string($value) && trim($value) === '' ? null : $value;

        $this->merge([
            'report_type' => $normalize($this->input('report_type')),
            'contract_sequence' => $normalize($this->input('contract_sequence')),
            'contract_date' => $normalize($this->input('contract_date')),
            'contract_status' => $normalize($this->input('contract_status')),
            'valuation_duration_days' => $normalize($this->input('valuation_duration_days')),
            'offer_validity_days' => $normalize($this->input('offer_validity_days')),
            'billing_dpp_amount' => $normalize($this->input('billing_dpp_amount')),
        ]);
    }
}

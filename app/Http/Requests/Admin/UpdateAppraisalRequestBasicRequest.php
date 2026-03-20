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
            'fee_total' => ['nullable', 'integer', 'min:1'],
            'fee_has_dp' => ['nullable', 'boolean'],
            'fee_dp_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
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
            'fee_total' => $normalize($this->input('fee_total')),
            'fee_dp_percent' => $normalize($this->input('fee_dp_percent')),
            'fee_has_dp' => $this->boolean('fee_has_dp'),
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

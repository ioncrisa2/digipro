<?php

namespace App\Http\Requests\Admin;

use App\Models\ReportSigner;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAppraisalReportConfigurationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->hasAdminAccess();
    }

    public function rules(): array
    {
        return [
            'report_reviewer_signer_id' => [
                'required',
                'integer',
                Rule::exists(ReportSigner::class, 'id')->where(fn ($query) => $query->where('role', 'reviewer')->where('is_active', true)),
            ],
            'report_public_appraiser_signer_id' => [
                'required',
                'integer',
                Rule::exists(ReportSigner::class, 'id')->where(fn ($query) => $query->where('role', 'public_appraiser')->where('is_active', true)),
            ],
        ];
    }
}

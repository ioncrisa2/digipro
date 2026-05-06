<?php

namespace App\Http\Requests\Admin;

use App\Models\ReportSigner;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAppraisalContractSignerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->hasAdminAccess();
    }

    public function rules(): array
    {
        return [
            'contract_public_appraiser_signer_id' => [
                'required',
                'integer',
                Rule::exists(ReportSigner::class, 'id')->where(function ($query) {
                    $query
                        ->where('role', 'public_appraiser')
                        ->where('is_active', true)
                        ->whereNotNull('email')
                        ->whereNotNull('user_id');
                }),
            ],
        ];
    }
}


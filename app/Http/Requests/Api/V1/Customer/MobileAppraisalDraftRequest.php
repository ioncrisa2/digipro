<?php

namespace App\Http\Requests\Api\V1\Customer;

use App\Enums\PurposeEnum;
use App\Enums\ReportTypeEnum;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class MobileAppraisalDraftRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'purpose' => ['sometimes', 'nullable', Rule::enum(PurposeEnum::class)],
            'report_type' => ['sometimes', 'nullable', Rule::enum(ReportTypeEnum::class)],
            'client_name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'client_address' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'client_spk_number' => ['sometimes', 'nullable', 'string', 'max:255'],
            'user_request_note' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'sertifikat_on_hand_confirmed' => ['sometimes', 'boolean'],
            'certificate_not_encumbered_confirmed' => ['sometimes', 'boolean'],
        ];
    }
}

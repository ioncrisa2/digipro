<?php

namespace App\Http\Requests\Api\V1\Customer;

use App\Enums\AppraisalStatusEnum;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class MobileAppraisalIndexRequest extends FormRequest
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
            'q' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', Rule::in([
                'all',
                ...array_map(
                    static fn (AppraisalStatusEnum $status): string => $status->value,
                    AppraisalStatusEnum::cases(),
                ),
            ])],
            'per_page' => ['nullable', 'integer', Rule::in([10, 25, 50])],
            'page' => ['nullable', 'integer', 'min:1'],
        ];
    }

    public function filters(): array
    {
        return [
            'q' => trim((string) $this->validated('q', '')),
            'status' => (string) $this->validated('status', 'all'),
            'per_page' => (int) $this->validated('per_page', 10),
        ];
    }
}

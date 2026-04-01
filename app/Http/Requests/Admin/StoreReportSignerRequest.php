<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreReportSignerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->hasAdminAccess();
    }

    public function rules(): array
    {
        return [
            'role' => ['required', Rule::in(['reviewer', 'public_appraiser'])],
            'name' => ['required', 'string', 'max:255'],
            'position_title' => ['nullable', 'string', 'max:255'],
            'title_suffix' => ['nullable', 'string', 'max:255'],
            'certification_number' => ['nullable', 'string', 'max:255'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $normalize = function (mixed $value): mixed {
            if (is_string($value)) {
                $value = trim($value);
            }

            return blank($value) ? null : $value;
        };

        $this->merge([
            'role' => $normalize($this->input('role')),
            'name' => $normalize($this->input('name')),
            'position_title' => $normalize($this->input('position_title')),
            'title_suffix' => $normalize($this->input('title_suffix')),
            'certification_number' => $normalize($this->input('certification_number')),
            'is_active' => $this->boolean('is_active', true),
        ]);
    }
}

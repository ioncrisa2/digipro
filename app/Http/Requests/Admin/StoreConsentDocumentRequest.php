<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreConsentDocumentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasAdminAccess() ?? false;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'status' => $this->input('status', 'draft'),
            'sections_json' => blank($this->input('sections_json')) ? null : $this->input('sections_json'),
        ]);
    }

    public function rules(): array
    {
        return [
            'code' => ['required', 'string', 'max:100'],
            'version' => ['required', 'string', 'max:50'],
            'title' => ['required', 'string', 'max:200'],
            'status' => ['required', Rule::in(['draft', 'archived'])],
            'checkbox_label' => ['nullable', 'string', 'max:255'],
            'sections_json' => ['required', 'json'],
        ];
    }
}

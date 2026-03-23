<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class StoreLegalDocumentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasAdminAccess() ?? false;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'is_active' => $this->boolean('is_active'),
            'effective_since' => blank($this->input('effective_since')) ? null : $this->input('effective_since'),
            'published_at' => blank($this->input('published_at')) ? null : $this->input('published_at'),
        ]);
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'company' => ['nullable', 'string', 'max:255'],
            'version' => ['nullable', 'string', 'max:50'],
            'effective_since' => ['nullable', 'date'],
            'content_html' => ['required', 'string'],
            'is_active' => ['required', 'boolean'],
            'published_at' => ['nullable', 'date'],
        ];
    }
}

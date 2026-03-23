<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreFeatureRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasAdminAccess() ?? false;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'icon' => $this->input('icon') === '__none' ? null : $this->input('icon'),
            'is_active' => $this->boolean('is_active'),
            'sort_order' => $this->input('sort_order', 0),
        ]);
    }

    public function rules(): array
    {
        return [
            'icon' => ['nullable', Rule::in(['TrendingUp', 'Zap', 'ShieldCheck', 'Smartphone', 'CheckCircle2', 'Star'])],
            'title' => ['required', 'string', 'max:150'],
            'description' => ['required', 'string'],
            'is_active' => ['required', 'boolean'],
            'sort_order' => ['required', 'integer', 'min:0'],
        ];
    }
}

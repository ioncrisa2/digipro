<?php

namespace App\Http\Requests\Admin;

use App\Models\User;
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
            'user_id' => ['nullable', 'integer', Rule::exists(User::class, 'id')],
            'role' => ['required', Rule::in(['reviewer', 'public_appraiser'])],
            'name' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:120'],
            'phone_number' => ['nullable', 'string', 'max:40'],
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

        $userId = $normalize($this->input('user_id'));
        $userId = is_numeric($userId) ? (int) $userId : null;

        $this->merge([
            'user_id' => $userId,
            'role' => $normalize($this->input('role')),
            'name' => $normalize($this->input('name')),
            'email' => $normalize($this->input('email')),
            'phone_number' => $normalize($this->input('phone_number')),
            'position_title' => $normalize($this->input('position_title')),
            'title_suffix' => $normalize($this->input('title_suffix')),
            'certification_number' => $normalize($this->input('certification_number')),
            'is_active' => $this->boolean('is_active', true),
        ]);
    }
}

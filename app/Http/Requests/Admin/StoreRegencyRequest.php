<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
class StoreRegencyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasAdminAccess() ?? false;
    }

    public function rules(): array
    {
        return [
            'id' => ['nullable', 'string', 'size:4'],
            'province_id' => ['required', 'exists:provinces,id'],
            'name' => ['required', 'string', 'max:255'],
        ];
    }
}

<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
class StoreProvinceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasAdminAccess() ?? false;
    }

    public function rules(): array
    {
        return [
            'id' => ['nullable', 'string', 'size:2'],
            'name' => ['required', 'string', 'max:255'],
        ];
    }
}

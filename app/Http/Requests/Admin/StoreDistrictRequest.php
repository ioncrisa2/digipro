<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
class StoreDistrictRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasAdminAccess() ?? false;
    }

    public function rules(): array
    {
        return [
            'id' => ['nullable', 'string', 'size:7'],
            'regency_id' => ['required', 'exists:regencies,id'],
            'name' => ['required', 'string', 'max:255'],
        ];
    }
}

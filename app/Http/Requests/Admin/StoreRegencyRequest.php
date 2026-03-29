<?php

namespace App\Http\Requests\Admin;

use App\Support\SystemNavigation;
use Illuminate\Foundation\Http\FormRequest;

class StoreRegencyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return SystemNavigation::hasSectionAccess($this->user(), SystemNavigation::MANAGE_ADMIN_MASTER_DATA);
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

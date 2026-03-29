<?php

namespace App\Http\Requests\Admin;

use App\Support\SystemNavigation;
use Illuminate\Foundation\Http\FormRequest;

class StoreProvinceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return SystemNavigation::hasSectionAccess($this->user(), SystemNavigation::MANAGE_ADMIN_MASTER_DATA);
    }

    public function rules(): array
    {
        return [
            'id' => ['nullable', 'string', 'size:2'],
            'name' => ['required', 'string', 'max:255'],
        ];
    }
}

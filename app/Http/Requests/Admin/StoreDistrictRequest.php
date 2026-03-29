<?php

namespace App\Http\Requests\Admin;

use App\Support\SystemNavigation;
use Illuminate\Foundation\Http\FormRequest;

class StoreDistrictRequest extends FormRequest
{
    public function authorize(): bool
    {
        return SystemNavigation::hasSectionAccess($this->user(), SystemNavigation::MANAGE_ADMIN_MASTER_DATA);
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

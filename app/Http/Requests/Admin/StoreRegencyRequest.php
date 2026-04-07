<?php

namespace App\Http\Requests\Admin;

use App\Support\SystemNavigation;

class StoreRegencyRequest extends SectionPermissionFormRequest
{
    protected function requiredSectionPermission(): string
    {
        return SystemNavigation::MANAGE_ADMIN_MASTER_DATA;
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

<?php

namespace App\Http\Requests\Admin;

use App\Support\SystemNavigation;

class StoreDistrictRequest extends SectionPermissionFormRequest
{
    protected function requiredSectionPermission(): string
    {
        return SystemNavigation::MANAGE_ADMIN_MASTER_DATA;
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

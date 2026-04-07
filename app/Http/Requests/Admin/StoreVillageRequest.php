<?php

namespace App\Http\Requests\Admin;

use App\Support\SystemNavigation;

class StoreVillageRequest extends SectionPermissionFormRequest
{
    protected function requiredSectionPermission(): string
    {
        return SystemNavigation::MANAGE_ADMIN_MASTER_DATA;
    }

    public function rules(): array
    {
        return [
            'id' => ['nullable', 'string', 'size:10'],
            'district_id' => ['required', 'exists:districts,id'],
            'name' => ['required', 'string', 'max:255'],
        ];
    }
}

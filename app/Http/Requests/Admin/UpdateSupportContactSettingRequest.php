<?php

namespace App\Http\Requests\Admin;

use App\Support\SystemNavigation;

class UpdateSupportContactSettingRequest extends AdminFormRequest
{
    public function authorize(): bool
    {
        return SystemNavigation::hasSectionAccess($this->user(), SystemNavigation::MANAGE_ADMIN_COMMUNICATIONS);
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'string', 'max:30'],
            'whatsapp' => ['nullable', 'string', 'max:30'],
            'email' => ['required', 'email', 'max:255'],
            'availability_label' => ['required', 'string', 'max:255'],
        ];
    }
}

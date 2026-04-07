<?php

namespace App\Http\Requests\Admin;

use App\Support\SystemNavigation;
use Illuminate\Foundation\Http\FormRequest;

abstract class SectionPermissionFormRequest extends FormRequest
{
    public function authorize(): bool
    {
        return SystemNavigation::hasSectionAccess($this->user(), $this->requiredSectionPermission());
    }

    abstract protected function requiredSectionPermission(): string;
}

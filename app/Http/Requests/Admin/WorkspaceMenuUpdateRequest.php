<?php

namespace App\Http\Requests\Admin;

use App\Support\SystemNavigation;
use Illuminate\Validation\Rule;

class WorkspaceMenuUpdateRequest extends AdminFormRequest
{
    public function rules(): array
    {
        return [
            'workspace_permissions' => ['array'],
            'workspace_permissions.*' => ['string', Rule::in(SystemNavigation::sectionPermissions())],
        ];
    }
}

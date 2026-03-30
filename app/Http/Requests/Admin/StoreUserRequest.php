<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Spatie\Permission\Models\Role;

class StoreUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasAdminAccess() ?? false;
    }

    public function rules(): array
    {
        $recordId = $this->route('user')?->id;
        $isCreate = $this->isMethod('post');

        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'email',
                'max:255',
                Rule::unique('users', 'email')->ignore($recordId),
            ],
            'password' => $isCreate
                ? ['required', 'string', 'min:8', 'max:255']
                : ['nullable', 'string', 'min:8', 'max:255'],
            'email_verified_at' => ['nullable', 'date'],
            'roles' => ['array'],
            'roles.*' => ['string', Rule::exists('roles', 'name'), Rule::in($this->manageableRoleNames())],
        ];
    }

    private function manageableRoleNames(): array
    {
        if ($this->user()?->hasRole((string) config('access-control.super_admin.name', 'super_admin'))) {
            return Role::query()->where('guard_name', 'web')->pluck('name')->all();
        }

        return ['customer'];
    }
}

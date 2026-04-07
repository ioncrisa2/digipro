<?php

namespace App\Support\Admin\MasterData;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Spatie\Permission\Models\Role;

class UserManagementService
{
    public function canManageUsersCreate(): bool
    {
        $user = auth()->user();

        return $user !== null && $user->hasAdminAccess();
    }

    public function roleSelectOptions(): array
    {
        return Role::query()
            ->where('guard_name', 'web')
            ->when(! $this->isSuperAdmin(auth()->user()), fn ($query) => $query->where('name', 'customer'))
            ->orderBy('name')
            ->get(['name'])
            ->map(fn (Role $role) => [
                'value' => $role->name,
                'label' => $role->name,
            ])
            ->values()
            ->all();
    }

    public function transformUserRow(User $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'role_names' => $user->roles->pluck('name')->values()->all(),
            'is_verified' => filled($user->email_verified_at),
            'created_at' => $user->created_at?->toIso8601String(),
            'show_url' => route('admin.master-data.users.show', $user),
            'edit_url' => route('admin.master-data.users.edit', $user),
            'destroy_url' => $this->canDeleteUser($user) ? route('admin.master-data.users.destroy', $user) : null,
        ];
    }

    public function showPayload(User $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'email_verified_at' => $user->email_verified_at?->toIso8601String(),
            'role_names' => $user->roles->pluck('name')->values()->all(),
            'created_at' => $user->created_at?->toIso8601String(),
            'updated_at' => $user->updated_at?->toIso8601String(),
        ];
    }

    public function manageableUsersQuery(): Builder
    {
        $query = User::query();

        if ($this->isSuperAdmin(auth()->user())) {
            return $query;
        }

        return $query
            ->whereHas('roles', fn ($roleQuery) => $roleQuery->where('name', 'customer'))
            ->whereDoesntHave('roles', fn ($roleQuery) => $roleQuery->where('name', '<>', 'customer'));
    }

    public function assignableRolesPayload(array $validated): array
    {
        if ($this->isSuperAdmin(auth()->user())) {
            return $validated['roles'] ?? [];
        }

        return ['customer'];
    }

    public function canManageUser(User $user): bool
    {
        $authUser = auth()->user();

        if (! ($authUser?->hasAdminAccess() ?? false)) {
            return false;
        }

        if ($this->isSuperAdmin($authUser)) {
            return true;
        }

        $roleNames = $user->roles()->pluck('name')->all();

        return $roleNames === ['customer'];
    }

    public function canDeleteUser(User $user): bool
    {
        $authUser = auth()->user();

        return $this->isSuperAdmin($authUser) && $authUser?->id !== $user->id;
    }

    public function summary(Builder $baseQuery): array
    {
        return [
            'total' => (clone $baseQuery)->count(),
            'verified' => (clone $baseQuery)->whereNotNull('email_verified_at')->count(),
            'admins' => (clone $baseQuery)->role(['admin', $this->superAdminRoleName()])->count(),
            'reviewers' => (clone $baseQuery)->role('Reviewer')->count(),
        ];
    }

    public function defaultCreateRoles(): array
    {
        return $this->isSuperAdmin(auth()->user()) ? [] : ['customer'];
    }

    private function superAdminRoleName(): string
    {
        return (string) config('access-control.super_admin.name', 'super_admin');
    }

    private function isSuperAdmin(?User $user): bool
    {
        return $user !== null && $user->hasRole($this->superAdminRoleName());
    }
}

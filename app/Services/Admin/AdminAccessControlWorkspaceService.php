<?php

namespace App\Services\Admin;

use App\Models\User;
use App\Support\SystemNavigation;
use Illuminate\Database\QueryException;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class AdminAccessControlWorkspaceService
{
    public function rolesIndexPayload(
        array $filters,
        int $perPage,
        bool $canCreate,
        bool $canDeleteAny,
        bool $canUpdate,
        bool $canDelete,
        string $superAdminRoleName,
    ): array {
        $records = Role::query()
            ->withCount('permissions')
            ->when($filters['q'] !== '', function ($query) use ($filters): void {
                $query->where('name', 'like', '%' . $filters['q'] . '%');
            })
            ->when($filters['guard'] !== 'all', fn ($query) => $query->where('guard_name', $filters['guard']))
            ->latest('updated_at')
            ->paginate($perPage)
            ->withQueryString();

        $records->through(fn (Role $role) => $this->roleRow($role, $canUpdate, $canDelete));

        return [
            'filters' => $filters,
            'guardOptions' => $this->roleGuardOptions(),
            'summary' => [
                'total' => Role::query()->count(),
                'web' => Role::query()->where('guard_name', 'web')->count(),
                'permissions' => Permission::query()->count(),
                'super_admins' => User::role($superAdminRoleName)->count(),
            ],
            'records' => $this->paginatedRecordsPayload($records),
            'canCreate' => $canCreate,
            'canDeleteAny' => $canDeleteAny,
            'createUrl' => route('admin.access-control.roles.create'),
            'workspaceMenusUrl' => route('admin.access-control.system-menus.index'),
        ];
    }

    public function rolesCreatePayload(): array
    {
        return [
            'mode' => 'create',
            'record' => [
                'name' => '',
                'guard_name' => 'web',
                'permissions' => [],
            ],
            'permissionGroups' => $this->rolePermissionGroups(),
            'indexUrl' => route('admin.access-control.roles.index'),
            'submitUrl' => route('admin.access-control.roles.store'),
        ];
    }

    public function saveRole(array $validated, ?Role $role = null): Role
    {
        $payload = [
            'name' => $validated['name'],
            'guard_name' => $validated['guard_name'] ?: 'web',
        ];

        if ($role === null) {
            $role = Role::query()->create($payload);
        } else {
            $role->forceFill($payload)->save();
        }

        $role->syncPermissions($validated['permissions'] ?? []);

        return $role;
    }

    public function roleShowPagePayload(Role $role, bool $canUpdate, bool $canDelete): array
    {
        $role->loadMissing('permissions:id,name,guard_name');

        return [
            'record' => $this->roleShowPayload($role),
            'canUpdate' => $canUpdate,
            'canDelete' => $canDelete,
            'indexUrl' => route('admin.access-control.roles.index'),
            'editUrl' => route('admin.access-control.roles.edit', $role),
            'deleteUrl' => route('admin.access-control.roles.destroy', $role),
            'workspaceMenuEditUrl' => route('admin.access-control.system-menus.edit', $role),
        ];
    }

    public function workspaceMenusIndexPayload(array $filters, int $perPage): array
    {
        $records = Role::query()
            ->with('permissions:id,name,guard_name')
            ->when($filters['q'] !== '', function ($query) use ($filters): void {
                $query->where('name', 'like', '%' . $filters['q'] . '%');
            })
            ->when($filters['guard'] !== 'all', fn ($query) => $query->where('guard_name', $filters['guard']))
            ->orderBy('name')
            ->paginate($perPage)
            ->withQueryString();

        $records->through(fn (Role $role) => $this->workspaceMenuRoleRow($role));

        return [
            'filters' => $filters,
            'guardOptions' => $this->roleGuardOptions(),
            'records' => $this->paginatedRecordsPayload($records),
            'summary' => [
                'roles' => Role::query()->count(),
                'sections' => count(SystemNavigation::menuManagementSections()),
                'default_reviewer_sections' => 5,
            ],
            'roleIndexUrl' => route('admin.access-control.roles.index'),
        ];
    }

    public function workspaceMenusEditPayload(Role $role): array
    {
        $role->loadMissing('permissions:id,name,guard_name');

        $selectedPermissions = $role->permissions
            ->pluck('name')
            ->intersect(SystemNavigation::sectionPermissions())
            ->values()
            ->all();

        return [
            'record' => [
                'id' => $role->id,
                'name' => $role->name,
                'guard_name' => $role->guard_name,
                'workspace_permissions' => $selectedPermissions,
            ],
            'sections' => SystemNavigation::menuManagementSections(),
            'indexUrl' => route('admin.access-control.system-menus.index'),
            'roleShowUrl' => route('admin.access-control.roles.show', $role),
            'submitUrl' => route('admin.access-control.system-menus.update', $role),
        ];
    }

    public function updateWorkspaceMenuPermissions(Role $role, array $validated): void
    {
        $selectedWorkspacePermissions = collect($validated['workspace_permissions'] ?? [])
            ->values()
            ->all();

        $remainingPermissions = $role->permissions
            ->pluck('name')
            ->reject(fn (string $permission) => in_array($permission, SystemNavigation::sectionPermissions(), true))
            ->values()
            ->all();

        $role->syncPermissions(array_values(array_unique([
            ...$remainingPermissions,
            ...$selectedWorkspacePermissions,
        ])));
    }

    public function rolesEditPayload(Role $role): array
    {
        $role->loadMissing('permissions:id,name,guard_name');

        return [
            'mode' => 'edit',
            'record' => [
                'id' => $role->id,
                'name' => $role->name,
                'guard_name' => $role->guard_name,
                'permissions' => $role->permissions->pluck('name')->values()->all(),
            ],
            'permissionGroups' => $this->rolePermissionGroups(),
            'indexUrl' => route('admin.access-control.roles.index'),
            'submitUrl' => route('admin.access-control.roles.update', $role),
        ];
    }

    public function deleteRole(Role $role): array
    {
        try {
            $role->delete();
        } catch (QueryException) {
            return [
                'type' => 'error',
                'message' => 'Role tidak bisa dihapus karena masih dipakai relasi lain.',
            ];
        }

        return [
            'type' => 'success',
            'message' => 'Role berhasil dihapus.',
        ];
    }

    private function roleGuardOptions(): array
    {
        return Role::query()
            ->distinct()
            ->orderBy('guard_name')
            ->pluck('guard_name')
            ->filter()
            ->values()
            ->map(fn (string $guard) => [
                'value' => $guard,
                'label' => $guard,
            ])
            ->all();
    }

    private function roleRow(Role $role, bool $canUpdate, bool $canDelete): array
    {
        return [
            'id' => $role->id,
            'name' => $role->name,
            'guard_name' => $role->guard_name,
            'permissions_count' => (int) ($role->permissions_count ?? 0),
            'updated_at' => $role->updated_at?->toIso8601String(),
            'show_url' => route('admin.access-control.roles.show', $role),
            'edit_url' => route('admin.access-control.roles.edit', $role),
            'destroy_url' => route('admin.access-control.roles.destroy', $role),
            'can_update' => $canUpdate,
            'can_delete' => $canDelete,
        ];
    }

    private function workspaceMenuRoleRow(Role $role): array
    {
        $workspacePermissions = $role->permissions
            ->pluck('name')
            ->intersect(SystemNavigation::sectionPermissions())
            ->values();

        $enabledSections = collect(SystemNavigation::menuManagementSections())
            ->filter(fn (array $section) => $workspacePermissions->contains($section['permission']))
            ->pluck('label')
            ->values()
            ->all();

        return [
            'id' => $role->id,
            'name' => $role->name,
            'guard_name' => $role->guard_name,
            'workspace_sections_count' => count($enabledSections),
            'workspace_sections' => $enabledSections,
            'edit_url' => route('admin.access-control.system-menus.edit', $role),
            'role_show_url' => route('admin.access-control.roles.show', $role),
        ];
    }

    private function roleShowPayload(Role $role): array
    {
        return [
            'id' => $role->id,
            'name' => $role->name,
            'guard_name' => $role->guard_name,
            'permissions_count' => $role->permissions->count(),
            'updated_at' => $role->updated_at?->toIso8601String(),
            'permission_groups' => $this->groupPermissions($role->permissions),
        ];
    }

    private function rolePermissionGroups(): array
    {
        $permissions = Permission::query()
            ->orderBy('name')
            ->get(['id', 'name', 'guard_name']);

        return $this->groupPermissions($permissions, includeSelectionData: true);
    }

    private function groupPermissions(iterable $permissions, bool $includeSelectionData = false): array
    {
        $workspaceRegistry = SystemNavigation::permissionRegistry();
        $knownPrefixes = [
            'force_delete_any',
            'force_delete',
            'delete_any',
            'restore_any',
            'view_any',
            'replicate',
            'reorder',
            'restore',
            'delete',
            'create',
            'update',
            'widget',
            'page',
            'view',
        ];

        $grouped = [];

        foreach ($permissions as $permission) {
            $name = is_string($permission) ? $permission : $permission->name;
            $guardName = is_string($permission) ? 'web' : $permission->guard_name;
            $matchedPrefix = 'other';
            $subject = $name;

            foreach ($knownPrefixes as $prefix) {
                $needle = $prefix . '_';
                if (str_starts_with($name, $needle)) {
                    $matchedPrefix = $prefix;
                    $subject = substr($name, strlen($needle));
                    break;
                }
            }

            if (isset($workspaceRegistry[$name])) {
                $matchedPrefix = 'workspace';
                $subject = $workspaceRegistry[$name]['title'];
            }

            $subjectKey = $subject;
            $grouped[$subjectKey] ??= [
                'key' => $subjectKey,
                'title' => Str::headline(str_replace('::', ' ', $subject)),
                'permissions' => [],
            ];

            $entry = [
                'name' => $name,
                'label' => $workspaceRegistry[$name]['label'] ?? Str::headline(str_replace(['::', '_'], [' ', ' '], $matchedPrefix)),
                'guard_name' => $guardName,
            ];

            if ($includeSelectionData) {
                $entry['value'] = $name;
            }

            $grouped[$subjectKey]['permissions'][] = $entry;
        }

        return collect($grouped)
            ->sortBy('title')
            ->map(function (array $group) {
                $group['permissions'] = collect($group['permissions'])
                    ->sortBy('label')
                    ->values()
                    ->all();

                return $group;
            })
            ->values()
            ->all();
    }

    private function paginatedRecordsPayload(object $records): array
    {
        return [
            'data' => $records->items(),
            'meta' => [
                'from' => $records->firstItem(),
                'to' => $records->lastItem(),
                'total' => $records->total(),
                'current_page' => $records->currentPage(),
                'last_page' => $records->lastPage(),
                'per_page' => $records->perPage(),
                'links' => $records->linkCollection()->toArray(),
            ],
        ];
    }
}

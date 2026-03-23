<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreRoleRequest;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Response;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class AccessControlController extends Controller
{
    public function rolesIndex(Request $request): Response
    {
        $this->authorizeRoleAbility('view_any_role');

        $filters = [
            'q' => trim((string) $request->query('q', '')),
            'guard' => (string) $request->query('guard', 'all'),
        ];

        $records = Role::query()
            ->withCount('permissions')
            ->when($filters['q'] !== '', function ($query) use ($filters): void {
                $query->where('name', 'like', '%' . $filters['q'] . '%');
            })
            ->when($filters['guard'] !== 'all', fn ($query) => $query->where('guard_name', $filters['guard']))
            ->latest('updated_at')
            ->paginate(15)
            ->withQueryString();

        $records->through(fn (Role $role) => $this->transformRoleRow($role));

        return inertia('Admin/Roles/Index', [
            'filters' => $filters,
            'guardOptions' => $this->roleGuardOptions(),
            'summary' => [
                'total' => Role::query()->count(),
                'web' => Role::query()->where('guard_name', 'web')->count(),
                'permissions' => Permission::query()->count(),
                'super_admins' => User::role($this->superAdminRoleName())->count(),
            ],
            'records' => $this->paginatedRecordsPayload($records),
            'canCreate' => $this->roleAbility('create_role'),
            'canDeleteAny' => $this->roleAbility('delete_any_role') || $this->roleAbility('delete_role'),
            'createUrl' => route('admin.access-control.roles.create'),
        ]);
    }

    public function rolesCreate(): Response
    {
        $this->authorizeRoleAbility('create_role');

        return inertia('Admin/Roles/Form', [
            'mode' => 'create',
            'record' => [
                'name' => '',
                'guard_name' => 'web',
                'permissions' => [],
            ],
            'permissionGroups' => $this->rolePermissionGroups(),
            'indexUrl' => route('admin.access-control.roles.index'),
            'submitUrl' => route('admin.access-control.roles.store'),
        ]);
    }

    public function rolesStore(StoreRoleRequest $request): RedirectResponse
    {
        $this->authorizeRoleAbility('create_role');

        $validated = $request->validated();

        $role = Role::query()->create([
            'name' => $validated['name'],
            'guard_name' => $validated['guard_name'] ?: 'web',
        ]);

        $role->syncPermissions($validated['permissions'] ?? []);

        return redirect()
            ->route('admin.access-control.roles.show', $role)
            ->with('success', 'Role berhasil ditambahkan.');
    }

    public function rolesShow(Role $role): Response
    {
        $this->authorizeRoleAbility('view_role');
        $role->loadMissing('permissions:id,name,guard_name');

        return inertia('Admin/Roles/Show', [
            'record' => $this->roleShowPayload($role),
            'canUpdate' => $this->roleAbility('update_role'),
            'canDelete' => $this->roleAbility('delete_role'),
            'indexUrl' => route('admin.access-control.roles.index'),
            'editUrl' => route('admin.access-control.roles.edit', $role),
            'deleteUrl' => route('admin.access-control.roles.destroy', $role),
        ]);
    }

    public function rolesEdit(Role $role): Response
    {
        $this->authorizeRoleAbility('update_role');
        $role->loadMissing('permissions:id,name,guard_name');

        return inertia('Admin/Roles/Form', [
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
        ]);
    }

    public function rolesUpdate(StoreRoleRequest $request, Role $role): RedirectResponse
    {
        $this->authorizeRoleAbility('update_role');

        $validated = $request->validated();

        $role->forceFill([
            'name' => $validated['name'],
            'guard_name' => $validated['guard_name'] ?: 'web',
        ])->save();

        $role->syncPermissions($validated['permissions'] ?? []);

        return redirect()
            ->route('admin.access-control.roles.show', $role)
            ->with('success', 'Role berhasil diperbarui.');
    }

    public function rolesDestroy(Role $role): RedirectResponse
    {
        $this->authorizeRoleAbility('delete_role');

        try {
            $role->delete();
        } catch (QueryException) {
            return redirect()
                ->route('admin.access-control.roles.index')
                ->with('error', 'Role tidak bisa dihapus karena masih dipakai relasi lain.');
        }

        return redirect()
            ->route('admin.access-control.roles.index')
            ->with('success', 'Role berhasil dihapus.');
    }

    private function roleAbility(string $ability): bool
    {
        $user = auth()->user();

        if ($user === null) {
            return false;
        }

        if ($user->hasRole($this->superAdminRoleName())) {
            return true;
        }

        return $user->can($ability);
    }

    private function authorizeRoleAbility(string $ability): void
    {
        abort_unless($this->roleAbility($ability), 403);
    }

    private function superAdminRoleName(): string
    {
        return (string) config('access-control.super_admin.name', 'super_admin');
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

    private function transformRoleRow(Role $role): array
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
            'can_update' => $this->roleAbility('update_role'),
            'can_delete' => $this->roleAbility('delete_role'),
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

            $subjectKey = $subject;
            $grouped[$subjectKey] ??= [
                'key' => $subjectKey,
                'title' => Str::headline(str_replace('::', ' ', $subject)),
                'permissions' => [],
            ];

            $entry = [
                'name' => $name,
                'label' => Str::headline(str_replace(['::', '_'], [' ', ' '], $matchedPrefix)),
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
                'links' => $records->linkCollection()->toArray(),
            ],
        ];
    }

    private function legacyRoleUrl(Role $role): ?string
    {
        return null;
    }
}

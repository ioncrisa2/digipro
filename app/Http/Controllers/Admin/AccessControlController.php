<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\RoleIndexRequest;
use App\Http\Requests\Admin\StoreRoleRequest;
use App\Http\Requests\Admin\WorkspaceMenuUpdateRequest;
use App\Services\Admin\AdminAccessControlWorkspaceService;
use Illuminate\Http\RedirectResponse;
use Inertia\Response;
use Spatie\Permission\Models\Role;

class AccessControlController extends Controller
{
    public function __construct(
        private readonly AdminAccessControlWorkspaceService $workspaceService,
    ) {
    }

    public function rolesIndex(RoleIndexRequest $request): Response
    {
        $this->authorizeRoleAbility('view_any_role');

        return inertia('Admin/Roles/Index', $this->workspaceService->rolesIndexPayload(
            $request->filters(),
            $request->perPage(),
            $this->roleAbility('create_role'),
            $this->roleAbility('delete_any_role') || $this->roleAbility('delete_role'),
            $this->roleAbility('update_role'),
            $this->roleAbility('delete_role'),
            $this->superAdminRoleName(),
        ));
    }

    public function rolesCreate(): Response
    {
        $this->authorizeRoleAbility('create_role');

        return inertia('Admin/Roles/Form', $this->workspaceService->rolesCreatePayload());
    }

    public function rolesStore(StoreRoleRequest $request): RedirectResponse
    {
        $this->authorizeRoleAbility('create_role');

        $role = $this->workspaceService->saveRole($request->validated());

        return redirect()
            ->route('admin.access-control.roles.show', $role)
            ->with('success', 'Role berhasil ditambahkan.');
    }

    public function rolesShow(Role $role): Response
    {
        $this->authorizeRoleAbility('view_role');

        return inertia('Admin/Roles/Show', $this->workspaceService->roleShowPagePayload(
            $role,
            $this->roleAbility('update_role'),
            $this->roleAbility('delete_role'),
        ));
    }

    public function workspaceMenusIndex(RoleIndexRequest $request): Response
    {
        $this->authorizeRoleAbility('view_any_role');

        return inertia('Admin/Roles/WorkspaceMenusIndex', $this->workspaceService
            ->workspaceMenusIndexPayload($request->filters(), $request->perPage()));
    }

    public function workspaceMenusEdit(Role $role): Response
    {
        $this->authorizeRoleAbility('update_role');

        return inertia('Admin/Roles/WorkspaceMenusForm', $this->workspaceService
            ->workspaceMenusEditPayload($role));
    }

    public function workspaceMenusUpdate(WorkspaceMenuUpdateRequest $request, Role $role): RedirectResponse
    {
        $this->authorizeRoleAbility('update_role');

        $this->workspaceService->updateWorkspaceMenuPermissions($role, $request->validated());

        return redirect()
            ->route('admin.access-control.system-menus.edit', $role)
            ->with('success', 'Akses menu sistem role berhasil diperbarui.');
    }

    public function rolesEdit(Role $role): Response
    {
        $this->authorizeRoleAbility('update_role');

        return inertia('Admin/Roles/Form', $this->workspaceService->rolesEditPayload($role));
    }

    public function rolesUpdate(StoreRoleRequest $request, Role $role): RedirectResponse
    {
        $this->authorizeRoleAbility('update_role');

        $this->workspaceService->saveRole($request->validated(), $role);

        return redirect()
            ->route('admin.access-control.roles.show', $role)
            ->with('success', 'Role berhasil diperbarui.');
    }

    public function rolesDestroy(Role $role): RedirectResponse
    {
        $this->authorizeRoleAbility('delete_role');

        $result = $this->workspaceService->deleteRole($role);

        return redirect()
            ->route('admin.access-control.roles.index')
            ->with($result['type'], $result['message']);
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
}

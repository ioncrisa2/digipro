<?php

namespace App\Services\Admin;

use App\Models\User;
use App\Support\Admin\MasterData\UserManagementService;

class AdminMasterDataWorkspaceService
{
    public function __construct(
        private readonly UserManagementService $userManagement,
    ) {
    }

    public function usersIndexPayload(array $filters, int $perPage): array
    {
        $baseQuery = $this->userManagement->manageableUsersQuery();

        $records = $this->userManagement->manageableUsersQuery()
            ->with('roles:id,name')
            ->when($filters['q'] !== '', function ($query) use ($filters): void {
                $query->where(function ($innerQuery) use ($filters): void {
                    $innerQuery
                        ->where('name', 'like', '%' . $filters['q'] . '%')
                        ->orWhere('email', 'like', '%' . $filters['q'] . '%');
                });
            })
            ->when($filters['role'] !== 'all', fn ($query) => $query->role($filters['role']))
            ->when($filters['verified'] === 'verified', fn ($query) => $query->whereNotNull('email_verified_at'))
            ->when($filters['verified'] === 'unverified', fn ($query) => $query->whereNull('email_verified_at'))
            ->latest('created_at')
            ->paginate($perPage)
            ->withQueryString();

        $records->through(fn (User $user) => $this->userManagement->transformUserRow($user));

        return [
            'filters' => $filters,
            'roleOptions' => $this->userManagement->roleSelectOptions(),
            'verifiedOptions' => [
                ['value' => 'all', 'label' => 'Semua Status'],
                ['value' => 'verified', 'label' => 'Verified'],
                ['value' => 'unverified', 'label' => 'Belum Verified'],
            ],
            'summary' => $this->userManagement->summary($baseQuery),
            'records' => $this->paginatedRecordsPayload($records),
            'canCreate' => $this->userManagement->canManageUsersCreate(),
            'createUrl' => route('admin.master-data.users.create'),
        ];
    }

    public function usersCreatePayload(): array
    {
        return [
            'mode' => 'create',
            'record' => [
                'name' => '',
                'email' => '',
                'email_verified_at' => '',
                'roles' => $this->userManagement->defaultCreateRoles(),
            ],
            'roleOptions' => $this->userManagement->roleSelectOptions(),
            'indexUrl' => route('admin.master-data.users.index'),
            'submitUrl' => route('admin.master-data.users.store'),
        ];
    }

    public function createUser(array $validated): User
    {
        $user = new User();
        $user->forceFill([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => $validated['password'],
            'email_verified_at' => $validated['email_verified_at'] ?? null,
        ]);
        $user->save();

        $user->syncRoles($this->userManagement->assignableRolesPayload($validated));

        return $user;
    }

    public function usersShowPayload(User $user): array
    {
        $user->loadMissing('roles:id,name');

        return [
            'record' => $this->userManagement->showPayload($user),
            'indexUrl' => route('admin.master-data.users.index'),
            'editUrl' => route('admin.master-data.users.edit', $user),
            'destroyUrl' => $this->userManagement->canDeleteUser($user)
                ? route('admin.master-data.users.destroy', $user)
                : null,
        ];
    }

    public function usersEditPayload(User $user): array
    {
        $user->loadMissing('roles:id,name');

        return [
            'mode' => 'edit',
            'record' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'email_verified_at' => $user->email_verified_at?->format('Y-m-d\\TH:i'),
                'roles' => $user->roles->pluck('name')->values()->all(),
            ],
            'roleOptions' => $this->userManagement->roleSelectOptions(),
            'indexUrl' => route('admin.master-data.users.index'),
            'submitUrl' => route('admin.master-data.users.update', $user),
        ];
    }

    public function updateUser(User $user, array $validated): void
    {
        $payload = [
            'name' => $validated['name'],
            'email' => $validated['email'],
            'email_verified_at' => $validated['email_verified_at'] ?? null,
        ];

        if (filled($validated['password'] ?? null)) {
            $payload['password'] = $validated['password'];
        }

        $user->forceFill($payload)->save();
        $user->syncRoles($this->userManagement->assignableRolesPayload($validated));
    }

    public function deleteUser(User $user): void
    {
        $user->delete();
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

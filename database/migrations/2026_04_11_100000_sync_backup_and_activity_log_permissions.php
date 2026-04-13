<?php

use App\Support\SystemNavigation;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('permissions') || ! Schema::hasTable('roles')) {
            return;
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $permissions = [
            SystemNavigation::MANAGE_ADMIN_BACKUPS,
            SystemNavigation::MANAGE_ADMIN_ACTIVITY_LOGS,
        ];

        foreach ($permissions as $permissionName) {
            Permission::findOrCreate($permissionName, 'web');
        }

        $superAdminRole = Role::findOrCreate((string) config('access-control.super_admin.name', 'super_admin'), 'web');
        $superAdminRole->givePermissionTo($permissions);

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        if (! Schema::hasTable('permissions')) {
            return;
        }

        Permission::query()
            ->whereIn('name', [
                SystemNavigation::MANAGE_ADMIN_BACKUPS,
                SystemNavigation::MANAGE_ADMIN_ACTIVITY_LOGS,
            ])
            ->where('guard_name', 'web')
            ->delete();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};

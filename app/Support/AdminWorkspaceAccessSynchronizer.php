<?php

namespace App\Support;

use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class AdminWorkspaceAccessSynchronizer
{
    public static function sync(string $guardName = 'web'): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (SystemNavigation::sectionPermissions() as $permissionName) {
            Permission::findOrCreate($permissionName, $guardName);
        }

        $reviewerRole = Role::findOrCreate('Reviewer', $guardName);
        $reviewerRole->givePermissionTo([
            SystemNavigation::ACCESS_REVIEWER_DASHBOARD,
            SystemNavigation::MANAGE_REVIEWER_REVIEWS,
            SystemNavigation::MANAGE_REVIEWER_COMPARABLES,
            SystemNavigation::MANAGE_ADMIN_REF_GUIDELINES,
            SystemNavigation::MANAGE_ADMIN_MASTER_DATA,
        ]);

        $adminRole = Role::findOrCreate('admin', $guardName);
        $adminRole->givePermissionTo(SystemNavigation::adminSectionPermissions());

        $superAdminRoleName = (string) config('access-control.super_admin.name', 'super_admin');
        $superAdminRole = Role::findOrCreate($superAdminRoleName, $guardName);
        $superAdminRole->givePermissionTo(SystemNavigation::sectionPermissions());

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}

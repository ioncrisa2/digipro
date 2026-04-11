<?php

namespace App\Support;

use App\Models\User;

class SystemNavigation
{
    public const ACCESS_REVIEWER_DASHBOARD = 'access_reviewer_dashboard';
    public const MANAGE_REVIEWER_REVIEWS = 'manage_reviewer_reviews';
    public const MANAGE_REVIEWER_COMPARABLES = 'manage_reviewer_comparables';

    public const ACCESS_ADMIN_DASHBOARD = 'access_admin_dashboard';
    public const MANAGE_ADMIN_APPRAISAL_REQUESTS = 'manage_admin_appraisal_requests';
    public const MANAGE_ADMIN_FINANCE = 'manage_admin_finance';
    public const MANAGE_ADMIN_MASTER_DATA = 'manage_admin_master_data';
    public const MANAGE_ADMIN_MASTER_DATA_USERS = 'manage_admin_master_data_users';
    public const MANAGE_ADMIN_REF_GUIDELINES = 'manage_admin_ref_guidelines';
    public const MANAGE_ADMIN_ACCESS_CONTROL = 'manage_admin_access_control';
    public const MANAGE_ADMIN_CONTENT = 'manage_admin_content';
    public const MANAGE_ADMIN_COMMUNICATIONS = 'manage_admin_communications';
    public const MANAGE_ADMIN_BACKUPS = 'manage_admin_backups';

    public static function reviewerSectionPermissions(): array
    {
        return SystemPermissionRegistry::reviewerSectionPermissions();
    }

    public static function adminSectionPermissions(): array
    {
        return SystemPermissionRegistry::adminSectionPermissions();
    }

    public static function sectionPermissions(): array
    {
        return SystemPermissionRegistry::sectionPermissions();
    }

    public static function sectionNav(): array
    {
        return SystemSectionNavigationRegistry::items();
    }

    public static function permissionRegistry(): array
    {
        return SystemPermissionRegistry::permissionRegistry();
    }

    public static function menuManagementSections(): array
    {
        return SystemMenuManagementRegistry::sections();
    }

    public static function permissionsForUser(?User $user): array
    {
        return SystemNavigationAccess::permissionsForUser($user);
    }

    public static function hasSectionAccess(?User $user, string $permission): bool
    {
        return SystemNavigationAccess::hasSectionAccess($user, $permission);
    }

    public static function hasContextAccess(?User $user, string $context): bool
    {
        return SystemNavigationAccess::hasContextAccess($user, $context);
    }

    public static function navForUser(?User $user, string $context): array
    {
        return SystemNavigationAccess::navForUser($user, $context);
    }

    public static function firstAccessibleRouteName(?User $user, string $context): ?string
    {
        return SystemNavigationAccess::firstAccessibleRouteName($user, $context);
    }
}

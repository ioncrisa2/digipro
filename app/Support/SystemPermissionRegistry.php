<?php

namespace App\Support;

class SystemPermissionRegistry
{
    public static function reviewerSectionPermissions(): array
    {
        return [
            SystemNavigation::ACCESS_REVIEWER_DASHBOARD,
            SystemNavigation::MANAGE_REVIEWER_REVIEWS,
            SystemNavigation::MANAGE_REVIEWER_COMPARABLES,
        ];
    }

    public static function adminSectionPermissions(): array
    {
        return [
            SystemNavigation::ACCESS_ADMIN_DASHBOARD,
            SystemNavigation::MANAGE_ADMIN_APPRAISAL_REQUESTS,
            SystemNavigation::MANAGE_ADMIN_FINANCE,
            SystemNavigation::MANAGE_ADMIN_MASTER_DATA,
            SystemNavigation::MANAGE_ADMIN_MASTER_DATA_USERS,
            SystemNavigation::MANAGE_ADMIN_REF_GUIDELINES,
            SystemNavigation::MANAGE_ADMIN_ACCESS_CONTROL,
            SystemNavigation::MANAGE_ADMIN_CONTENT,
            SystemNavigation::MANAGE_ADMIN_COMMUNICATIONS,
        ];
    }

    public static function sectionPermissions(): array
    {
        return [
            ...self::reviewerSectionPermissions(),
            ...self::adminSectionPermissions(),
        ];
    }

    public static function permissionRegistry(): array
    {
        return [
            SystemNavigation::ACCESS_REVIEWER_DASHBOARD => [
                'title' => 'Reviewer Workspace',
                'label' => 'Access Reviewer Dashboard',
            ],
            SystemNavigation::MANAGE_REVIEWER_REVIEWS => [
                'title' => 'Reviewer Workspace',
                'label' => 'Manage Reviewer Reviews',
            ],
            SystemNavigation::MANAGE_REVIEWER_COMPARABLES => [
                'title' => 'Reviewer Workspace',
                'label' => 'Manage Reviewer Comparables',
            ],
            SystemNavigation::ACCESS_ADMIN_DASHBOARD => [
                'title' => 'Admin Workspace',
                'label' => 'Access Admin Dashboard',
            ],
            SystemNavigation::MANAGE_ADMIN_APPRAISAL_REQUESTS => [
                'title' => 'Admin Workspace',
                'label' => 'Manage Admin Appraisal Requests',
            ],
            SystemNavigation::MANAGE_ADMIN_FINANCE => [
                'title' => 'Admin Workspace',
                'label' => 'Manage Admin Finance',
            ],
            SystemNavigation::MANAGE_ADMIN_MASTER_DATA => [
                'title' => 'System Menu',
                'label' => 'Manage Master Data',
            ],
            SystemNavigation::MANAGE_ADMIN_MASTER_DATA_USERS => [
                'title' => 'System Menu',
                'label' => 'Manage Registered Users',
            ],
            SystemNavigation::MANAGE_ADMIN_REF_GUIDELINES => [
                'title' => 'System Menu',
                'label' => 'Manage Reference Guidelines',
            ],
            SystemNavigation::MANAGE_ADMIN_ACCESS_CONTROL => [
                'title' => 'Admin Workspace',
                'label' => 'Manage Admin Access Control',
            ],
            SystemNavigation::MANAGE_ADMIN_CONTENT => [
                'title' => 'Admin Workspace',
                'label' => 'Manage Admin Content',
            ],
            SystemNavigation::MANAGE_ADMIN_COMMUNICATIONS => [
                'title' => 'Admin Workspace',
                'label' => 'Manage Admin Communications',
            ],
        ];
    }
}

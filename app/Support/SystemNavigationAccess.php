<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Arr;

class SystemNavigationAccess
{
    public static function permissionsForUser(?User $user): array
    {
        if (! $user) {
            return [];
        }

        $explicitPermissions = array_values(array_filter(
            SystemPermissionRegistry::sectionPermissions(),
            fn (string $permission): bool => $user->can($permission)
        ));

        if ($explicitPermissions !== []) {
            return $explicitPermissions;
        }

        if ($user->isSuperAdmin()) {
            return SystemPermissionRegistry::sectionPermissions();
        }

        if ($user->hasRole('admin')) {
            return SystemPermissionRegistry::adminSectionPermissions();
        }

        if ($user->hasRole('Reviewer')) {
            return array_values(array_unique([
                ...SystemPermissionRegistry::reviewerSectionPermissions(),
                SystemNavigation::MANAGE_ADMIN_REF_GUIDELINES,
                SystemNavigation::MANAGE_ADMIN_MASTER_DATA,
            ]));
        }

        return [];
    }

    public static function hasSectionAccess(?User $user, string $permission): bool
    {
        return in_array($permission, self::permissionsForUser($user), true);
    }

    public static function hasContextAccess(?User $user, string $context): bool
    {
        return self::navForUser($user, $context) !== [];
    }

    public static function navForUser(?User $user, string $context): array
    {
        return self::filterNav(SystemSectionNavigationRegistry::items(), self::permissionsForUser($user), $context);
    }

    public static function firstAccessibleRouteName(?User $user, string $context): ?string
    {
        $nav = self::navForUser($user, $context);

        foreach ($nav as $item) {
            if (! empty($item['subItems'])) {
                $firstChild = Arr::first($item['subItems']);

                if ($firstChild && ! empty($firstChild['routeName'])) {
                    return $firstChild['routeName'];
                }
            }

            if (! empty($item['routeName'])) {
                return $item['routeName'];
            }
        }

        return null;
    }

    private static function filterNav(array $items, array $permissions, string $context): array
    {
        $filtered = [];

        foreach ($items as $item) {
            $surface = $item['surface'] ?? 'shared';

            if (! in_array($surface, [$context, 'shared'], true)) {
                continue;
            }

            $requiredPermission = $item['requiredPermission'] ?? null;

            if ($requiredPermission && ! in_array($requiredPermission, $permissions, true)) {
                continue;
            }

            if (! empty($item['subItems'])) {
                $item['subItems'] = self::filterNav($item['subItems'], $permissions, $context);

                if ($item['subItems'] === []) {
                    continue;
                }
            }

            $filtered[] = $item;
        }

        return array_values($filtered);
    }
}

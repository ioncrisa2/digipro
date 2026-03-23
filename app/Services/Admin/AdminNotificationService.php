<?php

namespace App\Services\Admin;

use App\Models\User;
use App\Notifications\AdminActionNotification;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Notification;
use Spatie\Permission\Models\Role;

class AdminNotificationService
{
    public function notifyAdmins(
        string $title,
        string $message,
        ?string $url = null,
        ?string $icon = null,
        ?int $excludeUserId = null,
        string $actionLabel = 'Lihat',
    ): void {
        $recipients = $this->recipients($excludeUserId);

        if ($recipients->isEmpty()) {
            return;
        }

        Notification::send(
            $recipients,
            new AdminActionNotification($title, $message, $url, $actionLabel, $icon),
        );
    }

    public function recipients(?int $excludeUserId = null): Collection
    {
        $guardName = config('auth.defaults.guard', 'web');
        $roleCandidates = array_values(array_filter([
            config('access-control.super_admin.enabled', true)
                ? config('access-control.super_admin.name', 'super_admin')
                : null,
            'admin',
        ]));

        $existingRoleNames = Role::query()
            ->whereIn('name', $roleCandidates)
            ->where('guard_name', $guardName)
            ->pluck('name')
            ->values()
            ->all();

        if (empty($existingRoleNames)) {
            return collect();
        }

        return User::query()
            ->role($existingRoleNames, $guardName)
            ->when($excludeUserId !== null, fn ($query) => $query->whereKeyNot($excludeUserId))
            ->get();
    }
}

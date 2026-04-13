<?php

use App\Models\ActivityLog;
use App\Models\User;
use App\Support\AdminWorkspaceAccessSynchronizer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Role::findOrCreate('customer', 'web');
    AdminWorkspaceAccessSynchronizer::sync();
});

function createActivityLogAdmin(): User
{
    $user = User::factory()->create([
        'email_verified_at' => now(),
    ]);

    $user->assignRole('admin');

    return $user;
}

function createActivityLogSuperAdmin(): User
{
    $user = User::factory()->create([
        'email_verified_at' => now(),
    ]);

    $user->assignRole('super_admin');

    return $user;
}

it('allows super admin users to access the activity log workspace', function () {
    $superAdmin = createActivityLogSuperAdmin();

    $log = ActivityLog::query()->create([
        'user_id' => $superAdmin->id,
        'event_type' => 'visit',
        'workspace' => 'admin',
        'action_label' => 'Open dashboard',
        'route_name' => 'admin.dashboard',
        'method' => 'GET',
        'path' => '/admin',
        'status_code' => 200,
        'occurred_at' => now(),
    ]);

    $this
        ->actingAs($superAdmin)
        ->get(route('admin.activity-logs.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Admin/ActivityLogs/Index')
            ->where('records.data.0.id', $log->id));

    $this
        ->actingAs($superAdmin)
        ->get(route('admin.activity-logs.show', $log))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Admin/ActivityLogs/Show')
            ->where('record.id', $log->id)
            ->where('record.actor.id', $superAdmin->id));
});

it('blocks normal admin users from the activity log workspace', function () {
    $admin = createActivityLogAdmin();

    $this
        ->actingAs($admin)
        ->get(route('admin.activity-logs.index'))
        ->assertForbidden();
});

it('records authenticated profile page visits in the activity log middleware', function () {
    $user = User::factory()->create([
        'email_verified_at' => now(),
    ]);

    $user->assignRole('customer');

    $this
        ->actingAs($user)
        ->get(route('profile.edit'))
        ->assertOk();

    $log = ActivityLog::query()->latest('id')->first();

    expect($log)->not->toBeNull();
    expect($log->user_id)->toBe($user->id);
    expect($log->event_type)->toBe('visit');
    expect($log->workspace)->toBe('account');
    expect($log->route_name)->toBe('profile.edit');
    expect($log->method)->toBe('GET');
    expect($log->status_code)->toBe(200);
});

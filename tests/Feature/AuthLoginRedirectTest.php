<?php

use App\Models\User;
use App\Support\AdminWorkspaceAccessSynchronizer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Role::findOrCreate('customer', 'web');
    Role::findOrCreate('Reviewer', 'web');
    Role::findOrCreate('admin', 'web');

    AdminWorkspaceAccessSynchronizer::sync();
});

it('ignores a stale reviewer intended URL when a customer logs in', function (): void {
    $customer = User::factory()->create([
        'email' => 'customer@example.com',
    ]);
    $customer->assignRole('customer');

    $this
        ->withSession(['url.intended' => route('reviewer.dashboard')])
        ->post(route('login.proccess'), [
            'email' => $customer->email,
            'password' => 'password',
        ])
        ->assertRedirect(route('dashboard'));
});

it('ignores a stale reviewer intended URL when an admin logs in', function (): void {
    $admin = User::factory()->create([
        'email' => 'admin@example.com',
    ]);
    $admin->assignRole('admin');

    $this
        ->withSession(['url.intended' => route('reviewer.dashboard')])
        ->post(route('login.proccess'), [
            'email' => $admin->email,
            'password' => 'password',
        ])
        ->assertRedirect(route('admin.dashboard'));
});

it('keeps a reviewer intended URL when a reviewer logs in', function (): void {
    $reviewer = User::factory()->create([
        'email' => 'reviewer@example.com',
    ]);
    $reviewer->assignRole('Reviewer');

    $this
        ->withSession(['url.intended' => route('reviewer.assets.index')])
        ->post(route('login.proccess'), [
            'email' => $reviewer->email,
            'password' => 'password',
        ])
        ->assertRedirect(route('reviewer.assets.index'));
});

<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Role::findOrCreate('customer', 'web');
    Role::findOrCreate('admin', 'web');
});

it('returns json for unauthenticated mobile customer api requests', function (): void {
    $this
        ->getJson('/api/v1/customer/status')
        ->assertUnauthorized()
        ->assertJson([
            'message' => 'Unauthenticated.',
        ]);
});

it('rejects tokens without the mobile customer ability', function (): void {
    $user = User::factory()->create();
    $user->assignRole('customer');
    $token = $user->createToken('mobile-test', ['mobile:other'])->plainTextToken;

    $this
        ->withToken($token)
        ->getJson('/api/v1/customer/status')
        ->assertForbidden();
});

it('rejects unverified users even when their token is valid', function (): void {
    $user = User::factory()->unverified()->create();
    $user->assignRole('customer');
    $token = $user->createToken('mobile-test', ['mobile:customer'])->plainTextToken;

    $this
        ->withToken($token)
        ->getJson('/api/v1/customer/status')
        ->assertForbidden();
});

it('rejects api users without the customer role', function (): void {
    $user = User::factory()->create();
    $user->assignRole('admin');
    $token = $user->createToken('mobile-test', ['mobile:customer'])->plainTextToken;

    $this
        ->withToken($token)
        ->getJson('/api/v1/customer/status')
        ->assertForbidden();
});

it('allows verified customers with a mobile customer token', function (): void {
    $user = User::factory()->create([
        'email' => 'customer@example.com',
    ]);
    $user->assignRole('customer');
    $token = $user->createToken('mobile-test', ['mobile:customer'])->plainTextToken;

    $this
        ->withToken($token)
        ->getJson('/api/v1/customer/status')
        ->assertSuccessful()
        ->assertJsonPath('data.status', 'ok')
        ->assertJsonPath('data.api_version', 'v1')
        ->assertJsonPath('data.user.email', 'customer@example.com')
        ->assertJsonPath('data.user.email_verified', true)
        ->assertJsonPath('message', 'Mobile API is ready.');
});

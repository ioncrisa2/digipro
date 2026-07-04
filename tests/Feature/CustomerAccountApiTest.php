<?php

use App\Models\District;
use App\Models\Province;
use App\Models\Regency;
use App\Models\User;
use App\Models\Village;
use App\Notifications\MobileVerifyEmailNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Role::findOrCreate('customer', 'web');
    Storage::fake('public');
});

it('protects mobile profile and notification mutation endpoints', function (): void {
    $this->putJson('/api/v1/profile')->assertUnauthorized();
    $this->putJson('/api/v1/profile/password')->assertUnauthorized();
    $this->postJson('/api/v1/profile/password/verify')->assertUnauthorized();
    $this->postJson('/api/v1/notifications/read-all')->assertUnauthorized();
});

it('updates a customer profile and requires mobile email reverification', function (): void {
    [$user, $token] = customerAccountApiUser();
    customerAccountApiLocations();
    Notification::fake();

    $this->withToken($token)
        ->putJson('/api/v1/profile', customerAccountApiProfilePayload([
            'email' => 'mobile-profile@example.com',
        ]))
        ->assertOk()
        ->assertJsonPath('data.email', 'mobile-profile@example.com')
        ->assertJsonPath('data.email_verified', false)
        ->assertJsonPath('data.billing.village.id', '3171010001')
        ->assertJsonPath('data.profile_complete', true);

    $user->refresh();
    expect($user->email)->toBe('mobile-profile@example.com')
        ->and($user->email_verified_at)->toBeNull();
    Notification::assertSentTo($user, MobileVerifyEmailNotification::class);
});

it('rejects a mismatched billing location hierarchy', function (): void {
    [, $token] = customerAccountApiUser();
    customerAccountApiLocations();
    Province::query()->create(['id' => '32', 'name' => 'Jawa Barat']);

    $this->withToken($token)
        ->putJson('/api/v1/profile', customerAccountApiProfilePayload([
            'billing_province_id' => '32',
        ]))
        ->assertUnprocessable()
        ->assertJsonValidationErrors('billing_regency_id');
});

it('returns validated cascading location options', function (): void {
    [, $token] = customerAccountApiUser();
    customerAccountApiLocations();

    $this->withToken($token)
        ->getJson('/api/v1/profile/location-options?type=regencies&province_id=31')
        ->assertOk()
        ->assertJsonPath('data.0.value', '3171');

    $this->withToken($token)
        ->getJson('/api/v1/profile/location-options?type=regencies')
        ->assertUnprocessable()
        ->assertJsonValidationErrors('province_id');
});

it('verifies and updates the current password', function (): void {
    [$user, $token] = customerAccountApiUser();

    $this->withToken($token)
        ->postJson('/api/v1/profile/password/verify', ['current_password' => 'wrong-password'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('current_password');

    $this->withToken($token)
        ->postJson('/api/v1/profile/password/verify', ['current_password' => 'password'])
        ->assertOk()
        ->assertJsonPath('data.valid', true);

    $this->withToken($token)
        ->putJson('/api/v1/profile/password', [
            'current_password' => 'password',
            'password' => 'new-password-123',
            'password_confirmation' => 'new-password-123',
        ])
        ->assertOk();

    expect(Hash::check('new-password-123', $user->refresh()->password))->toBeTrue();
});

it('replaces and removes the authenticated customer avatar', function (): void {
    [$user, $token] = customerAccountApiUser(['avatar_url' => 'avatars/old.png']);
    Storage::disk('public')->put('avatars/old.png', 'old-avatar');

    $response = $this->withToken($token)
        ->post('/api/v1/profile/avatar', [
            'avatar' => UploadedFile::fake()->image('avatar.webp', 300, 300),
        ], ['Accept' => 'application/json'])
        ->assertOk();

    $newPath = $user->refresh()->getRawOriginal('avatar_url');
    expect($newPath)->not->toBeNull();
    Storage::disk('public')->assertExists($newPath);
    Storage::disk('public')->assertMissing('avatars/old.png');
    $response->assertJsonPath('data.avatar_url', Storage::disk('public')->url($newPath));

    $this->withToken($token)->deleteJson('/api/v1/profile/avatar')->assertOk();
    expect($user->refresh()->avatar_url)->toBeNull();
    Storage::disk('public')->assertMissing($newPath);
});

it('marks only owned notifications as read', function (): void {
    [$user, $token] = customerAccountApiUser();
    [$other] = customerAccountApiUser();
    $owned = customerAccountApiNotification($user, 'Owned notification');
    $secondOwned = customerAccountApiNotification($user, 'Second notification');
    $foreign = customerAccountApiNotification($other, 'Foreign notification');

    $this->withToken($token)
        ->postJson("/api/v1/notifications/{$foreign->id}/read")
        ->assertNotFound();

    $this->withToken($token)
        ->postJson("/api/v1/notifications/{$owned->id}/read")
        ->assertOk()
        ->assertJsonPath('data.read', true)
        ->assertJsonPath('unread_count', 1);

    $this->withToken($token)
        ->postJson('/api/v1/notifications/read-all')
        ->assertOk()
        ->assertJsonPath('data.updated_count', 1)
        ->assertJsonPath('data.unread_count', 0);

    expect($secondOwned->refresh()->read_at)->not->toBeNull()
        ->and($foreign->refresh()->read_at)->toBeNull();
});

function customerAccountApiUser(array $attributes = []): array
{
    $user = User::factory()->create($attributes);
    $user->assignRole('customer');

    return [$user, $user->createToken('customer-account-api', ['mobile:customer'])->plainTextToken];
}

function customerAccountApiLocations(): void
{
    Province::query()->create(['id' => '31', 'name' => 'DKI Jakarta']);
    Regency::query()->create(['id' => '3171', 'province_id' => '31', 'name' => 'Jakarta Selatan']);
    District::query()->create(['id' => '3171010', 'regency_id' => '3171', 'name' => 'Tebet']);
    Village::query()->create(['id' => '3171010001', 'district_id' => '3171010', 'name' => 'Tebet Barat']);
}

function customerAccountApiProfilePayload(array $overrides = []): array
{
    return array_merge([
        'name' => 'Customer Mobile',
        'email' => 'customer@example.com',
        'phone_number' => '081234567890',
        'billing_recipient_name' => 'Finance Mobile',
        'billing_province_id' => '31',
        'billing_regency_id' => '3171',
        'billing_district_id' => '3171010',
        'billing_village_id' => '3171010001',
        'billing_address_detail' => 'Jl. Mobile No. 10',
    ], $overrides);
}

function customerAccountApiNotification(User $user, string $title): DatabaseNotification
{
    return DatabaseNotification::query()->create([
        'id' => (string) Str::uuid(),
        'type' => 'TestNotification',
        'notifiable_type' => User::class,
        'notifiable_id' => $user->id,
        'data' => ['title' => $title, 'message' => $title],
    ]);
}

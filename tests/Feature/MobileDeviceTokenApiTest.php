<?php

use App\Models\MobileDeviceToken;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Role::findOrCreate('customer', 'web');
});

it('protects device token endpoints', function (): void {
    $this->postJson('/api/v1/notifications/device-token')->assertUnauthorized();
    $this->deleteJson('/api/v1/notifications/device-token')->assertUnauthorized();
});

it('registers an encrypted device token without exposing it', function (): void {
    [$user, $accessToken] = mobileDeviceTokenApiUser();
    $plainToken = mobileDeviceTokenValue('register');

    $this->withToken($accessToken)
        ->postJson('/api/v1/notifications/device-token', [
            'token' => $plainToken,
            'platform' => 'android',
            'device_name' => 'Pixel Test',
            'app_version' => '1.2.3',
            'os_version' => '16',
            'locale' => 'id-ID',
        ])
        ->assertCreated()
        ->assertJsonPath('data.platform', 'android')
        ->assertJsonPath('data.provider', 'fcm')
        ->assertJsonPath('data.device_name', 'Pixel Test')
        ->assertJsonMissing(['token' => $plainToken]);

    $record = MobileDeviceToken::query()->sole();
    expect($record->user_id)->toBe($user->id)
        ->and($record->token)->toBe($plainToken)
        ->and($record->token_hash)->toBe(hash('sha256', $plainToken))
        ->and($record->getRawOriginal('token'))->not->toBe($plainToken);
});

it('refreshes one token record and moves it to the latest authenticated user', function (): void {
    [$firstUser, $firstAccessToken] = mobileDeviceTokenApiUser();
    [$secondUser, $secondAccessToken] = mobileDeviceTokenApiUser();
    $plainToken = mobileDeviceTokenValue('shared');

    $this->withToken($firstAccessToken)->postJson('/api/v1/notifications/device-token', [
        'token' => $plainToken,
        'platform' => 'android',
        'app_version' => '1.0.0',
    ])->assertCreated();

    app('auth')->forgetGuards();
    $this->withToken($secondAccessToken)->postJson('/api/v1/notifications/device-token', [
        'token' => $plainToken,
        'platform' => 'ios',
        'provider' => 'apns',
        'app_version' => '2.0.0',
    ])->assertOk();

    $record = MobileDeviceToken::query()->sole();
    expect($record->user_id)->toBe($secondUser->id)
        ->and($record->user_id)->not->toBe($firstUser->id)
        ->and($record->platform)->toBe('ios')
        ->and($record->provider)->toBe('apns')
        ->and($record->app_version)->toBe('2.0.0');
});

it('validates device token metadata', function (): void {
    [, $accessToken] = mobileDeviceTokenApiUser();

    $this->withToken($accessToken)
        ->postJson('/api/v1/notifications/device-token', [
            'token' => 'short',
            'platform' => 'windows',
            'provider' => 'unknown',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['token', 'platform', 'provider']);
});

it('only removes a device token owned by the authenticated user', function (): void {
    [, $ownerAccessToken] = mobileDeviceTokenApiUser();
    [, $otherAccessToken] = mobileDeviceTokenApiUser();
    $plainToken = mobileDeviceTokenValue('remove');

    $this->withToken($ownerAccessToken)->postJson('/api/v1/notifications/device-token', [
        'token' => $plainToken,
        'platform' => 'android',
    ])->assertCreated();

    app('auth')->forgetGuards();
    $this->withToken($otherAccessToken)
        ->deleteJson('/api/v1/notifications/device-token', ['token' => $plainToken])
        ->assertNoContent();
    expect(MobileDeviceToken::query()->count())->toBe(1);

    app('auth')->forgetGuards();
    $this->withToken($ownerAccessToken)
        ->deleteJson('/api/v1/notifications/device-token', ['token' => $plainToken])
        ->assertNoContent();
    expect(MobileDeviceToken::query()->count())->toBe(0);
});

function mobileDeviceTokenApiUser(): array
{
    $user = User::factory()->create();
    $user->assignRole('customer');

    return [$user, $user->createToken('device-token-api', ['mobile:customer'])->plainTextToken];
}

function mobileDeviceTokenValue(string $suffix): string
{
    return "mobile-device-token-{$suffix}-abcdefghijklmnopqrstuvwxyz-0123456789";
}

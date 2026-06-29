<?php

use App\Models\User;
use App\Notifications\MobileVerifyEmailNotification;
use App\Notifications\ResetPasswordNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\URL;
use Laravel\Fortify\Contracts\TwoFactorAuthenticationProvider;
use Laravel\Fortify\Fortify;
use Spatie\Permission\Models\Role;

use function Pest\Laravel\mock;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Role::findOrCreate('customer', 'web');
    Role::findOrCreate('admin', 'web');
});

it('registers an unverified customer and issues a scoped mobile token', function (): void {
    Notification::fake();

    $response = $this->postJson('/api/v1/auth/register', [
        'name' => 'Customer Baru',
        'email' => 'CUSTOMER@example.com',
        'password' => 'Password1!',
        'password_confirmation' => 'Password1!',
        'terms' => true,
        'device_name' => 'Pixel 9',
    ]);

    $response
        ->assertCreated()
        ->assertJsonPath('data.token_type', 'Bearer')
        ->assertJsonPath('data.user.email', 'customer@example.com')
        ->assertJsonPath('data.user.email_verified', false)
        ->assertJsonPath('data.user.roles.0', 'customer')
        ->assertJsonStructure(['data' => ['access_token', 'expires_at']]);

    $user = User::query()->where('email', 'customer@example.com')->firstOrFail();

    expect($user->hasRole('customer'))->toBeTrue()
        ->and($user->tokens)->toHaveCount(1)
        ->and($user->tokens->first()->name)->toBe('Pixel 9')
        ->and($user->tokens->first()->abilities)->toBe(['mobile:customer'])
        ->and($user->tokens->first()->expires_at)->not->toBeNull();

    Notification::assertSentTo($user, MobileVerifyEmailNotification::class);
});

it('rejects disposable email addresses during registration', function (): void {
    $this->postJson('/api/v1/auth/register', [
        'name' => 'Disposable Customer',
        'email' => 'customer@10minutemail.com',
        'password' => 'Password1!',
        'password_confirmation' => 'Password1!',
        'terms' => true,
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('email');
});

it('logs in a customer and returns a mobile token', function (): void {
    $user = User::factory()->create(['email' => 'customer@example.com']);
    $user->assignRole('customer');

    $this->postJson('/api/v1/auth/login', [
        'email' => $user->email,
        'password' => 'password',
        'device_name' => 'Android Test',
    ])
        ->assertSuccessful()
        ->assertJsonPath('data.token_type', 'Bearer')
        ->assertJsonPath('data.user.id', $user->id)
        ->assertJsonPath('data.user.email_verified', true)
        ->assertJsonStructure(['data' => ['access_token', 'expires_at']]);

    expect($user->tokens()->count())->toBe(1);
});

it('returns a generic validation error for invalid credentials', function (): void {
    User::factory()->create(['email' => 'customer@example.com']);

    $this->postJson('/api/v1/auth/login', [
        'email' => 'customer@example.com',
        'password' => 'wrong-password',
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('email')
        ->assertJsonMissingPath('data.access_token');
});

it('rate limits repeated mobile login attempts by email and ip', function (): void {
    User::factory()->create(['email' => 'rate-limited@example.com']);

    $payload = [
        'email' => 'rate-limited@example.com',
        'password' => 'wrong-password',
    ];

    foreach (range(1, 5) as $attempt) {
        $this->postJson('/api/v1/auth/login', $payload)->assertUnprocessable();
    }

    $this->postJson('/api/v1/auth/login', $payload)->assertTooManyRequests();
});

it('rejects non-customer accounts from mobile login', function (): void {
    $admin = User::factory()->create(['email' => 'admin@example.com']);
    $admin->assignRole('admin');

    $this->postJson('/api/v1/auth/login', [
        'email' => $admin->email,
        'password' => 'password',
    ])
        ->assertForbidden()
        ->assertJsonPath('code', 'customer_access_required');

    expect($admin->tokens()->count())->toBe(0);
});

it('issues a limited token to an unverified customer', function (): void {
    $user = User::factory()->unverified()->create(['email' => 'customer@example.com']);
    $user->assignRole('customer');

    $token = $this->postJson('/api/v1/auth/login', [
        'email' => $user->email,
        'password' => 'password',
    ])
        ->assertSuccessful()
        ->assertJsonPath('data.user.email_verified', false)
        ->json('data.access_token');

    $this->withToken($token)->getJson('/api/v1/auth/me')->assertSuccessful();
    $this->withToken($token)->getJson('/api/v1/customer/status')->assertForbidden();
});

it('returns a two factor challenge without issuing an access token', function (): void {
    $user = twoFactorCustomer();

    $this->postJson('/api/v1/auth/login', [
        'email' => $user->email,
        'password' => 'password',
        'device_name' => 'Two Factor Device',
    ])
        ->assertSuccessful()
        ->assertJsonPath('data.requires_two_factor', true)
        ->assertJsonStructure(['data' => ['challenge_token', 'expires_in']])
        ->assertJsonMissingPath('data.access_token');

    expect($user->tokens()->count())->toBe(0);
});

it('verifies a two factor code once and then rejects challenge replay', function (): void {
    $user = twoFactorCustomer();

    mock(TwoFactorAuthenticationProvider::class)
        ->shouldReceive('verify')
        ->once()
        ->with('test-secret', '123456')
        ->andReturnTrue();

    $challenge = $this->postJson('/api/v1/auth/login', [
        'email' => $user->email,
        'password' => 'password',
        'device_name' => 'Authenticator Device',
    ])->json('data.challenge_token');

    $payload = [
        'challenge_token' => $challenge,
        'code' => '123456',
    ];

    $this->postJson('/api/v1/auth/two-factor/verify', $payload)
        ->assertSuccessful()
        ->assertJsonPath('data.user.id', $user->id)
        ->assertJsonStructure(['data' => ['access_token', 'expires_at']]);

    $this->postJson('/api/v1/auth/two-factor/verify', $payload)
        ->assertUnprocessable()
        ->assertJsonPath('code', 'invalid_two_factor_challenge');

    expect($user->tokens()->count())->toBe(1);
});

it('verifies and rotates a two factor recovery code', function (): void {
    $user = twoFactorCustomer();

    $challenge = $this->postJson('/api/v1/auth/login', [
        'email' => $user->email,
        'password' => 'password',
    ])->json('data.challenge_token');

    $this->postJson('/api/v1/auth/two-factor/verify', [
        'challenge_token' => $challenge,
        'recovery_code' => 'recovery-code',
    ])
        ->assertSuccessful()
        ->assertJsonStructure(['data' => ['access_token']]);

    expect($user->refresh()->recoveryCodes())->not->toContain('recovery-code');
});

it('returns the authenticated customer from me without requiring verified email', function (): void {
    $user = User::factory()->unverified()->create(['email' => 'customer@example.com']);
    $user->assignRole('customer');
    $token = $user->createToken('test', ['mobile:customer'])->plainTextToken;

    $this->withToken($token)
        ->getJson('/api/v1/auth/me')
        ->assertSuccessful()
        ->assertJsonPath('data.email', $user->email)
        ->assertJsonPath('data.email_verified', false);
});

it('resends a mobile verification notification', function (): void {
    Notification::fake();

    $user = User::factory()->unverified()->create();
    $user->assignRole('customer');
    $token = $user->createToken('test', ['mobile:customer'])->plainTextToken;

    $this->withToken($token)
        ->postJson('/api/v1/auth/email/verification-notification')
        ->assertSuccessful()
        ->assertJsonPath('data.email_verified', false);

    Notification::assertSentTo($user, MobileVerifyEmailNotification::class);
});

it('does not resend verification to an already verified customer', function (): void {
    Notification::fake();

    $user = User::factory()->create();
    $user->assignRole('customer');
    $token = $user->createToken('test', ['mobile:customer'])->plainTextToken;

    $this->withToken($token)
        ->postJson('/api/v1/auth/email/verification-notification')
        ->assertSuccessful()
        ->assertJsonPath('data.email_verified', true);

    Notification::assertNothingSent();
});

it('verifies customer email through a signed mobile link', function (): void {
    $user = User::factory()->unverified()->create();
    $user->assignRole('customer');

    $url = URL::temporarySignedRoute('api.v1.auth.email.verify', now()->addMinutes(5), [
        'id' => $user->id,
        'hash' => sha1($user->getEmailForVerification()),
    ]);

    $this->getJson($url)
        ->assertSuccessful()
        ->assertJsonPath('data.email_verified', true);

    expect($user->refresh()->hasVerifiedEmail())->toBeTrue();
});

it('rejects an unsigned mobile email verification link', function (): void {
    $user = User::factory()->unverified()->create();
    $user->assignRole('customer');

    $this->getJson(route('api.v1.auth.email.verify', [
        'id' => $user->id,
        'hash' => sha1($user->getEmailForVerification()),
    ]))
        ->assertForbidden();

    expect($user->refresh()->hasVerifiedEmail())->toBeFalse();
});

it('returns the same forgot password response without exposing account existence', function (): void {
    Notification::fake();

    $user = User::factory()->create(['email' => 'customer@example.com']);
    $user->assignRole('customer');

    $expectedMessage = 'Jika email terdaftar, tautan reset password akan dikirim.';

    $this->postJson('/api/v1/auth/forgot-password', ['email' => $user->email])
        ->assertSuccessful()
        ->assertJsonPath('message', $expectedMessage);

    $this->postJson('/api/v1/auth/forgot-password', ['email' => 'missing@example.com'])
        ->assertSuccessful()
        ->assertJsonPath('message', $expectedMessage);

    Notification::assertSentTo($user, ResetPasswordNotification::class);
});

it('resets a customer password and revokes existing mobile tokens', function (): void {
    $user = User::factory()->create([
        'email' => 'customer@example.com',
        'password' => Hash::make('OldPassword1!'),
    ]);
    $user->assignRole('customer');
    $user->createToken('existing', ['mobile:customer']);
    $resetToken = Password::broker()->createToken($user);

    $this->postJson('/api/v1/auth/reset-password', [
        'token' => $resetToken,
        'email' => $user->email,
        'password' => 'NewPassword1!',
        'password_confirmation' => 'NewPassword1!',
    ])->assertSuccessful();

    expect(Hash::check('NewPassword1!', $user->refresh()->password))->toBeTrue()
        ->and($user->tokens()->count())->toBe(0);
});

it('logs out only the current token', function (): void {
    $user = User::factory()->create();
    $user->assignRole('customer');
    $currentToken = $user->createToken('current', ['mobile:customer'])->plainTextToken;
    $user->createToken('other', ['mobile:customer']);

    $this->withToken($currentToken)
        ->postJson('/api/v1/auth/logout')
        ->assertSuccessful();

    expect($user->tokens()->pluck('name')->all())->toBe(['other']);
});

it('logs out all mobile tokens', function (): void {
    $user = User::factory()->create();
    $user->assignRole('customer');
    $currentToken = $user->createToken('current', ['mobile:customer'])->plainTextToken;
    $user->createToken('other', ['mobile:customer']);
    $user->createToken('integration', ['integration:other']);

    $this->withToken($currentToken)
        ->postJson('/api/v1/auth/logout-all')
        ->assertSuccessful();

    expect($user->tokens()->pluck('name')->all())->toBe(['integration']);
});

function twoFactorCustomer(): User
{
    $user = User::factory()->create(['email' => 'two-factor@example.com']);
    $user->assignRole('customer');
    $user->forceFill([
        'two_factor_secret' => Fortify::currentEncrypter()->encrypt('test-secret'),
        'two_factor_recovery_codes' => Fortify::currentEncrypter()->encrypt(json_encode(['recovery-code'])),
        'two_factor_confirmed_at' => now(),
    ])->save();

    return $user;
}

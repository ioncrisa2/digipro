<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

it('resets the password and redirects to login with the email prefilled', function () {
    $user = User::factory()->create([
        'email' => 'customer@example.com',
        'password' => Hash::make('OldPassword1!'),
    ]);

    $token = Password::broker()->createToken($user);

    $this
        ->post('/reset-password', [
            'token' => $token,
            'email' => $user->email,
            'password' => 'NewPassword1!',
            'password_confirmation' => 'NewPassword1!',
        ])
        ->assertRedirect(route('login', ['email' => $user->email]));

    expect(Hash::check('NewPassword1!', $user->refresh()->password))->toBeTrue();
});

it('passes the email query string to the login page', function () {
    $this
        ->get(route('login', ['email' => 'customer@example.com']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Auth/LoginPage')
            ->where('email', 'customer@example.com'));
});

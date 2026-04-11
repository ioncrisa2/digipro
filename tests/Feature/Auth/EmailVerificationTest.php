<?php

use App\Models\User;
use App\Notifications\ResetPasswordNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

it('renders the verification notice for unverified users', function () {
    $user = User::factory()->unverified()->create();

    $this
        ->actingAs($user)
        ->get('/email/verify')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('Auth/VerifyEmail'));
});

it('redirects verified users away from the verification notice', function () {
    $user = User::factory()->create();

    $this
        ->actingAs($user)
        ->get('/email/verify')
        ->assertRedirect(route('dashboard'));
});

it('does not resend a verification email for users that are already verified', function () {
    Notification::fake();

    $user = User::factory()->create();

    $this
        ->actingAs($user)
        ->post('/email/verification-notification')
        ->assertRedirect(route('dashboard'));

    Notification::assertNothingSent();
});

it('uses the custom password reset notification', function () {
    Notification::fake();

    $user = User::factory()->create();

    $user->sendPasswordResetNotification('reset-token');

    Notification::assertSentTo($user, ResetPasswordNotification::class);
});

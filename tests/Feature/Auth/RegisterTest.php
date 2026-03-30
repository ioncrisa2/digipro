<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

it('auto assigns the customer role when a user registers', function () {
    Role::findOrCreate('customer', 'web');

    $this
        ->post('/register', [
            'name' => 'Customer Baru',
            'email' => 'customer-baru@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'terms' => '1',
        ])
        ->assertRedirect(route('login'));

    $user = User::query()->where('email', 'customer-baru@example.com')->first();

    expect($user)->not->toBeNull();
    expect($user->hasRole('customer'))->toBeTrue();
});

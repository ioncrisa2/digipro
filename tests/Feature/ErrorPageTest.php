<?php

use Illuminate\Support\Facades\Route;
use Inertia\Testing\AssertableInertia as Assert;

it('renders the custom inertia 404 page', function () {
    $this->get('/missing-digipro-page')
        ->assertStatus(404)
        ->assertInertia(fn (Assert $page) => $page
            ->component('Errors/Status')
            ->where('status', 404)
        );
});

it('renders the custom inertia 403 page', function () {
    Route::middleware('web')->get('/_test-forbidden', fn () => abort(403));

    $this->get('/_test-forbidden')
        ->assertStatus(403)
        ->assertInertia(fn (Assert $page) => $page
            ->component('Errors/Status')
            ->where('status', 403)
        );
});

it('renders the custom inertia 419 page', function () {
    Route::middleware('web')->get('/_test-page-expired', fn () => abort(419));

    $this->get('/_test-page-expired')
        ->assertStatus(419)
        ->assertInertia(fn (Assert $page) => $page
            ->component('Errors/Status')
            ->where('status', 419)
        );
});

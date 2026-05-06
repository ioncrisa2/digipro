<?php

use App\Models\ReportSigner;
use App\Models\User;
use App\Support\AdminWorkspaceAccessSynchronizer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Role::findOrCreate('Reviewer', 'web');
    AdminWorkspaceAccessSynchronizer::sync();
});

it('does not expose public appraiser signing navigation or routes to ordinary reviewers', function (): void {
    $reviewer = User::factory()->create();
    $reviewer->assignRole('Reviewer');

    $this->actingAs($reviewer)
        ->get(route('reviewer.dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Reviewer/Dashboard')
            ->where('navigation.reviewer_nav', fn ($items) => collect($items)->pluck('key')->doesntContain('reviewer.contract-signatures')));

    $this->actingAs($reviewer)
        ->get(route('reviewer.contract-signatures.index'))
        ->assertForbidden();
});

it('shows public appraiser signing navigation and route only for mapped signer reviewers', function (): void {
    $reviewer = User::factory()->create();
    $reviewer->assignRole('Reviewer');

    ReportSigner::query()->create([
        'user_id' => $reviewer->id,
        'role' => 'public_appraiser',
        'name' => 'Penilai Publik A',
        'email' => 'public@appraiser.test',
        'is_active' => true,
    ]);

    $this->actingAs($reviewer)
        ->get(route('reviewer.dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Reviewer/Dashboard')
            ->where('navigation.reviewer_nav', fn ($items) => collect($items)->pluck('key')->contains('reviewer.contract-signatures')));

    $this->actingAs($reviewer)
        ->get(route('reviewer.contract-signatures.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('Reviewer/ContractSignatures/Index'));
});

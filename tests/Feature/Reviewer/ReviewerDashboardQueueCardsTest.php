<?php

use App\Enums\AppraisalStatusEnum;
use App\Enums\PurposeEnum;
use App\Models\AppraisalRequest;
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

it('shows reviewer work queue cards on dashboard', function (): void {
    $reviewer = User::factory()->create();
    $reviewer->assignRole('Reviewer');

    AppraisalRequest::query()->create([
        'user_id' => $reviewer->id,
        'request_number' => 'REQ-DASH-001',
        'purpose' => PurposeEnum::JualBeli,
        'status' => AppraisalStatusEnum::ContractSigned,
        'requested_at' => now(),
        'client_name' => 'PT Dashboard',
    ]);

    $this->actingAs($reviewer)
        ->get(route('reviewer.dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Reviewer/Dashboard')
            ->has('reviewWorkQueues', 4)
            ->where('reviewWorkQueues.1.value', 'ready_review')
            ->where('reviewWorkQueues.1.count', 1));
});

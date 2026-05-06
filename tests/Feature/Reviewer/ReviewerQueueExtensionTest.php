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

it('filters the reviewer queue by work queue preset and exposes queue options', function (): void {
    $reviewer = User::factory()->create();
    $reviewer->assignRole('Reviewer');

    AppraisalRequest::query()->create([
        'user_id' => $reviewer->id,
        'request_number' => 'REQ-READY-001',
        'purpose' => PurposeEnum::JualBeli,
        'status' => AppraisalStatusEnum::ContractSigned,
        'requested_at' => now(),
        'client_name' => 'PT Ready Review',
    ]);

    AppraisalRequest::query()->create([
        'user_id' => $reviewer->id,
        'request_number' => 'REQ-PROGRESS-001',
        'purpose' => PurposeEnum::JualBeli,
        'status' => AppraisalStatusEnum::ValuationOnProgress,
        'requested_at' => now(),
        'client_name' => 'PT In Progress',
    ]);

    $this->actingAs($reviewer)
        ->get(route('reviewer.reviews.index', ['queue' => 'ready_review']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Reviewer/Reviews/Index')
            ->where('filters.queue', 'ready_review')
            ->has('queueOptions', 4)
            ->where('records.data.0.request_number', 'REQ-READY-001')
            ->where('records.data.0.next_action.label', 'Mulai Review')
            ->missing('records.data.1'));
});

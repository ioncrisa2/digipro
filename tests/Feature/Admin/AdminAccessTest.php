<?php

use App\Enums\AppraisalStatusEnum;
use App\Enums\PurposeEnum;
use App\Models\AppraisalAsset;
use App\Models\AppraisalAssetFile;
use App\Models\AppraisalRequest;
use App\Models\AppraisalRequestFile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Role::findOrCreate('Reviewer', 'web');
    Role::findOrCreate('admin', 'web');
});

function createAdminUser(): User
{
    $user = User::factory()->create([
        'email_verified_at' => now(),
    ]);

    $user->assignRole('admin');

    return $user;
}

it('allows admin users to access the vue admin dashboard', function () {
    $admin = createAdminUser();

    $this
        ->actingAs($admin)
        ->get(route('admin.dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('Admin/Dashboard'));
});

it('blocks reviewer users from the admin dashboard', function () {
    $reviewer = User::factory()->create([
        'email_verified_at' => now(),
    ]);
    $reviewer->assignRole('Reviewer');

    $this
        ->actingAs($reviewer)
        ->get(route('admin.dashboard'))
        ->assertForbidden();
});

it('renders appraisal request detail in the vue admin workspace', function () {
    $admin = createAdminUser();
    $requester = User::factory()->create([
        'email_verified_at' => now(),
    ]);

    $record = AppraisalRequest::create([
        'user_id' => $requester->id,
        'request_number' => 'REQ-ADMIN-001',
        'purpose' => PurposeEnum::JualBeli,
        'status' => AppraisalStatusEnum::Submitted,
        'requested_at' => now(),
    ]);

    $this
        ->actingAs($admin)
        ->get(route('admin.appraisal-requests.show', $record))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Admin/AppraisalRequests/Show')
            ->where('record.request_number', 'REQ-ADMIN-001'));
});

it('renders the appraisal request index for admin users', function () {
    $admin = createAdminUser();
    $requester = User::factory()->create([
        'email_verified_at' => now(),
    ]);

    AppraisalRequest::create([
        'user_id' => $requester->id,
        'request_number' => 'REQ-ADMIN-INDEX-001',
        'purpose' => PurposeEnum::JualBeli,
        'status' => AppraisalStatusEnum::WaitingOffer,
        'requested_at' => now(),
    ]);

    $this
        ->actingAs($admin)
        ->get(route('admin.appraisal-requests.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Admin/AppraisalRequests/Index')
            ->where('records.meta.total', 1));
});

it('includes request files and grouped asset files in the admin request detail payload', function () {
    $admin = createAdminUser();
    $requester = User::factory()->create([
        'email_verified_at' => now(),
    ]);

    $record = AppraisalRequest::create([
        'user_id' => $requester->id,
        'request_number' => 'REQ-ADMIN-FILES-001',
        'purpose' => PurposeEnum::JualBeli,
        'status' => AppraisalStatusEnum::Submitted,
        'requested_at' => now(),
    ]);

    AppraisalRequestFile::create([
        'appraisal_request_id' => $record->id,
        'type' => 'contract_signed_pdf',
        'path' => 'contracts/test-contract.pdf',
        'original_name' => 'test-contract.pdf',
        'mime' => 'application/pdf',
        'size' => 1024,
    ]);

    $asset = AppraisalAsset::create([
        'appraisal_request_id' => $record->id,
        'asset_type' => 'tanah_bangunan',
        'peruntukan' => 'rumah_tinggal',
        'address' => 'Jl. Admin Files No. 1',
        'land_area' => 100,
        'building_area' => 80,
    ]);

    AppraisalAssetFile::create([
        'appraisal_asset_id' => $asset->id,
        'type' => 'doc_pbb',
        'path' => 'assets/doc-pbb.pdf',
        'original_name' => 'doc-pbb.pdf',
        'mime' => 'application/pdf',
        'size' => 2048,
    ]);

    AppraisalAssetFile::create([
        'appraisal_asset_id' => $asset->id,
        'type' => 'photo_front',
        'path' => 'assets/photo-front.jpg',
        'original_name' => 'photo-front.jpg',
        'mime' => 'image/jpeg',
        'size' => 4096,
    ]);

    $this
        ->actingAs($admin)
        ->get(route('admin.appraisal-requests.show', $record))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Admin/AppraisalRequests/Show')
            ->where('requestFiles.0.type', 'contract_signed_pdf')
            ->where('assets.0.documents.0.type', 'doc_pbb')
            ->where('assets.0.photos.0.type', 'photo_front'));
});

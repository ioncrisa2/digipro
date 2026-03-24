<?php

use App\Enums\AppraisalStatusEnum;
use App\Enums\PurposeEnum;
use App\Models\AppraisalAsset;
use App\Models\AppraisalAssetFile;
use App\Models\AppraisalRequest;
use App\Models\AppraisalRequestFile;
use App\Models\AppraisalRequestRevisionBatch;
use App\Models\AppraisalRequestRevisionItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Role::findOrCreate('admin', 'web');
    Role::findOrCreate('customer', 'web');
});

function createCustomerUserForRevision(): User
{
    $user = User::factory()->create([
        'email_verified_at' => now(),
    ]);

    $user->assignRole('customer');

    return $user;
}

function createAdminUserForRevision(): User
{
    $user = User::factory()->create([
        'email_verified_at' => now(),
    ]);

    $user->assignRole('admin');

    return $user;
}

it('renders the customer revision page for an open revision batch', function () {
    $customer = createCustomerUserForRevision();

    $record = AppraisalRequest::create([
        'user_id' => $customer->id,
        'request_number' => 'REQ-CUST-REV-001',
        'purpose' => PurposeEnum::JualBeli,
        'status' => AppraisalStatusEnum::DocsIncomplete,
        'requested_at' => now(),
    ]);

    $requestFile = AppraisalRequestFile::create([
        'appraisal_request_id' => $record->id,
        'type' => 'npwp',
        'path' => 'appraisal-requests/request/original-npwp.pdf',
        'original_name' => 'original-npwp.pdf',
        'mime' => 'application/pdf',
        'size' => 1024,
    ]);

    $batch = AppraisalRequestRevisionBatch::create([
        'appraisal_request_id' => $record->id,
        'created_by' => createAdminUserForRevision()->id,
        'status' => 'open',
        'admin_note' => 'Mohon upload ulang dokumen yang kurang jelas.',
    ]);

    AppraisalRequestRevisionItem::create([
        'revision_batch_id' => $batch->id,
        'item_type' => 'request_file',
        'requested_file_type' => 'npwp',
        'status' => 'pending',
        'issue_note' => 'Dokumen NPWP buram.',
        'original_request_file_id' => $requestFile->id,
    ]);

    $this
        ->actingAs($customer)
        ->get(route('appraisal.revisions.page', ['id' => $record->id]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Penilaian/Revision')
            ->where('record.request_number', 'REQ-CUST-REV-001')
            ->where('batch.id', $batch->id)
            ->where('batch.items.0.target_label', 'Dokumen Request: NPWP'));
});

it('submits replacement files from the customer revision page and preserves file history', function () {
    Storage::fake('public');

    $customer = createCustomerUserForRevision();
    $admin = createAdminUserForRevision();

    $record = AppraisalRequest::create([
        'user_id' => $customer->id,
        'request_number' => 'REQ-CUST-REV-002',
        'purpose' => PurposeEnum::JualBeli,
        'status' => AppraisalStatusEnum::DocsIncomplete,
        'requested_at' => now(),
    ]);

    $asset = AppraisalAsset::create([
        'appraisal_request_id' => $record->id,
        'asset_type' => 'tanah_bangunan',
        'address' => 'Jl. Revisi Customer No. 8',
    ]);

    $requestFile = AppraisalRequestFile::create([
        'appraisal_request_id' => $record->id,
        'type' => 'npwp',
        'path' => 'appraisal-requests/request/original-request.pdf',
        'original_name' => 'original-request.pdf',
        'mime' => 'application/pdf',
        'size' => 2048,
    ]);

    $assetPhoto = AppraisalAssetFile::create([
        'appraisal_asset_id' => $asset->id,
        'type' => 'photo_front',
        'path' => 'appraisal-requests/assets/original-front.jpg',
        'original_name' => 'original-front.jpg',
        'mime' => 'image/jpeg',
        'size' => 4096,
    ]);

    $batch = AppraisalRequestRevisionBatch::create([
        'appraisal_request_id' => $record->id,
        'created_by' => $admin->id,
        'status' => 'open',
        'admin_note' => 'Mohon perbaiki dokumen request dan foto depan.',
    ]);

    $requestItem = AppraisalRequestRevisionItem::create([
        'revision_batch_id' => $batch->id,
        'item_type' => 'request_file',
        'requested_file_type' => 'npwp',
        'status' => 'pending',
        'issue_note' => 'Upload ulang NPWP yang lebih jelas.',
        'original_request_file_id' => $requestFile->id,
    ]);

    $assetItem = AppraisalRequestRevisionItem::create([
        'revision_batch_id' => $batch->id,
        'appraisal_asset_id' => $asset->id,
        'item_type' => 'asset_photo',
        'requested_file_type' => 'photo_front',
        'status' => 'pending',
        'issue_note' => 'Foto depan kurang terang.',
        'original_asset_file_id' => $assetPhoto->id,
    ]);

    $this
        ->actingAs($customer)
        ->post(route('appraisal.revisions.submit', ['id' => $record->id]), [
            'replacements' => [
                $requestItem->id => UploadedFile::fake()->create('npwp-revisi.pdf', 120, 'application/pdf'),
                $assetItem->id => UploadedFile::fake()->image('foto-depan-baru.jpg'),
            ],
        ])
        ->assertRedirect(route('appraisal.show', ['id' => $record->id]));

    $record->refresh();
    $batch->refresh();
    $requestItem->refresh();
    $assetItem->refresh();

    expect($record->status)->toBe(AppraisalStatusEnum::Submitted);
    expect($record->verified_at)->toBeNull();
    expect($batch->status)->toBe('submitted');
    expect($batch->submitted_by)->toBe($customer->id);
    expect($requestItem->status)->toBe('reuploaded');
    expect($assetItem->status)->toBe('reuploaded');
    expect($requestItem->replacement_request_file_id)->not->toBeNull();
    expect($assetItem->replacement_asset_file_id)->not->toBeNull();
    expect(AppraisalRequestFile::query()->where('appraisal_request_id', $record->id)->count())->toBe(2);
    expect(AppraisalAssetFile::query()->where('appraisal_asset_id', $asset->id)->count())->toBe(2);
});

it('marks submitted revision batches as reviewed when admin verifies docs', function () {
    $customer = createCustomerUserForRevision();
    $admin = createAdminUserForRevision();

    $record = AppraisalRequest::create([
        'user_id' => $customer->id,
        'request_number' => 'REQ-CUST-REV-003',
        'purpose' => PurposeEnum::JualBeli,
        'status' => AppraisalStatusEnum::Submitted,
        'requested_at' => now(),
    ]);

    $batch = AppraisalRequestRevisionBatch::create([
        'appraisal_request_id' => $record->id,
        'created_by' => $admin->id,
        'submitted_by' => $customer->id,
        'status' => 'submitted',
        'submitted_at' => now(),
    ]);

    $item = AppraisalRequestRevisionItem::create([
        'revision_batch_id' => $batch->id,
        'item_type' => 'request_file',
        'requested_file_type' => 'npwp',
        'status' => 'reuploaded',
        'issue_note' => 'Revisi sudah diunggah.',
    ]);

    $this
        ->actingAs($admin)
        ->post(route('admin.appraisal-requests.actions.verify-docs', $record))
        ->assertRedirect();

    $record->refresh();
    $batch->refresh();
    $item->refresh();

    expect($record->status)->toBe(AppraisalStatusEnum::WaitingOffer);
    expect($batch->status)->toBe('reviewed');
    expect($batch->reviewed_by)->toBe($admin->id);
    expect($item->status)->toBe('approved');
});

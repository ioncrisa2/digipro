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
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Role::findOrCreate('admin', 'web');
    Role::findOrCreate('customer', 'web');
    Role::findOrCreate('Reviewer', 'web');
});

function createRevisionAdmin(): User
{
    $user = User::factory()->create([
        'email_verified_at' => now(),
    ]);

    $user->assignRole('admin');

    return $user;
}

function createRevisionCustomer(): User
{
    $user = User::factory()->create([
        'email_verified_at' => now(),
    ]);

    $user->assignRole('customer');

    return $user;
}

function createRevisionReviewer(): User
{
    $user = User::factory()->create([
        'email_verified_at' => now(),
    ]);

    $user->assignRole('Reviewer');

    return $user;
}

it('approves one revision item without affecting other items in the same batch', function () {
    $admin = createRevisionAdmin();
    $customer = createRevisionCustomer();

    $record = AppraisalRequest::create([
        'user_id' => $customer->id,
        'request_number' => 'REQ-REV-APPROVE-001',
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

    $firstReplacement = AppraisalRequestFile::create([
        'appraisal_request_id' => $record->id,
        'type' => 'npwp',
        'path' => 'appraisal-requests/1/revisions/batch-1/request/revision-npwp.pdf',
        'original_name' => 'revision-npwp.pdf',
        'mime' => 'application/pdf',
        'size' => 1024,
    ]);

    $secondReplacement = AppraisalRequestFile::create([
        'appraisal_request_id' => $record->id,
        'type' => 'permission',
        'path' => 'appraisal-requests/1/revisions/batch-1/request/revision-permission.pdf',
        'original_name' => 'revision-permission.pdf',
        'mime' => 'application/pdf',
        'size' => 1024,
    ]);

    $first = AppraisalRequestRevisionItem::create([
        'revision_batch_id' => $batch->id,
        'item_type' => 'request_file',
        'requested_file_type' => 'npwp',
        'status' => 'reuploaded',
        'issue_note' => 'Revisi pertama.',
        'replacement_request_file_id' => $firstReplacement->id,
    ]);

    $second = AppraisalRequestRevisionItem::create([
        'revision_batch_id' => $batch->id,
        'item_type' => 'request_file',
        'requested_file_type' => 'permission',
        'status' => 'reuploaded',
        'issue_note' => 'Revisi kedua.',
        'replacement_request_file_id' => $secondReplacement->id,
    ]);

    $this
        ->actingAs($admin)
        ->post(route('admin.appraisal-requests.revision-items.approve', [
            'appraisalRequest' => $record,
            'revisionItem' => $first,
        ]))
        ->assertRedirect();

    $batch->refresh();
    $first->refresh();
    $second->refresh();

    expect($first->status)->toBe('approved');
    expect($second->status)->toBe('reuploaded');
    expect($batch->status)->toBe('submitted');
});

it('reopens the same revision item when admin rejects a replacement upload', function () {
    $admin = createRevisionAdmin();
    $customer = createRevisionCustomer();

    $record = AppraisalRequest::create([
        'user_id' => $customer->id,
        'request_number' => 'REQ-REV-REJECT-001',
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

    $replacement = AppraisalRequestFile::create([
        'appraisal_request_id' => $record->id,
        'type' => 'npwp',
        'path' => 'appraisal-requests/1/revisions/batch-1/request/revision-npwp-reject.pdf',
        'original_name' => 'revision-npwp-reject.pdf',
        'mime' => 'application/pdf',
        'size' => 1024,
    ]);

    $item = AppraisalRequestRevisionItem::create([
        'revision_batch_id' => $batch->id,
        'item_type' => 'request_file',
        'requested_file_type' => 'npwp',
        'status' => 'reuploaded',
        'issue_note' => 'Masih perlu revisi.',
        'replacement_request_file_id' => $replacement->id,
    ]);

    $this
        ->actingAs($admin)
        ->post(route('admin.appraisal-requests.revision-items.reject', [
            'appraisalRequest' => $record,
            'revisionItem' => $item,
        ]), [
            'review_note' => 'Dokumen masih buram, mohon upload ulang.',
        ])
        ->assertRedirect();

    $record->refresh();
    $batch->refresh();
    $item->refresh();

    expect($record->status)->toBe(AppraisalStatusEnum::DocsIncomplete);
    expect($batch->status)->toBe('open');
    expect($item->status)->toBe('rejected');
    expect($item->review_note)->toBe('Dokumen masih buram, mohon upload ulang.');
});

it('blocks verify docs while unresolved revision items still exist', function () {
    $admin = createRevisionAdmin();
    $customer = createRevisionCustomer();

    $record = AppraisalRequest::create([
        'user_id' => $customer->id,
        'request_number' => 'REQ-REV-BLOCK-001',
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

    $replacement = AppraisalRequestFile::create([
        'appraisal_request_id' => $record->id,
        'type' => 'npwp',
        'path' => 'appraisal-requests/1/revisions/batch-1/request/revision-npwp-block.pdf',
        'original_name' => 'revision-npwp-block.pdf',
        'mime' => 'application/pdf',
        'size' => 1024,
    ]);

    AppraisalRequestRevisionItem::create([
        'revision_batch_id' => $batch->id,
        'item_type' => 'request_file',
        'requested_file_type' => 'npwp',
        'status' => 'reuploaded',
        'issue_note' => 'Masih menunggu review.',
        'replacement_request_file_id' => $replacement->id,
    ]);

    $this
        ->actingAs($admin)
        ->post(route('admin.appraisal-requests.actions.verify-docs', $record))
        ->assertRedirect();

    $record->refresh();
    expect($record->status)->toBe(AppraisalStatusEnum::Submitted);
});

it('shows only the approved replacement file as active in admin and reviewer workspaces', function () {
    $admin = createRevisionAdmin();
    $reviewer = createRevisionReviewer();
    $customer = createRevisionCustomer();

    $record = AppraisalRequest::create([
        'user_id' => $customer->id,
        'request_number' => 'REQ-REV-ACTIVE-001',
        'purpose' => PurposeEnum::JualBeli,
        'status' => AppraisalStatusEnum::ContractSigned,
        'requested_at' => now(),
    ]);

    $asset = AppraisalAsset::create([
        'appraisal_request_id' => $record->id,
        'asset_type' => 'tanah_bangunan',
        'address' => 'Jl. File Aktif No. 1',
    ]);

    $originalFile = AppraisalAssetFile::create([
        'appraisal_asset_id' => $asset->id,
        'type' => 'doc_pbb',
        'path' => 'appraisal-requests/assets/original-doc-pbb.pdf',
        'original_name' => 'original-doc-pbb.pdf',
        'mime' => 'application/pdf',
        'size' => 1024,
    ]);

    $replacementFile = AppraisalAssetFile::create([
        'appraisal_asset_id' => $asset->id,
        'type' => 'doc_pbb',
        'path' => 'appraisal-requests/1/revisions/batch-1/assets/1/revision-doc-pbb.pdf',
        'original_name' => 'revision-doc-pbb.pdf',
        'mime' => 'application/pdf',
        'size' => 2048,
    ]);

    $batch = AppraisalRequestRevisionBatch::create([
        'appraisal_request_id' => $record->id,
        'created_by' => $admin->id,
        'reviewed_by' => $admin->id,
        'status' => 'reviewed',
        'reviewed_at' => now(),
        'resolved_at' => now(),
    ]);

    AppraisalRequestRevisionItem::create([
        'revision_batch_id' => $batch->id,
        'appraisal_asset_id' => $asset->id,
        'item_type' => 'asset_document',
        'requested_file_type' => 'doc_pbb',
        'status' => 'approved',
        'issue_note' => 'Gunakan file terbaru.',
        'original_asset_file_id' => $originalFile->id,
        'replacement_asset_file_id' => $replacementFile->id,
    ]);

    $this
        ->actingAs($admin)
        ->get(route('admin.appraisal-requests.show', $record))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('assets.0.documents', fn ($documents) => count($documents) === 1 && $documents[0]['original_name'] === 'revision-doc-pbb.pdf'));

    $this
        ->actingAs($reviewer)
        ->get(route('reviewer.assets.show', $asset))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('asset.files', fn ($files) => count($files) === 1 && $files[0]['original_name'] === 'revision-doc-pbb.pdf'));
});

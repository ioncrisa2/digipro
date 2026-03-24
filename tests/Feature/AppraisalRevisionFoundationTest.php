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

uses(RefreshDatabase::class);

it('stores a revision batch with request and asset file references for historical tracking', function () {
    $admin = User::factory()->create([
        'email_verified_at' => now(),
    ]);

    $customer = User::factory()->create([
        'email_verified_at' => now(),
    ]);

    $request = AppraisalRequest::create([
        'user_id' => $customer->id,
        'request_number' => 'REQ-REV-FOUNDATION-001',
        'purpose' => PurposeEnum::JualBeli,
        'status' => AppraisalStatusEnum::Submitted,
        'requested_at' => now(),
    ]);

    $asset = AppraisalAsset::create([
        'appraisal_request_id' => $request->id,
        'asset_type' => 'tanah_bangunan',
        'address' => 'Jl. Historis 1',
    ]);

    $requestFile = AppraisalRequestFile::create([
        'appraisal_request_id' => $request->id,
        'type' => 'npwp',
        'path' => 'appraisal-requests/request/npwp-original.pdf',
        'original_name' => 'npwp-original.pdf',
        'mime' => 'application/pdf',
        'size' => 120,
    ]);

    $assetFile = AppraisalAssetFile::create([
        'appraisal_asset_id' => $asset->id,
        'type' => 'photo_front',
        'path' => 'appraisal-requests/assets/front-original.jpg',
        'original_name' => 'front-original.jpg',
        'mime' => 'image/jpeg',
        'size' => 240,
    ]);

    $batch = AppraisalRequestRevisionBatch::create([
        'appraisal_request_id' => $request->id,
        'created_by' => $admin->id,
        'status' => 'open',
        'admin_note' => 'Perlu revisi dokumen untuk beberapa item.',
    ]);

    $requestItem = AppraisalRequestRevisionItem::create([
        'revision_batch_id' => $batch->id,
        'item_type' => 'request_file',
        'requested_file_type' => 'npwp',
        'status' => 'pending',
        'issue_note' => 'NPWP kurang jelas, mohon upload ulang.',
        'original_request_file_id' => $requestFile->id,
    ]);

    $assetItem = AppraisalRequestRevisionItem::create([
        'revision_batch_id' => $batch->id,
        'appraisal_asset_id' => $asset->id,
        'item_type' => 'asset_photo',
        'requested_file_type' => 'photo_front',
        'status' => 'pending',
        'issue_note' => 'Foto depan properti blur.',
        'original_asset_file_id' => $assetFile->id,
    ]);

    $batch->load([
        'appraisalRequest',
        'creator',
        'items.originalRequestFile',
        'items.originalAssetFile',
        'items.appraisalAsset',
    ]);

    expect($batch->appraisalRequest->is($request))->toBeTrue();
    expect($batch->creator->is($admin))->toBeTrue();
    expect($batch->items)->toHaveCount(2);
    expect($requestItem->originalRequestFile->is($requestFile))->toBeTrue();
    expect($assetItem->originalAssetFile->is($assetFile))->toBeTrue();
    expect($assetItem->appraisalAsset->is($asset))->toBeTrue();
});

it('tracks replacement files as new records without overwriting the original upload', function () {
    $customer = User::factory()->create([
        'email_verified_at' => now(),
    ]);

    $request = AppraisalRequest::create([
        'user_id' => $customer->id,
        'request_number' => 'REQ-REV-FOUNDATION-002',
        'purpose' => PurposeEnum::JualBeli,
        'status' => AppraisalStatusEnum::DocsIncomplete,
        'requested_at' => now(),
    ]);

    $originalFile = AppraisalRequestFile::create([
        'appraisal_request_id' => $request->id,
        'type' => 'permission',
        'path' => 'appraisal-requests/request/permission-old.pdf',
        'original_name' => 'permission-old.pdf',
        'mime' => 'application/pdf',
        'size' => 111,
    ]);

    $replacementFile = AppraisalRequestFile::create([
        'appraisal_request_id' => $request->id,
        'type' => 'permission',
        'path' => 'appraisal-requests/request/permission-new.pdf',
        'original_name' => 'permission-new.pdf',
        'mime' => 'application/pdf',
        'size' => 222,
    ]);

    $batch = AppraisalRequestRevisionBatch::create([
        'appraisal_request_id' => $request->id,
        'status' => 'submitted',
        'submitted_by' => $customer->id,
        'submitted_at' => now(),
    ]);

    $item = AppraisalRequestRevisionItem::create([
        'revision_batch_id' => $batch->id,
        'item_type' => 'request_file',
        'requested_file_type' => 'permission',
        'status' => 'reuploaded',
        'issue_note' => 'Dokumen izin sebelumnya tidak sesuai.',
        'original_request_file_id' => $originalFile->id,
        'replacement_request_file_id' => $replacementFile->id,
    ]);

    $item->load(['originalRequestFile', 'replacementRequestFile']);

    expect($item->originalRequestFile->is($originalFile))->toBeTrue();
    expect($item->replacementRequestFile->is($replacementFile))->toBeTrue();
    expect(AppraisalRequestFile::query()->where('appraisal_request_id', $request->id)->count())->toBe(2);
});

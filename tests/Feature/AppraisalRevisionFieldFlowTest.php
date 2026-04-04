<?php

use App\Enums\AppraisalStatusEnum;
use App\Enums\PurposeEnum;
use App\Models\AppraisalAsset;
use App\Models\AppraisalFieldChangeLog;
use App\Models\AppraisalRequest;
use App\Models\AppraisalRequestRevisionBatch;
use App\Models\AppraisalRequestRevisionItem;
use App\Models\User;
use App\Support\AdminWorkspaceAccessSynchronizer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Role::findOrCreate('admin', 'web');
    Role::findOrCreate('customer', 'web');
    AdminWorkspaceAccessSynchronizer::sync();
});

function createRevisionAdminUser(): User
{
    $user = User::factory()->create([
        'email_verified_at' => now(),
    ]);

    $user->assignRole('admin');

    return $user;
}

function createRevisionCustomerUser(): User
{
    $user = User::factory()->create([
        'email_verified_at' => now(),
    ]);

    $user->assignRole('customer');

    return $user;
}

it('allows customer to submit a field revision and admin to approve it', function () {
    $customer = createRevisionCustomerUser();
    $admin = createRevisionAdminUser();

    $record = AppraisalRequest::create([
        'user_id' => $customer->id,
        'request_number' => 'REQ-FIELD-REV-001',
        'purpose' => PurposeEnum::JualBeli,
        'status' => AppraisalStatusEnum::DocsIncomplete,
        'requested_at' => now(),
    ]);

    $asset = AppraisalAsset::create([
        'appraisal_request_id' => $record->id,
        'asset_type' => 'tanah_bangunan',
        'title_document' => 'shm',
        'address' => 'Alamat Lama No. 1',
        'land_area' => 120,
    ]);

    $batch = AppraisalRequestRevisionBatch::create([
        'appraisal_request_id' => $record->id,
        'created_by' => $admin->id,
        'status' => 'open',
        'admin_note' => 'Perbaiki alamat objek agar lebih presisi.',
    ]);

    $item = AppraisalRequestRevisionItem::create([
        'revision_batch_id' => $batch->id,
        'appraisal_asset_id' => $asset->id,
        'item_type' => 'asset_field',
        'requested_file_type' => 'address',
        'requested_field_key' => 'address',
        'status' => 'pending',
        'issue_note' => 'Alamat masih kurang lengkap dan belum mencantumkan nomor ruko.',
        'original_value' => [
            'value' => 'Alamat Lama No. 1',
            'display' => 'Alamat Lama No. 1',
        ],
    ]);

    $this
        ->actingAs($customer)
        ->get(route('appraisal.revisions.page', ['id' => $record->id]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Penilaian/Revision')
            ->where('batch.items.0.field.label', 'Alamat Lengkap')
            ->where('batch.items.0.field.original_value.display', 'Alamat Lama No. 1'));

    $this
        ->actingAs($customer)
        ->post(route('appraisal.revisions.submit', ['id' => $record->id]), [
            'field_values' => [
                $item->id => 'Jl. Suka Senang Blok B-12, Kel. Sukamaju',
            ],
        ])
        ->assertRedirect(route('appraisal.show', ['id' => $record->id]));

    $record->refresh();
    $item->refresh();
    $batch->refresh();

    expect($record->status)->toBe(AppraisalStatusEnum::Submitted);
    expect($batch->status)->toBe('submitted');
    expect($item->status)->toBe('reuploaded');
    expect(data_get($item->replacement_value, 'display'))->toBe('Jl. Suka Senang Blok B-12, Kel. Sukamaju');

    $this
        ->actingAs($admin)
        ->post(route('admin.appraisal-requests.revision-items.approve', [
            'appraisalRequest' => $record,
            'revisionItem' => $item,
        ]))
        ->assertRedirect();

    $asset->refresh();
    $item->refresh();
    $batch->refresh();

    expect($asset->address)->toBe('Jl. Suka Senang Blok B-12, Kel. Sukamaju');
    expect($item->status)->toBe('approved');
    expect($batch->status)->toBe('reviewed');
    expect(AppraisalFieldChangeLog::query()->where('revision_item_id', $item->id)->count())->toBe(1);
});

it('allows admin to correct a customer input field directly', function () {
    $admin = createRevisionAdminUser();
    $customer = createRevisionCustomerUser();

    $record = AppraisalRequest::create([
        'user_id' => $customer->id,
        'request_number' => 'REQ-FIELD-REV-002',
        'purpose' => PurposeEnum::JualBeli,
        'status' => AppraisalStatusEnum::Submitted,
        'requested_at' => now(),
    ]);

    $asset = AppraisalAsset::create([
        'appraisal_request_id' => $record->id,
        'asset_type' => 'tanah_bangunan',
        'title_document' => 'shm',
        'address' => 'Jl. Lama',
        'coordinates_lat' => -6.11,
        'coordinates_lng' => 106.81,
        'land_area' => 150,
    ]);

    $this
        ->actingAs($admin)
        ->post(route('admin.appraisal-requests.field-corrections.store', $record), [
            'target_key' => "asset_field:{$asset->id}:coordinates_lat",
            'value' => '-6.1234567',
            'reason' => 'Koordinat customer salah input satu digit.',
        ])
        ->assertRedirect();

    $asset->refresh();

    expect((string) $asset->coordinates_lat)->toBe('-6.1234567');
    expect(AppraisalFieldChangeLog::query()->where('appraisal_asset_id', $asset->id)->where('change_source', 'admin_direct')->count())->toBe(1);
});

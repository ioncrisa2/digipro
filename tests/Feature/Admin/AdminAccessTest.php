<?php

use App\Enums\AppraisalStatusEnum;
use App\Enums\PurposeEnum;
use App\Enums\ContractStatusEnum;
use Carbon\Carbon;
use App\Models\AppraisalAsset;
use App\Models\AppraisalAssetFile;
use App\Models\AppraisalRequest;
use App\Models\AppraisalRequestFile;
use App\Models\OfficeBankAccount;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
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

it('renders the admin payments index in the vue workspace', function () {
    $admin = createAdminUser();
    $requester = User::factory()->create([
        'email_verified_at' => now(),
    ]);

    $record = AppraisalRequest::create([
        'user_id' => $requester->id,
        'request_number' => 'REQ-PAYMENT-INDEX-001',
        'purpose' => PurposeEnum::JualBeli,
        'status' => AppraisalStatusEnum::ContractSigned,
        'requested_at' => now(),
    ]);

    Payment::create([
        'appraisal_request_id' => $record->id,
        'amount' => 15000000,
        'method' => 'gateway',
        'gateway' => 'midtrans',
        'external_payment_id' => 'MID-ADMIN-LIST-001',
        'status' => 'pending',
        'proof_type' => 'gateway_id',
        'metadata' => [
            'invoice_number' => 'INV-2026-90001',
            'gateway_details' => [
                'payment_type' => 'bank_transfer',
                'va_numbers' => [['bank' => 'bca', 'va_number' => '1234567890']],
            ],
        ],
    ]);

    $this
        ->actingAs($admin)
        ->get(route('admin.finance.payments.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Admin/Payments/Index')
            ->where('records.data.0.invoice_number', 'INV-2026-90001')
            ->where('records.data.0.external_payment_id', 'MID-ADMIN-LIST-001'));
});

it('renders the admin payment detail in the vue workspace', function () {
    $admin = createAdminUser();
    $requester = User::factory()->create([
        'email_verified_at' => now(),
    ]);

    $record = AppraisalRequest::create([
        'user_id' => $requester->id,
        'request_number' => 'REQ-PAYMENT-SHOW-001',
        'purpose' => PurposeEnum::JualBeli,
        'status' => AppraisalStatusEnum::ContractSigned,
        'requested_at' => now(),
    ]);

    $payment = Payment::create([
        'appraisal_request_id' => $record->id,
        'amount' => 17500000,
        'method' => 'gateway',
        'gateway' => 'midtrans',
        'external_payment_id' => 'MID-ADMIN-SHOW-001',
        'status' => 'paid',
        'proof_type' => 'gateway_id',
        'paid_at' => now(),
        'metadata' => [
            'invoice_number' => 'INV-2026-90002',
            'gateway_details' => [
                'payment_type' => 'qris',
                'transaction_id' => 'TXN-001',
            ],
        ],
    ]);

    $this
        ->actingAs($admin)
        ->get(route('admin.finance.payments.show', $payment))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Admin/Payments/Show')
            ->where('record.invoice_number', 'INV-2026-90002')
            ->where('record.external_payment_id', 'MID-ADMIN-SHOW-001'));
});

it('renders the admin office bank accounts index in the vue workspace', function () {
    $admin = createAdminUser();

    OfficeBankAccount::create([
        'bank_name' => 'Bank Digi',
        'account_number' => '1234567890',
        'account_holder' => 'PT Digi Pro',
        'currency' => 'IDR',
        'is_active' => true,
        'sort_order' => 1,
    ]);

    $this
        ->actingAs($admin)
        ->get(route('admin.finance.office-bank-accounts.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Admin/OfficeBankAccounts/Index')
            ->where('records.0.bank_name', 'Bank Digi')
            ->where('summary.active', 1));
});

it('renders the admin office bank account create page in the vue workspace', function () {
    $admin = createAdminUser();

    $this
        ->actingAs($admin)
        ->get(route('admin.finance.office-bank-accounts.create'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Admin/OfficeBankAccounts/Form')
            ->where('mode', 'create')
            ->where('record.currency', 'IDR'));
});

it('stores an office bank account from the vue admin workspace', function () {
    $admin = createAdminUser();

    $this
        ->actingAs($admin)
        ->post(route('admin.finance.office-bank-accounts.store'), [
            'bank_name' => 'Bank Baru',
            'account_number' => '9876543210',
            'account_holder' => 'PT Digi Baru',
            'branch' => 'Jakarta',
            'currency' => 'idr',
            'notes' => 'rekening operasional',
            'is_active' => true,
            'sort_order' => 2,
        ])
        ->assertRedirect(route('admin.finance.office-bank-accounts.index'));

    $record = OfficeBankAccount::query()->where('account_number', '9876543210')->first();

    expect($record)->not->toBeNull();
    expect($record->currency)->toBe('IDR');
});

it('renders the admin office bank account edit page in the vue workspace', function () {
    $admin = createAdminUser();
    $account = OfficeBankAccount::create([
        'bank_name' => 'Bank Edit',
        'account_number' => '444333222',
        'account_holder' => 'PT Digi Edit',
        'currency' => 'IDR',
        'is_active' => true,
        'sort_order' => 3,
    ]);

    $this
        ->actingAs($admin)
        ->get(route('admin.finance.office-bank-accounts.edit', $account))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Admin/OfficeBankAccounts/Form')
            ->where('mode', 'edit')
            ->where('record.account_number', '444333222'));
});

it('updates an office bank account from the vue admin workspace', function () {
    $admin = createAdminUser();
    $account = OfficeBankAccount::create([
        'bank_name' => 'Bank Update',
        'account_number' => '555444333',
        'account_holder' => 'PT Digi Update',
        'currency' => 'IDR',
        'is_active' => true,
        'sort_order' => 4,
    ]);

    $this
        ->actingAs($admin)
        ->put(route('admin.finance.office-bank-accounts.update', $account), [
            'bank_name' => 'Bank Update Final',
            'account_number' => '555444333',
            'account_holder' => 'PT Digi Update Final',
            'branch' => 'Bandung',
            'currency' => 'usd',
            'notes' => 'updated',
            'is_active' => false,
            'sort_order' => 8,
        ])
        ->assertRedirect(route('admin.finance.office-bank-accounts.index'));

    $account->refresh();

    expect($account->bank_name)->toBe('Bank Update Final');
    expect($account->currency)->toBe('USD');
    expect($account->is_active)->toBeFalse();
    expect($account->sort_order)->toBe(8);
});

it('deletes an office bank account from the vue admin workspace', function () {
    $admin = createAdminUser();
    $account = OfficeBankAccount::create([
        'bank_name' => 'Bank Delete',
        'account_number' => '111222333',
        'account_holder' => 'PT Digi Delete',
        'currency' => 'IDR',
        'is_active' => true,
        'sort_order' => 1,
    ]);

    $this
        ->actingAs($admin)
        ->delete(route('admin.finance.office-bank-accounts.destroy', $account))
        ->assertRedirect(route('admin.finance.office-bank-accounts.index'));

    expect(OfficeBankAccount::find($account->id))->toBeNull();
});

it('renders the admin payment edit page in the vue workspace', function () {
    $admin = createAdminUser();
    $requester = User::factory()->create([
        'email_verified_at' => now(),
    ]);

    $requestRecord = AppraisalRequest::create([
        'user_id' => $requester->id,
        'request_number' => 'REQ-PAYMENT-EDIT-001',
        'purpose' => PurposeEnum::JualBeli,
        'status' => AppraisalStatusEnum::ContractSigned,
        'requested_at' => now(),
    ]);

    $payment = Payment::create([
        'appraisal_request_id' => $requestRecord->id,
        'amount' => 18000000,
        'method' => 'gateway',
        'gateway' => 'midtrans',
        'external_payment_id' => 'MID-ADMIN-EDIT-001',
        'status' => 'pending',
        'proof_type' => 'gateway_id',
        'metadata' => [
            'invoice_number' => 'INV-2026-90003',
        ],
    ]);

    $this
        ->actingAs($admin)
        ->get(route('admin.finance.payments.edit', $payment))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Admin/Payments/Edit')
            ->where('record.invoice_number', 'INV-2026-90003')
            ->where('record.external_payment_id', 'MID-ADMIN-EDIT-001'));
});

it('updates a payment from the vue admin workspace', function () {
    $admin = createAdminUser();
    $requester = User::factory()->create([
        'email_verified_at' => now(),
    ]);

    $requestRecord = AppraisalRequest::create([
        'user_id' => $requester->id,
        'request_number' => 'REQ-PAYMENT-UPDATE-001',
        'purpose' => PurposeEnum::JualBeli,
        'status' => AppraisalStatusEnum::ContractSigned,
        'requested_at' => now(),
    ]);

    $payment = Payment::create([
        'appraisal_request_id' => $requestRecord->id,
        'amount' => 19000000,
        'method' => 'gateway',
        'gateway' => 'midtrans',
        'external_payment_id' => 'MID-ADMIN-UPDATE-001',
        'status' => 'pending',
        'proof_type' => 'gateway_id',
        'metadata' => [
            'invoice_number' => 'INV-2026-90004',
        ],
    ]);

    $this
        ->actingAs($admin)
        ->put(route('admin.finance.payments.update', $payment), [
            'amount' => 21000000,
            'status' => 'paid',
            'gateway' => 'midtrans',
            'external_payment_id' => 'MID-ADMIN-UPDATED',
            'paid_at' => '2026-03-20 10:30:00',
            'metadata_json' => json_encode([
                'invoice_number' => 'INV-2026-90004A',
                'gateway_details' => [
                    'payment_type' => 'bank_transfer',
                    'va_numbers' => [['bank' => 'bni', 'va_number' => '99887766']],
                ],
            ]),
        ])
        ->assertRedirect(route('admin.finance.payments.show', $payment));

    $payment->refresh();

    expect($payment->amount)->toBe(21000000);
    expect($payment->status)->toBe('paid');
    expect($payment->external_payment_id)->toBe('MID-ADMIN-UPDATED');
    expect(data_get($payment->metadata, 'invoice_number'))->toBe('INV-2026-90004A');
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

it('includes negotiation summary and filter options in the admin request detail payload', function () {
    $admin = createAdminUser();
    $requester = User::factory()->create([
        'email_verified_at' => now(),
    ]);

    $record = AppraisalRequest::create([
        'user_id' => $requester->id,
        'request_number' => 'REQ-ADMIN-NEGO-001',
        'purpose' => PurposeEnum::JualBeli,
        'status' => AppraisalStatusEnum::WaitingOffer,
        'requested_at' => now(),
    ]);

    $record->offerNegotiations()->create([
        'user_id' => $requester->id,
        'action' => 'counter_request',
        'round' => 1,
        'offered_fee' => 20000000,
        'expected_fee' => 18000000,
        'reason' => 'Mohon penyesuaian',
    ]);

    $record->offerNegotiations()->create([
        'user_id' => $admin->id,
        'action' => 'offer_revised',
        'round' => 1,
        'offered_fee' => 19000000,
        'reason' => 'Counter offer admin',
    ]);

    $this
        ->actingAs($admin)
        ->get(route('admin.appraisal-requests.show', $record))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Admin/AppraisalRequests/Show')
            ->where('negotiationSummary.total', 2)
            ->where('negotiationSummary.counter_requests', 1)
            ->where('negotiationSummary.offers_sent', 1)
            ->where('negotiationActionOptions.0.value', 'offer_revised')
            ->where('negotiations.0.action_value', 'offer_revised'));
});

it('uploads a request-level file from the vue admin workspace', function () {
    Storage::fake('public');

    $admin = createAdminUser();
    $requester = User::factory()->create([
        'email_verified_at' => now(),
    ]);

    $record = AppraisalRequest::create([
        'user_id' => $requester->id,
        'request_number' => 'REQ-ADMIN-UPLOAD-001',
        'purpose' => PurposeEnum::JualBeli,
        'status' => AppraisalStatusEnum::Submitted,
        'requested_at' => now(),
    ]);

    $this
        ->actingAs($admin)
        ->post(route('admin.appraisal-requests.files.store', $record), [
            'type' => 'npwp',
            'file' => UploadedFile::fake()->create('npwp.pdf', 120, 'application/pdf'),
        ])
        ->assertRedirect();

    $file = AppraisalRequestFile::query()
        ->where('appraisal_request_id', $record->id)
        ->where('type', 'npwp')
        ->first();

    expect($file)->not->toBeNull();
    Storage::disk('public')->assertExists($file->path);
});

it('deletes a request-level file from the vue admin workspace', function () {
    Storage::fake('public');

    $admin = createAdminUser();
    $requester = User::factory()->create([
        'email_verified_at' => now(),
    ]);

    $record = AppraisalRequest::create([
        'user_id' => $requester->id,
        'request_number' => 'REQ-ADMIN-DELETE-FILE-001',
        'purpose' => PurposeEnum::JualBeli,
        'status' => AppraisalStatusEnum::Submitted,
        'requested_at' => now(),
    ]);

    Storage::disk('public')->put('appraisal-requests/test/request-files/permission.pdf', 'dummy');

    $file = AppraisalRequestFile::create([
        'appraisal_request_id' => $record->id,
        'type' => 'permission',
        'path' => 'appraisal-requests/test/request-files/permission.pdf',
        'original_name' => 'permission.pdf',
        'mime' => 'application/pdf',
        'size' => 5,
    ]);

    $this
        ->actingAs($admin)
        ->delete(route('admin.appraisal-requests.files.destroy', [$record, $file]))
        ->assertRedirect();

    expect(AppraisalRequestFile::find($file->id))->toBeNull();
    Storage::disk('public')->assertMissing('appraisal-requests/test/request-files/permission.pdf');
});

it('renders the asset create page in the vue admin workspace', function () {
    $admin = createAdminUser();
    $requester = User::factory()->create([
        'email_verified_at' => now(),
    ]);

    $record = AppraisalRequest::create([
        'user_id' => $requester->id,
        'request_number' => 'REQ-ASSET-CREATE-PAGE-001',
        'purpose' => PurposeEnum::JualBeli,
        'status' => AppraisalStatusEnum::Submitted,
        'requested_at' => now(),
    ]);

    $this
        ->actingAs($admin)
        ->get(route('admin.appraisal-requests.assets.create', $record))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Admin/AppraisalRequests/AssetForm')
            ->where('mode', 'create')
            ->where('requestRecord.request_number', 'REQ-ASSET-CREATE-PAGE-001'));
});

it('stores an appraisal asset from the vue admin workspace', function () {
    $admin = createAdminUser();
    $requester = User::factory()->create([
        'email_verified_at' => now(),
    ]);

    $record = AppraisalRequest::create([
        'user_id' => $requester->id,
        'request_number' => 'REQ-ASSET-STORE-001',
        'purpose' => PurposeEnum::JualBeli,
        'status' => AppraisalStatusEnum::Submitted,
        'requested_at' => now(),
    ]);

    $this
        ->actingAs($admin)
        ->post(route('admin.appraisal-requests.assets.store', $record), [
            'asset_code' => 'A-001',
            'asset_type' => 'tanah_bangunan',
            'peruntukan' => 'rumah_tinggal',
            'title_document' => 'shm',
            'land_shape' => 'persegi',
            'land_position' => 'tengah',
            'land_condition' => 'matang',
            'topography' => 'datar_sama_dengan_jalan',
            'address' => 'Jl. Batch Tujuh No. 1',
            'maps_link' => 'https://maps.google.com/?q=-6.2,106.8',
            'land_area' => 120,
            'building_area' => 95,
            'building_floors' => 2,
            'build_year' => 2018,
            'frontage_width' => 8,
            'access_road_width' => 6,
        ])
        ->assertRedirect(route('admin.appraisal-requests.show', $record));

    $asset = AppraisalAsset::query()
        ->where('appraisal_request_id', $record->id)
        ->latest('id')
        ->first();

    expect($asset)->not->toBeNull();
    expect($asset->asset_code)->toBe('A-001');
    expect($asset->asset_type)->toBe('tanah_bangunan');
    expect($asset->peruntukan)->toBe('rumah_tinggal');
    expect((float) $asset->land_area)->toBe(120.0);
});

it('updates an appraisal asset from the vue admin workspace', function () {
    $admin = createAdminUser();
    $requester = User::factory()->create([
        'email_verified_at' => now(),
    ]);

    $record = AppraisalRequest::create([
        'user_id' => $requester->id,
        'request_number' => 'REQ-ASSET-UPDATE-001',
        'purpose' => PurposeEnum::JualBeli,
        'status' => AppraisalStatusEnum::Submitted,
        'requested_at' => now(),
    ]);

    $asset = AppraisalAsset::create([
        'appraisal_request_id' => $record->id,
        'asset_code' => 'A-OLD',
        'asset_type' => 'tanah',
        'address' => 'Alamat lama',
    ]);

    $this
        ->actingAs($admin)
        ->put(route('admin.appraisal-requests.assets.update', [$record, $asset]), [
            'asset_code' => 'A-NEW',
            'asset_type' => 'tanah_bangunan',
            'peruntukan' => 'ruko',
            'title_document' => 'hgb',
            'address' => 'Alamat baru',
            'building_area' => 88,
            'building_floors' => 3,
            'renovation_year' => 2022,
        ])
        ->assertRedirect(route('admin.appraisal-requests.show', $record));

    $asset->refresh();

    expect($asset->asset_code)->toBe('A-NEW');
    expect($asset->asset_type)->toBe('tanah_bangunan');
    expect($asset->peruntukan)->toBe('ruko');
    expect($asset->title_document)->toBe('hgb');
    expect($asset->address)->toBe('Alamat baru');
    expect((float) $asset->building_area)->toBe(88.0);
});

it('deletes an appraisal asset from the vue admin workspace', function () {
    Storage::fake('public');

    $admin = createAdminUser();
    $requester = User::factory()->create([
        'email_verified_at' => now(),
    ]);

    $record = AppraisalRequest::create([
        'user_id' => $requester->id,
        'request_number' => 'REQ-ASSET-DELETE-001',
        'purpose' => PurposeEnum::JualBeli,
        'status' => AppraisalStatusEnum::Submitted,
        'requested_at' => now(),
    ]);

    $asset = AppraisalAsset::create([
        'appraisal_request_id' => $record->id,
        'asset_type' => 'tanah',
        'address' => 'Aset hapus',
    ]);

    Storage::disk('public')->put('appraisal-requests/test/assets/doc.pdf', 'dummy');

    AppraisalAssetFile::create([
        'appraisal_asset_id' => $asset->id,
        'type' => 'doc_pbb',
        'path' => 'appraisal-requests/test/assets/doc.pdf',
        'original_name' => 'doc.pdf',
        'mime' => 'application/pdf',
        'size' => 5,
    ]);

    $this
        ->actingAs($admin)
        ->delete(route('admin.appraisal-requests.assets.destroy', [$record, $asset]))
        ->assertRedirect();

    expect(AppraisalAsset::find($asset->id))->toBeNull();
    Storage::disk('public')->assertMissing('appraisal-requests/test/assets/doc.pdf');
});

it('uploads an asset document from the vue admin workspace', function () {
    Storage::fake('public');

    $admin = createAdminUser();
    $requester = User::factory()->create([
        'email_verified_at' => now(),
    ]);

    $record = AppraisalRequest::create([
        'user_id' => $requester->id,
        'request_number' => 'REQ-ASSET-DOC-UPLOAD-001',
        'purpose' => PurposeEnum::JualBeli,
        'status' => AppraisalStatusEnum::Submitted,
        'requested_at' => now(),
    ]);

    $asset = AppraisalAsset::create([
        'appraisal_request_id' => $record->id,
        'asset_type' => 'tanah',
        'address' => 'Aset dokumen',
    ]);

    $this
        ->actingAs($admin)
        ->post(route('admin.appraisal-requests.assets.files.store', [$record, $asset]), [
            'type' => 'doc_pbb',
            'file' => UploadedFile::fake()->create('pbb.pdf', 200, 'application/pdf'),
        ])
        ->assertRedirect();

    $file = AppraisalAssetFile::query()
        ->where('appraisal_asset_id', $asset->id)
        ->where('type', 'doc_pbb')
        ->first();

    expect($file)->not->toBeNull();
    Storage::disk('public')->assertExists($file->path);
});

it('uploads an asset photo from the vue admin workspace', function () {
    Storage::fake('public');

    $admin = createAdminUser();
    $requester = User::factory()->create([
        'email_verified_at' => now(),
    ]);

    $record = AppraisalRequest::create([
        'user_id' => $requester->id,
        'request_number' => 'REQ-ASSET-PHOTO-UPLOAD-001',
        'purpose' => PurposeEnum::JualBeli,
        'status' => AppraisalStatusEnum::Submitted,
        'requested_at' => now(),
    ]);

    $asset = AppraisalAsset::create([
        'appraisal_request_id' => $record->id,
        'asset_type' => 'tanah_bangunan',
        'address' => 'Aset foto',
    ]);

    $this
        ->actingAs($admin)
        ->post(route('admin.appraisal-requests.assets.files.store', [$record, $asset]), [
            'type' => 'photo_front',
            'file' => UploadedFile::fake()->image('front.jpg'),
        ])
        ->assertRedirect();

    $file = AppraisalAssetFile::query()
        ->where('appraisal_asset_id', $asset->id)
        ->where('type', 'photo_front')
        ->first();

    expect($file)->not->toBeNull();
    Storage::disk('public')->assertExists($file->path);
});

it('deletes an asset file from the vue admin workspace', function () {
    Storage::fake('public');

    $admin = createAdminUser();
    $requester = User::factory()->create([
        'email_verified_at' => now(),
    ]);

    $record = AppraisalRequest::create([
        'user_id' => $requester->id,
        'request_number' => 'REQ-ASSET-FILE-DELETE-001',
        'purpose' => PurposeEnum::JualBeli,
        'status' => AppraisalStatusEnum::Submitted,
        'requested_at' => now(),
    ]);

    $asset = AppraisalAsset::create([
        'appraisal_request_id' => $record->id,
        'asset_type' => 'tanah',
        'address' => 'Aset hapus file',
    ]);

    Storage::disk('public')->put('appraisal-requests/test/assets/photo.jpg', 'dummy');

    $file = AppraisalAssetFile::create([
        'appraisal_asset_id' => $asset->id,
        'type' => 'photo_front',
        'path' => 'appraisal-requests/test/assets/photo.jpg',
        'original_name' => 'photo.jpg',
        'mime' => 'image/jpeg',
        'size' => 5,
    ]);

    $this
        ->actingAs($admin)
        ->delete(route('admin.appraisal-requests.assets.files.destroy', [$record, $asset, $file]))
        ->assertRedirect();

    expect(AppraisalAssetFile::find($file->id))->toBeNull();
    Storage::disk('public')->assertMissing('appraisal-requests/test/assets/photo.jpg');
});

it('verifies docs from the vue admin workflow', function () {
    $admin = createAdminUser();
    $requester = User::factory()->create([
        'email_verified_at' => now(),
    ]);

    $record = AppraisalRequest::create([
        'user_id' => $requester->id,
        'request_number' => 'REQ-VERIFY-001',
        'purpose' => PurposeEnum::JualBeli,
        'status' => AppraisalStatusEnum::Submitted,
        'requested_at' => now(),
    ]);

    $this
        ->actingAs($admin)
        ->post(route('admin.appraisal-requests.actions.verify-docs', $record))
        ->assertRedirect();

    $record->refresh();

    expect($record->status)->toBe(AppraisalStatusEnum::WaitingOffer);
    expect($record->verified_at)->not->toBeNull();
});

it('marks docs incomplete from the vue admin workflow', function () {
    $admin = createAdminUser();
    $requester = User::factory()->create([
        'email_verified_at' => now(),
    ]);

    $record = AppraisalRequest::create([
        'user_id' => $requester->id,
        'request_number' => 'REQ-DOCS-001',
        'purpose' => PurposeEnum::JualBeli,
        'status' => AppraisalStatusEnum::WaitingOffer,
        'requested_at' => now(),
    ]);

    $this
        ->actingAs($admin)
        ->post(route('admin.appraisal-requests.actions.docs-incomplete', $record))
        ->assertRedirect();

    $record->refresh();

    expect($record->status)->toBe(AppraisalStatusEnum::DocsIncomplete);
});

it('marks contract signed from the vue admin workflow', function () {
    $admin = createAdminUser();
    $requester = User::factory()->create([
        'email_verified_at' => now(),
    ]);

    $record = AppraisalRequest::create([
        'user_id' => $requester->id,
        'request_number' => 'REQ-CONTRACT-001',
        'purpose' => PurposeEnum::JualBeli,
        'status' => AppraisalStatusEnum::WaitingSignature,
        'contract_status' => ContractStatusEnum::WaitingSignature,
        'requested_at' => now(),
    ]);

    $this
        ->actingAs($admin)
        ->post(route('admin.appraisal-requests.actions.contract-signed', $record))
        ->assertRedirect();

    $record->refresh();

    expect($record->status)->toBe(AppraisalStatusEnum::ContractSigned);
    expect($record->contract_status)->toBe(ContractStatusEnum::ContractSigned);
});

it('sends an initial offer from the vue admin workflow', function () {
    Carbon::setTestNow('2026-05-10 09:00:00');

    $admin = createAdminUser();
    $requester = User::factory()->create([
        'email_verified_at' => now(),
    ]);

    $record = AppraisalRequest::create([
        'user_id' => $requester->id,
        'request_number' => 'REQ-OFFER-001',
        'purpose' => PurposeEnum::JualBeli,
        'status' => AppraisalStatusEnum::Verified,
        'contract_status' => ContractStatusEnum::None,
        'requested_at' => now(),
    ]);

    $this
        ->actingAs($admin)
        ->post(route('admin.appraisal-requests.actions.send-offer', $record), [
            'fee_total' => 18000000,
            'fee_has_dp' => true,
            'fee_dp_percent' => 50,
            'contract_sequence' => 3,
            'offer_validity_days' => 10,
        ])
        ->assertRedirect();

    $record->refresh();
    $latestNegotiation = $record->offerNegotiations()->latest('id')->first();

    expect($record->status)->toBe(AppraisalStatusEnum::OfferSent);
    expect($record->contract_status)->toBe(ContractStatusEnum::SentToClient);
    expect($record->contract_number)->toBe('00003/AGR/DP/05/2026');
    expect($record->contract_date?->toDateString())->toBe('2026-05-10');
    expect($record->fee_total)->toBe(18000000);
    expect($record->fee_has_dp)->toBeTrue();
    expect((float) $record->fee_dp_percent)->toBe(50.0);
    expect($latestNegotiation?->action)->toBe('offer_sent');
    expect($latestNegotiation?->offered_fee)->toBe(18000000);

    Carbon::setTestNow();
});

it('sends a revised offer from the vue admin workflow when negotiation is active', function () {
    Carbon::setTestNow('2026-05-11 10:00:00');

    $admin = createAdminUser();
    $requester = User::factory()->create([
        'email_verified_at' => now(),
    ]);

    $record = AppraisalRequest::create([
        'user_id' => $requester->id,
        'request_number' => 'REQ-OFFER-REVISED-001',
        'purpose' => PurposeEnum::JualBeli,
        'status' => AppraisalStatusEnum::WaitingOffer,
        'contract_status' => ContractStatusEnum::Negotiation,
        'contract_sequence' => 7,
        'contract_number' => '00007/AGR/DP/05/2026',
        'requested_at' => now(),
    ]);

    $record->offerNegotiations()->create([
        'user_id' => $requester->id,
        'action' => 'counter_request',
        'round' => 1,
        'offered_fee' => 20000000,
        'expected_fee' => 19000000,
        'reason' => 'Mohon revisi fee',
    ]);

    $this
        ->actingAs($admin)
        ->post(route('admin.appraisal-requests.actions.send-offer', $record), [
            'fee_total' => 19500000,
            'fee_has_dp' => false,
            'contract_sequence' => 7,
            'offer_validity_days' => 7,
        ])
        ->assertRedirect();

    $record->refresh();
    $latestNegotiation = $record->offerNegotiations()->latest('id')->first();

    expect($record->status)->toBe(AppraisalStatusEnum::OfferSent);
    expect($record->contract_status)->toBe(ContractStatusEnum::SentToClient);
    expect($record->fee_total)->toBe(19500000);
    expect($latestNegotiation?->action)->toBe('offer_revised');
    expect($latestNegotiation?->round)->toBe(1);
    expect($latestNegotiation?->offered_fee)->toBe(19500000);

    Carbon::setTestNow();
});

it('approves the latest user negotiation from the vue admin workflow', function () {
    Carbon::setTestNow('2026-05-12 11:00:00');

    $admin = createAdminUser();
    $requester = User::factory()->create([
        'email_verified_at' => now(),
    ]);

    $record = AppraisalRequest::create([
        'user_id' => $requester->id,
        'request_number' => 'REQ-APPROVE-NEGO-001',
        'purpose' => PurposeEnum::JualBeli,
        'status' => AppraisalStatusEnum::WaitingOffer,
        'contract_status' => ContractStatusEnum::Negotiation,
        'contract_sequence' => 9,
        'fee_total' => 25000000,
        'offer_validity_days' => 14,
        'requested_at' => now(),
    ]);

    $counterRequest = $record->offerNegotiations()->create([
        'user_id' => $requester->id,
        'action' => 'counter_request',
        'round' => 1,
        'offered_fee' => 25000000,
        'expected_fee' => 23000000,
        'reason' => 'Harap menyesuaikan fee',
    ]);

    $this
        ->actingAs($admin)
        ->post(route('admin.appraisal-requests.actions.approve-latest-negotiation', $record))
        ->assertRedirect();

    $record->refresh();
    $latestNegotiation = $record->offerNegotiations()->latest('id')->first();

    expect($record->status)->toBe(AppraisalStatusEnum::OfferSent);
    expect($record->contract_status)->toBe(ContractStatusEnum::SentToClient);
    expect($record->fee_total)->toBe(23000000);
    expect($record->contract_number)->toBe('00009/AGR/DP/05/2026');
    expect($latestNegotiation?->action)->toBe('offer_revised');
    expect($latestNegotiation?->expected_fee)->toBe(23000000);
    expect(data_get($latestNegotiation?->meta, 'counter_request_id'))->toBe($counterRequest->id);

    Carbon::setTestNow();
});

it('verifies payment from the vue admin workflow when the latest payment is ready', function () {
    $admin = createAdminUser();
    $requester = User::factory()->create([
        'email_verified_at' => now(),
    ]);

    $record = AppraisalRequest::create([
        'user_id' => $requester->id,
        'request_number' => 'REQ-VERIFY-PAYMENT-001',
        'purpose' => PurposeEnum::JualBeli,
        'status' => AppraisalStatusEnum::ContractSigned,
        'contract_status' => ContractStatusEnum::ContractSigned,
        'requested_at' => now(),
    ]);

    Payment::create([
        'appraisal_request_id' => $record->id,
        'amount' => 15000000,
        'method' => 'gateway',
        'gateway' => 'midtrans',
        'external_payment_id' => 'MID-VERIFY-001',
        'status' => 'paid',
        'proof_type' => 'gateway_id',
        'paid_at' => now(),
    ]);

    $this
        ->actingAs($admin)
        ->post(route('admin.appraisal-requests.actions.verify-payment', $record))
        ->assertRedirect();

    $record->refresh();

    expect($record->status)->toBe(AppraisalStatusEnum::ValuationOnProgress);
});

it('blocks payment verification from the vue admin workflow when payment is not ready', function () {
    $admin = createAdminUser();
    $requester = User::factory()->create([
        'email_verified_at' => now(),
    ]);

    $record = AppraisalRequest::create([
        'user_id' => $requester->id,
        'request_number' => 'REQ-VERIFY-PAYMENT-002',
        'purpose' => PurposeEnum::JualBeli,
        'status' => AppraisalStatusEnum::ContractSigned,
        'contract_status' => ContractStatusEnum::ContractSigned,
        'requested_at' => now(),
    ]);

    Payment::create([
        'appraisal_request_id' => $record->id,
        'amount' => 15000000,
        'method' => 'gateway',
        'gateway' => 'midtrans',
        'external_payment_id' => 'MID-VERIFY-002',
        'status' => 'pending',
        'proof_type' => 'gateway_id',
    ]);

    $this
        ->actingAs($admin)
        ->from(route('admin.appraisal-requests.show', $record))
        ->post(route('admin.appraisal-requests.actions.verify-payment', $record))
        ->assertRedirect(route('admin.appraisal-requests.show', $record))
        ->assertSessionHas('error');

    $record->refresh();

    expect($record->status)->toBe(AppraisalStatusEnum::ContractSigned);
});

it('renders the basic edit page for admin users', function () {
    $admin = createAdminUser();
    $requester = User::factory()->create([
        'email_verified_at' => now(),
    ]);

    $record = AppraisalRequest::create([
        'user_id' => $requester->id,
        'request_number' => 'REQ-EDIT-001',
        'purpose' => PurposeEnum::JualBeli,
        'status' => AppraisalStatusEnum::Submitted,
        'requested_at' => now(),
    ]);

    $this
        ->actingAs($admin)
        ->get(route('admin.appraisal-requests.edit', $record))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Admin/AppraisalRequests/Edit')
            ->where('record.request_number', 'REQ-EDIT-001'));
});

it('updates safe appraisal request fields from the vue admin form', function () {
    $admin = createAdminUser();
    $requester = User::factory()->create([
        'email_verified_at' => now(),
    ]);

    $record = AppraisalRequest::create([
        'user_id' => $requester->id,
        'request_number' => 'REQ-UPDATE-001',
        'purpose' => PurposeEnum::JualBeli,
        'status' => AppraisalStatusEnum::Submitted,
        'report_type' => 'terinci',
        'requested_at' => now(),
    ]);

    $this
        ->actingAs($admin)
        ->put(route('admin.appraisal-requests.update', $record), [
            'client_name' => 'PT Contoh Klien',
            'report_type' => 'singkat',
            'user_request_note' => 'Catatan user diperbarui',
            'notes' => 'Catatan internal diperbarui',
        ])
        ->assertRedirect(route('admin.appraisal-requests.show', $record));

    $record->refresh();

    expect($record->client_name)->toBe('PT Contoh Klien');
    expect($record->report_type)->toBe(\App\Enums\ReportTypeEnum::Ringkas);
    expect($record->contract_status)->toBe(ContractStatusEnum::None);
    expect($record->user_request_note)->toBe('Catatan user diperbarui');
    expect($record->notes)->toBe('Catatan internal diperbarui');
});

it('updates contract and fee fields from the vue admin form', function () {
    Carbon::setTestNow('2026-04-15 10:00:00');

    $admin = createAdminUser();
    $requester = User::factory()->create([
        'email_verified_at' => now(),
    ]);

    $record = AppraisalRequest::create([
        'user_id' => $requester->id,
        'request_number' => 'REQ-CONTRACT-FEE-001',
        'purpose' => PurposeEnum::JualBeli,
        'status' => AppraisalStatusEnum::Submitted,
        'requested_at' => now(),
    ]);

    $this
        ->actingAs($admin)
        ->put(route('admin.appraisal-requests.update', $record), [
            'client_name' => 'PT Batch Empat',
            'report_type' => 'terinci',
            'contract_sequence' => 12,
            'contract_date' => '2026-04-20',
            'contract_status' => 'draft',
            'valuation_duration_days' => 25,
            'offer_validity_days' => 14,
            'fee_total' => 17500000,
            'fee_has_dp' => true,
            'fee_dp_percent' => 50,
            'user_request_note' => 'Butuh update kontrak',
            'notes' => 'Siap penawaran',
        ])
        ->assertRedirect(route('admin.appraisal-requests.show', $record));

    $record->refresh();

    expect($record->contract_sequence)->toBe(12);
    expect($record->contract_number)->toBe('00012/AGR/DP/04/2026');
    expect($record->contract_office_code)->toBe('0');
    expect($record->contract_month)->toBe(4);
    expect($record->contract_year)->toBe(2026);
    expect($record->contract_date?->toDateString())->toBe('2026-04-20');
    expect($record->contract_status)->toBe(ContractStatusEnum::Draft);
    expect($record->valuation_duration_days)->toBe(25);
    expect($record->offer_validity_days)->toBe(14);
    expect($record->fee_total)->toBe(17500000);
    expect($record->fee_has_dp)->toBeTrue();
    expect((float) $record->fee_dp_percent)->toBe(50.0);

    Carbon::setTestNow();
});

it('requires dp percent when dp is enabled on the vue admin edit form', function () {
    $admin = createAdminUser();
    $requester = User::factory()->create([
        'email_verified_at' => now(),
    ]);

    $record = AppraisalRequest::create([
        'user_id' => $requester->id,
        'request_number' => 'REQ-DP-001',
        'purpose' => PurposeEnum::JualBeli,
        'status' => AppraisalStatusEnum::Submitted,
        'requested_at' => now(),
    ]);

    $this
        ->actingAs($admin)
        ->from(route('admin.appraisal-requests.edit', $record))
        ->put(route('admin.appraisal-requests.update', $record), [
            'client_name' => 'PT DP',
            'report_type' => 'terinci',
            'fee_has_dp' => true,
            'fee_dp_percent' => '',
        ])
        ->assertRedirect(route('admin.appraisal-requests.edit', $record))
        ->assertSessionHasErrors(['fee_dp_percent']);
});

it('validates report type on the vue admin edit form', function () {
    $admin = createAdminUser();
    $requester = User::factory()->create([
        'email_verified_at' => now(),
    ]);

    $record = AppraisalRequest::create([
        'user_id' => $requester->id,
        'request_number' => 'REQ-INVALID-001',
        'purpose' => PurposeEnum::JualBeli,
        'status' => AppraisalStatusEnum::Submitted,
        'requested_at' => now(),
    ]);

    $this
        ->actingAs($admin)
        ->from(route('admin.appraisal-requests.edit', $record))
        ->put(route('admin.appraisal-requests.update', $record), [
            'client_name' => 'PT Invalid',
            'report_type' => 'foo',
            'user_request_note' => 'x',
            'notes' => 'y',
        ])
        ->assertRedirect(route('admin.appraisal-requests.edit', $record))
        ->assertSessionHasErrors(['report_type']);
});

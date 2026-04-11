<?php

use App\Enums\AppraisalStatusEnum;
use App\Enums\ContractStatusEnum;
use App\Enums\PurposeEnum;
use App\Models\AppraisalAsset;
use App\Models\AppraisalAssetFile;
use App\Models\AppraisalFieldChangeLog;
use App\Models\AppraisalOfferNegotiation;
use App\Models\AppraisalRequest;
use App\Models\AppraisalRequestFile;
use App\Models\AppraisalRequestRevisionBatch;
use App\Models\AppraisalRequestRevisionItem;
use App\Models\Payment;
use App\Models\User;
use App\Services\Backups\AppraisalRequestBackupService;
use App\Support\AdminWorkspaceAccessSynchronizer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Role::findOrCreate('customer', 'web');
    Role::findOrCreate('admin', 'web');
    Role::findOrCreate('super_admin', 'web');

    AdminWorkspaceAccessSynchronizer::sync();
    Storage::fake('public');
});

function makeBackupSuperAdminUser(): User
{
    $user = User::factory()->create([
        'email_verified_at' => now(),
    ]);

    $user->assignRole('super_admin');

    return $user;
}

function makeBackupAdminUser(): User
{
    $user = User::factory()->create([
        'email_verified_at' => now(),
    ]);

    $user->assignRole('admin');

    return $user;
}

function makeBackupCustomerUser(): User
{
    $user = User::factory()->create([
        'email_verified_at' => now(),
    ]);

    $user->assignRole('customer');

    return $user;
}

function createBackupDomainFixture(User $requester, User $actor): array
{
    $request = AppraisalRequest::create([
        'user_id' => $requester->id,
        'request_number' => 'REQ-BACKUP-001',
        'purpose' => PurposeEnum::JualBeli,
        'status' => AppraisalStatusEnum::Completed,
        'requested_at' => now()->subDays(5),
        'client_name' => 'PT Backup Aman',
        'contract_number' => 'CTR-BACKUP-001',
        'contract_status' => ContractStatusEnum::ContractSigned,
        'report_generated_at' => now()->subDay(),
        'report_generated_by' => $actor->id,
        'report_pdf_path' => 'legacy/reports/final.pdf',
        'report_pdf_size' => 4096,
        'report_draft_generated_at' => now()->subDays(2),
        'report_draft_pdf_path' => 'legacy/reports/draft.pdf',
        'report_draft_pdf_size' => 2048,
        'billing_invoice_file_path' => 'legacy/finance/billing.pdf',
        'tax_invoice_file_path' => 'legacy/finance/tax.pdf',
        'withholding_receipt_file_path' => 'legacy/finance/withholding.pdf',
    ]);

    $asset = AppraisalAsset::create([
        'appraisal_request_id' => $request->id,
        'asset_type' => 'tanah_bangunan',
        'asset_code' => 'A-1',
        'address' => 'Jl. Arsip Data No. 11',
    ]);

    $requestFilePath = "appraisal-requests/{$request->id}/request/npwp/npwp-awal.pdf";
    $requestRevisionPath = "appraisal-requests/{$request->id}/revisions/batch-1/request/revision-npwp.pdf";
    $assetFilePath = "appraisal-requests/{$request->id}/assets/{$asset->id}/documents/pbb/pbb-awal.pdf";
    $assetRevisionPath = "appraisal-requests/{$request->id}/revisions/batch-1/assets/{$asset->id}/revision-photo-front.jpg";

    Storage::disk('public')->put($requestFilePath, 'request-file');
    Storage::disk('public')->put($requestRevisionPath, 'request-revision');
    Storage::disk('public')->put($assetFilePath, 'asset-file');
    Storage::disk('public')->put($assetRevisionPath, 'asset-revision');

    $requestFile = AppraisalRequestFile::create([
        'appraisal_request_id' => $request->id,
        'type' => 'npwp',
        'path' => $requestFilePath,
        'original_name' => 'npwp-awal.pdf',
        'mime' => 'application/pdf',
        'size' => 1200,
    ]);

    $requestRevisionFile = AppraisalRequestFile::create([
        'appraisal_request_id' => $request->id,
        'type' => 'npwp',
        'path' => $requestRevisionPath,
        'original_name' => 'npwp-revisi.pdf',
        'mime' => 'application/pdf',
        'size' => 1300,
    ]);

    $assetFile = AppraisalAssetFile::create([
        'appraisal_asset_id' => $asset->id,
        'type' => 'doc_pbb',
        'path' => $assetFilePath,
        'original_name' => 'pbb-awal.pdf',
        'mime' => 'application/pdf',
        'size' => 1400,
    ]);

    $assetRevisionFile = AppraisalAssetFile::create([
        'appraisal_asset_id' => $asset->id,
        'type' => 'photo_front',
        'path' => $assetRevisionPath,
        'original_name' => 'foto-revisi.jpg',
        'mime' => 'image/jpeg',
        'size' => 1500,
    ]);

    $batch = AppraisalRequestRevisionBatch::create([
        'appraisal_request_id' => $request->id,
        'created_by' => $actor->id,
        'submitted_by' => $requester->id,
        'reviewed_by' => $actor->id,
        'status' => 'reviewed',
        'admin_note' => 'Batch revisi backup.',
        'submitted_at' => now()->subDays(3),
        'reviewed_at' => now()->subDays(2),
        'resolved_at' => now()->subDays(1),
    ]);

    $requestRevisionItem = AppraisalRequestRevisionItem::create([
        'revision_batch_id' => $batch->id,
        'item_type' => 'request_file',
        'requested_file_type' => 'npwp',
        'status' => 'approved',
        'issue_note' => 'Dokumen awal kurang jelas.',
        'original_request_file_id' => $requestFile->id,
        'replacement_request_file_id' => $requestRevisionFile->id,
        'reviewed_by' => $actor->id,
        'reviewed_at' => now()->subDays(2),
        'review_note' => 'Sudah valid.',
    ]);

    $assetRevisionItem = AppraisalRequestRevisionItem::create([
        'revision_batch_id' => $batch->id,
        'appraisal_asset_id' => $asset->id,
        'item_type' => 'asset_photo',
        'requested_file_type' => 'photo_front',
        'status' => 'approved',
        'issue_note' => 'Foto depan perlu diulang.',
        'original_asset_file_id' => $assetFile->id,
        'replacement_asset_file_id' => $assetRevisionFile->id,
        'reviewed_by' => $actor->id,
        'reviewed_at' => now()->subDays(2),
        'review_note' => 'Sudah valid.',
    ]);

    Payment::create([
        'appraisal_request_id' => $request->id,
        'amount' => 17500000,
        'method' => 'manual',
        'status' => 'paid',
        'paid_at' => now()->subDays(4),
        'proof_file_path' => 'legacy/payments/proof.png',
        'proof_original_name' => 'proof.png',
        'proof_mime' => 'image/png',
        'proof_size' => 2048,
        'proof_type' => 'upload',
        'metadata' => ['invoice_number' => 'INV-BACKUP-001'],
    ]);

    AppraisalOfferNegotiation::create([
        'appraisal_request_id' => $request->id,
        'user_id' => $actor->id,
        'action' => 'counter_request',
        'round' => 1,
        'offered_fee' => 15000000,
        'expected_fee' => 17500000,
        'selected_fee' => 17500000,
        'reason' => 'Negosiasi backup.',
        'meta' => ['source' => 'test'],
    ]);

    AppraisalFieldChangeLog::create([
        'appraisal_request_id' => $request->id,
        'appraisal_asset_id' => $asset->id,
        'revision_batch_id' => $batch->id,
        'revision_item_id' => $assetRevisionItem->id,
        'changed_by' => $actor->id,
        'change_source' => 'revision',
        'field_key' => 'address',
        'field_label' => 'Alamat',
        'old_value' => ['value' => 'Jl. Lama'],
        'new_value' => ['value' => 'Jl. Arsip Data No. 11'],
        'reason' => 'Sinkron dari revisi.',
    ]);

    return [
        'request' => $request,
        'asset' => $asset,
        'paths' => [
            $requestFilePath,
            $requestRevisionPath,
            $assetFilePath,
            $assetRevisionPath,
        ],
        'request_file_ids' => [$requestFile->id, $requestRevisionFile->id],
        'asset_file_ids' => [$assetFile->id, $assetRevisionFile->id],
        'revision_item_ids' => [$requestRevisionItem->id, $assetRevisionItem->id],
    ];
}

it('allows only super admin to access the backup workspace', function () {
    $superAdmin = makeBackupSuperAdminUser();
    $admin = makeBackupAdminUser();

    $this
        ->actingAs($superAdmin)
        ->get(route('admin.backups.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('Admin/Backups/Index'));

    $this
        ->actingAs($admin)
        ->get(route('admin.backups.index'))
        ->assertForbidden();
});

it('downloads a request backup zip with manifest and required datasets', function () {
    $superAdmin = makeBackupSuperAdminUser();
    $requester = makeBackupCustomerUser();
    $fixture = createBackupDomainFixture($requester, $superAdmin);

    $response = $this
        ->actingAs($superAdmin)
        ->get(route('admin.backups.download', $fixture['request']));

    $response
        ->assertOk()
        ->assertDownload('backup-appraisal-REQ-BACKUP-001.zip');

    $zipPath = $response->baseResponse->getFile()->getPathname();
    $zip = new ZipArchive();

    expect($zip->open($zipPath))->toBeTrue();

    $manifest = json_decode((string) $zip->getFromName('manifest.json'), true);
    $requestFiles = json_decode((string) $zip->getFromName('data/request_files.json'), true);
    $assetFiles = json_decode((string) $zip->getFromName('data/asset_files.json'), true);
    $revisionItems = json_decode((string) $zip->getFromName('data/revision_items.json'), true);

    expect($manifest['backup_type'])->toBe('appraisal_request_v1')
        ->and($manifest['schema_version'])->toBe(1)
        ->and($manifest['request_number'])->toBe('REQ-BACKUP-001')
        ->and($manifest['file_count'])->toBe(4)
        ->and($manifest['data_files'])->toContain('data/request.json', 'data/assets.json', 'data/revision_items.json')
        ->and($zip->locateName('files/request/1-npwp-awal.pdf'))->not->toBeFalse()
        ->and($zip->locateName('files/revisions/request/2-npwp-revisi.pdf'))->not->toBeFalse()
        ->and($zip->locateName('files/assets/1/1-pbb-awal.pdf'))->not->toBeFalse()
        ->and($zip->locateName('files/revisions/assets/1/2-foto-revisi.jpg'))->not->toBeFalse()
        ->and(count($requestFiles))->toBe(2)
        ->and(count($assetFiles))->toBe(2)
        ->and(count($revisionItems))->toBe(2);

    $zip->close();
});

it('restores a valid backup zip into a new appraisal request with remapped files and metadata', function () {
    $superAdmin = makeBackupSuperAdminUser();
    $requester = makeBackupCustomerUser();
    $fixture = createBackupDomainFixture($requester, $superAdmin);
    $backupService = app(AppraisalRequestBackupService::class);
    $archive = $backupService->createBackupZip($fixture['request']->fresh());
    $originalRequestId = $fixture['request']->id;
    $originalPaths = $fixture['paths'];

    $fixture['request']->delete();

    $upload = new UploadedFile(
        $archive['path'],
        basename($archive['path']),
        'application/zip',
        null,
        true
    );

    $this
        ->actingAs($superAdmin)
        ->post(route('admin.backups.restore'), [
            'backup_zip' => $upload,
        ])
        ->assertRedirect(route('admin.backups.index'))
        ->assertSessionHas('backup_restore_summary.request_number', 'REQ-BACKUP-001');

    $restored = AppraisalRequest::query()
        ->where('request_number', 'REQ-BACKUP-001')
        ->with([
            'assets.files',
            'files',
            'revisionBatches.items',
            'payments',
            'offerNegotiations',
            'fieldChangeLogs',
        ])
        ->first();

    expect($restored)->not->toBeNull()
        ->and($restored->id)->not->toBe($originalRequestId)
        ->and($restored->assets)->toHaveCount(1)
        ->and($restored->files)->toHaveCount(2)
        ->and($restored->assets->first()->files)->toHaveCount(2)
        ->and($restored->revisionBatches)->toHaveCount(1)
        ->and($restored->revisionBatches->first()->items)->toHaveCount(2)
        ->and($restored->payments)->toHaveCount(1)
        ->and($restored->offerNegotiations)->toHaveCount(1)
        ->and($restored->fieldChangeLogs)->toHaveCount(1)
        ->and($restored->report_draft_pdf_path)->toBeNull()
        ->and($restored->report_pdf_path)->toBeNull()
        ->and($restored->billing_invoice_file_path)->toBeNull()
        ->and($restored->tax_invoice_file_path)->toBeNull()
        ->and($restored->withholding_receipt_file_path)->toBeNull()
        ->and($restored->payments->first()->proof_file_path)->toBeNull();

    foreach ($restored->files as $file) {
        expect(Storage::disk('public')->exists($file->path))->toBeTrue()
            ->and($originalPaths)->not->toContain($file->path);
    }

    foreach ($restored->assets->first()->files as $file) {
        expect(Storage::disk('public')->exists($file->path))->toBeTrue()
            ->and($originalPaths)->not->toContain($file->path);
    }
});

it('rejects restore when the request number from backup still exists', function () {
    $superAdmin = makeBackupSuperAdminUser();
    $requester = makeBackupCustomerUser();
    $fixture = createBackupDomainFixture($requester, $superAdmin);
    $backupService = app(AppraisalRequestBackupService::class);
    $archive = $backupService->createBackupZip($fixture['request']->fresh());

    $upload = new UploadedFile(
        $archive['path'],
        basename($archive['path']),
        'application/zip',
        null,
        true
    );

    $this
        ->actingAs($superAdmin)
        ->post(route('admin.backups.restore'), [
            'backup_zip' => $upload,
        ])
        ->assertRedirect(route('admin.backups.index'))
        ->assertSessionHas('error');

    expect(AppraisalRequest::query()->where('request_number', 'REQ-BACKUP-001')->count())->toBe(1);
});

it('rejects restore when the backup checksum does not match the manifest', function () {
    $superAdmin = makeBackupSuperAdminUser();
    $requester = makeBackupCustomerUser();
    $fixture = createBackupDomainFixture($requester, $superAdmin);
    $backupService = app(AppraisalRequestBackupService::class);
    $archive = $backupService->createBackupZip($fixture['request']->fresh());

    $zip = new ZipArchive();
    expect($zip->open($archive['path']))->toBeTrue();
    $zip->deleteName('data/request.json');
    $zip->addFromString('data/request.json', json_encode(['tampered' => true]));
    $zip->close();

    $fixture['request']->delete();

    $upload = new UploadedFile(
        $archive['path'],
        basename($archive['path']),
        'application/zip',
        null,
        true
    );

    $this
        ->actingAs($superAdmin)
        ->post(route('admin.backups.restore'), [
            'backup_zip' => $upload,
        ])
        ->assertRedirect(route('admin.backups.index'))
        ->assertSessionHas('error');

    expect(AppraisalRequest::query()->where('request_number', 'REQ-BACKUP-001')->exists())->toBeFalse();
});

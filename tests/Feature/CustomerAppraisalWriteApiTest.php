<?php

use App\Enums\AppraisalStatusEnum;
use App\Models\AppraisalRequest;
use App\Models\ConsentDocument;
use App\Models\District;
use App\Models\GuidelineSet;
use App\Models\Province;
use App\Models\Regency;
use App\Models\User;
use App\Models\Village;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Role::findOrCreate('customer', 'web');
    Storage::fake('public');
});

it('creates, reads, and updates a persistent customer draft', function (): void {
    [$customer, $token] = appraisalWriteCustomer();

    $create = $this->withToken($token)
        ->postJson('/api/v1/appraisals/drafts', [
            'purpose' => 'jual_beli',
            'report_type' => 'terinci',
            'client_name' => 'PT Draft Mobile',
        ])
        ->assertCreated()
        ->assertJsonPath('data.status.value', 'draft')
        ->assertJsonPath('data.client_name', 'PT Draft Mobile');

    $draftId = $create->json('data.id');

    $this->withToken($token)
        ->putJson("/api/v1/appraisals/drafts/{$draftId}", [
            'client_spk_number' => 'SPK-MOBILE-001',
            'user_request_note' => 'Simpan otomatis dari step pertama.',
        ])
        ->assertOk()
        ->assertJsonPath('data.client_spk_number', 'SPK-MOBILE-001');

    $this->withToken($token)
        ->getJson("/api/v1/appraisals/drafts/{$draftId}")
        ->assertOk()
        ->assertJsonPath('data.user_request_note', 'Simpan otomatis dari step pertama.');

    expect(AppraisalRequest::query()->findOrFail($draftId)->user_id)->toBe($customer->id);
});

it('never exposes or mutates another customer draft', function (): void {
    [$owner] = appraisalWriteCustomer();
    [$other, $otherToken] = appraisalWriteCustomer();

    expect($owner->id)->not->toBe($other->id);

    $draftId = AppraisalRequest::query()->create([
        'user_id' => $owner->id,
        'purpose' => 'jual_beli',
        'valuation_objective' => 'kajian_nilai_pasar_dalam_bentuk_range',
        'status' => AppraisalStatusEnum::Draft,
        'client_name' => $owner->name,
        'report_type' => 'terinci',
        'report_format' => 'both',
        'physical_copies_count' => 1,
    ])->id;

    $this->withToken($otherToken)
        ->getJson("/api/v1/appraisals/drafts/{$draftId}")
        ->assertNotFound();

    $this->withToken($otherToken)
        ->putJson("/api/v1/appraisals/drafts/{$draftId}", ['client_name' => 'Bajak'])
        ->assertNotFound();

    $this->withToken($otherToken)
        ->postJson("/api/v1/appraisals/drafts/{$draftId}/assets", ['asset_type' => 'tanah'])
        ->assertNotFound();
});

it('adds, updates, and removes draft assets under the owned draft', function (): void {
    [, $token] = appraisalWriteCustomer();
    appraisalWriteLocations();
    $draftId = appraisalWriteDraft($this, $token);

    $created = $this->withToken($token)
        ->postJson("/api/v1/appraisals/drafts/{$draftId}/assets", [
            'asset_type' => 'rumah_tinggal',
            'province_id' => '31',
            'regency_id' => '3171',
            'district_id' => '3171010',
            'village_id' => '3171010001',
            'address' => 'Jl. Draft Aset No. 1',
        ])
        ->assertCreated()
        ->assertJsonPath('data.assets.0.asset_type', 'tanah_bangunan');

    $assetId = $created->json('data.assets.0.id');

    $this->withToken($token)
        ->putJson("/api/v1/appraisals/drafts/{$draftId}/assets/{$assetId}", [
            'land_area' => 150,
            'building_area' => 90,
        ])
        ->assertOk()
        ->assertJsonPath('data.assets.0.land_area', 150)
        ->assertJsonPath('data.assets.0.building_area', 90);

    $this->withToken($token)
        ->deleteJson("/api/v1/appraisals/drafts/{$draftId}/assets/{$assetId}")
        ->assertOk()
        ->assertJsonCount(0, 'data.assets');
});

it('validates, stores, lists, and deletes draft asset files', function (): void {
    [, $token] = appraisalWriteCustomer();
    appraisalWriteLocations();
    [$draftId, $assetId] = appraisalWriteDraftWithAsset($this, $token);

    $upload = $this->withToken($token)
        ->post("/api/v1/appraisals/drafts/{$draftId}/assets/{$assetId}/files", [
            'type' => 'doc_pbb',
            'files' => [UploadedFile::fake()->create('pbb.pdf', 200, 'application/pdf')],
        ], ['Accept' => 'application/json'])
        ->assertCreated()
        ->assertJsonPath('data.0.type', 'doc_pbb')
        ->assertJsonMissingPath('data.0.path');

    $fileId = $upload->json('data.0.id');
    $path = \App\Models\AppraisalAssetFile::query()->findOrFail($fileId)->path;
    Storage::disk('public')->assertExists($path);

    $this->withToken($token)
        ->post("/api/v1/appraisals/drafts/{$draftId}/assets/{$assetId}/files", [
            'type' => 'photo_front',
            'files' => [UploadedFile::fake()->create('malware.pdf', 10, 'application/pdf')],
        ], ['Accept' => 'application/json'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('files.0');

    $this->withToken($token)
        ->deleteJson("/api/v1/appraisals/drafts/{$draftId}/files/{$fileId}")
        ->assertNoContent();

    Storage::disk('public')->assertMissing($path);
});

it('rejects submission while draft data and required files are incomplete', function (): void {
    [, $token] = appraisalWriteCustomer([
        'phone_number' => '081234567890',
        'billing_recipient_name' => 'Penerima Laporan',
        'billing_address_detail' => 'Jl. Billing Mobile No. 10',
    ]);
    GuidelineSet::query()->create([
        'name' => 'Guideline Mobile',
        'year' => 2026,
        'is_active' => true,
    ]);
    $draftId = appraisalWriteDraft($this, $token);

    $this->withToken($token)
        ->postJson("/api/v1/appraisals/drafts/{$draftId}/submit")
        ->assertUnprocessable()
        ->assertJsonValidationErrors('assets');
});

it('accepts current consent and submits a complete draft exactly once', function (): void {
    [$customer, $token] = appraisalWriteCustomer([
        'phone_number' => '081234567890',
        'billing_recipient_name' => 'Penerima Laporan',
        'billing_address_detail' => 'Jl. Billing Mobile No. 10',
    ]);
    appraisalWriteLocations();
    GuidelineSet::query()->create([
        'name' => 'Guideline Mobile 2026',
        'year' => 2026,
        'is_active' => true,
    ]);
    $consent = appraisalWriteConsent();
    [$draftId, $assetId] = appraisalWriteDraftWithAsset($this, $token, [
        'sertifikat_on_hand_confirmed' => true,
        'certificate_not_encumbered_confirmed' => true,
    ]);

    appraisalWriteUpload($this, $token, $draftId, $assetId, 'doc_certs', appraisalWritePdf('sertifikat.pdf'));
    appraisalWriteUpload($this, $token, $draftId, $assetId, 'doc_pbb', appraisalWritePdf('pbb.pdf'));
    appraisalWriteUpload($this, $token, $draftId, $assetId, 'doc_imb', appraisalWritePdf('imb.pdf'));
    appraisalWriteUpload($this, $token, $draftId, $assetId, 'photo_access_road', appraisalWriteImage('akses.jpg'));
    appraisalWriteUpload($this, $token, $draftId, $assetId, 'photo_front', appraisalWriteImage('depan.jpg'));
    appraisalWriteUpload($this, $token, $draftId, $assetId, 'photo_interior', appraisalWriteImage('interior.jpg'));

    $this->withToken($token)
        ->postJson("/api/v1/appraisals/drafts/{$draftId}/submit")
        ->assertUnprocessable()
        ->assertJsonValidationErrors('consent');

    $this->withToken($token)
        ->postJson('/api/v1/appraisals/consent/accept', [
            'document_id' => $consent->id,
            'hash' => $consent->hash,
            'accepted' => true,
        ])
        ->assertOk()
        ->assertJsonPath('data.document_id', $consent->id);

    $this->withToken($token)
        ->postJson("/api/v1/appraisals/drafts/{$draftId}/submit")
        ->assertOk()
        ->assertJsonPath('data.id', $draftId)
        ->assertJsonPath('data.status', 'submitted');

    $record = AppraisalRequest::query()->findOrFail($draftId);
    expect($record->status)->toBe(AppraisalStatusEnum::Submitted)
        ->and($record->guideline_set_id)->not->toBeNull()
        ->and($record->consent_hash)->toBe($consent->hash)
        ->and($record->requested_at)->not->toBeNull()
        ->and($customer->notifications()->count())->toBe(1);

    $this->withToken($token)
        ->postJson("/api/v1/appraisals/drafts/{$draftId}/submit")
        ->assertNotFound();
});

function appraisalWriteCustomer(array $attributes = []): array
{
    $user = User::factory()->create($attributes);
    $user->assignRole('customer');

    return [$user, $user->createToken('appraisal-write-test', ['mobile:customer'])->plainTextToken];
}

function appraisalWriteLocations(): void
{
    Province::query()->create(['id' => '31', 'name' => 'DKI Jakarta']);
    Regency::query()->create(['id' => '3171', 'province_id' => '31', 'name' => 'Jakarta Selatan']);
    District::query()->create(['id' => '3171010', 'regency_id' => '3171', 'name' => 'Tebet']);
    Village::query()->create(['id' => '3171010001', 'district_id' => '3171010', 'name' => 'Tebet Barat']);
}

function appraisalWriteDraft($test, string $token, array $overrides = []): int
{
    return $test->withToken($token)
        ->postJson('/api/v1/appraisals/drafts', array_merge([
            'purpose' => 'jual_beli',
            'report_type' => 'terinci',
            'client_name' => 'Customer Mobile',
        ], $overrides))
        ->assertCreated()
        ->json('data.id');
}

function appraisalWriteDraftWithAsset($test, string $token, array $draftOverrides = []): array
{
    $draftId = appraisalWriteDraft($test, $token, $draftOverrides);
    $response = $test->withToken($token)
        ->postJson("/api/v1/appraisals/drafts/{$draftId}/assets", [
            'asset_type' => 'rumah_tinggal',
            'title_document' => 'shm',
            'province_id' => '31',
            'regency_id' => '3171',
            'district_id' => '3171010',
            'village_id' => '3171010001',
            'address' => 'Jl. Properti Mobile No. 1',
            'coordinates_lat' => -6.2297,
            'coordinates_lng' => 106.8295,
            'land_area' => 120,
            'building_area' => 80,
            'building_floors' => 2,
            'build_year' => 2020,
        ])
        ->assertCreated();

    return [$draftId, $response->json('data.assets.0.id')];
}

function appraisalWriteConsent(): ConsentDocument
{
    return ConsentDocument::query()->create([
        'code' => 'appraisal_request_consent',
        'version' => '2026-06-v1',
        'title' => 'Persetujuan Permohonan',
        'sections' => [['heading' => 'Pernyataan', 'items' => ['Data benar.']]],
        'checkbox_label' => 'Saya menyetujui.',
        'hash' => str_repeat('a', 64),
        'status' => 'published',
        'published_at' => now(),
    ]);
}

function appraisalWriteUpload($test, string $token, int $draftId, int $assetId, string $type, UploadedFile $file): void
{
    $test->withToken($token)
        ->post("/api/v1/appraisals/drafts/{$draftId}/assets/{$assetId}/files", [
            'type' => $type,
            'files' => [$file],
        ], ['Accept' => 'application/json'])
        ->assertCreated();
}

function appraisalWritePdf(string $name): UploadedFile
{
    return UploadedFile::fake()->create($name, 100, 'application/pdf');
}

function appraisalWriteImage(string $name): UploadedFile
{
    return UploadedFile::fake()->image($name, 800, 600);
}

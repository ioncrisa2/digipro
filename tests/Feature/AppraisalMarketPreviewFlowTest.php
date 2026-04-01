<?php

use App\Enums\AppraisalStatusEnum;
use App\Enums\ContractStatusEnum;
use App\Models\AppraisalAsset;
use App\Models\AppraisalRequest;
use App\Models\GuidelineSet;
use App\Models\Payment;
use App\Models\ReportSigner;
use App\Models\User;
use App\Support\AdminWorkspaceAccessSynchronizer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

it('rejects reviewer finalization when any asset does not have a complete market range', function () {
    Storage::fake('public');
    syncPreviewWorkspaceRoles();

    $customer = User::factory()->create(['email_verified_at' => now()]);
    $reviewer = User::factory()->create(['email_verified_at' => now()]);
    $reviewer->assignRole('Reviewer');

    $record = createMarketPreviewRequest($customer, [
        'status' => AppraisalStatusEnum::ValuationOnProgress,
    ]);

    $record->assets()->latest('id')->firstOrFail()->update([
        'estimated_value_high' => null,
    ]);

    $this->actingAs($reviewer)
        ->postJson(route('reviewer.api.reviews.finish', ['review' => $record->id]))
        ->assertStatus(422);

    $record->refresh();

    expect($record->status)->toBe(AppraisalStatusEnum::ValuationOnProgress);
    expect($record->market_preview_snapshot)->toBeNull();
});

it('publishes market preview when reviewer finishes review and exposes the preview page to the customer', function () {
    Storage::fake('public');
    syncPreviewWorkspaceRoles();

    $customer = User::factory()->create(['email_verified_at' => now()]);
    $reviewer = User::factory()->create(['email_verified_at' => now()]);
    $reviewer->assignRole('Reviewer');

    $record = createMarketPreviewRequest($customer, [
        'status' => AppraisalStatusEnum::ValuationOnProgress,
    ]);

    $this->actingAs($reviewer)
        ->postJson(route('reviewer.api.reviews.finish', ['review' => $record->id]))
        ->assertOk()
        ->assertJsonPath('review.status.value', AppraisalStatusEnum::PreviewReady->value);

    $record->refresh();

    expect($record->status)->toBe(AppraisalStatusEnum::PreviewReady);
    expect($record->market_preview_snapshot)->not->toBeNull();
    expect(data_get($record->market_preview_snapshot, 'summary.market_value_final'))->toBe(2400000000);

    $this->actingAs($customer)
        ->get(route('appraisal.market-preview.page', ['id' => $record->id]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Penilaian/MarketPreview')
            ->where('preview.request_number', $record->request_number)
            ->where('preview.can_approve', true)
            ->where('preview.can_appeal', true)
            ->where('preview.summary.market_value_final', 2400000000)
            ->has('preview.assets', 2)
        );
});

it('allows the customer to submit a single appeal and blocks a second appeal after preview is republished', function () {
    Storage::fake('public');
    syncPreviewWorkspaceRoles();

    $customer = User::factory()->create(['email_verified_at' => now()]);
    $reviewer = User::factory()->create(['email_verified_at' => now()]);
    $reviewer->assignRole('Reviewer');

    $record = createMarketPreviewRequest($customer, [
        'status' => AppraisalStatusEnum::PreviewReady,
        'market_preview_snapshot' => buildMarketPreviewSnapshotPayload(),
        'market_preview_version' => 1,
        'market_preview_published_at' => now()->subMinute(),
    ]);

    $this->actingAs($customer)
        ->post(route('appraisal.market-preview.appeal', ['id' => $record->id]), [
            'reason' => 'Perlu ditinjau ulang karena kondisi akses jalan menurut saya belum tercermin.',
        ])
        ->assertRedirect(route('appraisal.show', ['id' => $record->id]));

    $record->refresh();

    expect($record->status)->toBe(AppraisalStatusEnum::ValuationOnProgress);
    expect($record->market_preview_appeal_count)->toBe(1);
    expect($record->market_preview_appeal_reason)->toContain('akses jalan');

    $this->actingAs($reviewer)
        ->postJson(route('reviewer.api.reviews.finish', ['review' => $record->id]))
        ->assertOk()
        ->assertJsonPath('review.status.value', AppraisalStatusEnum::PreviewReady->value);

    $record->refresh();
    expect($record->market_preview_version)->toBe(2);

    $this->actingAs($customer)
        ->post(route('appraisal.market-preview.appeal', ['id' => $record->id]), [
            'reason' => 'Banding kedua.',
        ])
        ->assertRedirect(route('appraisal.market-preview.page', ['id' => $record->id]))
        ->assertSessionHas('error');

    $this->actingAs($customer)
        ->get(route('appraisal.market-preview.page', ['id' => $record->id]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('preview.can_appeal', false)
            ->where('preview.appeal_remaining', 0)
        );
});

it('creates an admin draft after customer approval and only exposes the final report after admin upload', function () {
    Storage::fake('public');
    syncPreviewWorkspaceRoles();

    $customer = User::factory()->create(['email_verified_at' => now()]);
    $admin = User::factory()->create(['email_verified_at' => now()]);
    $admin->assignRole('admin');

    $record = createMarketPreviewRequest($customer, [
        'status' => AppraisalStatusEnum::PreviewReady,
        'market_preview_snapshot' => buildMarketPreviewSnapshotPayload(),
        'market_preview_version' => 1,
        'market_preview_published_at' => now()->subMinute(),
    ]);

    $this->actingAs($customer)
        ->post(route('appraisal.market-preview.approve', ['id' => $record->id]))
        ->assertRedirect(route('appraisal.show', ['id' => $record->id]));

    $record->refresh();

    Payment::create([
        'appraisal_request_id' => $record->id,
        'amount' => 2500000,
        'method' => 'gateway',
        'gateway' => 'midtrans',
        'external_payment_id' => 'PAY-' . Str::upper(Str::random(8)),
        'status' => 'paid',
        'paid_at' => now()->subMinutes(5),
        'metadata' => [
            'invoice_number' => 'INV-' . Str::upper(Str::random(6)),
        ],
    ]);

    expect($record->status)->toBe(AppraisalStatusEnum::ReportPreparation);
    expect($record->market_preview_approved_at)->not->toBeNull();
    expect($record->report_draft_pdf_path)->not->toBeNull();
    Storage::disk('public')->assertExists($record->report_draft_pdf_path);

    $draftName = 'Draft-Laporan-' . preg_replace('/[^A-Za-z0-9\-_.]/', '-', (string) $record->request_number) . '.pdf';

    $reviewerSigner = ReportSigner::query()->create([
        'role' => 'reviewer',
        'name' => 'Reviewer Sertifikasi',
        'position_title' => 'Reviewer Bersertifikasi',
        'certification_number' => 'REV-001',
        'is_active' => true,
    ]);
    $publicAppraiserSigner = ReportSigner::query()->create([
        'role' => 'public_appraiser',
        'name' => 'Penilai Publik',
        'position_title' => 'Penilai Publik',
        'certification_number' => 'PP-001',
        'is_active' => true,
    ]);

    $this->actingAs($admin)
        ->post(route('admin.appraisal-requests.actions.report-configuration', ['appraisalRequest' => $record->id]), [
            'report_reviewer_signer_id' => $reviewerSigner->id,
            'report_public_appraiser_signer_id' => $publicAppraiserSigner->id,
        ])
        ->assertRedirect();

    $this->actingAs($admin)
        ->get(route('admin.appraisal-requests.actions.report-draft', ['appraisalRequest' => $record->id]))
        ->assertOk()
        ->assertDownload($draftName);

    $this->actingAs($customer)
        ->get(route('reports.show', ['id' => $record->id]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Reports/Show')
            ->where('report.summary.ready_report', false)
            ->where('report.system_documents', fn ($files) => ! collect($files)->pluck('type')->contains('report_pdf'))
        );

    $this->actingAs($admin)
        ->post(route('admin.appraisal-requests.actions.report-final', ['appraisalRequest' => $record->id]), [
            'report_pdf' => UploadedFile::fake()->create('laporan-final.pdf', 128, 'application/pdf'),
        ])
        ->assertRedirect();

    $record->refresh();

    expect($record->status)->toBe(AppraisalStatusEnum::Completed);
    expect($record->report_pdf_path)->not->toBeNull();
    expect($record->report_generated_at)->not->toBeNull();
    expect(data_get($record->report_signer_snapshot, 'reviewer.name'))->toBe('Reviewer Sertifikasi');
    Storage::disk('public')->assertExists($record->report_pdf_path);

    $this->actingAs($customer)
        ->get(route('reports.show', ['id' => $record->id]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Reports/Show')
            ->where('report.summary.ready_report', true)
            ->where('report.summary.ready_legal_documents', true)
            ->where('report.system_documents', fn ($files) => collect($files)->pluck('type')->contains('report_pdf'))
            ->where('report.legal_documents', fn ($files) => collect($files)->pluck('type')->contains('agreement_pdf')
                && collect($files)->pluck('type')->contains('disclaimer_pdf')
                && collect($files)->pluck('type')->contains('representative_letter_pdf'))
        );
});

function syncPreviewWorkspaceRoles(): void
{
    createPreviewGuidelineSet();
    AdminWorkspaceAccessSynchronizer::sync();
}

function createPreviewGuidelineSet(): GuidelineSet
{
    return GuidelineSet::query()->firstOrCreate(
        ['year' => 2026],
        [
            'name' => 'Guideline Preview 2026',
            'description' => 'Guideline aktif untuk flow preview kajian pasar.',
            'is_active' => true,
        ]
    );
}

function createMarketPreviewRequest(User $customer, array $overrides = []): AppraisalRequest
{
    $record = AppraisalRequest::create(array_merge([
        'user_id' => $customer->id,
        'request_number' => 'REQ-PVW-' . Str::upper(Str::random(6)),
        'purpose' => 'jual_beli',
        'valuation_objective' => 'kajian_nilai_pasar_dalam_bentuk_range',
        'status' => AppraisalStatusEnum::ValuationOnProgress,
        'requested_at' => now()->subDay(),
        'client_name' => 'PT Preview Customer',
        'contract_number' => '00123/AGR/DP/04/2026',
        'contract_date' => now()->toDateString(),
        'contract_status' => ContractStatusEnum::ContractSigned,
        'fee_total' => 2500000,
        'report_type' => 'terinci',
        'report_format' => 'digital',
        'sertifikat_on_hand_confirmed' => true,
        'certificate_not_encumbered_confirmed' => true,
        'certificate_statements_accepted_at' => now()->subHours(2),
    ], $overrides));

    AppraisalAsset::create([
        'appraisal_request_id' => $record->id,
        'asset_type' => 'tanah_bangunan',
        'address' => 'Jl. Preview Utama No. 1',
        'land_area' => 120,
        'building_area' => 80,
        'estimated_value_low' => 1000000000,
        'market_value_final' => 1200000000,
        'estimated_value_high' => 1400000000,
    ]);

    AppraisalAsset::create([
        'appraisal_request_id' => $record->id,
        'asset_type' => 'tanah_bangunan',
        'address' => 'Jl. Preview Kedua No. 2',
        'land_area' => 140,
        'building_area' => 90,
        'estimated_value_low' => 900000000,
        'market_value_final' => 1200000000,
        'estimated_value_high' => 1500000000,
    ]);

    return $record;
}

function buildMarketPreviewSnapshotPayload(): array
{
    return [
        'version' => 1,
        'published_at' => now()->toDateTimeString(),
        'request' => [
            'request_number' => 'REQ-PVW-SNAPSHOT',
            'client_name' => 'PT Preview Customer',
        ],
        'summary' => [
            'estimated_value_low' => 1900000000,
            'market_value_final' => 2400000000,
            'estimated_value_high' => 2900000000,
            'assets_count' => 2,
        ],
        'assets' => [
            [
                'asset_id' => 1,
                'asset_type' => 'tanah_bangunan',
                'asset_type_label' => 'Tanah Bangunan',
                'address' => 'Jl. Preview Utama No. 1',
                'land_area' => 120,
                'building_area' => 80,
                'estimated_value_low' => 1000000000,
                'market_value_final' => 1200000000,
                'estimated_value_high' => 1400000000,
            ],
            [
                'asset_id' => 2,
                'asset_type' => 'tanah_bangunan',
                'asset_type_label' => 'Tanah Bangunan',
                'address' => 'Jl. Preview Kedua No. 2',
                'land_area' => 140,
                'building_area' => 90,
                'estimated_value_low' => 900000000,
                'market_value_final' => 1200000000,
                'estimated_value_high' => 1500000000,
            ],
        ],
    ];
}

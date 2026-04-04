<?php

use App\Enums\AppraisalStatusEnum;
use App\Enums\ContractStatusEnum;
use App\Models\AppraisalAsset;
use App\Models\AppraisalRequest;
use App\Models\GuidelineSet;
use App\Models\User;
use App\Services\Reports\AppraisalReportPayloadBuilder;
use App\Support\AdminWorkspaceAccessSynchronizer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

it('renders the digipro draft report without inspection date or middle value text', function () {
    Storage::fake('public');
    createReportGuidelineSet();

    $customer = User::factory()->create(['email_verified_at' => now()]);
    $request = AppraisalRequest::create([
        'user_id' => $customer->id,
        'request_number' => 'REQ-RPT-' . Str::upper(Str::random(6)),
        'purpose' => 'jual_beli',
        'valuation_objective' => 'kajian_nilai_pasar_dalam_bentuk_range',
        'status' => AppraisalStatusEnum::ReportPreparation,
        'requested_at' => now()->subDays(5),
        'client_name' => 'PT Contoh Client',
        'contract_number' => '001/AGR/DP/04/2026',
        'contract_date' => now()->toDateString(),
        'contract_status' => ContractStatusEnum::ContractSigned,
        'market_preview_version' => 1,
        'market_preview_published_at' => now()->subDay(),
        'market_preview_snapshot' => [
            'summary' => [
                'estimated_value_low' => 700000000,
                'market_value_final' => 900000000,
                'estimated_value_high' => 1100000000,
                'assets_count' => 1,
            ],
            'assets' => [[
                'asset_id' => 1,
                'estimated_value_low' => 700000000,
                'market_value_final' => 900000000,
                'estimated_value_high' => 1100000000,
            ]],
        ],
        'report_signer_snapshot' => [
            'reviewer' => [
                'name' => 'Reviewer Bersertifikasi',
                'position_title' => 'Reviewer Bersertifikasi',
                'certification_number' => 'REV-123',
            ],
            'public_appraiser' => [
                'name' => 'Penilai Publik',
                'position_title' => 'Penilai Publik',
                'certification_number' => 'PP-456',
            ],
        ],
    ]);

    $asset = AppraisalAsset::create([
        'appraisal_request_id' => $request->id,
        'asset_type' => 'tanah',
        'title_document' => 'shm',
        'certificate_number' => 'SHM 685',
        'certificate_holder_name' => 'PT Contoh Client',
        'address' => 'Jl. Contoh No. 1',
        'land_area' => 120,
        'document_land_area' => 120,
        'estimated_value_low' => 700000000,
        'market_value_final' => 900000000,
        'estimated_value_high' => 1100000000,
    ]);

    $request->update([
        'market_preview_snapshot' => [
            'summary' => [
                'estimated_value_low' => 700000000,
                'market_value_final' => 900000000,
                'estimated_value_high' => 1100000000,
                'assets_count' => 1,
            ],
            'assets' => [[
                'asset_id' => $asset->id,
                'estimated_value_low' => 700000000,
                'market_value_final' => 900000000,
                'estimated_value_high' => 1100000000,
            ]],
        ],
    ]);

    $payload = app(AppraisalReportPayloadBuilder::class)->build($request->fresh());
    $html = view('pdfs.appraisal-market-report-draft', ['report' => $payload])->render();

    expect($html)->toContain('Tanggal Penilaian');
    expect($html)->not->toContain('Tanggal Inspeksi');
    expect($html)->not->toContain('Nilai Tengah');
    expect($html)->not->toContain('Data Bangunan');
});

it('shows building section in the draft report only when the asset has building data', function () {
    Storage::fake('public');
    createReportGuidelineSet();

    $customer = User::factory()->create(['email_verified_at' => now()]);
    $request = AppraisalRequest::create([
        'user_id' => $customer->id,
        'request_number' => 'REQ-RPT-' . Str::upper(Str::random(6)),
        'purpose' => 'jual_beli',
        'valuation_objective' => 'kajian_nilai_pasar_dalam_bentuk_range',
        'status' => AppraisalStatusEnum::ReportPreparation,
        'requested_at' => now()->subDays(5),
        'client_name' => 'PT Contoh Client',
        'contract_number' => '001/AGR/DP/04/2026',
        'contract_date' => now()->toDateString(),
        'contract_status' => ContractStatusEnum::ContractSigned,
        'market_preview_version' => 1,
        'market_preview_published_at' => now()->subDay(),
        'report_signer_snapshot' => [
            'reviewer' => ['name' => 'Reviewer', 'position_title' => 'Reviewer', 'certification_number' => 'REV'],
            'public_appraiser' => ['name' => 'Penilai Publik', 'position_title' => 'Penilai Publik', 'certification_number' => 'PP'],
        ],
    ]);

    $asset = AppraisalAsset::create([
        'appraisal_request_id' => $request->id,
        'asset_type' => 'tanah_bangunan',
        'title_document' => 'shm',
        'address' => 'Jl. Bangunan No. 2',
        'land_area' => 150,
        'building_area' => 85,
        'building_floors' => 2,
        'build_year' => 2018,
        'estimated_value_low' => 1200000000,
        'market_value_final' => 1450000000,
        'estimated_value_high' => 1700000000,
    ]);

    $request->update([
        'market_preview_snapshot' => [
            'summary' => [
                'estimated_value_low' => 1200000000,
                'market_value_final' => 1450000000,
                'estimated_value_high' => 1700000000,
                'assets_count' => 1,
            ],
            'assets' => [[
                'asset_id' => $asset->id,
                'estimated_value_low' => 1200000000,
                'market_value_final' => 1450000000,
                'estimated_value_high' => 1700000000,
            ]],
        ],
    ]);

    $payload = app(AppraisalReportPayloadBuilder::class)->build($request->fresh());
    $html = view('pdfs.appraisal-market-report-draft', ['report' => $payload])->render();

    expect($html)->toContain('Data Bangunan');
    expect($html)->toContain('Luas Bangunan');
});

function createReportGuidelineSet(): GuidelineSet
{
    AdminWorkspaceAccessSynchronizer::sync();

    return GuidelineSet::query()->firstOrCreate(
        ['year' => 2026],
        [
            'name' => 'Guideline Report 2026',
            'description' => 'Guideline aktif untuk report DigiPro.',
            'is_active' => true,
        ]
    );
}

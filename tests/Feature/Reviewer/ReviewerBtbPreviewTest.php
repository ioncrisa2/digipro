<?php

use App\Enums\AppraisalStatusEnum;
use App\Enums\PurposeEnum;
use App\Models\AppraisalAsset;
use App\Models\AppraisalRequest;
use App\Models\BuildingEconomicLife;
use App\Models\BuildingValuation;
use App\Models\ConstructionCostIndex;
use App\Models\CostElement;
use App\Models\FloorIndex;
use App\Models\GuidelineSet;
use App\Models\MappiRcnStandard;
use App\Models\User;
use App\Models\ValuationSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Role::findOrCreate('Reviewer', 'web');
});

function seedBtbReviewerReference(): GuidelineSet
{
    $guideline = GuidelineSet::create([
        'name' => 'Guideline Reviewer BTB',
        'year' => 2026,
        'is_active' => true,
    ]);

    ValuationSetting::create([
        'guideline_set_id' => $guideline->id,
        'year' => 2026,
        'key' => ValuationSetting::KEY_PPN_PERCENT,
        'label' => 'PPN (%)',
        'value_number' => 11,
    ]);

    ConstructionCostIndex::create([
        'guideline_set_id' => $guideline->id,
        'year' => 2026,
        'region_code' => '3171',
        'region_name' => 'Kota Jakarta',
        'ikk_value' => 1,
    ]);

    FloorIndex::create([
        'guideline_set_id' => $guideline->id,
        'year' => 2026,
        'building_class' => 'MENENGAH',
        'floor_count' => 2,
        'il_value' => 1,
    ]);

    BuildingEconomicLife::create([
        'guideline_item_id' => $guideline->id,
        'year' => 2026,
        'category' => 'Rumah Tinggal',
        'sub_category' => 'Menengah',
        'building_type' => 'BANGUNAN_RUMAH_TINGGAL',
        'building_class' => 'MENENGAH',
        'storey_min' => 1,
        'storey_max' => 3,
        'economic_life' => 50,
    ]);

    MappiRcnStandard::create([
        'guideline_set_id' => $guideline->id,
        'year' => 2026,
        'reference_region' => 'DKI Jakarta',
        'building_type' => 'BANGUNAN_RUMAH_TINGGAL',
        'building_class' => 'MENENGAH',
        'storey_pattern' => '2 Lantai',
        'rcn_value' => 2000000,
    ]);

    $groups = [
        ['PONDASI', 'Tapak Beton', 'FDN', 100000],
        ['STRUKTUR', 'Beton Bertulang', 'STR', 200000],
        ['RANGKA ATAP', 'Baja Ringan', 'RAF', 300000],
        ['PENUTUP ATAP', 'Genteng Beton', 'ROC', 400000],
        ['PLAFOND', 'Gipsum', 'CEI', 500000],
        ['DINDING', 'Bata Merah', 'WAL', 600000],
        ['PINTU & JENDELA', 'Kayu', 'DOW', 700000],
        ['LANTAI', 'Keramik', 'FLR', 800000],
        ['UTILITAS', 'Elektrikal', 'UTL', 900000],
    ];

    foreach ($groups as [$group, $name, $code, $cost]) {
        CostElement::create([
            'guideline_set_id' => $guideline->id,
            'year' => 2026,
            'base_region' => 'DKI Jakarta',
            'group' => $group,
            'element_code' => $code,
            'element_name' => $name,
            'building_type' => 'BANGUNAN_RUMAH_TINGGAL',
            'building_class' => 'MENENGAH',
            'storey_pattern' => '2 Lantai',
            'unit' => 'm2',
            'unit_cost' => $cost,
            'spec_json' => [
                'material_spec' => $name,
                'default_volume_percent' => 1,
                'source_sheet' => 'BUT_Print',
            ],
        ]);
    }

    return $guideline;
}

function createReviewerWithAsset(GuidelineSet $guideline, array $assetOverrides = []): array
{
    $reviewer = User::factory()->create();
    $reviewer->assignRole('Reviewer');

    $request = AppraisalRequest::create([
        'user_id' => $reviewer->id,
        'request_number' => 'REQ-BTB-001',
        'purpose' => PurposeEnum::JualBeli,
        'status' => AppraisalStatusEnum::ValuationOnProgress,
        'requested_at' => now(),
        'guideline_set_id' => $guideline->id,
    ]);

    $asset = AppraisalAsset::create(array_merge([
        'appraisal_request_id' => $request->id,
        'asset_type' => 'tanah_bangunan',
        'peruntukan' => 'rumah_tinggal',
        'title_document' => 'shm',
        'land_shape' => 'persegi',
        'land_position' => 'tengah',
        'land_condition' => 'matang',
        'topography' => 'datar_sama_dengan_jalan',
        'address' => 'Jl. Reviewer BTB',
        'land_area' => 150,
        'building_area' => 100,
        'building_floors' => 2,
        'build_year' => 2016,
        'regency_id' => '3171',
    ], $assetOverrides));

    return [$reviewer, $asset];
}

it('returns BTB preview payload for a built asset in reviewer btb preview', function () {
    $guideline = seedBtbReviewerReference();
    [$reviewer, $asset] = createReviewerWithAsset($guideline);

    $response = $this
        ->actingAs($reviewer)
        ->postJson(route('reviewer.api.assets.btb.preview', $asset), [
            'btb_input' => [
                'template_key' => 'rumah_menengah',
                'effective_age' => 10,
            ],
        ]);

    $response
        ->assertOk()
        ->assertJsonPath('btb.enabled', true)
        ->assertJsonPath('btb.input.template_key', 'rumah_menengah')
        ->assertJsonPath('btb.state.context.template_key', 'rumah_menengah')
        ->assertJsonPath('btb.state.context.usage', 'rumah_tinggal');
});

it('blocks BTB preview route for land only assets', function () {
    $guideline = seedBtbReviewerReference();
    [$reviewer, $asset] = createReviewerWithAsset($guideline, [
        'asset_type' => 'tanah',
        'peruntukan' => 'tanah_kosong',
        'building_area' => 0,
        'building_floors' => null,
        'build_year' => null,
    ]);

    $response = $this
        ->actingAs($reviewer)
        ->postJson(route('reviewer.api.assets.btb.preview', $asset));

    $response->assertNotFound();
});

it('renders dedicated BTB page only for assets with buildings', function () {
    $guideline = seedBtbReviewerReference();
    [$reviewer, $asset] = createReviewerWithAsset($guideline);

    $this
        ->actingAs($reviewer)
        ->get(route('reviewer.assets.btb', $asset))
        ->assertOk();
});

it('returns 404 for BTB page on land only assets', function () {
    $guideline = seedBtbReviewerReference();
    [$reviewer, $asset] = createReviewerWithAsset($guideline, [
        'asset_type' => 'tanah',
        'peruntukan' => 'tanah_kosong',
        'building_area' => 0,
        'building_floors' => null,
        'build_year' => null,
    ]);

    $this
        ->actingAs($reviewer)
        ->get(route('reviewer.assets.btb', $asset))
        ->assertNotFound();
});

it('persists BTB valuation rows and combines building value with land range totals', function () {
    $guideline = seedBtbReviewerReference();
    [$reviewer, $asset] = createReviewerWithAsset($guideline);

    $asset->update([
        'estimated_value_low' => 900000000,
        'estimated_value_high' => 1000000000,
        'market_value_final' => 950000000,
        'land_value_final' => 6333333,
    ]);

    $response = $this
        ->actingAs($reviewer)
        ->postJson(route('reviewer.api.assets.btb.save', $asset), [
            'btb_input' => [
                'template_key' => 'rumah_menengah',
                'effective_age' => 10,
            ],
        ]);

    $response
        ->assertOk()
        ->assertJsonPath('result.btb.state.context.template_key', 'rumah_menengah');

    $asset->refresh();
    $valuation = BuildingValuation::query()->where('appraisal_asset_id', $asset->id)->first();

    expect($valuation)->not->toBeNull();
    expect($asset->building_value_final)->toBe(data_get($response->json(), 'result.asset_values.building_value_final'));
    expect($asset->estimated_value_low)->toBe(900000000 + (int) $asset->building_value_final);
    expect($asset->estimated_value_high)->toBe(1000000000 + (int) $asset->building_value_final);
    expect($asset->market_value_final)->toBe(950000000 + (int) $asset->building_value_final);
    expect($valuation->costItems()->count())->toBeGreaterThan(0);
});

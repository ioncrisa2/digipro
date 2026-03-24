<?php

use App\Enums\AppraisalStatusEnum;
use App\Enums\PurposeEnum;
use App\Models\AppraisalAsset;
use App\Models\AppraisalAssetComparable;
use App\Models\AppraisalRequest;
use App\Models\User;
use App\Services\ComparableDataApi;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Role::findOrCreate('Reviewer', 'web');
    Role::findOrCreate('customer', 'web');
});

function createReviewerAssetFixture(): array
{
    $reviewer = User::factory()->create();
    $reviewer->assignRole('Reviewer');

    $request = AppraisalRequest::query()->create([
        'user_id' => $reviewer->id,
        'request_number' => 'REQ-TEST-001',
        'purpose' => PurposeEnum::JualBeli,
        'status' => AppraisalStatusEnum::ContractSigned,
        'requested_at' => now(),
    ]);

    $asset = AppraisalAsset::query()->create([
        'appraisal_request_id' => $request->id,
        'asset_type' => 'tanah',
        'peruntukan' => 'tanah_kosong',
        'title_document' => 'shm',
        'land_shape' => 'persegi',
        'land_position' => 'tengah',
        'land_condition' => 'matang',
        'topography' => 'datar_sama_dengan_jalan',
        'address' => 'Jl. Uji Reviewer',
        'land_area' => 120,
        'coordinates_lat' => -6.2,
        'coordinates_lng' => 106.8,
        'district_id' => '3171010',
    ]);

    return [$reviewer, $request, $asset];
}

it('allows reviewer to access the reviewer dashboard', function (): void {
    $user = User::factory()->create();
    $user->assignRole('Reviewer');

    $response = $this
        ->actingAs($user)
        ->get(route('reviewer.dashboard'));

    $response->assertOk();
});

it('blocks non reviewer users from the reviewer dashboard', function (): void {
    $user = User::factory()->create();
    $user->assignRole('customer');

    $response = $this
        ->actingAs($user)
        ->get(route('reviewer.dashboard'));

    $response->assertForbidden();
});

it('redirects reviewer away from customer dashboard and profile', function (): void {
    $user = User::factory()->create();
    $user->assignRole('Reviewer');

    $this
        ->actingAs($user)
        ->get(route('dashboard'))
        ->assertRedirect(route('reviewer.dashboard'));

    $this
        ->actingAs($user)
        ->get(route('profile.edit'))
        ->assertRedirect(route('reviewer.profile.edit'));

    $this
        ->actingAs($user)
        ->get(route('reviewer.profile.edit'))
        ->assertOk();
});

it('does not expose the old reviewer fallback path anymore', function (): void {
    $user = User::factory()->create();
    $user->assignRole('Reviewer');

    $this
        ->actingAs($user)
        ->get('/reviewer-old-panel')
        ->assertNotFound();
});

it('allows reviewer to preview and save adjustment state', function (): void {
    [$reviewer, $request, $asset] = createReviewerAssetFixture();

    $comparable = AppraisalAssetComparable::query()->create([
        'appraisal_asset_id' => $asset->id,
        'external_id' => 1001,
        'external_source' => 'test',
        'is_selected' => true,
        'manual_rank' => 1,
        'rank' => 1,
        'raw_price' => 100000000,
        'raw_land_area' => 100,
        'raw_peruntukan' => 'tanah_kosong',
        'snapshot_json' => [
            'harga' => 100000000,
            'luas_tanah' => 100,
            'peruntukan' => [
                'slug' => 'tanah_kosong',
                'name' => 'Tanah Kosong',
            ],
            'jenis_objek' => [
                'name' => 'Tanah',
            ],
        ],
    ]);

    $previewResponse = $this
        ->actingAs($reviewer)
        ->postJson(route('reviewer.api.assets.adjustment.preview', $asset), [
            'adjustment_inputs' => [
                (string) $comparable->id => [
                    'adj_shape' => 5,
                ],
            ],
            'general_inputs' => [
                (string) $comparable->id => [
                    'assumed_discount' => 5,
                ],
            ],
            'custom_adjustment_factors' => [],
        ]);

    $previewResponse
        ->assertOk()
        ->assertJsonPath('state.general_inputs.' . $comparable->id . '.assumed_discount', 5)
        ->assertJsonPath('state.adjustment_inputs.' . $comparable->id . '.adj_size', -16.67)
        ->assertJsonPath('state.comparables.0.likely_sale', 'Rp 95.000.000')
        ->assertJsonStructure([
            'message',
            'state' => [
                'asset_id',
                'comparables',
                'adjustment_inputs',
                'general_inputs',
                'adjustment_computed',
                'range_summary',
            ],
        ]);

    $saveResponse = $this
        ->actingAs($reviewer)
        ->postJson(route('reviewer.api.assets.adjustment.save', $asset), [
            'adjustment_inputs' => [
                (string) $comparable->id => [
                    'adj_shape' => 5,
                ],
            ],
            'general_inputs' => [
                (string) $comparable->id => [
                    'assumed_discount' => 10,
                ],
            ],
            'custom_adjustment_factors' => [],
        ]);

    $saveResponse
        ->assertOk()
        ->assertJsonStructure([
            'message',
        'result' => [
            'save_stats' => [
                'comparables_total',
                'comparables_estimable',
                ],
                'state' => [
                    'asset_id',
                    'range_summary',
                ],
            ],
        ]);

    expect($comparable->fresh()->assumed_discount_percent)->toBe(10.0);
});

it('adds saved building value to land range totals when saving land adjustment', function (): void {
    [$reviewer, $request, $asset] = createReviewerAssetFixture();

    $asset->update([
        'building_value_final' => 250000000,
    ]);

    $comparable = AppraisalAssetComparable::query()->create([
        'appraisal_asset_id' => $asset->id,
        'external_id' => 1002,
        'external_source' => 'test',
        'is_selected' => true,
        'manual_rank' => 1,
        'rank' => 1,
        'raw_price' => 100000000,
        'raw_land_area' => 100,
        'raw_peruntukan' => 'tanah_kosong',
        'snapshot_json' => [
            'harga' => 100000000,
            'luas_tanah' => 100,
            'peruntukan' => [
                'slug' => 'tanah_kosong',
                'name' => 'Tanah Kosong',
            ],
            'jenis_objek' => [
                'name' => 'Tanah',
            ],
        ],
    ]);

    $this
        ->actingAs($reviewer)
        ->postJson(route('reviewer.api.assets.adjustment.save', $asset), [
            'adjustment_inputs' => [
                (string) $comparable->id => [
                    'adj_shape' => 5,
                ],
            ],
            'general_inputs' => [
                (string) $comparable->id => [
                    'assumed_discount' => 10,
                ],
            ],
            'custom_adjustment_factors' => [],
        ])
        ->assertOk();

    $asset->refresh();

    expect($asset->estimated_value_low)->toBeGreaterThan($asset->building_value_final);
    expect($asset->estimated_value_high)->toBeGreaterThan($asset->building_value_final);
    expect($asset->market_value_final)->toBeGreaterThan($asset->building_value_final);
});

it('allows reviewer to update general asset data', function (): void {
    [$reviewer, $request, $asset] = createReviewerAssetFixture();

    $response = $this
        ->actingAs($reviewer)
        ->postJson(route('reviewer.api.assets.general-data', $asset), [
            'peruntukan' => 'rumah_tinggal',
            'title_document' => 'hgb',
            'land_shape' => 'persegi_panjang',
            'land_position' => 'hook',
            'land_condition' => 'belum_matang',
            'topography' => 'datar_lebih_tinggi_dari_jalan',
            'frontage_width' => 8,
            'access_road_width' => 6,
            'build_year' => 2020,
        ]);

    $response
        ->assertOk()
        ->assertJsonPath('asset.general_data.peruntukan', 'rumah_tinggal')
        ->assertJsonPath('asset.general_data.title_document', 'hgb')
        ->assertJsonPath('asset.general_data.build_year', 2020);
});

it('allows reviewer to search and sync comparables', function (): void {
    [$reviewer, $request, $asset] = createReviewerAssetFixture();

    $mockedItems = [[
        'id' => 777,
        'harga' => 250000000,
        'luas_tanah' => 150,
        'luas_bangunan' => 0,
        'alamat_data' => 'Jl. Pembanding Mock',
        'peruntukan' => [
            'slug' => 'tanah_kosong',
            'name' => 'Tanah Kosong',
        ],
        'jenis_objek' => [
            'name' => 'Tanah',
        ],
        'score' => 88.5,
        'distance' => 350,
        'priority_rank' => 1,
        'image_url' => 'https://example.test/mock.jpg',
    ]];

    $service = mock(ComparableDataApi::class);
    $service->shouldReceive('fetchSimilarForAsset')
        ->once()
        ->andReturn($mockedItems);
    $service->shouldReceive('upsertComparables')
        ->once()
        ->andReturnUsing(function (AppraisalAsset $passedAsset, array $items): int {
            foreach ($items as $item) {
                AppraisalAssetComparable::query()->create([
                    'appraisal_asset_id' => $passedAsset->id,
                    'external_id' => $item['id'],
                    'external_source' => 'test',
                    'is_selected' => false,
                    'manual_rank' => $item['priority_rank'] ?? null,
                    'rank' => $item['priority_rank'] ?? null,
                    'raw_price' => $item['harga'] ?? null,
                    'raw_land_area' => $item['luas_tanah'] ?? null,
                    'raw_building_area' => $item['luas_bangunan'] ?? null,
                    'raw_peruntukan' => data_get($item, 'peruntukan.slug'),
                    'snapshot_json' => $item,
                ]);
            }

            return count($items);
        });
    app()->instance(ComparableDataApi::class, $service);

    $searchResponse = $this
        ->actingAs($reviewer)
        ->postJson(route('reviewer.api.assets.comparables.search', $asset), [
            'range_km' => 5,
            'limit' => 10,
        ]);

    $searchResponse
        ->assertOk()
        ->assertJsonPath('results.0.id', '777')
        ->assertJsonPath('results.0.address', 'Jl. Pembanding Mock');

    $syncResponse = $this
        ->actingAs($reviewer)
        ->postJson(route('reviewer.api.assets.comparables.sync', $asset), [
            'items' => $mockedItems,
        ]);

    $syncResponse
        ->assertOk()
        ->assertJsonPath('comparables.0.external_id', '777');
});

it('allows reviewer to update comparable selection and rank', function (): void {
    [$reviewer, $request, $asset] = createReviewerAssetFixture();

    $comparable = AppraisalAssetComparable::query()->create([
        'appraisal_asset_id' => $asset->id,
        'external_id' => 2002,
        'external_source' => 'test',
        'is_selected' => false,
        'manual_rank' => 2,
        'rank' => 2,
        'snapshot_json' => [
            'peruntukan' => [
                'slug' => 'tanah_kosong',
                'name' => 'Tanah Kosong',
            ],
        ],
    ]);

    $response = $this
        ->actingAs($reviewer)
        ->postJson(route('reviewer.api.comparables.update', $comparable), [
            'is_selected' => true,
            'manual_rank' => 5,
        ]);

    $response
        ->assertOk()
        ->assertJsonPath('comparable.is_selected', true)
        ->assertJsonPath('comparable.manual_rank', 5);
});

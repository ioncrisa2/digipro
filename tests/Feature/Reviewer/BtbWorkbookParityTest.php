<?php

use App\Models\BuildingEconomicLife;
use App\Models\ConstructionCostIndex;
use App\Models\CostElement;
use App\Models\FloorIndex;
use App\Models\GuidelineSet;
use App\Models\ValuationSetting;
use App\Services\Reviewer\BtbWorksheetEngine;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function workbookParityFixtures(): array
{
    return [
        'rumah_mewah' => [
            'usage' => 'rumah_tinggal',
            'building_type' => 'BANGUNAN_RUMAH_TINGGAL',
            'building_class' => 'MEWAH',
            'floor_count' => 2,
            'storey_pattern' => '2 Lantai',
            'building_area' => 505.015,
            'group_costs' => [
                'PONDASI' => 928795,
                'STRUKTUR' => 1731790,
                'RANGKA ATAP' => 271453,
                'PENUTUP ATAP' => 368800,
                'PLAFOND' => 527500,
                'DINDING' => 1441857,
                'PINTU & JENDELA' => 517037,
                'LANTAI' => 898604,
                'UTILITAS' => 355586,
            ],
            'expected' => [
                'hard_cost_total_ikk_floor_index' => 7041422,
                'soft_cost_total' => 1021006,
                'total_brb_per_sqm' => 8949295,
                'depreciated_brb_per_sqm' => 6935704,
                'depreciated_brb_total' => 3502634397,
            ],
        ],
        'rumah_menengah' => [
            'usage' => 'rumah_tinggal',
            'building_type' => 'BANGUNAN_RUMAH_TINGGAL',
            'building_class' => 'MENENGAH',
            'floor_count' => 2,
            'storey_pattern' => '2 Lantai',
            'building_area' => 94.18,
            'group_costs' => [
                'PONDASI' => 789298,
                'STRUKTUR' => 1441764,
                'RANGKA ATAP' => 153752,
                'PENUTUP ATAP' => 226094,
                'PLAFOND' => 176894,
                'DINDING' => 866878,
                'PINTU & JENDELA' => 390897,
                'LANTAI' => 406678,
                'UTILITAS' => 243851,
            ],
            'expected' => [
                'hard_cost_total_ikk_floor_index' => 4696106,
                'soft_cost_total' => 680936,
                'total_brb_per_sqm' => 5968517,
                'depreciated_brb_per_sqm' => 4625600,
                'depreciated_brb_total' => 435639044,
            ],
        ],
        'rumah_sederhana' => [
            'usage' => 'rumah_tinggal',
            'building_type' => 'BANGUNAN_RUMAH_TINGGAL',
            'building_class' => 'SEDERHANA',
            'floor_count' => 1,
            'storey_pattern' => '1 Lantai',
            'building_area' => 36.2955,
            'group_costs' => [
                'PONDASI' => 432209,
                'STRUKTUR' => 611866,
                'RANGKA ATAP' => 243720,
                'PENUTUP ATAP' => 224968,
                'PLAFOND' => 112258,
                'DINDING' => 579118,
                'PINTU & JENDELA' => 308347,
                'LANTAI' => 223804,
                'UTILITAS' => 215255,
            ],
            'expected' => [
                'hard_cost_total_ikk_floor_index' => 2951545,
                'soft_cost_total' => 427974,
                'total_brb_per_sqm' => 3751266,
                'depreciated_brb_per_sqm' => 2907231,
                'depreciated_brb_total' => 105519411,
            ],
        ],
        'semi_permanen' => [
            'usage' => 'gudang',
            'building_type' => 'BGN. PERKEBUNAN (SEMI PERMANEN)',
            'building_class' => 'SEMI PERMANEN',
            'floor_count' => 1,
            'storey_pattern' => '1 Lantai',
            'building_area' => 45.0,
            'group_costs' => [
                'PONDASI' => 135043,
                'STRUKTUR' => 384000,
                'RANGKA ATAP' => 89424,
                'PENUTUP ATAP' => 154848,
                'PLAFOND' => 80417,
                'DINDING' => 444376,
                'PINTU & JENDELA' => 84718,
                'LANTAI' => 50167,
                'UTILITAS' => 104965,
            ],
            'expected' => [
                'hard_cost_total_ikk_floor_index' => 1527958,
                'soft_cost_total' => 221554,
                'total_brb_per_sqm' => 1941958,
                'depreciated_brb_per_sqm' => 1505018,
                'depreciated_brb_total' => 67725796,
            ],
        ],
        'gudang' => [
            'usage' => 'gudang',
            'building_type' => 'BANGUNAN_GUDANG',
            'building_class' => 'GUDANG',
            'floor_count' => 1,
            'storey_pattern' => '1 Lantai',
            'building_area' => 366.592,
            'group_costs' => [
                'PONDASI' => 381302,
                'STRUKTUR' => 1228673,
                'RANGKA ATAP' => 412764,
                'PENUTUP ATAP' => 112035,
                'PLAFOND' => 0,
                'DINDING' => 398615,
                'PINTU & JENDELA' => 81305,
                'LANTAI' => 324698,
                'UTILITAS' => 6138,
            ],
            'expected' => [
                'hard_cost_total_ikk_floor_index' => 2945530,
                'soft_cost_total' => 427102,
                'total_brb_per_sqm' => 3743622,
                'depreciated_brb_per_sqm' => 2901307,
                'depreciated_brb_total' => 1063595818,
            ],
        ],
        'low_rise_building' => [
            'usage' => 'kantor',
            'building_type' => 'BANGUNAN_GEDUNG_BERTINGKAT',
            'building_class' => 'LOW_RISE',
            'floor_count' => 3,
            'storey_pattern' => '3 Lantai',
            'building_area' => 240.4645,
            'group_costs' => [
                'PONDASI' => 511466,
                'STRUKTUR' => 1448003,
                'RANGKA ATAP' => 132010,
                'PENUTUP ATAP' => 264181,
                'PLAFOND' => 140447,
                'DINDING' => 611244,
                'PINTU & JENDELA' => 117041,
                'LANTAI' => 338128,
                'UTILITAS' => 159801,
            ],
            'expected' => [
                'hard_cost_total_ikk_floor_index' => 3722321,
                'soft_cost_total' => 539737,
                'total_brb_per_sqm' => 4730884,
                'depreciated_brb_per_sqm' => 3666435,
                'depreciated_brb_total' => 881647554,
            ],
        ],
    ];
}

beforeEach(function () {
    $this->guideline = GuidelineSet::create([
        'name' => 'BTB Workbook 2025',
        'year' => 2025,
        'is_active' => true,
    ]);

    ValuationSetting::create([
        'guideline_set_id' => $this->guideline->id,
        'year' => 2025,
        'key' => ValuationSetting::KEY_PPN_PERCENT,
        'label' => 'PPN (%)',
        'value_number' => 11,
    ]);

    ConstructionCostIndex::create([
        'guideline_set_id' => $this->guideline->id,
        'year' => 2025,
        'region_code' => '3171',
        'region_name' => 'Kota Jakarta',
        'ikk_value' => 1,
    ]);

    foreach (workbookParityFixtures() as $templateKey => $fixture) {
        FloorIndex::create([
            'guideline_set_id' => $this->guideline->id,
            'year' => 2025,
            'building_class' => $fixture['building_class'],
            'floor_count' => $fixture['floor_count'],
            'il_value' => 1,
        ]);

        BuildingEconomicLife::create([
            'guideline_item_id' => $this->guideline->id,
            'year' => 2025,
            'category' => $fixture['usage'],
            'sub_category' => $fixture['building_class'],
            'building_type' => $fixture['building_type'],
            'building_class' => $fixture['building_class'],
            'storey_min' => $fixture['floor_count'],
            'storey_max' => $fixture['floor_count'],
            'economic_life' => 40,
        ]);

        foreach ($fixture['group_costs'] as $group => $subtotal) {
            CostElement::create([
                'guideline_set_id' => $this->guideline->id,
                'year' => 2025,
                'base_region' => 'DKI Jakarta',
                'group' => $group,
                'element_code' => strtoupper($templateKey . '_' . str_replace(' ', '_', $group)),
                'element_name' => ucwords(strtolower($group)),
                'building_type' => $fixture['building_type'],
                'building_class' => $fixture['building_class'],
                'storey_pattern' => $fixture['storey_pattern'],
                'unit' => 'm2',
                'unit_cost' => $subtotal,
                'spec_json' => [
                    'material_spec' => ucwords(strtolower($group)),
                    'default_volume_percent' => 1,
                    'line_order' => 1,
                    'source_sheet' => 'BUT_Print',
                    'source_cell' => 'C1',
                ],
            ]);
        }
    }
});

it('matches workbook 2025 normalized KPI outputs for each BTB template', function (array $fixture, string $templateKey) {
    $engine = app(BtbWorksheetEngine::class);

    $result = $engine->build([
        'guideline_set_id' => $this->guideline->id,
        'year' => 2025,
        'usage' => $fixture['usage'],
        'template_key' => $templateKey,
        'building_class' => $fixture['building_class'],
        'floor_count' => $fixture['floor_count'],
        'building_area' => $fixture['building_area'],
        'land_area' => 1000,
        'market_value' => 10000000000,
        'region_code' => '3171',
        'build_year' => 2016,
        'renovation_year' => 2016,
    ]);

    expect(data_get($result, 'worksheet.hard_cost_total_ikk_floor_index'))->toBe($fixture['expected']['hard_cost_total_ikk_floor_index']);
    expect(data_get($result, 'worksheet.soft_cost_total'))->toBe($fixture['expected']['soft_cost_total']);
    expect(data_get($result, 'worksheet.total_brb_per_sqm'))->toBe($fixture['expected']['total_brb_per_sqm']);
    expect(data_get($result, 'depreciation.effective_age'))->toBe(9);
    expect(data_get($result, 'depreciation.total_depreciation_percent'))->toBe(0.225);
    expect(data_get($result, 'depreciation.depreciated_brb_per_sqm'))->toBe($fixture['expected']['depreciated_brb_per_sqm']);
    expect(data_get($result, 'depreciation.depreciated_brb_total'))->toBe($fixture['expected']['depreciated_brb_total']);
    expect(data_get($result, 'summary.residual_land_value'))->toBe(10000000000 - $fixture['expected']['depreciated_brb_total']);
})->with(function () {
    return collect(workbookParityFixtures())
        ->map(fn (array $fixture, string $templateKey) => [$fixture, $templateKey])
        ->values()
        ->all();
});

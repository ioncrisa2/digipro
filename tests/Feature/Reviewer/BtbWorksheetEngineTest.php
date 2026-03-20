<?php

use App\Models\BuildingEconomicLife;
use App\Models\ConstructionCostIndex;
use App\Models\CostElement;
use App\Models\FloorIndex;
use App\Models\GuidelineSet;
use App\Models\MappiRcnStandard;
use App\Models\ValuationSetting;
use App\Services\Reviewer\BtbWorksheetEngine;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->guideline = GuidelineSet::create([
        'name' => 'Guideline BTB 2026',
        'year' => 2026,
        'is_active' => true,
    ]);

    ValuationSetting::create([
        'guideline_set_id' => $this->guideline->id,
        'year' => 2026,
        'key' => ValuationSetting::KEY_PPN_PERCENT,
        'label' => 'PPN (%)',
        'value_number' => 11,
    ]);

    ConstructionCostIndex::create([
        'guideline_set_id' => $this->guideline->id,
        'year' => 2026,
        'region_code' => '3171',
        'region_name' => 'Kota Jakarta',
        'ikk_value' => 1.2,
    ]);

    FloorIndex::create([
        'guideline_set_id' => $this->guideline->id,
        'year' => 2026,
        'building_class' => 'MENENGAH',
        'floor_count' => 2,
        'il_value' => 1.1,
    ]);

    BuildingEconomicLife::create([
        'guideline_item_id' => $this->guideline->id,
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
        'guideline_set_id' => $this->guideline->id,
        'year' => 2026,
        'reference_region' => 'DKI Jakarta',
        'building_type' => 'BANGUNAN_RUMAH_TINGGAL',
        'building_class' => 'MENENGAH',
        'storey_pattern' => '2 Lantai',
        'rcn_value' => 2000000,
    ]);

    $lineRows = [
        ['group' => 'PONDASI', 'code' => 'FDN', 'name' => 'Tapak Beton', 'cost' => 100000],
        ['group' => 'STRUKTUR', 'code' => 'STR', 'name' => 'Beton Bertulang', 'cost' => 200000],
        ['group' => 'RANGKA ATAP', 'code' => 'RAF', 'name' => 'Baja Ringan', 'cost' => 300000],
        ['group' => 'PENUTUP ATAP', 'code' => 'ROC', 'name' => 'Genteng Beton', 'cost' => 400000],
        ['group' => 'PLAFOND', 'code' => 'CEI', 'name' => 'Gipsum', 'cost' => 500000],
        ['group' => 'DINDING', 'code' => 'WAL', 'name' => 'Bata Merah', 'cost' => 600000],
        ['group' => 'PINTU & JENDELA', 'code' => 'DOW', 'name' => 'Kayu', 'cost' => 700000],
        ['group' => 'LANTAI', 'code' => 'FLR', 'name' => 'Keramik', 'cost' => 800000],
        ['group' => 'UTILITAS', 'code' => 'UTL', 'name' => 'Elektrikal', 'cost' => 900000],
    ];

    foreach ($lineRows as $row) {
        CostElement::create([
            'guideline_set_id' => $this->guideline->id,
            'year' => 2026,
            'base_region' => 'DKI Jakarta',
            'group' => $row['group'],
            'element_code' => $row['code'],
            'element_name' => $row['name'],
            'building_type' => 'BANGUNAN_RUMAH_TINGGAL',
            'building_class' => 'MENENGAH',
            'storey_pattern' => '2 Lantai',
            'unit' => 'm2',
            'unit_cost' => $row['cost'],
            'spec_json' => [
                'material_spec' => $row['name'],
                'default_volume_percent' => 1,
                'source_sheet' => 'BUT_Print',
            ],
        ]);
    }
});

it('builds a BTB worksheet with direct, indirect, and depreciation outputs', function () {
    $engine = app(BtbWorksheetEngine::class);

    $result = $engine->build([
        'guideline_set_id' => $this->guideline->id,
        'year' => 2026,
        'usage' => 'rumah_tinggal',
        'building_class' => 'MENENGAH',
        'floor_count' => 2,
        'building_area' => 100,
        'region_code' => '3171',
        'effective_age' => 10,
        'material_quality_adjustment' => 1.05,
    ]);

    expect(data_get($result, 'context.template_key'))->toBe('rumah_menengah');
    expect(data_get($result, 'reference.ikk_value'))->toBe(1.2);
    expect(data_get($result, 'reference.floor_index_value'))->toBe(1.1);
    expect(data_get($result, 'reference.economic_life'))->toBe(50);
    expect(data_get($result, 'worksheet.hard_cost_total'))->toBe(4500000);
    expect(data_get($result, 'worksheet.hard_cost_total_ikk'))->toBe(5400000);
    expect(data_get($result, 'worksheet.hard_cost_total_ikk_floor_index'))->toBe(5940000);
    expect(data_get($result, 'worksheet.soft_cost_total'))->toBe(861300);
    expect(data_get($result, 'worksheet.total_rcn_before_vat'))->toBe(6801300);
    expect(data_get($result, 'worksheet.ppn_amount'))->toBe(748143);
    expect(data_get($result, 'worksheet.total_rcn'))->toBe(7549443);
    expect(data_get($result, 'depreciation.final_adjustment_factor'))->toBe(0.8);
    expect(data_get($result, 'depreciation.depreciated_brb_per_sqm'))->toBe(6341532);
    expect(data_get($result, 'depreciation.depreciated_brb_total'))->toBe(634153212);
});

it('applies subject overrides to direct cost items', function () {
    $engine = app(BtbWorksheetEngine::class);

    $result = $engine->build([
        'guideline_set_id' => $this->guideline->id,
        'year' => 2026,
        'usage' => 'rumah_tinggal',
        'building_class' => 'MENENGAH',
        'floor_count' => 2,
        'building_area' => 100,
        'region_code' => '3171',
        'subject_overrides' => [
            'foundation:tapak-beton' => [
                'subject_material_spec' => 'Tapak Beton Upgrade',
                'subject_unit_cost' => 150000,
                'subject_volume_percent' => 0.5,
                'other_adjustment_factor' => 1.2,
            ],
        ],
    ]);

    $foundation = collect(data_get($result, 'worksheet.hard_cost_lines'))
        ->firstWhere('line_code', 'foundation');

    expect(data_get($foundation, 'items.0.subject_material_spec'))->toBe('Tapak Beton Upgrade');
    expect(data_get($foundation, 'items.0.direct_cost_result'))->toBe(90000);
    expect(data_get($result, 'worksheet.hard_cost_total'))->toBe(4490000);
});

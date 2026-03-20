<?php

use App\Models\BuildingCostItem;
use App\Models\BuildingValuation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;

uses(RefreshDatabase::class);

it('adds worksheet columns required for BTB building valuations', function () {
    expect(Schema::hasColumns('building_valuations', [
        'worksheet_template',
        'building_type',
        'building_class',
        'floor_count',
        'valuation_year',
        'ikk_region_code',
        'ikk_region_label',
        'ikk_value',
        'base_rcn_unit_cost',
        'material_quality_adjustment',
        'maintenance_adjustment_factor',
        'final_adjustment_factor',
        'residual_land_value',
        'residual_land_value_per_sqm',
    ]))->toBeTrue();

    expect(Schema::hasColumns('building_cost_items', [
        'section_name',
        'row_order',
        'is_subtotal',
        'model_material_spec',
        'subject_material_spec',
        'model_volume_percent',
        'subject_volume_percent',
        'other_adjustment_factor',
        'direct_cost_result',
        'source_sheet',
        'source_cell',
    ]))->toBeTrue();
});

it('casts BTB worksheet fields on building models', function () {
    $valuation = new BuildingValuation();
    $costItem = new BuildingCostItem();

    expect($valuation->getCasts())->toMatchArray([
        'floor_count' => 'integer',
        'valuation_year' => 'integer',
        'ikk_value' => 'float',
        'base_rcn_unit_cost' => 'integer',
        'material_quality_adjustment' => 'float',
        'maintenance_adjustment_factor' => 'float',
        'final_adjustment_factor' => 'float',
        'residual_land_value' => 'integer',
        'residual_land_value_per_sqm' => 'integer',
    ]);

    expect($costItem->getCasts())->toMatchArray([
        'row_order' => 'integer',
        'is_subtotal' => 'boolean',
        'model_volume_percent' => 'float',
        'subject_volume_percent' => 'float',
        'other_adjustment_factor' => 'float',
        'direct_cost_result' => 'integer',
    ]);
});

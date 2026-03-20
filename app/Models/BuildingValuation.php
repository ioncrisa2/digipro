<?php

namespace App\Models;

use App\Models\FloorIndex;
use App\Models\GuidelineSet;
use App\Models\AppraisalAsset;
use App\Models\BuildingCostItem;
use App\Models\BuildingEconomicLife;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BuildingValuation extends Model
{
    protected $fillable = [
        'appraisal_asset_id',
        'guideline_set_id',
        'building_name',
        'worksheet_template',
        'building_type',
        'building_class',
        'floor_count',
        'valuation_year',
        'gross_floor_area',
        'ikk_region_code',
        'ikk_region_label',
        'ikk_value',
        'base_rcn_unit_cost',
        'effective_age',
        'economic_life',
        'economic_life_ref_id',
        'material_quality_adjustment',
        'depreciation_percent',
        'maintenance_adjustment_factor',
        'final_adjustment_factor',
        'il_ref_id',
        'il_value',
        'hard_cost_total',
        'soft_cost_total',
        'site_improvement_total',
        'total_rcn',
        'total_depreciated_value',
        'residual_land_value',
        'residual_land_value_per_sqm',
        'calculation_json',
        'notes',
    ];

    protected $casts = [
        'floor_count'             => 'integer',
        'valuation_year'          => 'integer',
        'gross_floor_area'        => 'float',
        'ikk_value'               => 'float',
        'base_rcn_unit_cost'      => 'integer',
        'effective_age'           => 'integer',
        'economic_life'           => 'integer',
        'material_quality_adjustment' => 'float',
        'depreciation_percent'    => 'float',
        'maintenance_adjustment_factor' => 'float',
        'final_adjustment_factor' => 'float',
        'il_value'                => 'float',
        'hard_cost_total'         => 'integer',
        'soft_cost_total'         => 'integer',
        'site_improvement_total'  => 'integer',
        'total_rcn'               => 'integer',
        'total_depreciated_value' => 'integer',
        'residual_land_value'     => 'integer',
        'residual_land_value_per_sqm' => 'integer',
        'calculation_json'        => 'array',
    ];

    public function asset(): BelongsTo
    {
        return $this->belongsTo(AppraisalAsset::class);
    }

    public function guidelineSet(): BelongsTo
    {
        return $this->belongsTo(GuidelineSet::class);
    }

    public function economicLifeRef(): BelongsTo
    {
        return $this->belongsTo(BuildingEconomicLife::class, 'economic_life_ref_id');
    }

    public function ilRef(): BelongsTo
    {
        return $this->belongsTo(FloorIndex::class, 'il_ref_id');
    }

    public function costItems(): HasMany
    {
        return $this->hasMany(BuildingCostItem::class);
    }

}

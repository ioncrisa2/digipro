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
        'gross_floor_area',
        'effective_age',
        'economic_life',
        'economic_life_ref_id',
        'depreciation_percent',
        'il_ref_id',
        'il_value',
        'hard_cost_total',
        'soft_cost_total',
        'site_improvement_total',
        'total_rcn',
        'total_depreciated_value',
        'calculation_json',
        'notes',
    ];

    protected $casts = [
        'gross_floor_area'        => 'float',
        'effective_age'           => 'integer',
        'economic_life'           => 'integer',
        'depreciation_percent'    => 'float',
        'il_value'                => 'float',
        'hard_cost_total'         => 'integer',
        'soft_cost_total'         => 'integer',
        'site_improvement_total'  => 'integer',
        'total_rcn'               => 'integer',
        'total_depreciated_value' => 'integer',
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

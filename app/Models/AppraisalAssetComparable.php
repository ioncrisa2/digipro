<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AppraisalAssetComparable extends Model
{
    protected $table = 'appraisal_assets_comparables';

    protected $fillable = [
        'appraisal_asset_id',
        'external_id',
        'external_source',
        'image_url',
        'is_selected',
        'manual_rank',
        'snapshot_json',
        'score',
        'weight',
        'auto_adjust_percent',
        'distance_meters',
        'total_adjustment_percent',
        'total_adjustment_percent_low',
        'total_adjustment_percent_high',
        'adjusted_unit_value',
        'indication_value',
        'rank',
        'raw_price',
        'raw_land_area',
        'raw_building_area',
        'raw_unit_price_land',
        'raw_peruntukan',
        'raw_data_date'
    ];

    protected $casts = [
        'snapshot_json'           => 'array',
        'score'                   => 'float',
        'weight'                  => 'float',
        'auto_adjust_percent'     => 'float',
        'distance_meters'         => 'float',
        'total_adjustment_percent'=> 'float',
        'total_adjustment_percent_low' => 'float',
        'total_adjustment_percent_high' => 'float',
        'adjusted_unit_value'     => 'integer',
        'indication_value'        => 'integer',
        'is_selected'             => 'boolean',
    ];

    public function asset(): BelongsTo
    {
        return $this->belongsTo(AppraisalAsset::class, 'appraisal_asset_id');
    }

    public function landAdjustments(): HasMany
    {
        return $this->hasMany(LandAdjustment::class);
    }
}

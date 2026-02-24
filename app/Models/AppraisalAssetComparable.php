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
        'snapshot_json',
        'score',
        'weight',
        'total_adjustment_percent',
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
        'total_adjustment_percent'=> 'float',
        'adjusted_unit_value'     => 'integer',
        'indication_value'        => 'integer',
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

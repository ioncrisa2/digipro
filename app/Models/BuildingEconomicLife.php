<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BuildingEconomicLife extends Model
{
    protected $table = 'ref_building_economic_life';

    protected $fillable = [
        'guideline_item_id',
        'year',
        'category',
        'sub_category',
        'building_type',
        'building_class',
        'storey_min',
        'storey_max',
        'economic_life'
    ];

    protected $casts = [
        'year' => 'integer',
        'storey_min' => 'integer',
        'storey_max' => 'integer',
        'economic_life' => 'integer',
    ];

    public function guidelineSet(): BelongsTo
    {
        return $this->belongsTo(GuidelineSet::class, 'guideline_item_id');
    }
}

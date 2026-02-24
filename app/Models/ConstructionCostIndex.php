<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ConstructionCostIndex extends Model
{
    protected $table = 'ref_construction_cost_index';

    protected $guarded = [];

    public function guidelineSet(): BelongsTo
    {
        return $this->belongsTo(GuidelineSet::class);
    }

    public function regency()
    {
        return $this->belongsTo(Regency::class, 'region_code', 'id');
    }
}

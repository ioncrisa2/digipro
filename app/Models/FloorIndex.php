<?php

namespace App\Models;

use App\Models\GuidelineSet;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FloorIndex extends Model
{
    protected $table = 'ref_floor_index';

    protected $fillable = [
        'guideline_set_id','year','building_class','floor_count','il_value'
    ];

    protected $casts = [
        'year' => 'integer',
        'floor_count' => 'integer',
        'il_value' => 'decimal:4',
    ];

    public function guidelineSet(): BelongsTo
    {
        return $this->belongsTo(GuidelineSet::class, 'guideline_set_id');
    }
}

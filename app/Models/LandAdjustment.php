<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LandAdjustment extends Model
{
    protected $fillable = [
        'appraisal_asset_comparable_id',
        'factor_id',
        'subject_value',
        'comparable_value',
        'adjustment_percent',
        'adjustment_amount',
        'note',
    ];

    protected $casts = [
        'adjustment_percent' => 'float',
        'adjustment_amount'  => 'integer',
    ];

    public function comparable(): BelongsTo
    {
        return $this->belongsTo(AppraisalAssetComparable::class);
    }

    public function factor(): BelongsTo
    {
        return $this->belongsTo(AdjustmentFactor::class);
    }
}

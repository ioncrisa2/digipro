<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AdjustmentFactor extends Model
{
    protected $fillable = [
        'code',
        'name',
        'category',
        'scope',
        'is_active',
        'sort_order',
    ];

    protected $casts = [
        'is_active'  => 'boolean',
        'sort_order' => 'integer',
    ];

    public function landAdjustments(): HasMany
    {
        return $this->hasMany(LandAdjustment::class, 'factor_id');
    }
}

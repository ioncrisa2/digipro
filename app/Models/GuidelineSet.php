<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class GuidelineSet extends Model
{
    protected $table = 'ref_guideline_sets';

    protected $fillable = [
        'name','year','description','is_active'
    ];

    protected $casts = [
        'is_active' => 'boolean'
    ];

    public function buildingEconomicLives(): HasMany
    {
        return $this->hasMany(BuildingEconomicLife::class, 'guideline_set_id');
    }

    public function floorIndexes(): HasMany
    {
        return $this->hasMany(FloorIndex::class,'guideline_set_id');
    }

    public function costElements(): HasMany
    {
        return $this->hasMany(CostElement::class, 'guideline_set_id');
    }

    public function constructionCostIndexes(): HasMany
    {
        return $this->hasMany(ConstructionCostIndex::class, 'guideline_set_id');
    }

    public function mappiRcnStandards(): HasMany
    {
        return $this->hasMany(MappiRcnStandard::class, 'guideline_set_id');
    }
}

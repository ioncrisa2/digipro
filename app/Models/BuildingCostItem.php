<?php

namespace App\Models;

use App\Models\BuildingValuation;
use App\Models\CostElement;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BuildingCostItem extends Model
{
    protected $fillable = [
        'building_valuation_id',
        'cost_element_id',
        'element_code',
        'element_name',
        'unit',
        'quantity',
        'ref_unit_cost',
        'ikk_value_used',
        'adjusted_unit_cost',
        'line_total',
        'meta_json',
    ];

    protected $casts = [
        'quantity'           => 'float',
        'ref_unit_cost'      => 'integer',
        'ikk_value_used'     => 'float',
        'adjusted_unit_cost' => 'integer',
        'line_total'         => 'integer',
        'meta_json'          => 'array',
    ];

    public function valuation(): BelongsTo
    {
        return $this->belongsTo(BuildingValuation::class, 'building_valuation_id');
    }

    public function costElement(): BelongsTo
    {
        return $this->belongsTo(CostElement::class, 'cost_element_id');
    }
}

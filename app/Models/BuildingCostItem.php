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
        'section_name',
        'row_order',
        'is_subtotal',
        'element_code',
        'element_name',
        'unit',
        'quantity',
        'ref_unit_cost',
        'ikk_value_used',
        'adjusted_unit_cost',
        'model_material_spec',
        'subject_material_spec',
        'model_volume_percent',
        'subject_volume_percent',
        'other_adjustment_factor',
        'direct_cost_result',
        'line_total',
        'source_sheet',
        'source_cell',
        'meta_json',
    ];

    protected $casts = [
        'row_order'          => 'integer',
        'is_subtotal'        => 'boolean',
        'quantity'           => 'float',
        'ref_unit_cost'      => 'integer',
        'ikk_value_used'     => 'float',
        'adjusted_unit_cost' => 'integer',
        'model_volume_percent' => 'float',
        'subject_volume_percent' => 'float',
        'other_adjustment_factor' => 'float',
        'direct_cost_result' => 'integer',
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

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CostElement extends Model
{
    protected $table = 'ref_cost_elements';


    protected $fillable = [
        'guideline_set_id',
        'year',
        'base_region',
        'group',
        'element_code',
        'element_name',
        'building_type',
        'building_class',
        'storey_pattern',
        'unit',
        'unit_cost',
        'spec_json',
    ];

    protected $casts = [
        'spec_json' => 'array',
    ];

    public function guidelineSet(): BelongsTo
    {
        return $this->belongsTo(GuidelineSet::class);
    }
}

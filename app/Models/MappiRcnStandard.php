<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MappiRcnStandard extends Model
{
    protected $table = 'ref_mappi_rcn_standards';

    protected $fillable = [
        'guideline_set_id',
        'year',
        'reference_region',
        'building_type',
        'building_class',
        'storey_pattern',
        'rcn_value',
        'notes',
    ];

    protected $casts = [
        'rcn_value' => 'float',
    ];

    public function guidelineSet(): BelongsTo
    {
        return $this->belongsTo(GuidelineSet::class, 'guideline_set_id');
    }
}

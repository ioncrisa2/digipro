<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AppraisalAssetFile extends Model
{
    protected $table = 'appraisal_asset_files';

    protected $fillable = [
        'appraisal_asset_id','type','path','original_name','mime','size'
    ];

    public function appraisalAsset(): BelongsTo
    {
        return $this->belongsTo(AppraisalAsset::class);
    }
}

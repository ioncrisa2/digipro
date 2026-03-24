<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

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

    public function originalRevisionItems(): HasMany
    {
        return $this->hasMany(AppraisalRequestRevisionItem::class, 'original_asset_file_id');
    }

    public function replacementRevisionItems(): HasMany
    {
        return $this->hasMany(AppraisalRequestRevisionItem::class, 'replacement_asset_file_id');
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AppraisalRequestFile extends Model
{
    protected $table = 'appraisal_request_files';

    protected $fillable = [
        'appraisal_request_id','type','path','original_name','mime','size'
    ];

    public function appraisalRequest(): BelongsTo
    {
        return $this->belongsTo(AppraisalRequest::class);
    }

    public function originalRevisionItems(): HasMany
    {
        return $this->hasMany(AppraisalRequestRevisionItem::class, 'original_request_file_id');
    }

    public function replacementRevisionItems(): HasMany
    {
        return $this->hasMany(AppraisalRequestRevisionItem::class, 'replacement_request_file_id');
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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
}

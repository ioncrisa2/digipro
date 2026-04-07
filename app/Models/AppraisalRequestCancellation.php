<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AppraisalRequestCancellation extends Model
{
    protected $fillable = [
        'appraisal_request_id',
        'user_id',
        'status_before_request',
        'phone_snapshot',
        'whatsapp_snapshot',
        'reason',
        'review_status',
        'review_note',
        'contacted_at',
        'reviewed_by',
        'reviewed_at',
    ];

    protected $casts = [
        'contacted_at' => 'datetime',
        'reviewed_at' => 'datetime',
    ];

    public function appraisalRequest(): BelongsTo
    {
        return $this->belongsTo(AppraisalRequest::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function reviewedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}

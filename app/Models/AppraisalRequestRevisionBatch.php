<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AppraisalRequestRevisionBatch extends Model
{
    protected $fillable = [
        'appraisal_request_id',
        'created_by',
        'submitted_by',
        'reviewed_by',
        'status',
        'admin_note',
        'submitted_at',
        'reviewed_at',
        'resolved_at',
    ];

    protected $casts = [
        'submitted_at' => 'datetime',
        'reviewed_at' => 'datetime',
        'resolved_at' => 'datetime',
    ];

    public function appraisalRequest(): BelongsTo
    {
        return $this->belongsTo(AppraisalRequest::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(AppraisalRequestRevisionItem::class, 'revision_batch_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function submitter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}

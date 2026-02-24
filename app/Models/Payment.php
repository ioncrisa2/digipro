<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Payment extends Model
{
    protected $fillable = [
        'appraisal_request_id',
        'amount',
        'method',
        'gateway',
        'external_payment_id',
        'status',
        'paid_at',
        'proof_file_path',
        'proof_original_name',
        'proof_mime',
        'proof_size',
        'proof_type',
        'metadata',
    ];

    protected $casts = [
        'paid_at' => 'datetime',
        'metadata' => 'array',
    ];

    public function appraisalRequest(): BelongsTo
    {
        return $this->belongsTo(AppraisalRequest::class);
    }
}
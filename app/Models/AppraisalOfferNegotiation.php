<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AppraisalOfferNegotiation extends Model
{
    protected $fillable = [
        'appraisal_request_id',
        'user_id',
        'action',
        'round',
        'offered_fee',
        'expected_fee',
        'selected_fee',
        'reason',
        'meta',
    ];

    protected $casts = [
        'round' => 'integer',
        'offered_fee' => 'integer',
        'expected_fee' => 'integer',
        'selected_fee' => 'integer',
        'meta' => 'array',
    ];

    public function appraisalRequest(): BelongsTo
    {
        return $this->belongsTo(AppraisalRequest::class, 'appraisal_request_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}

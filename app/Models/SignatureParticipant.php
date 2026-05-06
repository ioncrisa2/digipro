<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SignatureParticipant extends Model
{
    protected $fillable = [
        'signature_envelope_id',
        'role',
        'sequence',
        'email',
        'name',
        'external_order_id',
        'status',
        'signed_at',
        'meta',
    ];

    protected $casts = [
        'signed_at' => 'datetime',
        'meta' => 'array',
    ];

    public function envelope(): BelongsTo
    {
        return $this->belongsTo(SignatureEnvelope::class, 'signature_envelope_id');
    }
}


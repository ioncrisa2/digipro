<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class SignatureEnvelope extends Model
{
    protected $fillable = [
        'subject_type',
        'subject_id',
        'document_type',
        'provider',
        'model',
        'external_envelope_id',
        'uploader_email',
        'status',
        'document_hash',
        'original_pdf_path',
        'signed_pdf_path',
        'last_error',
        'meta',
    ];

    protected $casts = [
        'meta' => 'array',
    ];

    public function subject(): MorphTo
    {
        return $this->morphTo();
    }

    public function participants(): HasMany
    {
        return $this->hasMany(SignatureParticipant::class);
    }
}


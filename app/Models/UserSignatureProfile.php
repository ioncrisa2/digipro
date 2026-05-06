<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserSignatureProfile extends Model
{
    protected $fillable = [
        'user_id',
        'provider',
        'peruri_email',
        'peruri_phone',
        'nik',
        'reference_province_id',
        'reference_city_id',
        'registration_status',
        'kyc_status',
        'specimen_status',
        'certificate_status',
        'keyla_status',
        'keyla_qr_image',
        'last_checked_at',
        'last_error',
        'identity_payload',
        'kyc_video_path',
        'specimen_image_path',
        'meta',
    ];

    protected $casts = [
        'reference_province_id' => 'integer',
        'reference_city_id' => 'integer',
        'last_checked_at' => 'datetime',
        'identity_payload' => 'array',
        'meta' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}

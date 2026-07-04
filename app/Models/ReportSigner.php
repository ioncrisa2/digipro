<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReportSigner extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'role',
        'name',
        'email',
        'phone_number',
        'position_title',
        'title_suffix',
        'certification_number',
        'is_active',
        'peruri_certificate_status',
        'peruri_keyla_status',
        'peruri_last_checked_at',
        'demo_signature_path',
        'demo_signature_mime',
        'demo_signature_hash',
        'demo_signature_updated_at',
        'demo_signature_updated_by',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'peruri_last_checked_at' => 'datetime',
        'demo_signature_updated_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function demoSignatureUpdatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'demo_signature_updated_by');
    }
}

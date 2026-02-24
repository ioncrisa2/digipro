<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AppraisalUserConsent extends Model
{
    protected $table = 'appraisal_user_consents';

    protected $fillable = [
        'user_id',
        'consent_document_id',
        'code',
        'version',
        'hash',
        'accepted_at',
        'ip',
        'user_agent',
    ];

    protected $casts = [
        'accepted_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function document()
    {
        return $this->belongsTo(ConsentDocument::class, 'consent_document_id');
    }
}

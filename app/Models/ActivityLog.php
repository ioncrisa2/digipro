<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ActivityLog extends Model
{
    protected $fillable = [
        'user_id',
        'event_type',
        'workspace',
        'action_label',
        'route_name',
        'method',
        'path',
        'status_code',
        'route_params',
        'query_payload',
        'request_payload',
        'response_meta',
        'ip_address',
        'user_agent',
        'occurred_at',
    ];

    protected function casts(): array
    {
        return [
            'route_params' => 'array',
            'query_payload' => 'array',
            'request_payload' => 'array',
            'response_meta' => 'array',
            'occurred_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}

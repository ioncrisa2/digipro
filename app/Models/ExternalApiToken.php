<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

class ExternalApiToken extends Model
{
    protected $fillable = [
        'provider',
        'access_token',
        'refresh_token',
        'access_token_expires_at',
        'refresh_token_expires_at',
        'last_refreshed_at',
        'last_error',
    ];

    protected $casts = [
        'access_token_expires_at' => 'datetime',
        'refresh_token_expires_at' => 'datetime',
        'last_refreshed_at' => 'datetime',
    ];

    public function isAccessTokenValid(): bool
    {
        if (! $this->access_token) {
            return false;
        }

        if (! $this->access_token_expires_at) {
            return true;
        }

        return $this->access_token_expires_at->isFuture();
    }

    public function isRefreshTokenValid(): bool
    {
        if (! $this->refresh_token) {
            return false;
        }

        return $this->refresh_token_expires_at
            ? $this->refresh_token_expires_at->isFuture()
            : true;
    }
}

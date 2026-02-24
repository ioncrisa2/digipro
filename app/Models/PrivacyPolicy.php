<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;

class PrivacyPolicy extends Model
{
    protected $fillable = [
        'title',
        'company',
        'version',
        'effective_since',
        'content_html',
        'is_active',
        'published_at',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'effective_since' => 'date',
        'published_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::saved(function (PrivacyPolicy $document) {
            if (! $document->is_active) {
                return;
            }

            static::where('id', '!=', $document->id)
                ->where('is_active', true)
                ->update(['is_active' => false]);

            if (! $document->published_at) {
                $document->published_at = now();
                $document->saveQuietly();
            }
        });
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function toPublicArray(): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'company' => $this->company,
            'version' => $this->version,
            'effective_since' => $this->effective_since?->toDateString(),
            'published_at' => $this->published_at?->toDateTimeString(),
            'content_html' => $this->content_html,
        ];
    }
}
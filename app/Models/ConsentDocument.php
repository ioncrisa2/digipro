<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class ConsentDocument extends Model
{
    protected $table = "consent_document";

    protected $fillable = [
        'code','version','title','sections','checkbox_label',
        'hash','status','published_at','created_by','updated_by',
    ];

    protected $casts = [
        'sections' => 'array',
        'published_at' => 'datetime',
    ];

    public function scopeForCode(Builder $query, string $code): Builder
    {
        return $query->where('code', $code);
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('status', 'published')->whereNotNull('published_at');
    }

    public function payloadForHash(): array
    {
        return [
            'code' => $this->code,
            'version' => $this->version,
            'title' => $this->title,
            'sections' => $this->sections,
            'checkbox_label' => $this->checkbox_label,
        ];
    }

    public static function computeHash(array $payload): string
    {
        return hash('sha256', json_encode($payload, JSON_UNESCAPED_UNICODE));
    }
}

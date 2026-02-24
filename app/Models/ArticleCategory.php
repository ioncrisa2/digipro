<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use App\Models\Article;

class ArticleCategory extends Model
{
    protected $fillable = [
        'name',
        'slug',
        'description',
        'sort_order',
        'is_active',
        'show_in_nav',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'sort_order' => 'integer',
        'show_in_nav' => 'boolean',
    ];

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeShowInNav(Builder $query): Builder
    {
        return $query->where('show_in_nav', true);
    }

    public function articles()
    {
        return $this->hasMany(Article::class, 'category_id');
    }
}

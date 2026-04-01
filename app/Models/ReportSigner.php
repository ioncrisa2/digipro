<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ReportSigner extends Model
{
    use HasFactory;

    protected $fillable = [
        'role',
        'name',
        'position_title',
        'title_suffix',
        'certification_number',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];
}

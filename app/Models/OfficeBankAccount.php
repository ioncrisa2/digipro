<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OfficeBankAccount extends Model
{
    protected $fillable = [
        'bank_name',
        'account_number',
        'account_holder',
        'branch',
        'currency',
        'is_active',
        'sort_order',
        'notes',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];
}

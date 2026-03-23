<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ContactMessage extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'email',
        'subject',
        'message',
        'status',
        'read_at',
        'handled_at',
        'handled_by',
        'source',
        'ip_address',
        'user_agent',
    ];

    protected $casts = [
        'read_at' => 'datetime',
        'handled_at' => 'datetime',
    ];

    public function handledBy()
    {
        return $this->belongsTo(\App\Models\User::class, 'handled_by');
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SupportContactSetting extends Model
{
    protected $fillable = [
        'name',
        'phone',
        'whatsapp',
        'email',
        'availability_label',
    ];
}

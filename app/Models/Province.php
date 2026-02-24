<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Province extends Model
{
    use HasFactory;

    public $incrementing = false; // ID bukan integer
    protected $keyType = 'string'; // ID adalah string
    protected $fillable = ['id', 'name'];

    public function regencies(): HasMany
    {
        return $this->hasMany(Regency::class);
    }
}

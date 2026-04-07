<?php

namespace App\Models;

use App\Traits\HasStringPrimaryKey;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Province extends Model
{
    use HasFactory;
    use HasStringPrimaryKey;

    protected $fillable = ['id', 'name'];

    public function regencies(): HasMany
    {
        return $this->hasMany(Regency::class);
    }
}

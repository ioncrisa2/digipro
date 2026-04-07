<?php

namespace App\Models;

use App\Traits\HasStringPrimaryKey;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Village extends Model
{
    use HasFactory;
    use HasStringPrimaryKey;

    protected $fillable = ['id', 'name', 'district_id'];

    public function district(): BelongsTo
    {
        return $this->belongsTo(District::class);
    }
}

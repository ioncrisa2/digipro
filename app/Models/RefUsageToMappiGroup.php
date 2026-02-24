<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;

class RefUsageToMappiGroup extends Model
{
    protected $table = 'ref_usage_to_mappi_group';

    protected $fillable = [
        'peruntukan_enum',
        'mappi_building_type',
        'mappi_building_class',
        'default_storey_group',
        'notes',
    ];

    protected $casts = [
        'peruntukan_enum'     => 'string',
        'mappi_building_type' => 'string',
        'mappi_building_class'=> 'string',
        'default_storey_group'=> 'string',
        'notes'               => 'string',
    ];

    /**
     * Scope untuk pencarian berdasarkan peruntukan (enum internal pembanding)
     */
    public function scopeForPeruntukan(Builder $query, string $peruntukan): Builder
    {
        return $query->where('peruntukan_enum', $peruntukan);
    }

    /**
     * Helper - ambil mapping berdasarkan peruntukan.
     * Menghindari repeated query pada service.
     */
    public static function getMappingFor(string $peruntukan): ?self
    {
        return static::query()
            ->where('peruntukan_enum', $peruntukan)
            ->first();
    }

    /**
     * Helper: return building type untuk BTB / IL
     */
    public function buildingType(): ?string
    {
        return $this->mappi_building_type;
    }

    /**
     * Helper: return building class untuk BTB / IL
     */
    public function buildingClass(): ?string
    {
        return $this->mappi_building_class;
    }

    /**
     * Helper: return storey group (opsional)
     */
    public function storeyGroup(): ?string
    {
        return $this->default_storey_group;
    }
}

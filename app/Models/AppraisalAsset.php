<?php

namespace App\Models;

use App\Models\AppraisalAssetComparable;
use App\Models\AppraisalAssetFile;
use App\Models\AppraisalRequest;
use App\Models\BuildingValuation;
use App\Models\ConstructionCostIndex;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class AppraisalAsset extends Model
{
    protected $fillable = [
        'appraisal_request_id',
        'asset_code',
        'asset_type',
        'peruntukan',
        'title_document',
        'certificate_number',
        'certificate_holder_name',
        'certificate_issued_at',
        'land_book_date',
        'document_land_area',
        'legal_notes',
        'land_shape',
        'land_position',
        'land_condition',
        'topography',
        'province_id',
        'regency_id',
        'district_id',
        'village_id',
        'address',
        'maps_link',
        'coordinates_lat',
        'coordinates_lng',
        'land_area',
        'building_area',
        'building_floors',
        'build_year',
        'renovation_year',
        'frontage_width',
        'access_road_width',
        'ikk_ref_id',
        'ikk_value_used',
        'land_value_final',
        'building_value_final',
        'market_value_final',
        'estimated_value_low',
        'estimated_value_high',
    ];

    protected $casts = [
        'province_id'     => 'string',
        'regency_id'      => 'string',
        'district_id'     => 'string',
        'village_id'      => 'string',
        'land_value_final' => 'integer',
        'building_value_final' => 'integer',
        'market_value_final' => 'integer',
        'estimated_value_low' => 'integer',
        'estimated_value_high' => 'integer',
        'coordinates_lat' => 'float',
        'coordinates_lng' => 'float',
        'certificate_issued_at' => 'date',
        'land_book_date' => 'date',
        'land_area'       => 'float',
        'document_land_area' => 'float',
        'building_area'   => 'float',
        'frontage_width'  => 'float',
        'access_road_width' => 'float',
    ];

    public function request(): BelongsTo
    {
        return $this->belongsTo(AppraisalRequest::class, 'appraisal_request_id');
    }

    public function ikkRef(): BelongsTo
    {
        return $this->belongsTo(ConstructionCostIndex::class, 'ikk_ref_id');
    }

    public function comparables(): HasMany
    {
        return $this->hasMany(AppraisalAssetComparable::class);
    }

    public function buildingValuation(): HasOne
    {
        return $this->hasOne(BuildingValuation::class);
    }

    public function files(): HasMany
    {
        return $this->hasMany(AppraisalAssetFile::class);
    }

    public function revisionItems(): HasMany
    {
        return $this->hasMany(AppraisalRequestRevisionItem::class);
    }

    public function fieldChangeLogs(): HasMany
    {
        return $this->hasMany(AppraisalFieldChangeLog::class);
    }
}

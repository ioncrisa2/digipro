<?php

namespace App\Http\Resources\Api\V1;

use App\Support\Mobile\AppraisalStatusPresentation;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AppraisalDraftResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'request_number' => $this->request_number,
            'status' => AppraisalStatusPresentation::make($this->status),
            'purpose' => $this->purpose?->value,
            'report_type' => $this->report_type?->value,
            'client_name' => $this->client_name,
            'client_address' => $this->client_address,
            'client_spk_number' => $this->client_spk_number,
            'user_request_note' => $this->user_request_note,
            'sertifikat_on_hand_confirmed' => (bool) $this->sertifikat_on_hand_confirmed,
            'certificate_not_encumbered_confirmed' => (bool) $this->certificate_not_encumbered_confirmed,
            'report_format' => $this->report_format,
            'physical_copies_count' => (int) $this->physical_copies_count,
            'assets_count' => (int) ($this->assets_count ?? $this->assets->count()),
            'assets' => $this->assets->map(static fn ($asset): array => [
                'id' => $asset->id,
                'asset_type' => $asset->asset_type,
                'peruntukan' => $asset->peruntukan,
                'title_document' => $asset->title_document,
                'land_shape' => $asset->land_shape,
                'land_position' => $asset->land_position,
                'land_condition' => $asset->land_condition,
                'topography' => $asset->topography,
                'province_id' => $asset->province_id,
                'regency_id' => $asset->regency_id,
                'district_id' => $asset->district_id,
                'village_id' => $asset->village_id,
                'address' => $asset->address,
                'maps_link' => $asset->maps_link,
                'coordinates_lat' => $asset->coordinates_lat,
                'coordinates_lng' => $asset->coordinates_lng,
                'land_area' => $asset->land_area,
                'building_area' => $asset->building_area,
                'building_floors' => $asset->building_floors,
                'build_year' => $asset->build_year,
                'renovation_year' => $asset->renovation_year,
                'frontage_width' => $asset->frontage_width,
                'access_road_width' => $asset->access_road_width,
                'files' => AppraisalAssetFileResource::collection($asset->files)->resolve($request),
            ])->values(),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}

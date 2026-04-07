<?php

namespace App\Support\Admin;

use App\Enums\AssetTypeEnum;
use App\Models\AppraisalAsset;
use App\Models\AppraisalRequest;
use App\Models\District;
use App\Models\Province;
use App\Models\Regency;
use App\Models\Village;
use App\Support\AppraisalAssetFieldOptions;
use Illuminate\Http\Request;
use InvalidArgumentException;

class AppraisalAssetFormBuilder
{
    public function buildEditorProps(
        Request $request,
        AppraisalRequest $appraisalRequest,
        ?AppraisalAsset $asset = null
    ): array {
        $provinceId = $this->blankToNull($request->query('province_id', $asset?->province_id));
        $regencyId = $this->blankToNull($request->query('regency_id', $asset?->regency_id));
        $districtId = $this->blankToNull($request->query('district_id', $asset?->district_id));

        return [
            'mode' => $asset ? 'edit' : 'create',
            'requestRecord' => [
                'id' => $appraisalRequest->id,
                'request_number' => $appraisalRequest->request_number ?? ('REQ-' . $appraisalRequest->id),
                'show_url' => route('admin.appraisal-requests.show', $appraisalRequest),
            ],
            'record' => $this->assetFormRecord($asset),
            'assetTypeOptions' => [
                ['value' => AssetTypeEnum::TANAH->value, 'label' => AssetTypeEnum::TANAH->label()],
                ['value' => AssetTypeEnum::TANAH_BANGUNAN->value, 'label' => AssetTypeEnum::TANAH_BANGUNAN->label()],
            ],
            'usageOptions' => AppraisalAssetFieldOptions::usageOptions(),
            'titleDocumentOptions' => AppraisalAssetFieldOptions::titleDocumentOptions(),
            'landShapeOptions' => AppraisalAssetFieldOptions::landShapeOptions(),
            'landPositionOptions' => AppraisalAssetFieldOptions::landPositionOptions(),
            'landConditionOptions' => AppraisalAssetFieldOptions::landConditionOptions(),
            'topographyOptions' => AppraisalAssetFieldOptions::topographyOptions(),
            'provinces' => Province::query()->select(['id', 'name'])->orderBy('name')->get()->values(),
            'regencies' => $provinceId
                ? Regency::query()->select(['id', 'name'])->where('province_id', $provinceId)->orderBy('name')->get()->values()
                : [],
            'districts' => $regencyId
                ? District::query()->select(['id', 'name'])->where('regency_id', $regencyId)->orderBy('name')->get()->values()
                : [],
            'villages' => $districtId
                ? Village::query()->select(['id', 'name'])->where('district_id', $districtId)->orderBy('name')->get()->values()
                : [],
        ];
    }

    public function assetPayload(array $validated): array
    {
        return [
            'asset_code' => $this->blankToNull($validated['asset_code'] ?? null),
            'asset_type' => $validated['asset_type'],
            'peruntukan' => $this->blankToNull($validated['peruntukan'] ?? null),
            'title_document' => $this->blankToNull($validated['title_document'] ?? null),
            'certificate_number' => $this->blankToNull($validated['certificate_number'] ?? null),
            'certificate_holder_name' => $this->blankToNull($validated['certificate_holder_name'] ?? null),
            'certificate_issued_at' => $this->blankToNull($validated['certificate_issued_at'] ?? null),
            'land_book_date' => $this->blankToNull($validated['land_book_date'] ?? null),
            'document_land_area' => $this->blankToNull($validated['document_land_area'] ?? null),
            'legal_notes' => $this->blankToNull($validated['legal_notes'] ?? null),
            'land_shape' => $this->blankToNull($validated['land_shape'] ?? null),
            'land_position' => $this->blankToNull($validated['land_position'] ?? null),
            'land_condition' => $this->blankToNull($validated['land_condition'] ?? null),
            'topography' => $this->blankToNull($validated['topography'] ?? null),
            'province_id' => $this->blankToNull($validated['province_id'] ?? null),
            'regency_id' => $this->blankToNull($validated['regency_id'] ?? null),
            'district_id' => $this->blankToNull($validated['district_id'] ?? null),
            'village_id' => $this->blankToNull($validated['village_id'] ?? null),
            'address' => $this->blankToNull($validated['address'] ?? null),
            'maps_link' => $this->blankToNull($validated['maps_link'] ?? null),
            'coordinates_lat' => $this->blankToNull($validated['coordinates_lat'] ?? null),
            'coordinates_lng' => $this->blankToNull($validated['coordinates_lng'] ?? null),
            'land_area' => $this->blankToNull($validated['land_area'] ?? null),
            'building_area' => $this->blankToNull($validated['building_area'] ?? null),
            'building_floors' => $this->blankToNull($validated['building_floors'] ?? null),
            'build_year' => $this->blankToNull($validated['build_year'] ?? null),
            'renovation_year' => $this->blankToNull($validated['renovation_year'] ?? null),
            'frontage_width' => $this->blankToNull($validated['frontage_width'] ?? null),
            'access_road_width' => $this->blankToNull($validated['access_road_width'] ?? null),
        ];
    }

    public function ensureBelongsToRequest(AppraisalRequest $appraisalRequest, AppraisalAsset $asset): void
    {
        abort_unless((int) $asset->appraisal_request_id === (int) $appraisalRequest->id, 404);
    }

    public function assetFileDirectory(string $type): string
    {
        return match ($type) {
            'doc_pbb' => 'documents/pbb',
            'doc_imb' => 'documents/imb',
            'doc_certs' => 'documents/certificate',
            'photo_access_road' => 'photos/access_road',
            'photo_front' => 'photos/front',
            'photo_interior' => 'photos/interior',
            default => throw new InvalidArgumentException("Unsupported asset file type [{$type}]."),
        };
    }

    private function assetFormRecord(?AppraisalAsset $asset): array
    {
        return [
            'id' => $asset?->id,
            'asset_code' => $asset?->asset_code,
            'asset_type' => $asset?->asset_type,
            'peruntukan' => $asset?->peruntukan,
            'title_document' => $asset?->title_document,
            'certificate_number' => $asset?->certificate_number,
            'certificate_holder_name' => $asset?->certificate_holder_name,
            'certificate_issued_at' => $asset?->certificate_issued_at?->toDateString(),
            'land_book_date' => $asset?->land_book_date?->toDateString(),
            'document_land_area' => $asset?->document_land_area,
            'legal_notes' => $asset?->legal_notes,
            'land_shape' => $asset?->land_shape,
            'land_position' => $asset?->land_position,
            'land_condition' => $asset?->land_condition,
            'topography' => $asset?->topography,
            'province_id' => $asset?->province_id,
            'regency_id' => $asset?->regency_id,
            'district_id' => $asset?->district_id,
            'village_id' => $asset?->village_id,
            'address' => $asset?->address,
            'maps_link' => $asset?->maps_link,
            'coordinates_lat' => $asset?->coordinates_lat,
            'coordinates_lng' => $asset?->coordinates_lng,
            'land_area' => $asset?->land_area,
            'building_area' => $asset?->building_area,
            'building_floors' => $asset?->building_floors,
            'build_year' => $asset?->build_year,
            'renovation_year' => $asset?->renovation_year,
            'frontage_width' => $asset?->frontage_width,
            'access_road_width' => $asset?->access_road_width,
        ];
    }

    private function blankToNull(mixed $value): mixed
    {
        return is_string($value) && trim($value) === '' ? null : $value;
    }
}

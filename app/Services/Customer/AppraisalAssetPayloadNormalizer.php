<?php

namespace App\Services\Customer;

use App\Enums\AssetTypeEnum;

class AppraisalAssetPayloadNormalizer
{
    public function prepare(array $row): array
    {
        $asset = $this->normalizeRow($row);
        $assetType = $this->mapAssetTypeToDb($asset['type'] ?? $asset['asset_type'] ?? null);
        $isLandOnly = $assetType === AssetTypeEnum::TANAH->value;
        [$lat, $lng] = $this->resolveCoordinates($asset);

        return [
            'raw' => $asset,
            'asset_type' => $assetType,
            'has_building' => ! $isLandOnly,
            'address' => $asset['address'] ?? null,
            'maps_link' => $asset['maps_link'] ?? $asset['map_url'] ?? null,
            'coordinates_lat' => $lat,
            'coordinates_lng' => $lng,
        ];
    }

    public function toModelAttributes(int $appraisalRequestId, array $prepared): array
    {
        $asset = $prepared['raw'];
        $hasBuilding = (bool) ($prepared['has_building'] ?? false);

        return [
            'appraisal_request_id' => $appraisalRequestId,
            'asset_type' => $prepared['asset_type'],
            'peruntukan' => $asset['peruntukan'] ?? null,
            // 'title_document' => $asset['title_document'] ?? null,
            // 'land_shape' => $asset['land_shape'] ?? null,
            // 'land_position' => $asset['land_position'] ?? null,
            // 'land_condition' => $asset['land_condition'] ?? null,
            // 'topography' => $asset['topography'] ?? null,
            'land_area' => $asset['land_area'] ?? null,
            'building_area' => $hasBuilding ? ($asset['building_area'] ?? null) : null,
            'building_floors' => $hasBuilding ? ($asset['floors'] ?? $asset['building_floors'] ?? null) : null,
            'build_year' => $hasBuilding ? ($asset['build_year'] ?? null) : null,
            'renovation_year' => $hasBuilding ? ($asset['renovation_year'] ?? null) : null,
            // 'frontage_width' => $asset['frontage_width'] ?? null,
            // 'access_road_width' => $asset['access_road_width'] ?? null,
            'province_id' => $this->normalizeLocationCode($asset['province_id'] ?? null, 2),
            'regency_id' => $this->normalizeLocationCode($asset['regency_id'] ?? null, 4),
            'district_id' => $this->normalizeLocationCode($asset['district_id'] ?? null, 7),
            'village_id' => $this->normalizeLocationCode($asset['village_id'] ?? null, 10),
            'address' => $prepared['address'],
            'coordinates_lat' => $prepared['coordinates_lat'],
            'coordinates_lng' => $prepared['coordinates_lng'],
            'maps_link' => $prepared['maps_link'],
        ];
    }

    private function normalizeRow(array $row): array
    {
        if (isset($row['data']) && is_string($row['data'])) {
            $decoded = json_decode($row['data'], true);

            if (is_array($decoded)) {
                return $decoded;
            }
        }

        return $row;
    }

    private function resolveCoordinates(array $asset): array
    {
        if (isset($asset['coordinates_lat']) || isset($asset['coordinates_lng'])) {
            return [
                $this->asFloatOrNull($asset['coordinates_lat'] ?? null),
                $this->asFloatOrNull($asset['coordinates_lng'] ?? null),
            ];
        }

        return $this->parseCoordinates($asset['coordinates'] ?? null);
    }

    private function mapAssetTypeToDb(?string $type): ?string
    {
        if (! $type) {
            return null;
        }

        if ($type === 'land') {
            return AssetTypeEnum::TANAH->value;
        }

        if ($type === AssetTypeEnum::TANAH->value) {
            return AssetTypeEnum::TANAH->value;
        }

        return AssetTypeEnum::TANAH_BANGUNAN->value;
    }

    private function parseCoordinates(mixed $raw): array
    {
        if ($raw === null || $raw === '') {
            return [null, null];
        }

        if (is_string($raw)) {
            $trim = trim($raw);

            if ($trim !== '' && ($trim[0] === '{' || $trim[0] === '[')) {
                $decoded = json_decode($trim, true);

                if (is_array($decoded)) {
                    $lat = $decoded['lat'] ?? $decoded['latitude'] ?? null;
                    $lng = $decoded['lng'] ?? $decoded['longitude'] ?? null;

                    return [$this->asFloatOrNull($lat), $this->asFloatOrNull($lng)];
                }
            }

            if (str_contains($trim, ',')) {
                [$lat, $lng] = array_pad(array_map('trim', explode(',', $trim, 2)), 2, null);

                return [$this->asFloatOrNull($lat), $this->asFloatOrNull($lng)];
            }
        }

        if (is_array($raw)) {
            $lat = $raw['lat'] ?? $raw['latitude'] ?? null;
            $lng = $raw['lng'] ?? $raw['longitude'] ?? null;

            return [$this->asFloatOrNull($lat), $this->asFloatOrNull($lng)];
        }

        return [null, null];
    }

    private function asFloatOrNull(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        return is_numeric($value) ? (float) $value : null;
    }

    private function normalizeLocationCode(mixed $value, int $length): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        $digits = preg_replace('/\D+/', '', (string) $value);

        if ($digits === '' || strlen($digits) > $length) {
            return null;
        }

        return str_pad($digits, $length, '0', STR_PAD_LEFT);
    }
}

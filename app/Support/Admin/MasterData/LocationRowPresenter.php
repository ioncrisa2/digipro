<?php

namespace App\Support\Admin\MasterData;

use App\Models\District;
use App\Models\Province;
use App\Models\Regency;
use App\Models\Village;

class LocationRowPresenter
{
    public function province(Province $province, string $editUrl, string $destroyUrl): array
    {
        return [
            'id' => $province->id,
            'code' => $province->id,
            'name' => $province->name,
            'details' => [],
            'stats' => [
                ['label' => 'Kabupaten/Kota', 'value' => (int) ($province->regencies_count ?? 0)],
            ],
            'edit_url' => $editUrl,
            'destroy_url' => $destroyUrl,
        ];
    }

    public function regency(Regency $regency, string $editUrl, string $destroyUrl): array
    {
        return [
            'id' => $regency->id,
            'code' => $regency->id,
            'name' => $regency->name,
            'details' => [
                'Provinsi: ' . ($regency->province?->name ?? '-'),
            ],
            'stats' => [
                ['label' => 'Kecamatan', 'value' => (int) ($regency->districts_count ?? 0)],
            ],
            'edit_url' => $editUrl,
            'destroy_url' => $destroyUrl,
        ];
    }

    public function district(District $district, string $editUrl, string $destroyUrl): array
    {
        return [
            'id' => $district->id,
            'code' => $district->id,
            'name' => $district->name,
            'details' => [
                'Kabupaten/Kota: ' . ($district->regency?->name ?? '-'),
                'Provinsi: ' . ($district->regency?->province?->name ?? '-'),
            ],
            'stats' => [
                ['label' => 'Kelurahan/Desa', 'value' => (int) ($district->villages_count ?? 0)],
            ],
            'edit_url' => $editUrl,
            'destroy_url' => $destroyUrl,
        ];
    }

    public function village(Village $village, string $editUrl, string $destroyUrl): array
    {
        return [
            'id' => $village->id,
            'code' => $village->id,
            'name' => $village->name,
            'details' => [
                'Kecamatan: ' . ($village->district?->name ?? '-'),
                'Kabupaten/Kota: ' . ($village->district?->regency?->name ?? '-'),
                'Provinsi: ' . ($village->district?->regency?->province?->name ?? '-'),
            ],
            'stats' => [],
            'edit_url' => $editUrl,
            'destroy_url' => $destroyUrl,
        ];
    }
}

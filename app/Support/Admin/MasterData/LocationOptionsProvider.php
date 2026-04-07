<?php

namespace App\Support\Admin\MasterData;

use App\Models\District;
use App\Models\Province;
use App\Models\Regency;

class LocationOptionsProvider
{
    public function provinceSelectOptions(): array
    {
        return Province::query()
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (Province $province) => [
                'value' => (string) $province->id,
                'label' => $province->name . ' (' . $province->id . ')',
            ])
            ->values()
            ->all();
    }

    public function provinceFilterOptions(): array
    {
        return [
            ['value' => 'all', 'label' => 'Semua Provinsi'],
            ...$this->provinceSelectOptions(),
        ];
    }

    public function regencySelectOptionsByProvince(?string $provinceId): array
    {
        if (blank($provinceId)) {
            return [];
        }

        return Regency::query()
            ->where('province_id', $provinceId)
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (Regency $regency) => [
                'value' => (string) $regency->id,
                'label' => $regency->name . ' (' . $regency->id . ')',
            ])
            ->values()
            ->all();
    }

    public function districtSelectOptionsByRegency(?string $regencyId): array
    {
        if (blank($regencyId)) {
            return [];
        }

        return District::query()
            ->where('regency_id', $regencyId)
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (District $district) => [
                'value' => (string) $district->id,
                'label' => $district->name . ' (' . $district->id . ')',
            ])
            ->values()
            ->all();
    }

    public function regencyFilterOptions(): array
    {
        return Regency::query()
            ->with('province:id,name')
            ->orderBy('name')
            ->get(['id', 'name', 'province_id'])
            ->map(fn (Regency $regency) => [
                'value' => (string) $regency->id,
                'label' => $regency->name . ' - ' . ($regency->province?->name ?? '-'),
            ])
            ->values()
            ->all();
    }

    public function districtFilterOptions(): array
    {
        return District::query()
            ->with(['regency:id,name,province_id', 'regency.province:id,name'])
            ->orderBy('name')
            ->get(['id', 'name', 'regency_id'])
            ->map(fn (District $district) => [
                'value' => (string) $district->id,
                'label' => $district->name
                    . ' - '
                    . ($district->regency?->name ?? '-')
                    . ' / '
                    . ($district->regency?->province?->name ?? '-'),
            ])
            ->values()
            ->all();
    }
}

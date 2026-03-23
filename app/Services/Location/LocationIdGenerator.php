<?php

namespace App\Services\Location;

use App\Models\District;
use App\Models\Province;
use App\Models\Regency;
use App\Models\Village;
use Illuminate\Database\Eloquent\Builder;
use InvalidArgumentException;
use RuntimeException;

class LocationIdGenerator
{
    public function nextProvinceId(): string
    {
        return $this->nextIndependentId(
            Province::query(),
            length: 2,
            label: 'Provinsi',
            minValue: 11,
        );
    }

    public function nextRegencyId(string $provinceId): string
    {
        $this->assertParentExists(Province::query(), $provinceId, 'Provinsi');

        return $this->nextIdForParent(
            Regency::query(),
            parentColumn: 'province_id',
            parentId: $provinceId,
            suffixLength: 2,
            label: 'Kabupaten / Kota',
        );
    }

    public function nextDistrictId(string $regencyId): string
    {
        $this->assertParentExists(Regency::query(), $regencyId, 'Kabupaten / Kota');

        return $this->nextIdForParent(
            District::query(),
            parentColumn: 'regency_id',
            parentId: $regencyId,
            suffixLength: 3,
            label: 'Kecamatan',
        );
    }

    public function nextVillageId(string $districtId): string
    {
        $this->assertParentExists(District::query(), $districtId, 'Kecamatan');

        return $this->nextIdForParent(
            Village::query(),
            parentColumn: 'district_id',
            parentId: $districtId,
            suffixLength: 3,
            label: 'Desa / Kelurahan',
        );
    }

    private function assertParentExists(Builder $query, string $parentId, string $label): void
    {
        if (blank($parentId)) {
            throw new InvalidArgumentException("Parent id untuk {$label} wajib diisi.");
        }

        if (! $query->where('id', $parentId)->exists()) {
            throw new InvalidArgumentException("{$label} dengan id '{$parentId}' tidak ditemukan.");
        }
    }

    private function nextIndependentId(
        Builder $query,
        int $length,
        string $label,
        int $minValue = 1,
    ): string {
        $expected = $minValue;
        $maxAllowed = (10 ** $length) - 1;

        $query
            ->orderBy('id')
            ->lockForUpdate()
            ->pluck('id')
            ->each(function ($id) use (&$expected, $maxAllowed, $length): bool|null {
                $id = (string) $id;

                if (strlen($id) !== $length || ! ctype_digit($id)) {
                    return null;
                }

                $numericValue = (int) $id;

                if ($numericValue < $expected) {
                    return null;
                }

                if ($numericValue === $expected) {
                    $expected++;
                }

                if ($expected > $maxAllowed) {
                    return false;
                }

                return null;
            });

        if ($expected > $maxAllowed) {
            throw new RuntimeException("Urutan kode {$label} sudah penuh.");
        }

        return str_pad((string) $expected, $length, '0', STR_PAD_LEFT);
    }

    private function nextIdForParent(
        Builder $query,
        string $parentColumn,
        string $parentId,
        int $suffixLength,
        string $label,
    ): string {
        $parentLength = strlen($parentId);
        $expectedSuffix = 1;
        $maxAllowedSuffix = (10 ** $suffixLength) - 1;

        $query
            ->where($parentColumn, $parentId)
            ->orderBy('id')
            ->lockForUpdate()
            ->pluck('id')
            ->each(function ($id) use (&$expectedSuffix, $maxAllowedSuffix, $parentId, $parentLength, $suffixLength): bool|null {
                $id = (string) $id;

                if (! str_starts_with($id, $parentId)) {
                    return null;
                }

                $suffix = substr($id, $parentLength);

                if ($suffix === '' || strlen($suffix) !== $suffixLength || ! ctype_digit($suffix)) {
                    return null;
                }

                $numericSuffix = (int) $suffix;

                if ($numericSuffix < $expectedSuffix) {
                    return null;
                }

                if ($numericSuffix === $expectedSuffix) {
                    $expectedSuffix++;
                }

                if ($expectedSuffix > $maxAllowedSuffix) {
                    return false;
                }

                return null;
            });

        if ($expectedSuffix > $maxAllowedSuffix) {
            throw new RuntimeException("Urutan kode {$label} untuk parent {$parentId} sudah penuh.");
        }

        return $parentId . str_pad((string) $expectedSuffix, $suffixLength, '0', STR_PAD_LEFT);
    }
}

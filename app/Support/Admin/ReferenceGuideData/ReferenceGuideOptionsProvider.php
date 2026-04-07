<?php

namespace App\Support\Admin\ReferenceGuideData;

use App\Models\ConstructionCostIndex;
use App\Models\CostElement;
use App\Models\FloorIndex;
use App\Models\GuidelineSet;
use App\Models\MappiRcnStandard;
use App\Models\Province;
use App\Models\Regency;

class ReferenceGuideOptionsProvider
{
    public function guidelineSetOptions(bool $includeAll = false): array
    {
        $options = GuidelineSet::query()
            ->orderByDesc('year')
            ->get(['id', 'name', 'year', 'is_active'])
            ->map(fn (GuidelineSet $guidelineSet) => [
                'value' => (string) $guidelineSet->id,
                'label' => $guidelineSet->name . ' (' . $guidelineSet->year . ')' . ($guidelineSet->is_active ? ' - aktif' : ''),
                'year' => (int) $guidelineSet->year,
            ])
            ->values()
            ->all();

        if (! $includeAll) {
            return $options;
        }

        return [
            ['value' => 'all', 'label' => 'Semua Guideline Set'],
            ...$options,
        ];
    }

    public function constructionCostIndexYearOptions(): array
    {
        return $this->yearOptions(ConstructionCostIndex::class);
    }

    public function costElementYearOptions(): array
    {
        return $this->yearOptions(CostElement::class);
    }

    public function floorIndexYearOptions(bool $includeAll = false): array
    {
        return $this->yearOptions(FloorIndex::class, $includeAll);
    }

    public function mappiRcnYearOptions(bool $includeAll = false): array
    {
        return $this->yearOptions(MappiRcnStandard::class, $includeAll);
    }

    public function floorIndexBuildingClassOptions(bool $includeAll = false): array
    {
        return $this->distinctOptions(FloorIndex::class, 'building_class', $includeAll, 'Semua Class');
    }

    public function mappiRcnBuildingTypeOptions(bool $includeAll = false): array
    {
        return $this->distinctOptions(MappiRcnStandard::class, 'building_type', $includeAll, 'Semua Building Type');
    }

    public function mappiRcnBuildingClassOptions(bool $includeAll = false): array
    {
        return $this->distinctOptions(MappiRcnStandard::class, 'building_class', $includeAll, 'Semua Building Class');
    }

    public function mappiRcnFormOptions(): array
    {
        return [
            'building_types' => $this->distinctValues(MappiRcnStandard::class, 'building_type'),
            'building_classes' => $this->distinctValues(MappiRcnStandard::class, 'building_class'),
            'storey_patterns' => $this->distinctValues(MappiRcnStandard::class, 'storey_pattern'),
        ];
    }

    public function costElementBaseRegionOptions(bool $includeAll = false): array
    {
        return $this->distinctOptions(CostElement::class, 'base_region', $includeAll, 'Semua Base Region');
    }

    public function costElementGroupOptions(bool $includeAll = false): array
    {
        return $this->distinctOptions(CostElement::class, 'group', $includeAll, 'Semua Group');
    }

    public function costElementFormOptions(): array
    {
        return [
            'groups' => $this->distinctValues(CostElement::class, 'group', 300),
            'element_codes' => $this->distinctValues(CostElement::class, 'element_code', 500),
            'element_names' => $this->distinctValues(CostElement::class, 'element_name', 500),
            'building_types' => $this->distinctValues(CostElement::class, 'building_type', 200),
            'building_classes' => $this->distinctValues(CostElement::class, 'building_class', 200),
            'storey_patterns' => $this->distinctValues(CostElement::class, 'storey_pattern', 200),
        ];
    }

    public function provinceSelectOptions(bool $withCode = true): array
    {
        return Province::query()
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (Province $province) => [
                'value' => (string) $province->id,
                'label' => $withCode ? $province->name . ' (' . $province->id . ')' : $province->name,
            ])
            ->values()
            ->all();
    }

    public function provinceFilterOptions(bool $includeAll = false): array
    {
        $options = $this->provinceSelectOptions();

        if (! $includeAll) {
            return $options;
        }

        return [
            ['value' => 'all', 'label' => 'Semua Provinsi'],
            ...$options,
        ];
    }

    public function regencySelectOptionsByProvince(?string $provinceId, bool $withCode = true): array
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
                'label' => $withCode ? $regency->name . ' (' . $regency->id . ')' : $regency->name,
            ])
            ->values()
            ->all();
    }

    private function yearOptions(string $modelClass, bool $includeAll = false): array
    {
        $options = $modelClass::query()
            ->distinct()
            ->orderByDesc('year')
            ->pluck('year')
            ->map(fn ($year) => [
                'value' => (string) $year,
                'label' => (string) $year,
            ])
            ->values()
            ->all();

        if (! $includeAll) {
            return $options;
        }

        return [
            ['value' => 'all', 'label' => 'Semua Tahun'],
            ...$options,
        ];
    }

    private function distinctOptions(string $modelClass, string $column, bool $includeAll, string $allLabel): array
    {
        $options = $modelClass::query()
            ->whereNotNull($column)
            ->where($column, '<>', '')
            ->distinct()
            ->orderBy($column)
            ->pluck($column)
            ->map(fn (string $value) => [
                'value' => $value,
                'label' => $value,
            ])
            ->values()
            ->all();

        if (! $includeAll) {
            return $options;
        }

        return [
            ['value' => 'all', 'label' => $allLabel],
            ...$options,
        ];
    }

    private function distinctValues(string $modelClass, string $column, ?int $limit = null): array
    {
        $query = $modelClass::query()
            ->whereNotNull($column)
            ->where($column, '<>', '')
            ->distinct()
            ->orderBy($column);

        if ($limit !== null) {
            $query->limit($limit);
        }

        return $query->pluck($column)->values()->all();
    }
}

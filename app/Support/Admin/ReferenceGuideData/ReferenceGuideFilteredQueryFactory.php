<?php

namespace App\Support\Admin\ReferenceGuideData;

use App\Models\ConstructionCostIndex;
use App\Models\CostElement;
use App\Models\FloorIndex;
use App\Models\MappiRcnStandard;

class ReferenceGuideFilteredQueryFactory
{
    public function constructionCostIndices(array $filters)
    {
        return ConstructionCostIndex::query()
            ->when($filters['q'] !== '', function ($query) use ($filters): void {
                $query->where(function ($innerQuery) use ($filters): void {
                    $innerQuery
                        ->where('region_code', 'like', '%' . $filters['q'] . '%')
                        ->orWhere('region_name', 'like', '%' . $filters['q'] . '%');
                });
            })
            ->when(
                $filters['guideline_set_id'] !== 'all',
                fn ($query) => $query->where('guideline_set_id', (int) $filters['guideline_set_id'])
            )
            ->when(
                $filters['year'] !== 'all',
                fn ($query) => $query->where('year', (int) $filters['year'])
            )
            ->when(
                $filters['province_id'] !== 'all',
                fn ($query) => $query->whereHas('regency', fn ($regencyQuery) => $regencyQuery->where('province_id', $filters['province_id']))
            );
    }

    public function costElements(array $filters)
    {
        return CostElement::query()
            ->when($filters['q'] !== '', function ($query) use ($filters): void {
                $query->where(function ($innerQuery) use ($filters): void {
                    $innerQuery
                        ->where('group', 'like', '%' . $filters['q'] . '%')
                        ->orWhere('element_code', 'like', '%' . $filters['q'] . '%')
                        ->orWhere('element_name', 'like', '%' . $filters['q'] . '%')
                        ->orWhere('building_type', 'like', '%' . $filters['q'] . '%')
                        ->orWhere('building_class', 'like', '%' . $filters['q'] . '%')
                        ->orWhere('storey_pattern', 'like', '%' . $filters['q'] . '%');
                });
            })
            ->when(
                $filters['guideline_set_id'] !== 'all',
                fn ($query) => $query->where('guideline_set_id', (int) $filters['guideline_set_id'])
            )
            ->when(
                $filters['year'] !== 'all',
                fn ($query) => $query->where('year', (int) $filters['year'])
            )
            ->when(
                $filters['base_region'] !== 'all',
                fn ($query) => $query->where('base_region', $filters['base_region'])
            )
            ->when(
                $filters['group'] !== 'all',
                fn ($query) => $query->where('group', $filters['group'])
            );
    }

    public function floorIndices(array $filters)
    {
        return FloorIndex::query()
            ->when($filters['q'] !== '', function ($query) use ($filters): void {
                $query->where(function ($innerQuery) use ($filters): void {
                    $innerQuery
                        ->where('building_class', 'like', '%' . $filters['q'] . '%')
                        ->orWhere('floor_count', 'like', '%' . $filters['q'] . '%');
                });
            })
            ->when(
                $filters['guideline_set_id'] !== 'all',
                fn ($query) => $query->where('guideline_set_id', (int) $filters['guideline_set_id'])
            )
            ->when(
                $filters['year'] !== 'all',
                fn ($query) => $query->where('year', (int) $filters['year'])
            )
            ->when(
                $filters['building_class'] !== 'all',
                fn ($query) => $query->where('building_class', $filters['building_class'])
            );
    }

    public function mappiRcnStandards(array $filters)
    {
        return MappiRcnStandard::query()
            ->when($filters['q'] !== '', function ($query) use ($filters): void {
                $query->where(function ($innerQuery) use ($filters): void {
                    $innerQuery
                        ->where('building_type', 'like', '%' . $filters['q'] . '%')
                        ->orWhere('building_class', 'like', '%' . $filters['q'] . '%')
                        ->orWhere('storey_pattern', 'like', '%' . $filters['q'] . '%')
                        ->orWhere('notes', 'like', '%' . $filters['q'] . '%');
                });
            })
            ->when(
                $filters['guideline_set_id'] !== 'all',
                fn ($query) => $query->where('guideline_set_id', (int) $filters['guideline_set_id'])
            )
            ->when(
                $filters['year'] !== 'all',
                fn ($query) => $query->where('year', (int) $filters['year'])
            )
            ->when(
                $filters['building_type'] !== 'all',
                fn ($query) => $query->where('building_type', $filters['building_type'])
            )
            ->when(
                $filters['building_class'] !== 'all',
                fn ($query) => $query->where('building_class', $filters['building_class'])
            );
    }
}

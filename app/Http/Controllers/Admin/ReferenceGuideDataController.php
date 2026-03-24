<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreConstructionCostIndexRequest;
use App\Http\Requests\Admin\StoreCostElementRequest;
use App\Http\Requests\Admin\StoreFloorIndexRequest;
use App\Http\Requests\Admin\StoreMappiRcnStandardRequest;
use App\Models\ConstructionCostIndex;
use App\Models\CostElement;
use App\Models\FloorIndex;
use App\Models\GuidelineSet;
use App\Models\MappiRcnStandard;
use App\Models\Province;
use App\Models\Regency;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Response;

class ReferenceGuideDataController extends Controller
{
    public function constructionCostIndicesIndex(Request $request): Response
    {
        $filters = [
            'q' => trim((string) $request->query('q', '')),
            'guideline_set_id' => (string) $request->query('guideline_set_id', 'all'),
            'year' => (string) $request->query('year', 'all'),
            'province_id' => (string) $request->query('province_id', 'all'),
            'per_page' => (string) $this->adminPerPage($request),
        ];

        $records = ConstructionCostIndex::query()
            ->with([
                'guidelineSet:id,name,year,is_active',
                'regency:id,name,province_id',
                'regency.province:id,name',
            ])
            ->when($filters['q'] !== '', function ($query) use ($filters): void {
                $query->where(function ($innerQuery) use ($filters): void {
                    $innerQuery
                        ->where('region_name', 'like', '%' . $filters['q'] . '%')
                        ->orWhere('region_code', 'like', '%' . $filters['q'] . '%');
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
            )
            ->orderByDesc('year')
            ->orderBy('region_code')
            ->paginate($this->adminPerPage($request))
            ->withQueryString();

        $records->through(fn (ConstructionCostIndex $record) => $this->transformConstructionCostIndexRow($record));

        return inertia('Admin/ConstructionCostIndices/Index', [
            'filters' => $filters,
            'guidelineSetOptions' => $this->guidelineSetOptions(includeAll: true),
            'yearOptions' => $this->constructionCostIndexYearOptions(),
            'provinceOptions' => $this->provinceFilterOptions(includeAll: true),
            'summary' => [
                'total' => ConstructionCostIndex::query()->count(),
                'guideline_sets' => ConstructionCostIndex::query()->distinct('guideline_set_id')->count('guideline_set_id'),
                'provinces' => Regency::query()
                    ->whereIn('id', ConstructionCostIndex::query()->distinct()->pluck('region_code'))
                    ->distinct('province_id')
                    ->count('province_id'),
                'active_guideline' => ConstructionCostIndex::query()
                    ->whereHas('guidelineSet', fn ($query) => $query->where('is_active', true))
                    ->count(),
            ],
            'records' => $this->paginatedRecordsPayload($records),
            'createUrl' => route('admin.ref-guidelines.construction-cost-indices.create'),
            'ikkByProvinceUrl' => route('admin.ref-guidelines.ikk-by-province.index'),
        ]);
    }

    public function constructionCostIndicesCreate(): Response
    {
        $activeGuideline = GuidelineSet::query()->where('is_active', true)->first();

        return inertia('Admin/ConstructionCostIndices/Form', [
            'mode' => 'create',
            'record' => [
                'guideline_set_id' => $activeGuideline?->id,
                'year' => $activeGuideline?->year ?? (int) now()->format('Y'),
                'province_id' => '',
                'region_code' => '',
                'ikk_value' => '',
            ],
            'guidelineSetOptions' => $this->guidelineSetOptions(),
            'provinceOptions' => $this->provinceSelectOptions(),
            'regencyOptions' => [],
            'submitUrl' => route('admin.ref-guidelines.construction-cost-indices.store'),
            'indexUrl' => route('admin.ref-guidelines.construction-cost-indices.index'),
            'ikkByProvinceUrl' => route('admin.ref-guidelines.ikk-by-province.index'),
        ]);
    }

    public function constructionCostIndicesStore(StoreConstructionCostIndexRequest $request): RedirectResponse
    {
        $validated = $request->validated();
        $regency = Regency::query()->findOrFail($validated['region_code']);

        ConstructionCostIndex::query()->create([
            'guideline_set_id' => $validated['guideline_set_id'],
            'year' => $validated['year'],
            'region_code' => $regency->id,
            'region_name' => $regency->name,
            'ikk_value' => $validated['ikk_value'],
        ]);

        return redirect()
            ->route('admin.ref-guidelines.construction-cost-indices.index')
            ->with('success', 'IKK berhasil ditambahkan.');
    }

    public function constructionCostIndicesEdit(ConstructionCostIndex $constructionCostIndex): Response
    {
        $constructionCostIndex->loadMissing('regency:id,name,province_id');

        return inertia('Admin/ConstructionCostIndices/Form', [
            'mode' => 'edit',
            'record' => [
                'id' => $constructionCostIndex->id,
                'guideline_set_id' => $constructionCostIndex->guideline_set_id,
                'year' => (int) $constructionCostIndex->year,
                'province_id' => (string) ($constructionCostIndex->regency?->province_id ?? ''),
                'region_code' => (string) $constructionCostIndex->region_code,
                'ikk_value' => (float) $constructionCostIndex->ikk_value,
            ],
            'guidelineSetOptions' => $this->guidelineSetOptions(),
            'provinceOptions' => $this->provinceSelectOptions(),
            'regencyOptions' => $this->regencySelectOptionsByProvince($constructionCostIndex->regency?->province_id),
            'submitUrl' => route('admin.ref-guidelines.construction-cost-indices.update', $constructionCostIndex),
            'indexUrl' => route('admin.ref-guidelines.construction-cost-indices.index'),
            'ikkByProvinceUrl' => route('admin.ref-guidelines.ikk-by-province.index'),
        ]);
    }

    public function constructionCostIndicesUpdate(StoreConstructionCostIndexRequest $request, ConstructionCostIndex $constructionCostIndex): RedirectResponse
    {
        $validated = $request->validated();
        $regency = Regency::query()->findOrFail($validated['region_code']);

        $constructionCostIndex->forceFill([
            'guideline_set_id' => $validated['guideline_set_id'],
            'year' => $validated['year'],
            'region_code' => $regency->id,
            'region_name' => $regency->name,
            'ikk_value' => $validated['ikk_value'],
        ])->save();

        return redirect()
            ->route('admin.ref-guidelines.construction-cost-indices.index')
            ->with('success', 'IKK berhasil diperbarui.');
    }

    public function constructionCostIndicesDestroy(ConstructionCostIndex $constructionCostIndex): RedirectResponse
    {
        try {
            $constructionCostIndex->delete();
        } catch (QueryException) {
            return redirect()
                ->route('admin.ref-guidelines.construction-cost-indices.index')
                ->with('error', 'IKK tidak bisa dihapus karena masih dipakai data appraisal atau reviewer.');
        }

        return redirect()
            ->route('admin.ref-guidelines.construction-cost-indices.index')
            ->with('success', 'IKK berhasil dihapus.');
    }

    public function costElementsIndex(Request $request): Response
    {
        $filters = [
            'q' => trim((string) $request->query('q', '')),
            'guideline_set_id' => (string) $request->query('guideline_set_id', 'all'),
            'year' => (string) $request->query('year', 'all'),
            'base_region' => (string) $request->query('base_region', 'all'),
            'group' => (string) $request->query('group', 'all'),
            'per_page' => (string) $this->adminPerPage($request),
        ];

        $records = CostElement::query()
            ->with('guidelineSet:id,name,year,is_active')
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
            )
            ->orderByDesc('year')
            ->orderBy('group')
            ->orderBy('element_code')
            ->paginate($this->adminPerPage($request))
            ->withQueryString();

        $records->through(fn (CostElement $record) => $this->transformCostElementRow($record));

        return inertia('Admin/CostElements/Index', [
            'filters' => $filters,
            'guidelineSetOptions' => $this->guidelineSetOptions(includeAll: true),
            'yearOptions' => $this->costElementYearOptions(),
            'baseRegionOptions' => $this->costElementBaseRegionOptions(includeAll: true),
            'groupOptions' => $this->costElementGroupOptions(includeAll: true),
            'summary' => [
                'total' => CostElement::query()->count(),
                'guideline_sets' => CostElement::query()->distinct('guideline_set_id')->count('guideline_set_id'),
                'groups' => CostElement::query()->distinct('group')->count('group'),
                'active_guideline' => CostElement::query()
                    ->whereHas('guidelineSet', fn ($query) => $query->where('is_active', true))
                    ->count(),
            ],
            'records' => $this->paginatedRecordsPayload($records),
            'createUrl' => route('admin.ref-guidelines.cost-elements.create'),
        ]);
    }

    public function costElementsCreate(): Response
    {
        $activeGuideline = GuidelineSet::query()->where('is_active', true)->first();

        return inertia('Admin/CostElements/Form', [
            'mode' => 'create',
            'record' => [
                'guideline_set_id' => $activeGuideline?->id,
                'year' => $activeGuideline?->year ?? (int) now()->format('Y'),
                'base_region' => 'DKI Jakarta',
                'group' => '',
                'element_code' => '',
                'element_name' => '',
                'building_type' => '',
                'building_class' => '',
                'storey_pattern' => '',
                'unit' => 'm2',
                'unit_cost' => '',
                'spec_json' => '',
            ],
            'guidelineSetOptions' => $this->guidelineSetOptions(),
            'formOptions' => $this->costElementFormOptions(),
            'submitUrl' => route('admin.ref-guidelines.cost-elements.store'),
            'indexUrl' => route('admin.ref-guidelines.cost-elements.index'),
        ]);
    }

    public function costElementsStore(StoreCostElementRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        CostElement::query()->create([
            'guideline_set_id' => $validated['guideline_set_id'],
            'year' => $validated['year'],
            'base_region' => $validated['base_region'],
            'group' => $validated['group'],
            'element_code' => $validated['element_code'],
            'element_name' => $validated['element_name'],
            'building_type' => $validated['building_type'] ?? null,
            'building_class' => $validated['building_class'] ?? null,
            'storey_pattern' => $validated['storey_pattern'] ?? null,
            'unit' => $validated['unit'],
            'unit_cost' => $validated['unit_cost'],
            'spec_json' => $validated['spec_json'] ?? null,
        ]);

        return redirect()
            ->route('admin.ref-guidelines.cost-elements.index')
            ->with('success', 'Cost element berhasil ditambahkan.');
    }

    public function costElementsEdit(CostElement $costElement): Response
    {
        return inertia('Admin/CostElements/Form', [
            'mode' => 'edit',
            'record' => [
                'id' => $costElement->id,
                'guideline_set_id' => $costElement->guideline_set_id,
                'year' => (int) $costElement->year,
                'base_region' => $costElement->base_region,
                'group' => $costElement->group,
                'element_code' => $costElement->element_code,
                'element_name' => $costElement->element_name,
                'building_type' => $costElement->building_type,
                'building_class' => $costElement->building_class,
                'storey_pattern' => $costElement->storey_pattern,
                'unit' => $costElement->unit,
                'unit_cost' => (int) $costElement->unit_cost,
                'spec_json' => $costElement->spec_json
                    ? json_encode($costElement->spec_json, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
                    : '',
            ],
            'guidelineSetOptions' => $this->guidelineSetOptions(),
            'formOptions' => $this->costElementFormOptions(),
            'submitUrl' => route('admin.ref-guidelines.cost-elements.update', $costElement),
            'indexUrl' => route('admin.ref-guidelines.cost-elements.index'),
        ]);
    }

    public function costElementsUpdate(StoreCostElementRequest $request, CostElement $costElement): RedirectResponse
    {
        $validated = $request->validated();

        $costElement->forceFill([
            'guideline_set_id' => $validated['guideline_set_id'],
            'year' => $validated['year'],
            'base_region' => $validated['base_region'],
            'group' => $validated['group'],
            'element_code' => $validated['element_code'],
            'element_name' => $validated['element_name'],
            'building_type' => $validated['building_type'] ?? null,
            'building_class' => $validated['building_class'] ?? null,
            'storey_pattern' => $validated['storey_pattern'] ?? null,
            'unit' => $validated['unit'],
            'unit_cost' => $validated['unit_cost'],
            'spec_json' => $validated['spec_json'] ?? null,
        ])->save();

        return redirect()
            ->route('admin.ref-guidelines.cost-elements.index')
            ->with('success', 'Cost element berhasil diperbarui.');
    }

    public function costElementsDestroy(CostElement $costElement): RedirectResponse
    {
        try {
            $costElement->delete();
        } catch (QueryException) {
            return redirect()
                ->route('admin.ref-guidelines.cost-elements.index')
                ->with('error', 'Cost element tidak bisa dihapus karena masih dipakai perhitungan reviewer.');
        }

        return redirect()
            ->route('admin.ref-guidelines.cost-elements.index')
            ->with('success', 'Cost element berhasil dihapus.');
    }

    public function floorIndicesIndex(Request $request): Response
    {
        $filters = [
            'q' => trim((string) $request->query('q', '')),
            'guideline_set_id' => (string) $request->query('guideline_set_id', 'all'),
            'year' => (string) $request->query('year', 'all'),
            'building_class' => (string) $request->query('building_class', 'all'),
            'per_page' => (string) $this->adminPerPage($request),
        ];

        $records = FloorIndex::query()
            ->with('guidelineSet:id,name,year,is_active')
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
            )
            ->orderByDesc('year')
            ->orderBy('building_class')
            ->orderBy('floor_count')
            ->paginate($this->adminPerPage($request))
            ->withQueryString();

        $records->through(fn (FloorIndex $record) => $this->transformFloorIndexRow($record));

        return inertia('Admin/FloorIndices/Index', [
            'filters' => $filters,
            'guidelineSetOptions' => $this->guidelineSetOptions(includeAll: true),
            'yearOptions' => $this->floorIndexYearOptions(includeAll: true),
            'buildingClassOptions' => $this->floorIndexBuildingClassOptions(includeAll: true),
            'summary' => [
                'total' => FloorIndex::query()->count(),
                'guideline_sets' => FloorIndex::query()->distinct('guideline_set_id')->count('guideline_set_id'),
                'classes' => FloorIndex::query()->distinct('building_class')->count('building_class'),
                'active_guideline' => FloorIndex::query()
                    ->whereHas('guidelineSet', fn ($query) => $query->where('is_active', true))
                    ->count(),
            ],
            'records' => $this->paginatedRecordsPayload($records),
            'createUrl' => route('admin.ref-guidelines.floor-indices.create'),
        ]);
    }

    public function floorIndicesCreate(): Response
    {
        $activeGuideline = GuidelineSet::query()->where('is_active', true)->first();

        return inertia('Admin/FloorIndices/Form', [
            'mode' => 'create',
            'record' => [
                'guideline_set_id' => $activeGuideline?->id,
                'year' => $activeGuideline?->year ?? (int) now()->format('Y'),
                'building_class' => 'DEFAULT',
                'floor_count' => '',
                'il_value' => '',
            ],
            'guidelineSetOptions' => $this->guidelineSetOptions(),
            'buildingClassOptions' => $this->floorIndexBuildingClassOptions(),
            'submitUrl' => route('admin.ref-guidelines.floor-indices.store'),
            'indexUrl' => route('admin.ref-guidelines.floor-indices.index'),
        ]);
    }

    public function floorIndicesStore(StoreFloorIndexRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        FloorIndex::query()->create([
            'guideline_set_id' => $validated['guideline_set_id'],
            'year' => $validated['year'],
            'building_class' => $validated['building_class'],
            'floor_count' => $validated['floor_count'],
            'il_value' => $validated['il_value'],
        ]);

        return redirect()
            ->route('admin.ref-guidelines.floor-indices.index')
            ->with('success', 'Floor index berhasil ditambahkan.');
    }

    public function floorIndicesEdit(FloorIndex $floorIndex): Response
    {
        return inertia('Admin/FloorIndices/Form', [
            'mode' => 'edit',
            'record' => [
                'id' => $floorIndex->id,
                'guideline_set_id' => $floorIndex->guideline_set_id,
                'year' => (int) $floorIndex->year,
                'building_class' => $floorIndex->building_class,
                'floor_count' => (int) $floorIndex->floor_count,
                'il_value' => (float) $floorIndex->il_value,
            ],
            'guidelineSetOptions' => $this->guidelineSetOptions(),
            'buildingClassOptions' => $this->floorIndexBuildingClassOptions(),
            'submitUrl' => route('admin.ref-guidelines.floor-indices.update', $floorIndex),
            'indexUrl' => route('admin.ref-guidelines.floor-indices.index'),
        ]);
    }

    public function floorIndicesUpdate(StoreFloorIndexRequest $request, FloorIndex $floorIndex): RedirectResponse
    {
        $validated = $request->validated();

        $floorIndex->forceFill([
            'guideline_set_id' => $validated['guideline_set_id'],
            'year' => $validated['year'],
            'building_class' => $validated['building_class'],
            'floor_count' => $validated['floor_count'],
            'il_value' => $validated['il_value'],
        ])->save();

        return redirect()
            ->route('admin.ref-guidelines.floor-indices.index')
            ->with('success', 'Floor index berhasil diperbarui.');
    }

    public function floorIndicesDestroy(FloorIndex $floorIndex): RedirectResponse
    {
        try {
            $floorIndex->delete();
        } catch (QueryException) {
            return redirect()
                ->route('admin.ref-guidelines.floor-indices.index')
                ->with('error', 'Floor index tidak bisa dihapus karena masih dipakai perhitungan reviewer.');
        }

        return redirect()
            ->route('admin.ref-guidelines.floor-indices.index')
            ->with('success', 'Floor index berhasil dihapus.');
    }

    public function mappiRcnStandardsIndex(Request $request): Response
    {
        $filters = [
            'q' => trim((string) $request->query('q', '')),
            'guideline_set_id' => (string) $request->query('guideline_set_id', 'all'),
            'year' => (string) $request->query('year', 'all'),
            'building_type' => (string) $request->query('building_type', 'all'),
            'building_class' => (string) $request->query('building_class', 'all'),
            'per_page' => (string) $this->adminPerPage($request),
        ];

        $records = MappiRcnStandard::query()
            ->with('guidelineSet:id,name,year,is_active')
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
            )
            ->orderByDesc('year')
            ->orderBy('building_type')
            ->orderBy('building_class')
            ->paginate($this->adminPerPage($request))
            ->withQueryString();

        $records->through(fn (MappiRcnStandard $record) => $this->transformMappiRcnStandardRow($record));

        return inertia('Admin/MappiRcnStandards/Index', [
            'filters' => $filters,
            'guidelineSetOptions' => $this->guidelineSetOptions(includeAll: true),
            'yearOptions' => $this->mappiRcnYearOptions(includeAll: true),
            'buildingTypeOptions' => $this->mappiRcnBuildingTypeOptions(includeAll: true),
            'buildingClassOptions' => $this->mappiRcnBuildingClassOptions(includeAll: true),
            'summary' => [
                'total' => MappiRcnStandard::query()->count(),
                'guideline_sets' => MappiRcnStandard::query()->distinct('guideline_set_id')->count('guideline_set_id'),
                'building_types' => MappiRcnStandard::query()->distinct('building_type')->count('building_type'),
                'active_guideline' => MappiRcnStandard::query()
                    ->whereHas('guidelineSet', fn ($query) => $query->where('is_active', true))
                    ->count(),
            ],
            'records' => $this->paginatedRecordsPayload($records),
            'createUrl' => route('admin.ref-guidelines.mappi-rcn-standards.create'),
        ]);
    }

    public function mappiRcnStandardsCreate(): Response
    {
        $activeGuideline = GuidelineSet::query()->where('is_active', true)->first();

        return inertia('Admin/MappiRcnStandards/Form', [
            'mode' => 'create',
            'record' => [
                'guideline_set_id' => $activeGuideline?->id,
                'year' => $activeGuideline?->year ?? (int) now()->format('Y'),
                'reference_region' => 'DKI Jakarta',
                'building_type' => '',
                'building_class' => '',
                'storey_pattern' => '',
                'rcn_value' => '',
                'notes' => '',
            ],
            'guidelineSetOptions' => $this->guidelineSetOptions(),
            'formOptions' => $this->mappiRcnFormOptions(),
            'submitUrl' => route('admin.ref-guidelines.mappi-rcn-standards.store'),
            'indexUrl' => route('admin.ref-guidelines.mappi-rcn-standards.index'),
        ]);
    }

    public function mappiRcnStandardsStore(StoreMappiRcnStandardRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        MappiRcnStandard::query()->create([
            'guideline_set_id' => $validated['guideline_set_id'],
            'year' => $validated['year'],
            'reference_region' => $validated['reference_region'],
            'building_type' => $validated['building_type'],
            'building_class' => $validated['building_class'] ?? null,
            'storey_pattern' => $validated['storey_pattern'] ?? null,
            'rcn_value' => $validated['rcn_value'],
            'notes' => $validated['notes'] ?? null,
        ]);

        return redirect()
            ->route('admin.ref-guidelines.mappi-rcn-standards.index')
            ->with('success', 'MAPPI RCN berhasil ditambahkan.');
    }

    public function mappiRcnStandardsEdit(MappiRcnStandard $mappiRcnStandard): Response
    {
        return inertia('Admin/MappiRcnStandards/Form', [
            'mode' => 'edit',
            'record' => [
                'id' => $mappiRcnStandard->id,
                'guideline_set_id' => $mappiRcnStandard->guideline_set_id,
                'year' => (int) $mappiRcnStandard->year,
                'reference_region' => $mappiRcnStandard->reference_region,
                'building_type' => $mappiRcnStandard->building_type,
                'building_class' => $mappiRcnStandard->building_class,
                'storey_pattern' => $mappiRcnStandard->storey_pattern,
                'rcn_value' => (int) $mappiRcnStandard->rcn_value,
                'notes' => $mappiRcnStandard->notes,
            ],
            'guidelineSetOptions' => $this->guidelineSetOptions(),
            'formOptions' => $this->mappiRcnFormOptions(),
            'submitUrl' => route('admin.ref-guidelines.mappi-rcn-standards.update', $mappiRcnStandard),
            'indexUrl' => route('admin.ref-guidelines.mappi-rcn-standards.index'),
        ]);
    }

    public function mappiRcnStandardsUpdate(StoreMappiRcnStandardRequest $request, MappiRcnStandard $mappiRcnStandard): RedirectResponse
    {
        $validated = $request->validated();

        $mappiRcnStandard->forceFill([
            'guideline_set_id' => $validated['guideline_set_id'],
            'year' => $validated['year'],
            'reference_region' => $validated['reference_region'],
            'building_type' => $validated['building_type'],
            'building_class' => $validated['building_class'] ?? null,
            'storey_pattern' => $validated['storey_pattern'] ?? null,
            'rcn_value' => $validated['rcn_value'],
            'notes' => $validated['notes'] ?? null,
        ])->save();

        return redirect()
            ->route('admin.ref-guidelines.mappi-rcn-standards.index')
            ->with('success', 'MAPPI RCN berhasil diperbarui.');
    }

    public function mappiRcnStandardsDestroy(MappiRcnStandard $mappiRcnStandard): RedirectResponse
    {
        try {
            $mappiRcnStandard->delete();
        } catch (QueryException) {
            return redirect()
                ->route('admin.ref-guidelines.mappi-rcn-standards.index')
                ->with('error', 'MAPPI RCN tidak bisa dihapus karena masih dipakai perhitungan reviewer.');
        }

        return redirect()
            ->route('admin.ref-guidelines.mappi-rcn-standards.index')
            ->with('success', 'MAPPI RCN berhasil dihapus.');
    }

    private function transformConstructionCostIndexRow(ConstructionCostIndex $constructionCostIndex): array
    {
        $constructionCostIndex->loadMissing([
            'guidelineSet:id,name,is_active',
            'regency:id,name,province_id',
            'regency.province:id,name',
        ]);

        return [
            'id' => $constructionCostIndex->id,
            'guideline_set_name' => $constructionCostIndex->guidelineSet?->name ?? '-',
            'guideline_is_active' => (bool) ($constructionCostIndex->guidelineSet?->is_active ?? false),
            'year' => (int) $constructionCostIndex->year,
            'province_name' => $constructionCostIndex->regency?->province?->name ?? '-',
            'region_code' => (string) $constructionCostIndex->region_code,
            'region_name' => $constructionCostIndex->region_name,
            'ikk_value' => (float) $constructionCostIndex->ikk_value,
            'updated_at' => $constructionCostIndex->updated_at?->toIso8601String(),
            'edit_url' => route('admin.ref-guidelines.construction-cost-indices.edit', $constructionCostIndex),
            'destroy_url' => route('admin.ref-guidelines.construction-cost-indices.destroy', $constructionCostIndex),
        ];
    }

    private function transformCostElementRow(CostElement $costElement): array
    {
        $costElement->loadMissing('guidelineSet:id,name,is_active');

        return [
            'id' => $costElement->id,
            'guideline_set_name' => $costElement->guidelineSet?->name ?? '-',
            'guideline_is_active' => (bool) ($costElement->guidelineSet?->is_active ?? false),
            'year' => (int) $costElement->year,
            'base_region' => $costElement->base_region,
            'group' => $costElement->group,
            'element_code' => $costElement->element_code,
            'element_name' => $costElement->element_name,
            'building_type' => $costElement->building_type,
            'building_class' => $costElement->building_class,
            'storey_pattern' => $costElement->storey_pattern,
            'unit' => $costElement->unit,
            'unit_cost' => (int) $costElement->unit_cost,
            'spec_json' => $costElement->spec_json,
            'updated_at' => $costElement->updated_at?->toIso8601String(),
            'edit_url' => route('admin.ref-guidelines.cost-elements.edit', $costElement),
            'destroy_url' => route('admin.ref-guidelines.cost-elements.destroy', $costElement),
        ];
    }

    private function transformFloorIndexRow(FloorIndex $floorIndex): array
    {
        $floorIndex->loadMissing('guidelineSet:id,name,is_active');

        return [
            'id' => $floorIndex->id,
            'guideline_set_name' => $floorIndex->guidelineSet?->name ?? '-',
            'guideline_is_active' => (bool) ($floorIndex->guidelineSet?->is_active ?? false),
            'year' => (int) $floorIndex->year,
            'building_class' => $floorIndex->building_class,
            'floor_count' => (int) $floorIndex->floor_count,
            'il_value' => (float) $floorIndex->il_value,
            'updated_at' => $floorIndex->updated_at?->toIso8601String(),
            'edit_url' => route('admin.ref-guidelines.floor-indices.edit', $floorIndex),
            'destroy_url' => route('admin.ref-guidelines.floor-indices.destroy', $floorIndex),
        ];
    }

    private function transformMappiRcnStandardRow(MappiRcnStandard $mappiRcnStandard): array
    {
        $mappiRcnStandard->loadMissing('guidelineSet:id,name,is_active');

        return [
            'id' => $mappiRcnStandard->id,
            'guideline_set_name' => $mappiRcnStandard->guidelineSet?->name ?? '-',
            'guideline_is_active' => (bool) ($mappiRcnStandard->guidelineSet?->is_active ?? false),
            'year' => (int) $mappiRcnStandard->year,
            'reference_region' => $mappiRcnStandard->reference_region,
            'building_type' => $mappiRcnStandard->building_type,
            'building_class' => $mappiRcnStandard->building_class,
            'storey_pattern' => $mappiRcnStandard->storey_pattern,
            'rcn_value' => (int) $mappiRcnStandard->rcn_value,
            'notes' => $mappiRcnStandard->notes,
            'updated_at' => $mappiRcnStandard->updated_at?->toIso8601String(),
            'edit_url' => route('admin.ref-guidelines.mappi-rcn-standards.edit', $mappiRcnStandard),
            'destroy_url' => route('admin.ref-guidelines.mappi-rcn-standards.destroy', $mappiRcnStandard),
        ];
    }

    private function guidelineSetOptions(bool $includeAll = false): array
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

    private function constructionCostIndexYearOptions(): array
    {
        return ConstructionCostIndex::query()
            ->distinct()
            ->orderByDesc('year')
            ->pluck('year')
            ->map(fn ($year) => [
                'value' => (string) $year,
                'label' => (string) $year,
            ])
            ->values()
            ->all();
    }

    private function costElementYearOptions(): array
    {
        return CostElement::query()
            ->distinct()
            ->orderByDesc('year')
            ->pluck('year')
            ->map(fn ($year) => [
                'value' => (string) $year,
                'label' => (string) $year,
            ])
            ->values()
            ->all();
    }

    private function floorIndexYearOptions(bool $includeAll = false): array
    {
        $options = FloorIndex::query()
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

    private function floorIndexBuildingClassOptions(bool $includeAll = false): array
    {
        $options = FloorIndex::query()
            ->whereNotNull('building_class')
            ->where('building_class', '<>', '')
            ->distinct()
            ->orderBy('building_class')
            ->pluck('building_class')
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
            ['value' => 'all', 'label' => 'Semua Class'],
            ...$options,
        ];
    }

    private function mappiRcnYearOptions(bool $includeAll = false): array
    {
        $options = MappiRcnStandard::query()
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

    private function mappiRcnBuildingTypeOptions(bool $includeAll = false): array
    {
        $options = MappiRcnStandard::query()
            ->whereNotNull('building_type')
            ->where('building_type', '<>', '')
            ->distinct()
            ->orderBy('building_type')
            ->pluck('building_type')
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
            ['value' => 'all', 'label' => 'Semua Building Type'],
            ...$options,
        ];
    }

    private function mappiRcnBuildingClassOptions(bool $includeAll = false): array
    {
        $options = MappiRcnStandard::query()
            ->whereNotNull('building_class')
            ->where('building_class', '<>', '')
            ->distinct()
            ->orderBy('building_class')
            ->pluck('building_class')
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
            ['value' => 'all', 'label' => 'Semua Building Class'],
            ...$options,
        ];
    }

    private function mappiRcnFormOptions(): array
    {
        return [
            'building_types' => MappiRcnStandard::query()
                ->whereNotNull('building_type')
                ->where('building_type', '<>', '')
                ->distinct()
                ->orderBy('building_type')
                ->pluck('building_type')
                ->values()
                ->all(),
            'building_classes' => MappiRcnStandard::query()
                ->whereNotNull('building_class')
                ->where('building_class', '<>', '')
                ->distinct()
                ->orderBy('building_class')
                ->pluck('building_class')
                ->values()
                ->all(),
            'storey_patterns' => MappiRcnStandard::query()
                ->whereNotNull('storey_pattern')
                ->where('storey_pattern', '<>', '')
                ->distinct()
                ->orderBy('storey_pattern')
                ->pluck('storey_pattern')
                ->values()
                ->all(),
        ];
    }

    private function costElementBaseRegionOptions(bool $includeAll = false): array
    {
        $options = CostElement::query()
            ->whereNotNull('base_region')
            ->where('base_region', '<>', '')
            ->distinct()
            ->orderBy('base_region')
            ->pluck('base_region')
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
            ['value' => 'all', 'label' => 'Semua Base Region'],
            ...$options,
        ];
    }

    private function costElementGroupOptions(bool $includeAll = false): array
    {
        $options = CostElement::query()
            ->whereNotNull('group')
            ->where('group', '<>', '')
            ->distinct()
            ->orderBy('group')
            ->pluck('group')
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
            ['value' => 'all', 'label' => 'Semua Group'],
            ...$options,
        ];
    }

    private function costElementFormOptions(): array
    {
        return [
            'groups' => CostElement::query()
                ->whereNotNull('group')
                ->where('group', '<>', '')
                ->distinct()
                ->orderBy('group')
                ->limit(300)
                ->pluck('group')
                ->values()
                ->all(),
            'element_codes' => CostElement::query()
                ->whereNotNull('element_code')
                ->where('element_code', '<>', '')
                ->distinct()
                ->orderBy('element_code')
                ->limit(500)
                ->pluck('element_code')
                ->values()
                ->all(),
            'element_names' => CostElement::query()
                ->whereNotNull('element_name')
                ->where('element_name', '<>', '')
                ->distinct()
                ->orderBy('element_name')
                ->limit(500)
                ->pluck('element_name')
                ->values()
                ->all(),
            'building_types' => CostElement::query()
                ->whereNotNull('building_type')
                ->where('building_type', '<>', '')
                ->distinct()
                ->orderBy('building_type')
                ->limit(200)
                ->pluck('building_type')
                ->values()
                ->all(),
            'building_classes' => CostElement::query()
                ->whereNotNull('building_class')
                ->where('building_class', '<>', '')
                ->distinct()
                ->orderBy('building_class')
                ->limit(200)
                ->pluck('building_class')
                ->values()
                ->all(),
            'storey_patterns' => CostElement::query()
                ->whereNotNull('storey_pattern')
                ->where('storey_pattern', '<>', '')
                ->distinct()
                ->orderBy('storey_pattern')
                ->limit(200)
                ->pluck('storey_pattern')
                ->values()
                ->all(),
        ];
    }

    protected function paginatedRecordsPayload(object $records): array
    {
        return [
            'data' => $records->items(),
            'meta' => [
                'from' => $records->firstItem(),
                'to' => $records->lastItem(),
                'total' => $records->total(),
                'current_page' => $records->currentPage(),
                'last_page' => $records->lastPage(),
                'per_page' => $records->perPage(),
                'links' => $records->linkCollection()->toArray(),
            ],
        ];
    }

    private function provinceSelectOptions(): array
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

    private function provinceFilterOptions(bool $includeAll = false): array
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

    private function regencySelectOptionsByProvince(?string $provinceId): array
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

    private function legacyConstructionCostIndexUrl(ConstructionCostIndex $constructionCostIndex): ?string
    {
        return null;
    }

    private function legacyCostElementUrl(CostElement $costElement): ?string
    {
        return null;
    }

    private function legacyFloorIndexUrl(FloorIndex $floorIndex): ?string
    {
        return null;
    }

    private function legacyMappiRcnStandardUrl(MappiRcnStandard $mappiRcnStandard): ?string
    {
        return null;
    }
}

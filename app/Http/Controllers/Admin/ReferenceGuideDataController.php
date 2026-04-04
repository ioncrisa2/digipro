<?php

namespace App\Http\Controllers\Admin;

use App\Exports\CostElementExport;
use App\Exports\FloorIndexExport;
use App\Exports\IkkExport;
use App\Exports\MappiRcnStandardExport;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ImportConstructionCostIndicesRequest;
use App\Http\Requests\Admin\ImportCostElementRequest;
use App\Http\Requests\Admin\ImportFloorIndexRequest;
use App\Http\Requests\Admin\ImportMappiRcnStandardRequest;
use App\Http\Requests\Admin\ReferenceGuideDataIndexRequest;
use App\Http\Requests\Admin\StoreConstructionCostIndexRequest;
use App\Http\Requests\Admin\StoreCostElementRequest;
use App\Http\Requests\Admin\StoreFloorIndexRequest;
use App\Http\Requests\Admin\StoreMappiRcnStandardRequest;
use App\Imports\CostElementImport;
use App\Imports\FloorIndexImport;
use App\Imports\IKKImport;
use App\Imports\MappiRcnStandardImport;
use App\Models\ConstructionCostIndex;
use App\Models\CostElement;
use App\Models\FloorIndex;
use App\Models\GuidelineSet;
use App\Models\MappiRcnStandard;
use App\Models\Province;
use App\Models\Regency;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Inertia\Response;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Throwable;

class ReferenceGuideDataController extends Controller
{
    public function constructionCostIndicesIndex(ReferenceGuideDataIndexRequest $request): Response
    {
        $activeGuideline = GuidelineSet::query()->where('is_active', true)->first();
        $filters = $request->filters(['q', 'guideline_set_id', 'year', 'province_id']);

        $records = $this->constructionCostIndicesFilteredQuery($filters)
            ->with([
                'guidelineSet:id,name,year,is_active',
                'regency:id,name,province_id',
                'regency.province:id,name',
            ])
            ->orderByDesc('year')
            ->orderBy('region_code')
            ->paginate($request->perPage())
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
            'indexUrl' => $this->workspaceRoute('ref-guidelines.construction-cost-indices.index'),
            'createUrl' => $this->workspaceRoute('ref-guidelines.construction-cost-indices.create'),
            'ikkByProvinceUrl' => $this->workspaceRoute('ref-guidelines.ikk-by-province.index'),
            'importUrl' => $this->workspaceRoute('ref-guidelines.construction-cost-indices.import'),
            'exportUrl' => $this->workspaceRoute('ref-guidelines.construction-cost-indices.export', array_filter([
                'q' => $filters['q'] !== '' ? $filters['q'] : null,
                'guideline_set_id' => $filters['guideline_set_id'] !== 'all' ? $filters['guideline_set_id'] : null,
                'year' => $filters['year'] !== 'all' ? $filters['year'] : null,
                'province_id' => $filters['province_id'] !== 'all' ? $filters['province_id'] : null,
            ])),
            'importDefaults' => [
                'guideline_set_id' => $filters['guideline_set_id'] !== 'all'
                    ? (int) $filters['guideline_set_id']
                    : $activeGuideline?->id,
                'year' => $filters['year'] !== 'all'
                    ? (int) $filters['year']
                    : ($activeGuideline?->year ?? (int) now()->format('Y')),
                'skip_province_rows' => true,
                'require_regency' => true,
            ],
        ]);
    }

    public function constructionCostIndicesExport(ReferenceGuideDataIndexRequest $request): BinaryFileResponse
    {
        $filters = $request->filters(['q', 'guideline_set_id', 'year', 'province_id'], false);

        $query = $this->constructionCostIndicesFilteredQuery($filters)->orderByDesc('year')->orderBy('region_code');

        return Excel::download(
            new IkkExport($query),
            'ikk-' . now()->format('Ymd-His') . '.xlsx'
        );
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
            'submitUrl' => $this->workspaceRoute('ref-guidelines.construction-cost-indices.store'),
            'indexUrl' => $this->workspaceRoute('ref-guidelines.construction-cost-indices.index'),
            'ikkByProvinceUrl' => $this->workspaceRoute('ref-guidelines.ikk-by-province.index'),
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
            ->route($this->workspaceRouteName('ref-guidelines.construction-cost-indices.index'))
            ->with('success', 'IKK berhasil ditambahkan.');
    }

    public function constructionCostIndicesImport(ImportConstructionCostIndicesRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        $import = new IKKImport(
            guidelineSetId: (int) $validated['guideline_set_id'],
            year: (int) $validated['year'],
            skipProvinceRows: $request->boolean('skip_province_rows', true),
            requireRegency: $request->boolean('require_regency', true),
        );

        try {
            Excel::import($import, $validated['file']);
        } catch (Throwable $e) {
            return redirect()
                ->route($this->workspaceRouteName('ref-guidelines.construction-cost-indices.index'))
                ->with('error', 'Import IKK gagal diproses. Pastikan format header Excel sesuai template: kode, nama_provinsi_kota_kabupaten, ikk_mappi.');
        }

        return redirect()
            ->route($this->workspaceRouteName('ref-guidelines.construction-cost-indices.index'), [
                'guideline_set_id' => $validated['guideline_set_id'],
                'year' => $validated['year'],
            ])
            ->with(
                'success',
                "Import IKK selesai. {$import->inserted} row baru, {$import->updated} row diperbarui, {$import->skipped} row dilewati."
            );
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
            'submitUrl' => $this->workspaceRoute('ref-guidelines.construction-cost-indices.update', $constructionCostIndex),
            'indexUrl' => $this->workspaceRoute('ref-guidelines.construction-cost-indices.index'),
            'ikkByProvinceUrl' => $this->workspaceRoute('ref-guidelines.ikk-by-province.index'),
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
            ->route($this->workspaceRouteName('ref-guidelines.construction-cost-indices.index'))
            ->with('success', 'IKK berhasil diperbarui.');
    }

    public function constructionCostIndicesDestroy(ConstructionCostIndex $constructionCostIndex): RedirectResponse
    {
        try {
            $constructionCostIndex->delete();
        } catch (QueryException) {
            return redirect()
                ->route($this->workspaceRouteName('ref-guidelines.construction-cost-indices.index'))
                ->with('error', 'IKK tidak bisa dihapus karena masih dipakai data appraisal atau reviewer.');
        }

        return redirect()
            ->route($this->workspaceRouteName('ref-guidelines.construction-cost-indices.index'))
            ->with('success', 'IKK berhasil dihapus.');
    }

    public function costElementsIndex(ReferenceGuideDataIndexRequest $request): Response
    {
        $activeGuideline = GuidelineSet::query()->where('is_active', true)->first();
        $filters = $request->filters(['q', 'guideline_set_id', 'year', 'base_region', 'group']);

        $records = $this->costElementsFilteredQuery($filters)
            ->with('guidelineSet:id,name,year,is_active')
            ->orderByDesc('year')
            ->orderBy('group')
            ->orderBy('element_code')
            ->paginate($request->perPage())
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
            'indexUrl' => $this->workspaceRoute('ref-guidelines.cost-elements.index'),
            'createUrl' => $this->workspaceRoute('ref-guidelines.cost-elements.create'),
            'importUrl' => $this->workspaceRoute('ref-guidelines.cost-elements.import'),
            'exportUrl' => $this->workspaceRoute('ref-guidelines.cost-elements.export', array_filter([
                'q' => $filters['q'] !== '' ? $filters['q'] : null,
                'guideline_set_id' => $filters['guideline_set_id'] !== 'all' ? $filters['guideline_set_id'] : null,
                'year' => $filters['year'] !== 'all' ? $filters['year'] : null,
                'base_region' => $filters['base_region'] !== 'all' ? $filters['base_region'] : null,
                'group' => $filters['group'] !== 'all' ? $filters['group'] : null,
            ])),
            'importDefaults' => [
                'guideline_set_id' => $filters['guideline_set_id'] !== 'all'
                    ? (int) $filters['guideline_set_id']
                    : $activeGuideline?->id,
                'year' => $filters['year'] !== 'all'
                    ? (int) $filters['year']
                    : ($activeGuideline?->year ?? (int) now()->format('Y')),
                'base_region' => $filters['base_region'] !== 'all'
                    ? $filters['base_region']
                    : 'DKI Jakarta',
            ],
        ]);
    }

    public function costElementsExport(ReferenceGuideDataIndexRequest $request): BinaryFileResponse
    {
        $filters = $request->filters(['q', 'guideline_set_id', 'year', 'base_region', 'group'], false);

        $query = $this->costElementsFilteredQuery($filters)
            ->orderByDesc('year')
            ->orderBy('group')
            ->orderBy('element_code');

        return Excel::download(
            new CostElementExport($query),
            'cost-elements-' . now()->format('Ymd-His') . '.xlsx'
        );
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
            'submitUrl' => $this->workspaceRoute('ref-guidelines.cost-elements.store'),
            'indexUrl' => $this->workspaceRoute('ref-guidelines.cost-elements.index'),
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
            ->route($this->workspaceRouteName('ref-guidelines.cost-elements.index'))
            ->with('success', 'Cost element berhasil ditambahkan.');
    }

    public function costElementsImport(ImportCostElementRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        $import = new CostElementImport(
            guidelineSetId: (int) $validated['guideline_set_id'],
            year: (int) $validated['year'],
            baseRegion: trim((string) $validated['base_region']),
        );

        try {
            Excel::import($import, $validated['file']);
        } catch (Throwable) {
            return redirect()
                ->route($this->workspaceRouteName('ref-guidelines.cost-elements.index'))
                ->with('error', 'Import biaya unit terpasang gagal diproses. Pastikan header Excel sesuai template: group, element_code, element_name, building_type, building_class, storey_pattern, unit, unit_cost, spec_json.');
        }

        return redirect()
            ->route($this->workspaceRouteName('ref-guidelines.cost-elements.index'), [
                'guideline_set_id' => $validated['guideline_set_id'],
                'year' => $validated['year'],
                'base_region' => $validated['base_region'],
            ])
            ->with(
                'success',
                "Import biaya unit terpasang selesai. {$import->inserted} row baru, {$import->updated} row diperbarui, {$import->skipped} row dilewati."
            );
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
            'submitUrl' => $this->workspaceRoute('ref-guidelines.cost-elements.update', $costElement),
            'indexUrl' => $this->workspaceRoute('ref-guidelines.cost-elements.index'),
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
            ->route($this->workspaceRouteName('ref-guidelines.cost-elements.index'))
            ->with('success', 'Cost element berhasil diperbarui.');
    }

    public function costElementsDestroy(CostElement $costElement): RedirectResponse
    {
        try {
            $costElement->delete();
        } catch (QueryException) {
            return redirect()
                ->route($this->workspaceRouteName('ref-guidelines.cost-elements.index'))
                ->with('error', 'Cost element tidak bisa dihapus karena masih dipakai perhitungan reviewer.');
        }

        return redirect()
            ->route($this->workspaceRouteName('ref-guidelines.cost-elements.index'))
            ->with('success', 'Cost element berhasil dihapus.');
    }

    public function floorIndicesIndex(ReferenceGuideDataIndexRequest $request): Response
    {
        $activeGuideline = GuidelineSet::query()->where('is_active', true)->first();
        $filters = $request->filters(['q', 'guideline_set_id', 'year', 'building_class']);

        $records = $this->floorIndicesFilteredQuery($filters)
            ->with('guidelineSet:id,name,year,is_active')
            ->orderByDesc('year')
            ->orderBy('building_class')
            ->orderBy('floor_count')
            ->paginate($request->perPage())
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
            'indexUrl' => $this->workspaceRoute('ref-guidelines.floor-indices.index'),
            'createUrl' => $this->workspaceRoute('ref-guidelines.floor-indices.create'),
            'importUrl' => $this->workspaceRoute('ref-guidelines.floor-indices.import'),
            'exportUrl' => $this->workspaceRoute('ref-guidelines.floor-indices.export', array_filter([
                'q' => $filters['q'] !== '' ? $filters['q'] : null,
                'guideline_set_id' => $filters['guideline_set_id'] !== 'all' ? $filters['guideline_set_id'] : null,
                'year' => $filters['year'] !== 'all' ? $filters['year'] : null,
                'building_class' => $filters['building_class'] !== 'all' ? $filters['building_class'] : null,
            ])),
            'importDefaults' => [
                'guideline_set_id' => $filters['guideline_set_id'] !== 'all'
                    ? (int) $filters['guideline_set_id']
                    : $activeGuideline?->id,
                'year' => $filters['year'] !== 'all'
                    ? (int) $filters['year']
                    : ($activeGuideline?->year ?? (int) now()->format('Y')),
            ],
        ]);
    }

    public function floorIndicesExport(ReferenceGuideDataIndexRequest $request): BinaryFileResponse
    {
        $filters = $request->filters(['q', 'guideline_set_id', 'year', 'building_class'], false);

        $query = $this->floorIndicesFilteredQuery($filters)
            ->orderByDesc('year')
            ->orderBy('building_class')
            ->orderBy('floor_count');

        return Excel::download(
            new FloorIndexExport($query),
            'floor-indices-' . now()->format('Ymd-His') . '.xlsx'
        );
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
            'submitUrl' => $this->workspaceRoute('ref-guidelines.floor-indices.store'),
            'indexUrl' => $this->workspaceRoute('ref-guidelines.floor-indices.index'),
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
            ->route($this->workspaceRouteName('ref-guidelines.floor-indices.index'))
            ->with('success', 'Floor index berhasil ditambahkan.');
    }

    public function floorIndicesImport(ImportFloorIndexRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        $import = new FloorIndexImport(
            guidelineSetId: (int) $validated['guideline_set_id'],
            year: (int) $validated['year'],
        );

        try {
            Excel::import($import, $validated['file']);
        } catch (Throwable $e) {
            return redirect()
                ->route($this->workspaceRouteName('ref-guidelines.floor-indices.index'))
                ->with('error', 'Import floor index gagal diproses. Pastikan format header Excel sesuai template: building_class, floor_count, il_value.');
        }

        return redirect()
            ->route($this->workspaceRouteName('ref-guidelines.floor-indices.index'), [
                'guideline_set_id' => $validated['guideline_set_id'],
                'year' => $validated['year'],
            ])
            ->with(
                'success',
                "Import floor index selesai. {$import->processed} row diproses, {$import->skipped} row dilewati."
            );
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
            'submitUrl' => $this->workspaceRoute('ref-guidelines.floor-indices.update', $floorIndex),
            'indexUrl' => $this->workspaceRoute('ref-guidelines.floor-indices.index'),
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
            ->route($this->workspaceRouteName('ref-guidelines.floor-indices.index'))
            ->with('success', 'Floor index berhasil diperbarui.');
    }

    public function floorIndicesDestroy(FloorIndex $floorIndex): RedirectResponse
    {
        try {
            $floorIndex->delete();
        } catch (QueryException) {
            return redirect()
                ->route($this->workspaceRouteName('ref-guidelines.floor-indices.index'))
                ->with('error', 'Floor index tidak bisa dihapus karena masih dipakai perhitungan reviewer.');
        }

        return redirect()
            ->route($this->workspaceRouteName('ref-guidelines.floor-indices.index'))
            ->with('success', 'Floor index berhasil dihapus.');
    }

    public function mappiRcnStandardsIndex(ReferenceGuideDataIndexRequest $request): Response
    {
        $activeGuideline = GuidelineSet::query()->where('is_active', true)->first();
        $filters = $request->filters(['q', 'guideline_set_id', 'year', 'building_type', 'building_class']);

        $records = $this->mappiRcnStandardsFilteredQuery($filters)
            ->with('guidelineSet:id,name,year,is_active')
            ->orderByDesc('year')
            ->orderBy('building_type')
            ->orderBy('building_class')
            ->paginate($request->perPage())
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
            'indexUrl' => $this->workspaceRoute('ref-guidelines.mappi-rcn-standards.index'),
            'createUrl' => $this->workspaceRoute('ref-guidelines.mappi-rcn-standards.create'),
            'importUrl' => $this->workspaceRoute('ref-guidelines.mappi-rcn-standards.import'),
            'exportUrl' => $this->workspaceRoute('ref-guidelines.mappi-rcn-standards.export', array_filter([
                'q' => $filters['q'] !== '' ? $filters['q'] : null,
                'guideline_set_id' => $filters['guideline_set_id'] !== 'all' ? $filters['guideline_set_id'] : null,
                'year' => $filters['year'] !== 'all' ? $filters['year'] : null,
                'building_type' => $filters['building_type'] !== 'all' ? $filters['building_type'] : null,
                'building_class' => $filters['building_class'] !== 'all' ? $filters['building_class'] : null,
            ])),
            'importDefaults' => [
                'guideline_set_id' => $filters['guideline_set_id'] !== 'all'
                    ? (int) $filters['guideline_set_id']
                    : $activeGuideline?->id,
                'year' => $filters['year'] !== 'all'
                    ? (int) $filters['year']
                    : ($activeGuideline?->year ?? (int) now()->format('Y')),
                'reference_region' => 'DKI Jakarta',
            ],
        ]);
    }

    public function mappiRcnStandardsExport(ReferenceGuideDataIndexRequest $request): BinaryFileResponse
    {
        $filters = $request->filters(['q', 'guideline_set_id', 'year', 'building_type', 'building_class'], false);

        $query = $this->mappiRcnStandardsFilteredQuery($filters)
            ->orderByDesc('year')
            ->orderBy('building_type')
            ->orderBy('building_class');

        return Excel::download(
            new MappiRcnStandardExport($query),
            'mappi-rcn-standards-' . now()->format('Ymd-His') . '.xlsx'
        );
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
            'submitUrl' => $this->workspaceRoute('ref-guidelines.mappi-rcn-standards.store'),
            'indexUrl' => $this->workspaceRoute('ref-guidelines.mappi-rcn-standards.index'),
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
            ->route($this->workspaceRouteName('ref-guidelines.mappi-rcn-standards.index'))
            ->with('success', 'MAPPI RCN berhasil ditambahkan.');
    }

    public function mappiRcnStandardsImport(ImportMappiRcnStandardRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        $import = new MappiRcnStandardImport(
            guidelineSetId: (int) $validated['guideline_set_id'],
            year: (int) $validated['year'],
            referenceRegion: trim((string) $validated['reference_region']),
        );

        try {
            Excel::import($import, $validated['file']);
        } catch (Throwable) {
            return redirect()
                ->route($this->workspaceRouteName('ref-guidelines.mappi-rcn-standards.index'))
                ->with('error', 'Import MAPPI RCN gagal diproses. Pastikan header Excel sesuai template: building_type, building_class, storey_pattern, rcn_value, notes.');
        }

        return redirect()
            ->route($this->workspaceRouteName('ref-guidelines.mappi-rcn-standards.index'), [
                'guideline_set_id' => $validated['guideline_set_id'],
                'year' => $validated['year'],
            ])
            ->with(
                'success',
                "Import MAPPI RCN selesai. {$import->inserted} row baru, {$import->updated} row diperbarui, {$import->skipped} row dilewati."
            );
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
            'submitUrl' => $this->workspaceRoute('ref-guidelines.mappi-rcn-standards.update', $mappiRcnStandard),
            'indexUrl' => $this->workspaceRoute('ref-guidelines.mappi-rcn-standards.index'),
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
            ->route($this->workspaceRouteName('ref-guidelines.mappi-rcn-standards.index'))
            ->with('success', 'MAPPI RCN berhasil diperbarui.');
    }

    public function mappiRcnStandardsDestroy(MappiRcnStandard $mappiRcnStandard): RedirectResponse
    {
        try {
            $mappiRcnStandard->delete();
        } catch (QueryException) {
            return redirect()
                ->route($this->workspaceRouteName('ref-guidelines.mappi-rcn-standards.index'))
                ->with('error', 'MAPPI RCN tidak bisa dihapus karena masih dipakai perhitungan reviewer.');
        }

        return redirect()
            ->route($this->workspaceRouteName('ref-guidelines.mappi-rcn-standards.index'))
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
            'edit_url' => $this->workspaceRoute('ref-guidelines.construction-cost-indices.edit', $constructionCostIndex),
            'destroy_url' => $this->workspaceRoute('ref-guidelines.construction-cost-indices.destroy', $constructionCostIndex),
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
            'edit_url' => $this->workspaceRoute('ref-guidelines.cost-elements.edit', $costElement),
            'destroy_url' => $this->workspaceRoute('ref-guidelines.cost-elements.destroy', $costElement),
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
            'edit_url' => $this->workspaceRoute('ref-guidelines.floor-indices.edit', $floorIndex),
            'destroy_url' => $this->workspaceRoute('ref-guidelines.floor-indices.destroy', $floorIndex),
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
            'edit_url' => $this->workspaceRoute('ref-guidelines.mappi-rcn-standards.edit', $mappiRcnStandard),
            'destroy_url' => $this->workspaceRoute('ref-guidelines.mappi-rcn-standards.destroy', $mappiRcnStandard),
        ];
    }

    private function constructionCostIndicesFilteredQuery(array $filters)
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

    private function costElementsFilteredQuery(array $filters)
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

    private function floorIndicesFilteredQuery(array $filters)
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

    private function mappiRcnStandardsFilteredQuery(array $filters)
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
}

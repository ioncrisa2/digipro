<?php

namespace App\Services\Admin;

use App\Http\Requests\Admin\ImportBuildingEconomicLifeRequest;
use App\Http\Requests\Admin\ImportConstructionCostIndicesRequest;
use App\Http\Requests\Admin\ImportCostElementRequest;
use App\Http\Requests\Admin\ImportFloorIndexRequest;
use App\Http\Requests\Admin\ImportMappiRcnStandardRequest;
use App\Imports\BuildingEconomicLifeImport;
use App\Imports\CostElementImport;
use App\Imports\FloorIndexImport;
use App\Imports\IKKImport;
use App\Imports\MappiRcnStandardImport;
use App\Models\BuildingEconomicLife;
use App\Models\ConstructionCostIndex;
use App\Models\CostElement;
use App\Models\FloorIndex;
use App\Models\GuidelineSet;
use App\Models\MappiRcnStandard;
use App\Models\Regency;
use App\Support\Admin\ReferenceGuideData\ReferenceGuideFilteredQueryFactory;
use App\Support\Admin\ReferenceGuideData\ReferenceGuideOptionsProvider;
use App\Support\Admin\ReferenceGuideData\ReferenceGuideRowPresenter;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;

class AdminReferenceGuideDataWorkspaceService
{
    public function __construct(
        private readonly ReferenceGuideFilteredQueryFactory $filteredQueries,
        private readonly ReferenceGuideOptionsProvider $options,
        private readonly ReferenceGuideRowPresenter $rows,
    ) {
    }

    public function constructionCostIndicesIndexPayload(array $filters, int $perPage, string $workspacePrefix): array
    {
        $activeGuideline = $this->activeGuideline();
        $records = $this->filteredQueries->constructionCostIndices($filters)
            ->with([
                'guidelineSet:id,name,year,is_active',
                'regency:id,name,province_id',
                'regency.province:id,name',
            ])
            ->orderByDesc('year')
            ->orderBy('region_code')
            ->paginate($perPage)
            ->withQueryString();

        $records->through(fn (ConstructionCostIndex $record) => $this->rows->constructionCostIndex(
            $record,
            $this->workspaceRoute($workspacePrefix, 'ref-guidelines.construction-cost-indices.edit', $record),
            $this->workspaceRoute($workspacePrefix, 'ref-guidelines.construction-cost-indices.destroy', $record),
        ));

        return [
            'filters' => $filters,
            'guidelineSetOptions' => $this->options->guidelineSetOptions(includeAll: true),
            'yearOptions' => $this->options->constructionCostIndexYearOptions(),
            'provinceOptions' => $this->options->provinceFilterOptions(includeAll: true),
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
            'indexUrl' => $this->workspaceRoute($workspacePrefix, 'ref-guidelines.construction-cost-indices.index'),
            'createUrl' => $this->workspaceRoute($workspacePrefix, 'ref-guidelines.construction-cost-indices.create'),
            'ikkByProvinceUrl' => $this->workspaceRoute($workspacePrefix, 'ref-guidelines.ikk-by-province.index'),
            'importUrl' => $this->workspaceRoute($workspacePrefix, 'ref-guidelines.construction-cost-indices.import'),
            'exportUrl' => $this->workspaceRoute($workspacePrefix, 'ref-guidelines.construction-cost-indices.export', $this->activeFilterParams(
                $filters,
                ['q', 'guideline_set_id', 'year', 'province_id']
            )),
            'importDefaults' => [
                'guideline_set_id' => $filters['guideline_set_id'] !== 'all' ? (int) $filters['guideline_set_id'] : $activeGuideline?->id,
                'year' => $filters['year'] !== 'all' ? (int) $filters['year'] : ($activeGuideline?->year ?? (int) now()->format('Y')),
                'skip_province_rows' => true,
                'require_regency' => true,
            ],
        ];
    }

    public function constructionCostIndicesCreatePayload(string $workspacePrefix): array
    {
        $activeGuideline = $this->activeGuideline();

        return [
            'mode' => 'create',
            'record' => [
                'guideline_set_id' => $activeGuideline?->id,
                'year' => $activeGuideline?->year ?? (int) now()->format('Y'),
                'province_id' => '',
                'region_code' => '',
                'ikk_value' => '',
            ],
            'guidelineSetOptions' => $this->options->guidelineSetOptions(),
            'provinceOptions' => $this->options->provinceSelectOptions(),
            'regencyOptions' => [],
            'submitUrl' => $this->workspaceRoute($workspacePrefix, 'ref-guidelines.construction-cost-indices.store'),
            'indexUrl' => $this->workspaceRoute($workspacePrefix, 'ref-guidelines.construction-cost-indices.index'),
            'ikkByProvinceUrl' => $this->workspaceRoute($workspacePrefix, 'ref-guidelines.ikk-by-province.index'),
        ];
    }

    public function constructionCostIndicesEditPayload(ConstructionCostIndex $record, string $workspacePrefix): array
    {
        $record->loadMissing('regency:id,name,province_id');

        return [
            'mode' => 'edit',
            'record' => [
                'id' => $record->id,
                'guideline_set_id' => $record->guideline_set_id,
                'year' => (int) $record->year,
                'province_id' => (string) ($record->regency?->province_id ?? ''),
                'region_code' => (string) $record->region_code,
                'ikk_value' => (float) $record->ikk_value,
            ],
            'guidelineSetOptions' => $this->options->guidelineSetOptions(),
            'provinceOptions' => $this->options->provinceSelectOptions(),
            'regencyOptions' => $this->options->regencySelectOptionsByProvince($record->regency?->province_id),
            'submitUrl' => $this->workspaceRoute($workspacePrefix, 'ref-guidelines.construction-cost-indices.update', $record),
            'indexUrl' => $this->workspaceRoute($workspacePrefix, 'ref-guidelines.construction-cost-indices.index'),
            'ikkByProvinceUrl' => $this->workspaceRoute($workspacePrefix, 'ref-guidelines.ikk-by-province.index'),
        ];
    }

    public function saveConstructionCostIndex(array $validated, ?ConstructionCostIndex $record = null): void
    {
        $regency = Regency::query()->findOrFail($validated['region_code']);

        $payload = [
            'guideline_set_id' => $validated['guideline_set_id'],
            'year' => $validated['year'],
            'region_code' => $regency->id,
            'region_name' => $regency->name,
            'ikk_value' => $validated['ikk_value'],
        ];

        if ($record) {
            $record->forceFill($payload)->save();

            return;
        }

        ConstructionCostIndex::query()->create($payload);
    }

    public function importConstructionCostIndices(ImportConstructionCostIndicesRequest $request): array
    {
        $validated = $request->validated();

        $import = new IKKImport(
            guidelineSetId: (int) $validated['guideline_set_id'],
            year: (int) $validated['year'],
            skipProvinceRows: $request->boolean('skip_province_rows', true),
            requireRegency: $request->boolean('require_regency', true),
        );

        Excel::import($import, $validated['file']);

        return [
            'filters' => [
                'guideline_set_id' => $validated['guideline_set_id'],
                'year' => $validated['year'],
            ],
            'message' => "Import IKK selesai. {$import->inserted} row baru, {$import->updated} row diperbarui, {$import->skipped} row dilewati.",
        ];
    }

    public function constructionCostIndicesExportQuery(array $filters)
    {
        return $this->filteredQueries->constructionCostIndices($filters)
            ->orderByDesc('year')
            ->orderBy('region_code');
    }

    public function deleteConstructionCostIndex(ConstructionCostIndex $record): void
    {
        $this->deleteReferenceRecord(
            $record,
            'IKK tidak bisa dihapus karena masih dipakai data appraisal atau reviewer.'
        );
    }

    public function ikkByProvinceIndexPayload(
        array $filters,
        ?int $guidelineSetId,
        ?int $year,
        ?string $provinceId,
        string $workspacePrefix,
    ): array {
        return [
            'filters' => $filters,
            'guidelineSetOptions' => $this->options->guidelineSetOptions(),
            'provinceOptions' => $this->options->provinceSelectOptions(withCode: false),
            'items' => $this->ikkByProvinceItems($guidelineSetId, $year, $provinceId),
            'submitUrl' => $this->workspaceRoute($workspacePrefix, 'ref-guidelines.ikk-by-province.save'),
        ];
    }

    public function saveIkkByProvince(array $validated): array
    {
        $provinceId = (string) $validated['province_id'];
        $guidelineSetId = (int) $validated['guideline_set_id'];
        $year = (int) $validated['year'];
        $regencyMap = Regency::query()
            ->where('province_id', $provinceId)
            ->orderByRaw('CAST(id AS UNSIGNED) ASC')
            ->get(['id', 'name'])
            ->keyBy(fn (Regency $regency) => (string) $regency->id);

        foreach ($validated['items'] as $row) {
            abort_unless($regencyMap->has((string) $row['region_code']), 422);
        }

        $now = now();

        DB::transaction(function () use ($validated, $guidelineSetId, $year, $now, $regencyMap): void {
            foreach ($validated['items'] as $row) {
                /** @var Regency $regency */
                $regency = $regencyMap->get((string) $row['region_code']);

                ConstructionCostIndex::query()->updateOrCreate(
                    [
                        'guideline_set_id' => $guidelineSetId,
                        'year' => $year,
                        'region_code' => $regency->id,
                    ],
                    [
                        'region_name' => $regency->name,
                        'ikk_value' => $row['ikk_value'],
                        'updated_at' => $now,
                    ]
                );
            }
        });

        return [
            'guideline_set_id' => $guidelineSetId,
            'year' => $year,
            'province_id' => $provinceId,
        ];
    }

    public function costElementsIndexPayload(array $filters, int $perPage, string $workspacePrefix): array
    {
        $activeGuideline = $this->activeGuideline();
        $records = $this->filteredQueries->costElements($filters)
            ->with('guidelineSet:id,name,year,is_active')
            ->orderByDesc('year')
            ->orderBy('group')
            ->orderBy('element_code')
            ->paginate($perPage)
            ->withQueryString();

        $records->through(fn (CostElement $record) => $this->rows->costElement(
            $record,
            $this->workspaceRoute($workspacePrefix, 'ref-guidelines.cost-elements.edit', $record),
            $this->workspaceRoute($workspacePrefix, 'ref-guidelines.cost-elements.destroy', $record),
        ));

        return [
            'filters' => $filters,
            'guidelineSetOptions' => $this->options->guidelineSetOptions(includeAll: true),
            'yearOptions' => $this->options->costElementYearOptions(),
            'baseRegionOptions' => $this->options->costElementBaseRegionOptions(includeAll: true),
            'groupOptions' => $this->options->costElementGroupOptions(includeAll: true),
            'summary' => [
                'total' => CostElement::query()->count(),
                'guideline_sets' => CostElement::query()->distinct('guideline_set_id')->count('guideline_set_id'),
                'groups' => CostElement::query()->distinct('group')->count('group'),
                'active_guideline' => CostElement::query()
                    ->whereHas('guidelineSet', fn ($query) => $query->where('is_active', true))
                    ->count(),
            ],
            'records' => $this->paginatedRecordsPayload($records),
            'indexUrl' => $this->workspaceRoute($workspacePrefix, 'ref-guidelines.cost-elements.index'),
            'createUrl' => $this->workspaceRoute($workspacePrefix, 'ref-guidelines.cost-elements.create'),
            'importUrl' => $this->workspaceRoute($workspacePrefix, 'ref-guidelines.cost-elements.import'),
            'exportUrl' => $this->workspaceRoute($workspacePrefix, 'ref-guidelines.cost-elements.export', $this->activeFilterParams(
                $filters,
                ['q', 'guideline_set_id', 'year', 'base_region', 'group']
            )),
            'importDefaults' => [
                'guideline_set_id' => $filters['guideline_set_id'] !== 'all' ? (int) $filters['guideline_set_id'] : $activeGuideline?->id,
                'year' => $filters['year'] !== 'all' ? (int) $filters['year'] : ($activeGuideline?->year ?? (int) now()->format('Y')),
                'base_region' => $filters['base_region'] !== 'all' ? $filters['base_region'] : 'DKI Jakarta',
            ],
        ];
    }

    public function costElementsCreatePayload(string $workspacePrefix): array
    {
        $activeGuideline = $this->activeGuideline();

        return [
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
            'guidelineSetOptions' => $this->options->guidelineSetOptions(),
            'formOptions' => $this->options->costElementFormOptions(),
            'submitUrl' => $this->workspaceRoute($workspacePrefix, 'ref-guidelines.cost-elements.store'),
            'indexUrl' => $this->workspaceRoute($workspacePrefix, 'ref-guidelines.cost-elements.index'),
        ];
    }

    public function costElementsEditPayload(CostElement $record, string $workspacePrefix): array
    {
        return [
            'mode' => 'edit',
            'record' => [
                'id' => $record->id,
                'guideline_set_id' => $record->guideline_set_id,
                'year' => (int) $record->year,
                'base_region' => $record->base_region,
                'group' => $record->group,
                'element_code' => $record->element_code,
                'element_name' => $record->element_name,
                'building_type' => $record->building_type,
                'building_class' => $record->building_class,
                'storey_pattern' => $record->storey_pattern,
                'unit' => $record->unit,
                'unit_cost' => (int) $record->unit_cost,
                'spec_json' => is_array($record->spec_json) ? json_encode($record->spec_json, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : ($record->spec_json ?? ''),
            ],
            'guidelineSetOptions' => $this->options->guidelineSetOptions(),
            'formOptions' => $this->options->costElementFormOptions(),
            'submitUrl' => $this->workspaceRoute($workspacePrefix, 'ref-guidelines.cost-elements.update', $record),
            'indexUrl' => $this->workspaceRoute($workspacePrefix, 'ref-guidelines.cost-elements.index'),
        ];
    }

    public function saveCostElement(array $validated, ?CostElement $record = null): void
    {
        $payload = [
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
        ];

        if ($record) {
            $record->forceFill($payload)->save();

            return;
        }

        CostElement::query()->create($payload);
    }

    public function importCostElements(ImportCostElementRequest $request): array
    {
        $validated = $request->validated();

        $import = new CostElementImport(
            guidelineSetId: (int) $validated['guideline_set_id'],
            year: (int) $validated['year'],
            baseRegion: trim((string) $validated['base_region']),
        );

        Excel::import($import, $validated['file']);

        return [
            'filters' => [
                'guideline_set_id' => $validated['guideline_set_id'],
                'year' => $validated['year'],
                'base_region' => $validated['base_region'],
            ],
            'message' => "Import biaya unit terpasang selesai. {$import->inserted} row baru, {$import->updated} row diperbarui, {$import->skipped} row dilewati.",
        ];
    }

    public function costElementsExportQuery(array $filters)
    {
        return $this->filteredQueries->costElements($filters)
            ->orderByDesc('year')
            ->orderBy('group')
            ->orderBy('element_code');
    }

    public function deleteCostElement(CostElement $record): void
    {
        $this->deleteReferenceRecord(
            $record,
            'Cost element tidak bisa dihapus karena masih dipakai perhitungan reviewer.'
        );
    }

    public function floorIndicesIndexPayload(array $filters, int $perPage, string $workspacePrefix): array
    {
        $activeGuideline = $this->activeGuideline();
        $records = $this->filteredQueries->floorIndices($filters)
            ->with('guidelineSet:id,name,year,is_active')
            ->orderByDesc('year')
            ->orderBy('building_class')
            ->orderBy('floor_count')
            ->paginate($perPage)
            ->withQueryString();

        $records->through(fn (FloorIndex $record) => $this->rows->floorIndex(
            $record,
            $this->workspaceRoute($workspacePrefix, 'ref-guidelines.floor-indices.edit', $record),
            $this->workspaceRoute($workspacePrefix, 'ref-guidelines.floor-indices.destroy', $record),
        ));

        return [
            'filters' => $filters,
            'guidelineSetOptions' => $this->options->guidelineSetOptions(includeAll: true),
            'yearOptions' => $this->options->floorIndexYearOptions(includeAll: true),
            'buildingClassOptions' => $this->options->floorIndexBuildingClassOptions(includeAll: true),
            'summary' => [
                'total' => FloorIndex::query()->count(),
                'guideline_sets' => FloorIndex::query()->distinct('guideline_set_id')->count('guideline_set_id'),
                'classes' => FloorIndex::query()->distinct('building_class')->count('building_class'),
                'active_guideline' => FloorIndex::query()
                    ->whereHas('guidelineSet', fn ($query) => $query->where('is_active', true))
                    ->count(),
            ],
            'records' => $this->paginatedRecordsPayload($records),
            'indexUrl' => $this->workspaceRoute($workspacePrefix, 'ref-guidelines.floor-indices.index'),
            'createUrl' => $this->workspaceRoute($workspacePrefix, 'ref-guidelines.floor-indices.create'),
            'importUrl' => $this->workspaceRoute($workspacePrefix, 'ref-guidelines.floor-indices.import'),
            'exportUrl' => $this->workspaceRoute($workspacePrefix, 'ref-guidelines.floor-indices.export', $this->activeFilterParams(
                $filters,
                ['q', 'guideline_set_id', 'year', 'building_class']
            )),
            'importDefaults' => [
                'guideline_set_id' => $filters['guideline_set_id'] !== 'all' ? (int) $filters['guideline_set_id'] : $activeGuideline?->id,
                'year' => $filters['year'] !== 'all' ? (int) $filters['year'] : ($activeGuideline?->year ?? (int) now()->format('Y')),
            ],
        ];
    }

    public function floorIndicesCreatePayload(string $workspacePrefix): array
    {
        $activeGuideline = $this->activeGuideline();

        return [
            'mode' => 'create',
            'record' => [
                'guideline_set_id' => $activeGuideline?->id,
                'year' => $activeGuideline?->year ?? (int) now()->format('Y'),
                'building_class' => 'DEFAULT',
                'floor_count' => '',
                'il_value' => '',
            ],
            'guidelineSetOptions' => $this->options->guidelineSetOptions(),
            'buildingClassOptions' => $this->options->floorIndexBuildingClassOptions(),
            'submitUrl' => $this->workspaceRoute($workspacePrefix, 'ref-guidelines.floor-indices.store'),
            'indexUrl' => $this->workspaceRoute($workspacePrefix, 'ref-guidelines.floor-indices.index'),
        ];
    }

    public function floorIndicesEditPayload(FloorIndex $record, string $workspacePrefix): array
    {
        return [
            'mode' => 'edit',
            'record' => [
                'id' => $record->id,
                'guideline_set_id' => $record->guideline_set_id,
                'year' => (int) $record->year,
                'building_class' => $record->building_class,
                'floor_count' => (int) $record->floor_count,
                'il_value' => (float) $record->il_value,
            ],
            'guidelineSetOptions' => $this->options->guidelineSetOptions(),
            'buildingClassOptions' => $this->options->floorIndexBuildingClassOptions(),
            'submitUrl' => $this->workspaceRoute($workspacePrefix, 'ref-guidelines.floor-indices.update', $record),
            'indexUrl' => $this->workspaceRoute($workspacePrefix, 'ref-guidelines.floor-indices.index'),
        ];
    }

    public function saveFloorIndex(array $validated, ?FloorIndex $record = null): void
    {
        $payload = [
            'guideline_set_id' => $validated['guideline_set_id'],
            'year' => $validated['year'],
            'building_class' => $validated['building_class'],
            'floor_count' => $validated['floor_count'],
            'il_value' => $validated['il_value'],
        ];

        if ($record) {
            $record->forceFill($payload)->save();

            return;
        }

        FloorIndex::query()->create($payload);
    }

    public function importFloorIndices(ImportFloorIndexRequest $request): array
    {
        $validated = $request->validated();

        $import = new FloorIndexImport(
            guidelineSetId: (int) $validated['guideline_set_id'],
            year: (int) $validated['year'],
        );

        Excel::import($import, $validated['file']);

        return [
            'filters' => [
                'guideline_set_id' => $validated['guideline_set_id'],
                'year' => $validated['year'],
            ],
            'message' => "Import floor index selesai. {$import->processed} row diproses, {$import->skipped} row dilewati.",
        ];
    }

    public function floorIndicesExportQuery(array $filters)
    {
        return $this->filteredQueries->floorIndices($filters)
            ->orderByDesc('year')
            ->orderBy('building_class')
            ->orderBy('floor_count');
    }

    public function deleteFloorIndex(FloorIndex $record): void
    {
        $this->deleteReferenceRecord(
            $record,
            'Floor index tidak bisa dihapus karena masih dipakai perhitungan reviewer.'
        );
    }

    public function mappiRcnStandardsIndexPayload(array $filters, int $perPage, string $workspacePrefix): array
    {
        $activeGuideline = $this->activeGuideline();
        $records = $this->filteredQueries->mappiRcnStandards($filters)
            ->with('guidelineSet:id,name,year,is_active')
            ->orderByDesc('year')
            ->orderBy('building_type')
            ->orderBy('building_class')
            ->paginate($perPage)
            ->withQueryString();

        $records->through(fn (MappiRcnStandard $record) => $this->rows->mappiRcnStandard(
            $record,
            $this->workspaceRoute($workspacePrefix, 'ref-guidelines.mappi-rcn-standards.edit', $record),
            $this->workspaceRoute($workspacePrefix, 'ref-guidelines.mappi-rcn-standards.destroy', $record),
        ));

        return [
            'filters' => $filters,
            'guidelineSetOptions' => $this->options->guidelineSetOptions(includeAll: true),
            'yearOptions' => $this->options->mappiRcnYearOptions(includeAll: true),
            'buildingTypeOptions' => $this->options->mappiRcnBuildingTypeOptions(includeAll: true),
            'buildingClassOptions' => $this->options->mappiRcnBuildingClassOptions(includeAll: true),
            'summary' => [
                'total' => MappiRcnStandard::query()->count(),
                'guideline_sets' => MappiRcnStandard::query()->distinct('guideline_set_id')->count('guideline_set_id'),
                'building_types' => MappiRcnStandard::query()->distinct('building_type')->count('building_type'),
                'active_guideline' => MappiRcnStandard::query()
                    ->whereHas('guidelineSet', fn ($query) => $query->where('is_active', true))
                    ->count(),
            ],
            'records' => $this->paginatedRecordsPayload($records),
            'indexUrl' => $this->workspaceRoute($workspacePrefix, 'ref-guidelines.mappi-rcn-standards.index'),
            'createUrl' => $this->workspaceRoute($workspacePrefix, 'ref-guidelines.mappi-rcn-standards.create'),
            'importUrl' => $this->workspaceRoute($workspacePrefix, 'ref-guidelines.mappi-rcn-standards.import'),
            'exportUrl' => $this->workspaceRoute($workspacePrefix, 'ref-guidelines.mappi-rcn-standards.export', $this->activeFilterParams(
                $filters,
                ['q', 'guideline_set_id', 'year', 'building_type', 'building_class']
            )),
            'importDefaults' => [
                'guideline_set_id' => $filters['guideline_set_id'] !== 'all' ? (int) $filters['guideline_set_id'] : $activeGuideline?->id,
                'year' => $filters['year'] !== 'all' ? (int) $filters['year'] : ($activeGuideline?->year ?? (int) now()->format('Y')),
                'reference_region' => 'DKI Jakarta',
            ],
        ];
    }

    public function mappiRcnStandardsCreatePayload(string $workspacePrefix): array
    {
        $activeGuideline = $this->activeGuideline();

        return [
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
            'guidelineSetOptions' => $this->options->guidelineSetOptions(),
            'formOptions' => $this->options->mappiRcnFormOptions(),
            'submitUrl' => $this->workspaceRoute($workspacePrefix, 'ref-guidelines.mappi-rcn-standards.store'),
            'indexUrl' => $this->workspaceRoute($workspacePrefix, 'ref-guidelines.mappi-rcn-standards.index'),
        ];
    }

    public function mappiRcnStandardsEditPayload(MappiRcnStandard $record, string $workspacePrefix): array
    {
        return [
            'mode' => 'edit',
            'record' => [
                'id' => $record->id,
                'guideline_set_id' => $record->guideline_set_id,
                'year' => (int) $record->year,
                'reference_region' => $record->reference_region,
                'building_type' => $record->building_type,
                'building_class' => $record->building_class,
                'storey_pattern' => $record->storey_pattern,
                'rcn_value' => (int) $record->rcn_value,
                'notes' => $record->notes,
            ],
            'guidelineSetOptions' => $this->options->guidelineSetOptions(),
            'formOptions' => $this->options->mappiRcnFormOptions(),
            'submitUrl' => $this->workspaceRoute($workspacePrefix, 'ref-guidelines.mappi-rcn-standards.update', $record),
            'indexUrl' => $this->workspaceRoute($workspacePrefix, 'ref-guidelines.mappi-rcn-standards.index'),
        ];
    }

    public function saveMappiRcnStandard(array $validated, ?MappiRcnStandard $record = null): void
    {
        $payload = [
            'guideline_set_id' => $validated['guideline_set_id'],
            'year' => $validated['year'],
            'reference_region' => $validated['reference_region'],
            'building_type' => $validated['building_type'],
            'building_class' => $validated['building_class'] ?? null,
            'storey_pattern' => $validated['storey_pattern'] ?? null,
            'rcn_value' => $validated['rcn_value'],
            'notes' => $validated['notes'] ?? null,
        ];

        if ($record) {
            $record->forceFill($payload)->save();

            return;
        }

        MappiRcnStandard::query()->create($payload);
    }

    public function importMappiRcnStandards(ImportMappiRcnStandardRequest $request): array
    {
        $validated = $request->validated();

        $import = new MappiRcnStandardImport(
            guidelineSetId: (int) $validated['guideline_set_id'],
            year: (int) $validated['year'],
            referenceRegion: trim((string) $validated['reference_region']),
        );

        Excel::import($import, $validated['file']);

        return [
            'filters' => [
                'guideline_set_id' => $validated['guideline_set_id'],
                'year' => $validated['year'],
            ],
            'message' => "Import MAPPI RCN selesai. {$import->inserted} row baru, {$import->updated} row diperbarui, {$import->skipped} row dilewati.",
        ];
    }

    public function mappiRcnStandardsExportQuery(array $filters)
    {
        return $this->filteredQueries->mappiRcnStandards($filters)
            ->orderByDesc('year')
            ->orderBy('building_type')
            ->orderBy('building_class');
    }

    public function deleteMappiRcnStandard(MappiRcnStandard $record): void
    {
        $this->deleteReferenceRecord(
            $record,
            'MAPPI RCN tidak bisa dihapus karena masih dipakai perhitungan reviewer.'
        );
    }

    public function buildingEconomicLivesIndexPayload(array $filters, int $perPage, string $workspacePrefix): array
    {
        $activeGuideline = $this->activeGuideline();
        $records = $this->buildingEconomicLivesFilteredQuery($filters)
            ->with('guidelineSet:id,name,year,is_active')
            ->orderByDesc('year')
            ->orderBy('category')
            ->orderBy('building_class')
            ->orderBy('storey_min')
            ->paginate($perPage)
            ->withQueryString();

        $records->through(fn (BuildingEconomicLife $record) => $this->buildingEconomicLifeRow(
            $record,
            $workspacePrefix,
        ));

        return [
            'filters' => $filters,
            'guidelineSetOptions' => $this->options->guidelineSetOptions(includeAll: true),
            'yearOptions' => $this->buildingEconomicLifeYearOptions(includeAll: true),
            'categoryOptions' => $this->buildingEconomicLifeCategoryOptions(includeAll: true),
            'buildingClassOptions' => $this->buildingEconomicLifeBuildingClassOptions(includeAll: true),
            'summary' => [
                'total' => BuildingEconomicLife::query()->count(),
                'guideline_sets' => BuildingEconomicLife::query()->distinct('guideline_item_id')->count('guideline_item_id'),
                'categories' => BuildingEconomicLife::query()->distinct('category')->count('category'),
                'active_guideline' => BuildingEconomicLife::query()
                    ->whereHas('guidelineSet', fn ($query) => $query->where('is_active', true))
                    ->count(),
            ],
            'records' => $this->paginatedRecordsPayload($records),
            'indexUrl' => $this->workspaceRoute($workspacePrefix, 'ref-guidelines.building-economic-lives.index'),
            'createUrl' => $this->workspaceRoute($workspacePrefix, 'ref-guidelines.building-economic-lives.create'),
            'importUrl' => $this->workspaceRoute($workspacePrefix, 'ref-guidelines.building-economic-lives.import'),
            'exportUrl' => $this->workspaceRoute($workspacePrefix, 'ref-guidelines.building-economic-lives.export', $this->activeFilterParams(
                $filters,
                ['q', 'guideline_item_id', 'year', 'category', 'building_class']
            )),
            'importDefaults' => [
                'guideline_item_id' => $filters['guideline_item_id'] !== 'all'
                    ? (int) $filters['guideline_item_id']
                    : $activeGuideline?->id,
                'year' => $filters['year'] !== 'all'
                    ? (int) $filters['year']
                    : ($activeGuideline?->year ?? (int) now()->format('Y')),
            ],
        ];
    }

    public function buildingEconomicLivesExportQuery(array $filters)
    {
        return $this->buildingEconomicLivesFilteredQuery($filters)
            ->orderByDesc('year')
            ->orderBy('category')
            ->orderBy('building_class')
            ->orderBy('storey_min');
    }

    public function buildingEconomicLivesCreatePayload(string $workspacePrefix): array
    {
        $activeGuideline = $this->activeGuideline();

        return [
            'mode' => 'create',
            'record' => [
                'guideline_item_id' => $activeGuideline?->id,
                'year' => $activeGuideline?->year ?? (int) now()->format('Y'),
                'category' => '',
                'sub_category' => '',
                'building_type' => '',
                'building_class' => '',
                'storey_min' => '',
                'storey_max' => '',
                'economic_life' => '',
            ],
            'guidelineSetOptions' => $this->options->guidelineSetOptions(),
            'formOptions' => $this->buildingEconomicLifeFormOptions(),
            'submitUrl' => $this->workspaceRoute($workspacePrefix, 'ref-guidelines.building-economic-lives.store'),
            'indexUrl' => $this->workspaceRoute($workspacePrefix, 'ref-guidelines.building-economic-lives.index'),
        ];
    }

    public function buildingEconomicLivesEditPayload(BuildingEconomicLife $record, string $workspacePrefix): array
    {
        return [
            'mode' => 'edit',
            'record' => [
                'id' => $record->id,
                'guideline_item_id' => $record->guideline_item_id,
                'year' => (int) $record->year,
                'category' => $record->category,
                'sub_category' => $record->sub_category,
                'building_type' => $record->building_type,
                'building_class' => $record->building_class,
                'storey_min' => $record->storey_min,
                'storey_max' => $record->storey_max,
                'economic_life' => (int) $record->economic_life,
            ],
            'guidelineSetOptions' => $this->options->guidelineSetOptions(),
            'formOptions' => $this->buildingEconomicLifeFormOptions(),
            'submitUrl' => $this->workspaceRoute($workspacePrefix, 'ref-guidelines.building-economic-lives.update', $record),
            'indexUrl' => $this->workspaceRoute($workspacePrefix, 'ref-guidelines.building-economic-lives.index'),
        ];
    }

    public function saveBuildingEconomicLife(array $validated, ?BuildingEconomicLife $record = null): void
    {
        $payload = [
            'guideline_item_id' => $validated['guideline_item_id'],
            'year' => $validated['year'],
            'category' => $validated['category'],
            'sub_category' => $this->blankToNull($validated['sub_category'] ?? null),
            'building_type' => $this->blankToNull($validated['building_type'] ?? null),
            'building_class' => $this->blankToNull($validated['building_class'] ?? null),
            'storey_min' => $this->blankToNull($validated['storey_min'] ?? null),
            'storey_max' => $this->blankToNull($validated['storey_max'] ?? null),
            'economic_life' => $validated['economic_life'],
        ];

        if ($record) {
            $record->forceFill($payload)->save();

            return;
        }

        BuildingEconomicLife::query()->create($payload);
    }

    public function importBuildingEconomicLives(ImportBuildingEconomicLifeRequest $request): array
    {
        $validated = $request->validated();

        $import = new BuildingEconomicLifeImport(
            guidelineItemId: (int) $validated['guideline_item_id'],
            year: (int) $validated['year'],
        );

        Excel::import($import, $validated['file']);

        return [
            'filters' => [
                'guideline_item_id' => $validated['guideline_item_id'],
                'year' => $validated['year'],
            ],
            'message' => "Import BEL selesai. {$import->processed} row diproses, {$import->skipped} row dilewati.",
        ];
    }

    private function activeGuideline(): ?GuidelineSet
    {
        return GuidelineSet::query()->where('is_active', true)->first();
    }

    private function ikkByProvinceItems(?int $guidelineSetId, ?int $year, ?string $provinceId): array
    {
        if ($guidelineSetId === null || $year === null || blank($provinceId)) {
            return [];
        }

        $regencies = Regency::query()
            ->where('province_id', $provinceId)
            ->orderByRaw('CAST(id AS UNSIGNED) ASC')
            ->get(['id', 'name']);

        $existing = ConstructionCostIndex::query()
            ->where('guideline_set_id', $guidelineSetId)
            ->where('year', $year)
            ->whereIn('region_code', $regencies->pluck('id')->all())
            ->pluck('ikk_value', 'region_code');

        return $regencies
            ->map(fn (Regency $regency) => [
                'region_code' => (string) $regency->id,
                'regency_name' => (string) $regency->name,
                'ikk_value' => $existing[(string) $regency->id] ?? null,
            ])
            ->values()
            ->all();
    }

    private function buildingEconomicLivesFilteredQuery(array $filters)
    {
        return BuildingEconomicLife::query()
            ->when($filters['q'] !== '', function ($query) use ($filters): void {
                $query->where(function ($innerQuery) use ($filters): void {
                    $innerQuery
                        ->where('category', 'like', '%' . $filters['q'] . '%')
                        ->orWhere('sub_category', 'like', '%' . $filters['q'] . '%')
                        ->orWhere('building_type', 'like', '%' . $filters['q'] . '%')
                        ->orWhere('building_class', 'like', '%' . $filters['q'] . '%');
                });
            })
            ->when(
                $filters['guideline_item_id'] !== 'all',
                fn ($query) => $query->where('guideline_item_id', (int) $filters['guideline_item_id'])
            )
            ->when(
                $filters['year'] !== 'all',
                fn ($query) => $query->where('year', (int) $filters['year'])
            )
            ->when(
                $filters['category'] !== 'all',
                fn ($query) => $query->where('category', $filters['category'])
            )
            ->when(
                $filters['building_class'] !== 'all',
                fn ($query) => $query->where('building_class', $filters['building_class'])
            );
    }

    private function buildingEconomicLifeRow(BuildingEconomicLife $record, string $workspacePrefix): array
    {
        $record->loadMissing('guidelineSet:id,name,is_active');

        return [
            'id' => $record->id,
            'guideline_set_name' => $record->guidelineSet?->name ?? '-',
            'guideline_is_active' => (bool) ($record->guidelineSet?->is_active ?? false),
            'year' => (int) $record->year,
            'category' => $record->category,
            'sub_category' => $record->sub_category,
            'building_type' => $record->building_type,
            'building_class' => $record->building_class,
            'storey_label' => $this->buildingEconomicLifeStoreyLabel($record),
            'economic_life' => (int) $record->economic_life,
            'updated_at' => $record->updated_at?->toIso8601String(),
            'edit_url' => $this->workspaceRoute($workspacePrefix, 'ref-guidelines.building-economic-lives.edit', $record),
            'destroy_url' => $this->workspaceRoute($workspacePrefix, 'ref-guidelines.building-economic-lives.destroy', $record),
        ];
    }

    private function buildingEconomicLifeYearOptions(bool $includeAll = false): array
    {
        return $this->distinctScalarOptions(BuildingEconomicLife::class, 'year', $includeAll, 'Semua Tahun', desc: true);
    }

    private function buildingEconomicLifeCategoryOptions(bool $includeAll = false): array
    {
        return $this->distinctScalarOptions(BuildingEconomicLife::class, 'category', $includeAll, 'Semua Kategori');
    }

    private function buildingEconomicLifeBuildingClassOptions(bool $includeAll = false): array
    {
        return $this->distinctScalarOptions(BuildingEconomicLife::class, 'building_class', $includeAll, 'Semua Class');
    }

    private function buildingEconomicLifeFormOptions(): array
    {
        return [
            'categories' => $this->distinctValues(BuildingEconomicLife::class, 'category', 200),
            'sub_categories' => $this->distinctValues(BuildingEconomicLife::class, 'sub_category', 200),
            'building_types' => $this->distinctValues(BuildingEconomicLife::class, 'building_type', 200),
            'building_classes' => $this->distinctValues(BuildingEconomicLife::class, 'building_class', 200),
        ];
    }

    private function buildingEconomicLifeStoreyLabel(BuildingEconomicLife $record): string
    {
        $min = $record->storey_min;
        $max = $record->storey_max;

        return match (true) {
            $min !== null && $max !== null => "{$min}-{$max} lantai",
            $min !== null => ">= {$min} lantai",
            $max !== null => "<= {$max} lantai",
            default => 'Semua lantai',
        };
    }

    private function distinctScalarOptions(
        string $modelClass,
        string $column,
        bool $includeAll,
        string $allLabel,
        bool $desc = false
    ): array {
        $query = $modelClass::query()
            ->whereNotNull($column)
            ->where($column, '<>', '')
            ->distinct();

        $query = $desc ? $query->orderByDesc($column) : $query->orderBy($column);

        $options = $query->pluck($column)
            ->map(fn (int|string $value) => [
                'value' => (string) $value,
                'label' => (string) $value,
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

    private function distinctValues(string $modelClass, string $column, int $limit): array
    {
        return $modelClass::query()
            ->whereNotNull($column)
            ->where($column, '<>', '')
            ->distinct()
            ->orderBy($column)
            ->limit($limit)
            ->pluck($column)
            ->values()
            ->all();
    }

    private function blankToNull(mixed $value): mixed
    {
        return is_string($value) && trim($value) === '' ? null : $value;
    }

    private function deleteReferenceRecord(object $record, string $message): void
    {
        try {
            $record->delete();
        } catch (QueryException) {
            throw new \RuntimeException($message);
        }
    }

    private function workspaceRoute(string $workspacePrefix, string $suffix, mixed $parameters = []): string
    {
        return route($workspacePrefix . '.' . ltrim($suffix, '.'), $parameters);
    }

    private function activeFilterParams(array $filters, array $keys): array
    {
        $params = [];

        foreach ($keys as $key) {
            $value = $filters[$key] ?? null;

            if ($value === null || $value === '' || $value === 'all') {
                continue;
            }

            $params[$key] = $value;
        }

        return $params;
    }

    private function paginatedRecordsPayload(object $records): array
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
}

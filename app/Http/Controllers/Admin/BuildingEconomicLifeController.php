<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreBuildingEconomicLifeRequest;
use App\Models\BuildingEconomicLife;
use App\Models\GuidelineSet;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Response;

class BuildingEconomicLifeController extends Controller
{
    public function index(Request $request): Response
    {
        $filters = [
            'q' => trim((string) $request->query('q', '')),
            'guideline_item_id' => (string) $request->query('guideline_item_id', 'all'),
            'year' => (string) $request->query('year', 'all'),
            'category' => (string) $request->query('category', 'all'),
            'building_class' => (string) $request->query('building_class', 'all'),
            'per_page' => (string) $this->adminPerPage($request),
        ];

        $records = BuildingEconomicLife::query()
            ->with('guidelineSet:id,name,year,is_active')
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
            )
            ->orderByDesc('year')
            ->orderBy('category')
            ->orderBy('building_class')
            ->orderBy('storey_min')
            ->paginate($this->adminPerPage($request))
            ->withQueryString();

        $records->through(fn (BuildingEconomicLife $record) => $this->transformBuildingEconomicLifeRow($record));

        return inertia('Admin/BuildingEconomicLives/Index', [
            'filters' => $filters,
            'guidelineSetOptions' => $this->guidelineSetOptions(includeAll: true),
            'yearOptions' => $this->yearOptions(includeAll: true),
            'categoryOptions' => $this->categoryOptions(includeAll: true),
            'buildingClassOptions' => $this->buildingClassOptions(includeAll: true),
            'summary' => [
                'total' => BuildingEconomicLife::query()->count(),
                'guideline_sets' => BuildingEconomicLife::query()->distinct('guideline_item_id')->count('guideline_item_id'),
                'categories' => BuildingEconomicLife::query()->distinct('category')->count('category'),
                'active_guideline' => BuildingEconomicLife::query()
                    ->whereHas('guidelineSet', fn ($query) => $query->where('is_active', true))
                    ->count(),
            ],
            'records' => $this->paginatedRecordsPayload($records),
            'createUrl' => route('admin.ref-guidelines.building-economic-lives.create'),
        ]);
    }

    public function create(): Response
    {
        $activeGuideline = GuidelineSet::query()->where('is_active', true)->first();

        return inertia('Admin/BuildingEconomicLives/Form', [
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
            'guidelineSetOptions' => $this->guidelineSetOptions(),
            'formOptions' => $this->formOptions(),
            'submitUrl' => route('admin.ref-guidelines.building-economic-lives.store'),
            'indexUrl' => route('admin.ref-guidelines.building-economic-lives.index'),
        ]);
    }

    public function store(StoreBuildingEconomicLifeRequest $request): RedirectResponse
    {
        BuildingEconomicLife::query()->create($this->payload($request->validated()));

        return redirect()
            ->route('admin.ref-guidelines.building-economic-lives.index')
            ->with('success', 'BEL berhasil ditambahkan.');
    }

    public function edit(BuildingEconomicLife $buildingEconomicLife): Response
    {
        return inertia('Admin/BuildingEconomicLives/Form', [
            'mode' => 'edit',
            'record' => [
                'id' => $buildingEconomicLife->id,
                'guideline_item_id' => $buildingEconomicLife->guideline_item_id,
                'year' => (int) $buildingEconomicLife->year,
                'category' => $buildingEconomicLife->category,
                'sub_category' => $buildingEconomicLife->sub_category,
                'building_type' => $buildingEconomicLife->building_type,
                'building_class' => $buildingEconomicLife->building_class,
                'storey_min' => $buildingEconomicLife->storey_min,
                'storey_max' => $buildingEconomicLife->storey_max,
                'economic_life' => (int) $buildingEconomicLife->economic_life,
            ],
            'guidelineSetOptions' => $this->guidelineSetOptions(),
            'formOptions' => $this->formOptions(),
            'submitUrl' => route('admin.ref-guidelines.building-economic-lives.update', $buildingEconomicLife),
            'indexUrl' => route('admin.ref-guidelines.building-economic-lives.index'),
        ]);
    }

    public function update(
        StoreBuildingEconomicLifeRequest $request,
        BuildingEconomicLife $buildingEconomicLife
    ): RedirectResponse {
        $buildingEconomicLife->forceFill($this->payload($request->validated()))->save();

        return redirect()
            ->route('admin.ref-guidelines.building-economic-lives.index')
            ->with('success', 'BEL berhasil diperbarui.');
    }

    public function destroy(BuildingEconomicLife $buildingEconomicLife): RedirectResponse
    {
        try {
            $buildingEconomicLife->delete();
        } catch (QueryException) {
            return redirect()
                ->route('admin.ref-guidelines.building-economic-lives.index')
                ->with('error', 'BEL tidak bisa dihapus karena masih dipakai perhitungan reviewer.');
        }

        return redirect()
            ->route('admin.ref-guidelines.building-economic-lives.index')
            ->with('success', 'BEL berhasil dihapus.');
    }

    private function transformBuildingEconomicLifeRow(BuildingEconomicLife $record): array
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
            'storey_label' => $this->storeyLabel($record),
            'economic_life' => (int) $record->economic_life,
            'updated_at' => $record->updated_at?->toIso8601String(),
            'edit_url' => route('admin.ref-guidelines.building-economic-lives.edit', $record),
            'destroy_url' => route('admin.ref-guidelines.building-economic-lives.destroy', $record),
        ];
    }

    private function payload(array $validated): array
    {
        return [
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

    private function yearOptions(bool $includeAll = false): array
    {
        $options = BuildingEconomicLife::query()
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

    private function categoryOptions(bool $includeAll = false): array
    {
        $options = BuildingEconomicLife::query()
            ->whereNotNull('category')
            ->where('category', '<>', '')
            ->distinct()
            ->orderBy('category')
            ->pluck('category')
            ->map(fn (string $value) => ['value' => $value, 'label' => $value])
            ->values()
            ->all();

        if (! $includeAll) {
            return $options;
        }

        return [
            ['value' => 'all', 'label' => 'Semua Kategori'],
            ...$options,
        ];
    }

    private function buildingClassOptions(bool $includeAll = false): array
    {
        $options = BuildingEconomicLife::query()
            ->whereNotNull('building_class')
            ->where('building_class', '<>', '')
            ->distinct()
            ->orderBy('building_class')
            ->pluck('building_class')
            ->map(fn (string $value) => ['value' => $value, 'label' => $value])
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

    private function formOptions(): array
    {
        return [
            'categories' => $this->pluckDistinctValues('category', 200),
            'sub_categories' => $this->pluckDistinctValues('sub_category', 200),
            'building_types' => $this->pluckDistinctValues('building_type', 200),
            'building_classes' => $this->pluckDistinctValues('building_class', 200),
        ];
    }

    private function pluckDistinctValues(string $column, int $limit = 200): array
    {
        return BuildingEconomicLife::query()
            ->whereNotNull($column)
            ->where($column, '<>', '')
            ->distinct()
            ->orderBy($column)
            ->limit($limit)
            ->pluck($column)
            ->values()
            ->all();
    }

    private function storeyLabel(BuildingEconomicLife $record): string
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

    private function blankToNull(mixed $value): mixed
    {
        return is_string($value) && trim($value) === '' ? null : $value;
    }

    private function legacyUrl(BuildingEconomicLife $record): ?string
    {
        return null;
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
}

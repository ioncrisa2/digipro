<?php

namespace App\Services\Admin;

use App\Models\ConstructionCostIndex;
use App\Models\GuidelineSet;
use App\Models\ValuationSetting;
use App\Support\Admin\ReferenceGuideData\ReferenceGuideOptionsProvider;
use Illuminate\Support\Facades\DB;

class AdminReferenceGuideSettingsWorkspaceService
{
    public function __construct(
        private readonly ReferenceGuideOptionsProvider $options,
    ) {
    }

    public function guidelineSetsIndexPayload(array $filters, int $perPage, string $workspacePrefix): array
    {
        $records = $this->guidelineSetFilteredQuery($filters)
            ->withCount([
                'constructionCostIndexes',
                'costElements',
                'floorIndexes',
                'mappiRcnStandards',
            ])
            ->orderByDesc('year')
            ->paginate($perPage)
            ->withQueryString();

        $records->through(fn (GuidelineSet $guidelineSet) => $this->guidelineSetRow(
            $guidelineSet,
            $workspacePrefix,
        ));

        return [
            'filters' => $filters,
            'statusOptions' => [
                ['value' => 'all', 'label' => 'Semua Status'],
                ['value' => 'active', 'label' => 'Aktif'],
                ['value' => 'inactive', 'label' => 'Nonaktif'],
            ],
            'summary' => [
                'total' => GuidelineSet::query()->count(),
                'active' => GuidelineSet::query()->where('is_active', true)->count(),
                'valuation_settings' => ValuationSetting::query()->count(),
                'ikk_rows' => ConstructionCostIndex::query()->count(),
            ],
            'records' => $this->paginatedRecordsPayload($records),
            'indexUrl' => $this->workspaceRoute($workspacePrefix, 'ref-guidelines.guideline-sets.index'),
            'createUrl' => $this->workspaceRoute($workspacePrefix, 'ref-guidelines.guideline-sets.create'),
            'exportUrl' => $this->workspaceRoute(
                $workspacePrefix,
                'ref-guidelines.guideline-sets.export',
                $this->activeFilterParams($filters, ['q', 'status'])
            ),
        ];
    }

    public function guidelineSetsExportQuery(array $filters)
    {
        return $this->guidelineSetFilteredQuery($filters)->orderByDesc('year');
    }

    public function guidelineSetsCreatePayload(string $workspacePrefix): array
    {
        return [
            'mode' => 'create',
            'record' => [
                'name' => '',
                'year' => (int) now()->format('Y'),
                'description' => '',
                'is_active' => false,
            ],
            'submitUrl' => $this->workspaceRoute($workspacePrefix, 'ref-guidelines.guideline-sets.store'),
            'indexUrl' => $this->workspaceRoute($workspacePrefix, 'ref-guidelines.guideline-sets.index'),
        ];
    }

    public function guidelineSetsEditPayload(GuidelineSet $guidelineSet, string $workspacePrefix): array
    {
        return [
            'mode' => 'edit',
            'record' => [
                'id' => $guidelineSet->id,
                'name' => $guidelineSet->name,
                'year' => $guidelineSet->year,
                'description' => $guidelineSet->description,
                'is_active' => (bool) $guidelineSet->is_active,
            ],
            'submitUrl' => $this->workspaceRoute($workspacePrefix, 'ref-guidelines.guideline-sets.update', $guidelineSet),
            'indexUrl' => $this->workspaceRoute($workspacePrefix, 'ref-guidelines.guideline-sets.index'),
        ];
    }

    public function saveGuidelineSet(array $validated, ?GuidelineSet $guidelineSet = null): GuidelineSet
    {
        return DB::transaction(function () use ($validated, $guidelineSet): GuidelineSet {
            $isActive = (bool) ($validated['is_active'] ?? false);

            if ($isActive) {
                $query = GuidelineSet::query();

                if ($guidelineSet !== null) {
                    $query->whereKeyNot($guidelineSet->id);
                }

                $query->update(['is_active' => false]);
            }

            $payload = [
                'name' => $validated['name'],
                'year' => $validated['year'],
                'description' => $validated['description'] ?? null,
                'is_active' => $isActive,
            ];

            if ($guidelineSet !== null) {
                $guidelineSet->forceFill($payload)->save();

                return $guidelineSet;
            }

            return GuidelineSet::query()->create($payload);
        });
    }

    public function valuationSettingsIndexPayload(array $filters, int $perPage, string $workspacePrefix): array
    {
        $records = ValuationSetting::query()
            ->with('guidelineSet:id,name,year,is_active')
            ->when($filters['q'] !== '', function ($query) use ($filters): void {
                $query->where(function ($innerQuery) use ($filters): void {
                    $innerQuery
                        ->where('label', 'like', '%' . $filters['q'] . '%')
                        ->orWhere('key', 'like', '%' . $filters['q'] . '%')
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
                $filters['key'] !== 'all',
                fn ($query) => $query->where('key', $filters['key'])
            )
            ->latest('updated_at')
            ->paginate($perPage)
            ->withQueryString();

        $records->through(fn (ValuationSetting $valuationSetting) => $this->valuationSettingRow(
            $valuationSetting,
            $workspacePrefix,
        ));

        return [
            'filters' => $filters,
            'guidelineSetOptions' => $this->options->guidelineSetOptions(includeAll: true),
            'yearOptions' => $this->valuationSettingYearOptions(),
            'keyOptions' => $this->valuationSettingKeyOptions(includeAll: true),
            'summary' => [
                'total' => ValuationSetting::query()->count(),
                'guideline_sets' => ValuationSetting::query()->distinct('guideline_set_id')->count('guideline_set_id'),
                'active_guideline' => ValuationSetting::query()
                    ->whereHas('guidelineSet', fn ($query) => $query->where('is_active', true))
                    ->count(),
            ],
            'records' => $this->paginatedRecordsPayload($records),
            'createUrl' => $this->workspaceRoute($workspacePrefix, 'ref-guidelines.valuation-settings.create'),
        ];
    }

    public function valuationSettingsCreatePayload(string $workspacePrefix): array
    {
        $activeGuideline = $this->activeGuideline();

        return [
            'mode' => 'create',
            'record' => [
                'guideline_set_id' => $activeGuideline?->id,
                'year' => $activeGuideline?->year ?? (int) now()->format('Y'),
                'key' => ValuationSetting::KEY_PPN_PERCENT,
                'label' => ValuationSetting::labelForKey(ValuationSetting::KEY_PPN_PERCENT),
                'value_number' => null,
                'value_text' => '',
                'notes' => '',
            ],
            'guidelineSetOptions' => $this->options->guidelineSetOptions(),
            'keyOptions' => $this->valuationSettingKeyOptions(),
            'submitUrl' => $this->workspaceRoute($workspacePrefix, 'ref-guidelines.valuation-settings.store'),
            'indexUrl' => $this->workspaceRoute($workspacePrefix, 'ref-guidelines.valuation-settings.index'),
        ];
    }

    public function valuationSettingsEditPayload(ValuationSetting $valuationSetting, string $workspacePrefix): array
    {
        return [
            'mode' => 'edit',
            'record' => [
                'id' => $valuationSetting->id,
                'guideline_set_id' => $valuationSetting->guideline_set_id,
                'year' => $valuationSetting->year,
                'key' => $valuationSetting->key,
                'label' => $valuationSetting->label,
                'value_number' => $valuationSetting->value_number,
                'value_text' => $valuationSetting->value_text,
                'notes' => $valuationSetting->notes,
            ],
            'guidelineSetOptions' => $this->options->guidelineSetOptions(),
            'keyOptions' => $this->valuationSettingKeyOptions(),
            'submitUrl' => $this->workspaceRoute($workspacePrefix, 'ref-guidelines.valuation-settings.update', $valuationSetting),
            'indexUrl' => $this->workspaceRoute($workspacePrefix, 'ref-guidelines.valuation-settings.index'),
        ];
    }

    public function saveValuationSetting(array $validated, ?ValuationSetting $valuationSetting = null): ValuationSetting
    {
        $payload = [
            'guideline_set_id' => $validated['guideline_set_id'],
            'year' => $validated['year'],
            'key' => $validated['key'],
            'label' => $validated['label'],
            'value_number' => $validated['value_number'],
            'value_text' => $validated['value_text'] ?? null,
            'notes' => $validated['notes'] ?? null,
        ];

        if ($valuationSetting !== null) {
            $valuationSetting->forceFill($payload)->save();

            return $valuationSetting;
        }

        return ValuationSetting::query()->create($payload);
    }

    private function guidelineSetFilteredQuery(array $filters)
    {
        return GuidelineSet::query()
            ->when($filters['q'] !== '', function ($query) use ($filters): void {
                $query->where(function ($innerQuery) use ($filters): void {
                    $innerQuery
                        ->where('name', 'like', '%' . $filters['q'] . '%')
                        ->orWhere('description', 'like', '%' . $filters['q'] . '%');
                });
            })
            ->when($filters['status'] === 'active', fn ($query) => $query->where('is_active', true))
            ->when($filters['status'] === 'inactive', fn ($query) => $query->where('is_active', false));
    }

    private function guidelineSetRow(GuidelineSet $guidelineSet, string $workspacePrefix): array
    {
        return [
            'id' => $guidelineSet->id,
            'name' => $guidelineSet->name,
            'year' => (int) $guidelineSet->year,
            'description' => $guidelineSet->description,
            'is_active' => (bool) $guidelineSet->is_active,
            'construction_cost_indexes_count' => (int) ($guidelineSet->construction_cost_indexes_count ?? 0),
            'cost_elements_count' => (int) ($guidelineSet->cost_elements_count ?? 0),
            'floor_indexes_count' => (int) ($guidelineSet->floor_indexes_count ?? 0),
            'mappi_rcn_standards_count' => (int) ($guidelineSet->mappi_rcn_standards_count ?? 0),
            'updated_at' => $guidelineSet->updated_at?->toIso8601String(),
            'edit_url' => $this->workspaceRoute($workspacePrefix, 'ref-guidelines.guideline-sets.edit', $guidelineSet),
            'destroy_url' => $this->workspaceRoute($workspacePrefix, 'ref-guidelines.guideline-sets.destroy', $guidelineSet),
        ];
    }

    private function valuationSettingRow(ValuationSetting $valuationSetting, string $workspacePrefix): array
    {
        $valuationSetting->loadMissing('guidelineSet:id,name,is_active');

        return [
            'id' => $valuationSetting->id,
            'guideline_set_name' => $valuationSetting->guidelineSet?->name ?? '-',
            'guideline_is_active' => (bool) ($valuationSetting->guidelineSet?->is_active ?? false),
            'year' => (int) $valuationSetting->year,
            'key' => $valuationSetting->key,
            'key_label' => ValuationSetting::labelForKey($valuationSetting->key),
            'label' => $valuationSetting->label,
            'value_number' => (float) ($valuationSetting->value_number ?? 0),
            'value_text' => $valuationSetting->value_text,
            'notes' => $valuationSetting->notes,
            'updated_at' => $valuationSetting->updated_at?->toIso8601String(),
            'edit_url' => $this->workspaceRoute($workspacePrefix, 'ref-guidelines.valuation-settings.edit', $valuationSetting),
            'destroy_url' => $this->workspaceRoute($workspacePrefix, 'ref-guidelines.valuation-settings.destroy', $valuationSetting),
        ];
    }

    private function valuationSettingKeyOptions(bool $includeAll = false): array
    {
        $options = collect(ValuationSetting::keyOptions())
            ->map(fn (string $label, string $value) => [
                'value' => $value,
                'label' => $label,
            ])
            ->values()
            ->all();

        if (! $includeAll) {
            return $options;
        }

        return [
            ['value' => 'all', 'label' => 'Semua Key'],
            ...$options,
        ];
    }

    private function valuationSettingYearOptions(): array
    {
        return ValuationSetting::query()
            ->distinct()
            ->orderByDesc('year')
            ->pluck('year')
            ->filter()
            ->values()
            ->map(fn (int|string $year) => [
                'value' => (string) $year,
                'label' => (string) $year,
            ])
            ->all();
    }

    private function activeGuideline(): ?GuidelineSet
    {
        return GuidelineSet::query()->where('is_active', true)->first();
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

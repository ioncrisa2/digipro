<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreGuidelineSetRequest;
use App\Http\Requests\Admin\StoreValuationSettingRequest;
use App\Models\ConstructionCostIndex;
use App\Models\GuidelineSet;
use App\Models\ValuationSetting;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Response;

class ReferenceGuideSettingsController extends Controller
{
    public function guidelineSetsIndex(Request $request): Response
    {
        $filters = [
            'q' => trim((string) $request->query('q', '')),
            'status' => (string) $request->query('status', 'all'),
        ];

        $records = GuidelineSet::query()
            ->withCount([
                'constructionCostIndexes',
                'costElements',
                'floorIndexes',
                'mappiRcnStandards',
            ])
            ->when($filters['q'] !== '', function ($query) use ($filters): void {
                $query->where(function ($innerQuery) use ($filters): void {
                    $innerQuery
                        ->where('name', 'like', '%' . $filters['q'] . '%')
                        ->orWhere('description', 'like', '%' . $filters['q'] . '%');
                });
            })
            ->when($filters['status'] === 'active', fn ($query) => $query->where('is_active', true))
            ->when($filters['status'] === 'inactive', fn ($query) => $query->where('is_active', false))
            ->orderByDesc('year')
            ->paginate(15)
            ->withQueryString();

        $records->through(fn (GuidelineSet $guidelineSet) => $this->transformGuidelineSetRow($guidelineSet));

        return inertia('Admin/GuidelineSets/Index', [
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
            'createUrl' => route('admin.ref-guidelines.guideline-sets.create'),
        ]);
    }

    public function guidelineSetsCreate(): Response
    {
        return inertia('Admin/GuidelineSets/Form', [
            'mode' => 'create',
            'record' => [
                'name' => '',
                'year' => (int) now()->format('Y'),
                'description' => '',
                'is_active' => false,
            ],
            'submitUrl' => route('admin.ref-guidelines.guideline-sets.store'),
            'indexUrl' => route('admin.ref-guidelines.guideline-sets.index'),
        ]);
    }

    public function guidelineSetsStore(StoreGuidelineSetRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        DB::transaction(function () use ($validated): void {
            if ((bool) ($validated['is_active'] ?? false)) {
                GuidelineSet::query()->update(['is_active' => false]);
            }

            GuidelineSet::query()->create([
                'name' => $validated['name'],
                'year' => $validated['year'],
                'description' => $validated['description'] ?? null,
                'is_active' => (bool) ($validated['is_active'] ?? false),
            ]);
        });

        return redirect()
            ->route('admin.ref-guidelines.guideline-sets.index')
            ->with('success', 'Guideline set berhasil ditambahkan.');
    }

    public function guidelineSetsEdit(GuidelineSet $guidelineSet): Response
    {
        return inertia('Admin/GuidelineSets/Form', [
            'mode' => 'edit',
            'record' => [
                'id' => $guidelineSet->id,
                'name' => $guidelineSet->name,
                'year' => $guidelineSet->year,
                'description' => $guidelineSet->description,
                'is_active' => (bool) $guidelineSet->is_active,
            ],
            'submitUrl' => route('admin.ref-guidelines.guideline-sets.update', $guidelineSet),
            'indexUrl' => route('admin.ref-guidelines.guideline-sets.index'),
        ]);
    }

    public function guidelineSetsUpdate(StoreGuidelineSetRequest $request, GuidelineSet $guidelineSet): RedirectResponse
    {
        $validated = $request->validated();

        DB::transaction(function () use ($validated, $guidelineSet): void {
            if ((bool) ($validated['is_active'] ?? false)) {
                GuidelineSet::query()
                    ->whereKeyNot($guidelineSet->id)
                    ->update(['is_active' => false]);
            }

            $guidelineSet->forceFill([
                'name' => $validated['name'],
                'year' => $validated['year'],
                'description' => $validated['description'] ?? null,
                'is_active' => (bool) ($validated['is_active'] ?? false),
            ])->save();
        });

        return redirect()
            ->route('admin.ref-guidelines.guideline-sets.index')
            ->with('success', 'Guideline set berhasil diperbarui.');
    }

    public function guidelineSetsDestroy(GuidelineSet $guidelineSet): RedirectResponse
    {
        try {
            $guidelineSet->delete();
        } catch (QueryException) {
            return redirect()
                ->route('admin.ref-guidelines.guideline-sets.index')
                ->with('error', 'Guideline set tidak bisa dihapus karena masih dipakai resource referensi lain.');
        }

        return redirect()
            ->route('admin.ref-guidelines.guideline-sets.index')
            ->with('success', 'Guideline set berhasil dihapus.');
    }

    public function valuationSettingsIndex(Request $request): Response
    {
        $filters = [
            'q' => trim((string) $request->query('q', '')),
            'guideline_set_id' => (string) $request->query('guideline_set_id', 'all'),
            'year' => (string) $request->query('year', 'all'),
            'key' => (string) $request->query('key', 'all'),
        ];

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
            ->paginate(15)
            ->withQueryString();

        $records->through(fn (ValuationSetting $valuationSetting) => $this->transformValuationSettingRow($valuationSetting));

        return inertia('Admin/ValuationSettings/Index', [
            'filters' => $filters,
            'guidelineSetOptions' => $this->guidelineSetOptions(includeAll: true),
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
            'createUrl' => route('admin.ref-guidelines.valuation-settings.create'),
        ]);
    }

    public function valuationSettingsCreate(): Response
    {
        $activeGuideline = GuidelineSet::query()->where('is_active', true)->first();

        return inertia('Admin/ValuationSettings/Form', [
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
            'guidelineSetOptions' => $this->guidelineSetOptions(),
            'keyOptions' => $this->valuationSettingKeyOptions(),
            'submitUrl' => route('admin.ref-guidelines.valuation-settings.store'),
            'indexUrl' => route('admin.ref-guidelines.valuation-settings.index'),
        ]);
    }

    public function valuationSettingsStore(StoreValuationSettingRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        ValuationSetting::query()->create([
            'guideline_set_id' => $validated['guideline_set_id'],
            'year' => $validated['year'],
            'key' => $validated['key'],
            'label' => $validated['label'],
            'value_number' => $validated['value_number'],
            'value_text' => $validated['value_text'] ?? null,
            'notes' => $validated['notes'] ?? null,
        ]);

        return redirect()
            ->route('admin.ref-guidelines.valuation-settings.index')
            ->with('success', 'Valuation setting berhasil ditambahkan.');
    }

    public function valuationSettingsEdit(ValuationSetting $valuationSetting): Response
    {
        return inertia('Admin/ValuationSettings/Form', [
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
            'guidelineSetOptions' => $this->guidelineSetOptions(),
            'keyOptions' => $this->valuationSettingKeyOptions(),
            'submitUrl' => route('admin.ref-guidelines.valuation-settings.update', $valuationSetting),
            'indexUrl' => route('admin.ref-guidelines.valuation-settings.index'),
        ]);
    }

    public function valuationSettingsUpdate(StoreValuationSettingRequest $request, ValuationSetting $valuationSetting): RedirectResponse
    {
        $validated = $request->validated();

        $valuationSetting->forceFill([
            'guideline_set_id' => $validated['guideline_set_id'],
            'year' => $validated['year'],
            'key' => $validated['key'],
            'label' => $validated['label'],
            'value_number' => $validated['value_number'],
            'value_text' => $validated['value_text'] ?? null,
            'notes' => $validated['notes'] ?? null,
        ])->save();

        return redirect()
            ->route('admin.ref-guidelines.valuation-settings.index')
            ->with('success', 'Valuation setting berhasil diperbarui.');
    }

    public function valuationSettingsDestroy(ValuationSetting $valuationSetting): RedirectResponse
    {
        $valuationSetting->delete();

        return redirect()
            ->route('admin.ref-guidelines.valuation-settings.index')
            ->with('success', 'Valuation setting berhasil dihapus.');
    }

    private function transformGuidelineSetRow(GuidelineSet $guidelineSet): array
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
            'edit_url' => route('admin.ref-guidelines.guideline-sets.edit', $guidelineSet),
            'destroy_url' => route('admin.ref-guidelines.guideline-sets.destroy', $guidelineSet),
        ];
    }

    private function transformValuationSettingRow(ValuationSetting $valuationSetting): array
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
            'edit_url' => route('admin.ref-guidelines.valuation-settings.edit', $valuationSetting),
            'destroy_url' => route('admin.ref-guidelines.valuation-settings.destroy', $valuationSetting),
        ];
    }

    private function guidelineSetOptions(bool $includeAll = false): array
    {
        $options = GuidelineSet::query()
            ->orderByDesc('year')
            ->get(['id', 'name', 'year', 'is_active'])
            ->map(fn (GuidelineSet $guidelineSet) => [
                'value' => (string) $guidelineSet->id,
                'label' => $guidelineSet->name . ' (' . $guidelineSet->year . ')' . ($guidelineSet->is_active ? ' · aktif' : ''),
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

    private function paginatedRecordsPayload(object $records): array
    {
        return [
            'data' => $records->items(),
            'meta' => [
                'from' => $records->firstItem(),
                'to' => $records->lastItem(),
                'total' => $records->total(),
                'links' => $records->linkCollection()->toArray(),
            ],
        ];
    }

    private function legacyGuidelineSetUrl(GuidelineSet $guidelineSet): ?string
    {
        return null;
    }

    private function legacyValuationSettingUrl(ValuationSetting $valuationSetting): ?string
    {
        return null;
    }
}

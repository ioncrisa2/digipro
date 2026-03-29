<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SaveIkkByProvinceRequest;
use App\Models\ConstructionCostIndex;
use App\Models\GuidelineSet;
use App\Models\Province;
use App\Models\Regency;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Response;

class IkkByProvinceController extends Controller
{
    public function index(Request $request): Response
    {
        $activeGuideline = GuidelineSet::query()->where('is_active', true)->first();

        $filters = [
            'guideline_set_id' => (string) ($request->query('guideline_set_id', $activeGuideline?->id ?? '')),
            'year' => (string) ($request->query('year', $activeGuideline?->year ?? now()->format('Y'))),
            'province_id' => (string) $request->query('province_id', ''),
        ];

        return inertia('Admin/IkkByProvince/Index', [
            'filters' => $filters,
            'guidelineSetOptions' => $this->guidelineSetOptions(),
            'provinceOptions' => $this->provinceOptions(),
            'items' => $this->items(
                $this->normalizeGuidelineId($filters['guideline_set_id']),
                $this->normalizeYear($filters['year']),
                $this->normalizeProvinceId($filters['province_id'])
            ),
            'submitUrl' => $this->workspaceRoute('ref-guidelines.ikk-by-province.save'),
        ]);
    }

    public function save(SaveIkkByProvinceRequest $request): RedirectResponse
    {
        $validated = $request->validated();
        $provinceId = (string) $validated['province_id'];
        $guidelineSetId = (int) $validated['guideline_set_id'];
        $year = (int) $validated['year'];

        $regencies = Regency::query()
            ->where('province_id', $provinceId)
            ->orderByRaw('CAST(id AS UNSIGNED) ASC')
            ->get(['id', 'name']);

        $regencyMap = $regencies->keyBy(fn (Regency $regency) => (string) $regency->id);

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

        return redirect()
            ->route($this->workspaceRouteName('ref-guidelines.ikk-by-province.index'), [
                'guideline_set_id' => $guidelineSetId,
                'year' => $year,
                'province_id' => $provinceId,
            ])
            ->with('success', 'IKK by Province berhasil disimpan.');
    }

    private function items(?int $guidelineSetId, ?int $year, ?string $provinceId): array
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

    private function guidelineSetOptions(): array
    {
        return GuidelineSet::query()
            ->orderByDesc('year')
            ->get(['id', 'name', 'year', 'is_active'])
            ->map(fn (GuidelineSet $guidelineSet) => [
                'value' => (string) $guidelineSet->id,
                'label' => $guidelineSet->name . ' (' . $guidelineSet->year . ')' . ($guidelineSet->is_active ? ' - aktif' : ''),
                'year' => (int) $guidelineSet->year,
            ])
            ->values()
            ->all();
    }

    private function provinceOptions(): array
    {
        return Province::query()
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (Province $province) => [
                'value' => (string) $province->id,
                'label' => $province->name,
            ])
            ->values()
            ->all();
    }

    private function normalizeGuidelineId(mixed $value): ?int
    {
        return is_numeric($value) ? (int) $value : null;
    }

    private function normalizeYear(mixed $value): ?int
    {
        return is_numeric($value) ? (int) $value : null;
    }

    private function normalizeProvinceId(mixed $value): ?string
    {
        $id = trim((string) $value);

        return $id === '' ? null : $id;
    }
}

<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\IkkByProvinceIndexRequest;
use App\Http\Requests\Admin\SaveIkkByProvinceRequest;
use App\Models\ConstructionCostIndex;
use App\Models\Regency;
use App\Support\Admin\ReferenceGuideData\ReferenceGuideOptionsProvider;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Inertia\Response;

class IkkByProvinceController extends Controller
{
    public function __construct(
        private readonly ReferenceGuideOptionsProvider $options,
    ) {
    }

    public function index(IkkByProvinceIndexRequest $request): Response
    {
        $filters = $request->filters();

        return inertia('Admin/IkkByProvince/Index', [
            'filters' => $filters,
            'guidelineSetOptions' => $this->options->guidelineSetOptions(),
            'provinceOptions' => $this->options->provinceSelectOptions(withCode: false),
            'items' => $this->items(
                $request->guidelineSetId(),
                $request->yearValue(),
                $request->provinceId()
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

}

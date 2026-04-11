<?php

namespace App\Http\Controllers\Reviewer;

use App\Http\Controllers\Controller;
use App\Http\Requests\Reviewer\ComparableIndexRequest;
use App\Http\Requests\Reviewer\SearchComparablesRequest;
use App\Http\Requests\Reviewer\SyncComparablesRequest;
use App\Http\Requests\Reviewer\UpdateComparableRequest;
use App\Models\AppraisalAsset;
use App\Models\AppraisalAssetComparable;
use App\Services\Reviewer\ComparableDataApi;
use App\Services\Reviewer\ReviewerWorkspaceService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

class ComparableController extends Controller
{
    public function __construct(
        private readonly ReviewerWorkspaceService $workspace,
    ) {
    }

    public function index(ComparableIndexRequest $request): Response
    {
        $filters = $request->filters();

        $baseQuery = AppraisalAssetComparable::query()
            ->with(['asset:id,appraisal_request_id,address', 'asset.request:id,request_number,status'])
            ->withCount('landAdjustments')
            ->whereHas('asset.request', fn (Builder $query): Builder => $query->whereIn('status', $this->workspace->reviewerStatuses()))
            ->when($filters['asset_id'], fn (Builder $query): Builder => $query->where('appraisal_asset_id', $filters['asset_id']))
            ->when($filters['is_selected'] !== '' && $filters['is_selected'] !== 'all', function (Builder $query) use ($filters): void {
                $query->where('is_selected', $filters['is_selected'] === '1');
            })
            ->when($filters['q'] !== '', function (Builder $query) use ($filters): void {
                $q = $filters['q'];
                $query->where(function (Builder $nested) use ($q): void {
                    $nested
                        ->where('external_id', 'like', "%{$q}%")
                        ->orWhere('raw_peruntukan', 'like', "%{$q}%")
                        ->orWhereHas('asset', fn (Builder $assetQuery): Builder => $assetQuery->where('address', 'like', "%{$q}%"));
                });
            })
            ->latest('updated_at');

        $comparables = (clone $baseQuery)
            ->orderBy('appraisal_asset_id')
            ->orderByRaw('COALESCE(`manual_rank`, `rank`, 9999)')
            ->paginate($filters['per_page'])
            ->withQueryString()
            ->through(fn (AppraisalAssetComparable $comparable): array => $this->workspace->serializeComparableListItem($comparable));

        $summaryBase = AppraisalAssetComparable::query()
            ->whereHas('asset.request', fn (Builder $query): Builder => $query->whereIn('status', $this->workspace->reviewerStatuses()));

        return Inertia::render('Reviewer/Comparables/Index', [
            'filters' => $filters,
            'summary' => [
                'total' => (clone $summaryBase)->count(),
                'dipakai' => (clone $summaryBase)->where('is_selected', true)->count(),
                'perlu_penyesuaian' => (clone $summaryBase)
                    ->where('is_selected', true)
                    ->where(function (Builder $query): void {
                        $query
                            ->whereNull('adjusted_unit_value')
                            ->orWhereNull('indication_value');
                    })
                    ->count(),
                'diperbarui_hari_ini' => (clone $summaryBase)->whereDate('updated_at', today())->count(),
            ],
            'records' => $this->paginatedRecordsPayload($comparables),
        ]);
    }

    public function show(AppraisalAssetComparable $comparable): Response
    {
        $comparable->load([
            'asset:id,appraisal_request_id,address',
            'asset.request:id,request_number,status',
            'landAdjustments.factor:id,code,name',
        ]);

        return Inertia::render('Reviewer/Comparables/Show', [
            'comparable' => $this->workspace->serializeComparableDetail($comparable),
        ]);
    }

    public function search(SearchComparablesRequest $request, AppraisalAsset $asset, ComparableDataApi $service): JsonResponse
    {
        $data = $request->validated();

        try {
            $items = $service->fetchSimilarForAsset($asset, (int) $data['limit'], (float) $data['range_km']);
        } catch (Throwable $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 422);
        }

        $existing = $asset->comparables()
            ->get(['external_id', 'is_selected', 'manual_rank'])
            ->keyBy(fn (AppraisalAssetComparable $comparable) => (string) $comparable->external_id);

        $results = collect($items)
            ->map(function (array $item) use ($existing): array {
                $externalId = (string) ($item['id'] ?? '');
                /** @var AppraisalAssetComparable|null $saved */
                $saved = $existing->get($externalId);

                return [
                    'id' => $externalId,
                    'price' => $item['harga'] ?? null,
                    'land_area' => $item['luas_tanah'] ?? null,
                    'building_area' => $item['luas_bangunan'] ?? null,
                    'address' => data_get($item, 'alamat_data') ?? data_get($item, 'alamat') ?? '-',
                    'source' => data_get($item, 'sumber') ?? data_get($item, 'source'),
                    'peruntukan' => data_get($item, 'peruntukan.name') ?? data_get($item, 'peruntukan.slug') ?? data_get($item, 'peruntukan'),
                    'score' => $item['score'] ?? null,
                    'distance' => $item['distance'] ?? null,
                    'priority_rank' => $item['priority_rank'] ?? null,
                    'image_url' => $item['image_url'] ?? null,
                    'is_saved' => $saved !== null,
                    'is_selected' => (bool) ($saved?->is_selected ?? false),
                    'manual_rank' => $saved?->manual_rank,
                    'raw' => $item,
                ];
            })
            ->values();

        return response()->json([
            'message' => 'Pencarian pembanding selesai.',
            'results' => $results,
        ]);
    }

    public function sync(SyncComparablesRequest $request, AppraisalAsset $asset, ComparableDataApi $service): JsonResponse
    {
        $items = $request->filteredItems();

        if ($items === []) {
            return response()->json(['message' => 'Belum ada pembanding dipilih.'], 422);
        }

        $created = DB::transaction(function () use ($asset, $items, $service): int {
            $asset->comparables()->delete();

            return $service->upsertComparables($asset, $items);
        });

        $asset->load([
            'comparables' => fn ($query) => $query
                ->withCount('landAdjustments')
                ->orderByRaw('COALESCE(`manual_rank`, `rank`, 9999)')
                ->orderBy('id'),
        ]);

        return response()->json([
            'message' => "Pembanding disimpan: {$created}.",
            'comparables' => $asset->comparables
                ->map(fn (AppraisalAssetComparable $comparable): array => $this->workspace->serializeComparableListItem($comparable))
                ->values(),
        ]);
    }

    public function update(UpdateComparableRequest $request, AppraisalAssetComparable $comparable): JsonResponse
    {
        $comparable->update($request->validated());

        return response()->json([
            'message' => 'Data pembanding diperbarui.',
            'comparable' => $this->workspace->serializeComparableDetail($comparable->fresh(['asset.request', 'landAdjustments.factor'])),
        ]);
    }
}

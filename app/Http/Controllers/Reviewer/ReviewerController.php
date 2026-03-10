<?php

namespace App\Http\Controllers\Reviewer;

use App\Enums\AppraisalStatusEnum;
use App\Http\Controllers\Controller;
use App\Models\AppraisalAsset;
use App\Models\AppraisalAssetComparable;
use App\Models\AppraisalRequest;
use App\Services\ComparableDataApi;
use App\Services\Reviewer\ReviewerWorkspaceService;
use App\Support\AppraisalAssetFieldOptions;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

class ReviewerController extends Controller
{
    public function __construct(
        private readonly ReviewerWorkspaceService $workspace,
    ) {}

    public function dashboard(): Response
    {
        $stats = [
            'ready_review' => AppraisalRequest::query()
                ->where('status', AppraisalStatusEnum::ContractSigned)
                ->count(),
            'in_progress' => AppraisalRequest::query()
                ->where('status', AppraisalStatusEnum::ValuationOnProgress)
                ->count(),
            'completed' => AppraisalRequest::query()
                ->where('status', AppraisalStatusEnum::ValuationCompleted)
                ->count(),
            'assets_need_adjustment' => AppraisalAsset::query()
                ->whereHas('request', function (Builder $query): void {
                    $query->whereIn('status', [
                        AppraisalStatusEnum::ContractSigned->value,
                        AppraisalStatusEnum::ValuationOnProgress->value,
                    ]);
                })
                ->whereHas('comparables', fn (Builder $query): Builder => $query->where('is_selected', true))
                ->where(function (Builder $query): void {
                    $query
                        ->whereNull('estimated_value_low')
                        ->orWhereNull('estimated_value_high')
                        ->orWhereNull('market_value_final');
                })
                ->count(),
        ];

        $queuePreview = AppraisalRequest::query()
            ->with('user:id,name')
            ->withCount('assets')
            ->whereIn('status', $this->workspace->reviewerStatuses())
            ->latest('requested_at')
            ->limit(6)
            ->get()
            ->map(fn (AppraisalRequest $record): array => $this->workspace->serializeReviewListItem($record))
            ->values();

        $assetPreview = AppraisalAsset::query()
            ->with(['request:id,request_number,status'])
            ->withCount([
                'comparables as selected_comparables_count' => fn (Builder $query): Builder => $query->where('is_selected', true),
            ])
            ->whereHas('request', fn (Builder $query): Builder => $query->whereIn('status', $this->workspace->reviewerStatuses()))
            ->latest('updated_at')
            ->limit(6)
            ->get()
            ->map(fn (AppraisalAsset $asset): array => $this->workspace->serializeAssetListItem($asset))
            ->values();

        $activityPreview = AppraisalAssetComparable::query()
            ->with(['asset:id,appraisal_request_id,address', 'asset.request:id,request_number,status'])
            ->withCount('landAdjustments')
            ->where('is_selected', true)
            ->whereDate('appraisal_assets_comparables.updated_at', Carbon::today())
            ->latest('updated_at')
            ->limit(8)
            ->get()
            ->map(fn (AppraisalAssetComparable $comparable): array => [
                'id' => $comparable->id,
                'external_id' => (string) $comparable->external_id,
                'request_number' => $comparable->asset?->request?->request_number ?? '-',
                'asset_address' => $comparable->asset?->address ?? '-',
                'adjustment_factors' => (int) $comparable->land_adjustments_count,
                'total_adjustment_percent' => $comparable->total_adjustment_percent,
                'adjusted_unit_value' => $comparable->adjusted_unit_value,
                'updated_at' => optional($comparable->updated_at)?->toDateTimeString(),
                'detail_url' => route('reviewer.comparables.show', $comparable),
                'adjustment_url' => route('reviewer.assets.adjustment', $comparable->appraisal_asset_id),
            ])
            ->values();

        return Inertia::render('Reviewer/Dashboard', [
            'stats' => $stats,
            'queuePreview' => $queuePreview,
            'assetPreview' => $assetPreview,
            'activityPreview' => $activityPreview,
        ]);
    }

    public function reviewsIndex(Request $request): Response
    {
        $filters = [
            'q' => trim((string) $request->string('q')),
            'status' => (string) $request->string('status', 'all'),
        ];

        $reviews = AppraisalRequest::query()
            ->with('user:id,name')
            ->withCount('assets')
            ->whereIn('status', $this->workspace->reviewerStatuses())
            ->when($filters['q'] !== '', function (Builder $query) use ($filters): void {
                $q = $filters['q'];
                $query->where(function (Builder $nested) use ($q): void {
                    $nested
                        ->where('request_number', 'like', "%{$q}%")
                        ->orWhere('client_name', 'like', "%{$q}%")
                        ->orWhereHas('user', fn (Builder $userQuery): Builder => $userQuery->where('name', 'like', "%{$q}%"));
                });
            })
            ->when($filters['status'] !== '' && $filters['status'] !== 'all', fn (Builder $query): Builder => $query->where('status', $filters['status']))
            ->latest('requested_at')
            ->paginate(12)
            ->withQueryString()
            ->through(fn (AppraisalRequest $record): array => $this->workspace->serializeReviewListItem($record));

        return Inertia::render('Reviewer/Reviews/Index', [
            'filters' => $filters,
            'statusOptions' => $this->workspace->reviewStatusOptions(),
            'reviews' => $reviews,
        ]);
    }

    public function reviewsShow(AppraisalRequest $review): Response
    {
        $review->load([
            'user:id,name,email',
            'assets.files:id,appraisal_asset_id,type,path,original_name,mime,size,created_at',
            'payments:id,appraisal_request_id,status,paid_at,updated_at,created_at',
        ])->loadCount('assets');

        return Inertia::render('Reviewer/Reviews/Show', [
            'review' => [
                'id' => $review->id,
                'request_number' => $review->request_number ?? ('REQ-' . $review->id),
                'status' => $this->workspace->statusPayload($review->status),
                'requested_at' => optional($review->requested_at)?->toDateTimeString(),
                'contract_number' => $review->contract_number,
                'client_name' => $review->client_name ?: ($review->user?->name ?? '-'),
                'client_email' => $review->user?->email ?? '-',
                'client_address' => $review->client_address,
                'notes' => $review->notes,
                'fee_total' => $review->fee_total,
                'assets_count' => (int) $review->assets_count,
                'latest_payment_status' => $this->workspace->paymentStatusLabel($review->payments->sortByDesc('id')->first()?->status),
                'assets' => $review->assets->map(fn (AppraisalAsset $asset): array => $this->workspace->serializeAssetDetail($asset))->values(),
                'files' => $review->assets
                    ->flatMap(fn (AppraisalAsset $asset) => $asset->files->map(fn ($file): array => $this->workspace->serializeAssetFile($file, $asset)))
                    ->values(),
            ],
        ]);
    }

    public function assetsIndex(Request $request): Response
    {
        $filters = [
            'q' => trim((string) $request->string('q')),
            'status' => (string) $request->string('status', 'all'),
            'needs_adjustment' => $request->boolean('needs_adjustment', false),
        ];

        $assets = AppraisalAsset::query()
            ->with(['request:id,request_number,status'])
            ->withCount([
                'comparables',
                'comparables as selected_comparables_count' => fn (Builder $query): Builder => $query->where('is_selected', true),
            ])
            ->whereHas('request', function (Builder $query) use ($filters): void {
                $query->whereIn('status', $this->workspace->reviewerStatuses());

                if ($filters['status'] !== '' && $filters['status'] !== 'all') {
                    $query->where('status', $filters['status']);
                }
            })
            ->when($filters['q'] !== '', function (Builder $query) use ($filters): void {
                $q = $filters['q'];
                $query->where(function (Builder $nested) use ($q): void {
                    $nested
                        ->where('address', 'like', "%{$q}%")
                        ->orWhereHas('request', fn (Builder $requestQuery): Builder => $requestQuery->where('request_number', 'like', "%{$q}%"));
                });
            })
            ->when($filters['needs_adjustment'], function (Builder $query): void {
                $query
                    ->whereHas('comparables', fn (Builder $nested): Builder => $nested->where('is_selected', true))
                    ->where(function (Builder $nested): void {
                        $nested
                            ->whereNull('estimated_value_low')
                            ->orWhereNull('estimated_value_high')
                            ->orWhereNull('market_value_final');
                    });
            })
            ->latest('updated_at')
            ->paginate(12)
            ->withQueryString()
            ->through(fn (AppraisalAsset $asset): array => $this->workspace->serializeAssetListItem($asset));

        return Inertia::render('Reviewer/Assets/Index', [
            'filters' => $filters,
            'statusOptions' => $this->workspace->reviewStatusOptions(),
            'assets' => $assets,
        ]);
    }

    public function assetsShow(AppraisalAsset $asset): Response
    {
        $asset->load([
            'request:id,request_number,status,requested_at',
            'files:id,appraisal_asset_id,type,path,original_name,mime,size,created_at',
            'comparables' => fn ($query) => $query
                ->withCount('landAdjustments')
                ->orderByRaw('COALESCE(`manual_rank`, `rank`, 9999)')
                ->orderBy('id'),
        ]);

        return Inertia::render('Reviewer/Assets/Show', [
            'asset' => $this->workspace->serializeAssetDetail($asset, includeComparables: true, includeFiles: true),
            'fieldOptions' => [
                'usageOptions' => AppraisalAssetFieldOptions::usageOptions(),
                'titleDocumentOptions' => AppraisalAssetFieldOptions::titleDocumentOptions(),
                'landShapeOptions' => AppraisalAssetFieldOptions::landShapeOptions(),
                'landPositionOptions' => AppraisalAssetFieldOptions::landPositionOptions(),
                'landConditionOptions' => AppraisalAssetFieldOptions::landConditionOptions(),
                'topographyOptions' => AppraisalAssetFieldOptions::topographyOptions(),
            ],
            'searchDefaults' => [
                'range_km' => (float) config('comparable.default_range_km', 10),
                'limit' => (int) config('comparable.default_limit', 100),
            ],
        ]);
    }

    public function assetsAdjustment(AppraisalAsset $asset): Response
    {
        return Inertia::render('Reviewer/Assets/Adjustment', [
            'asset' => [
                'id' => $asset->id,
                'address' => $asset->address,
                'request_number' => $asset->request?->request_number,
                'adjustment_save_url' => route('reviewer.api.assets.adjustment.save', $asset),
                'adjustment_preview_url' => route('reviewer.api.assets.adjustment.preview', $asset),
            ],
            'workbench' => $this->workspace->makeAdjustmentWorkbench($asset->id)->exportReviewerPayload(),
        ]);
    }

    public function comparablesIndex(Request $request): Response
    {
        $filters = [
            'q' => trim((string) $request->string('q')),
            'asset_id' => $request->integer('asset_id') ?: null,
            'is_selected' => $request->string('is_selected', 'all')->toString(),
        ];

        $comparables = AppraisalAssetComparable::query()
            ->with(['asset:id,appraisal_request_id,address', 'asset.request:id,request_number,status'])
            ->withCount('landAdjustments')
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
            ->orderBy('appraisal_asset_id')
            ->orderByRaw('COALESCE(`manual_rank`, `rank`, 9999)')
            ->orderByDesc('updated_at')
            ->paginate(15)
            ->withQueryString()
            ->through(fn (AppraisalAssetComparable $comparable): array => $this->workspace->serializeComparableListItem($comparable));

        return Inertia::render('Reviewer/Comparables/Index', [
            'filters' => $filters,
            'comparables' => $comparables,
        ]);
    }

    public function comparablesShow(AppraisalAssetComparable $comparable): Response
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

    public function startReview(AppraisalRequest $review): JsonResponse
    {
        if (($review->status?->value ?? (string) $review->status) === AppraisalStatusEnum::ContractSigned->value) {
            $review->update(['status' => AppraisalStatusEnum::ValuationOnProgress]);
        }

        return response()->json([
            'message' => 'Status request berubah menjadi Proses Valuasi Berjalan.',
            'review' => [
                'id' => $review->id,
                'status' => $this->workspace->statusPayload($review->fresh()->status),
            ],
        ]);
    }

    public function finishReview(AppraisalRequest $review): JsonResponse
    {
        if (($review->status?->value ?? (string) $review->status) === AppraisalStatusEnum::ValuationOnProgress->value) {
            $review->update(['status' => AppraisalStatusEnum::ValuationCompleted]);
        }

        return response()->json([
            'message' => 'Status request berubah menjadi Proses Valuasi Selesai.',
            'review' => [
                'id' => $review->id,
                'status' => $this->workspace->statusPayload($review->fresh()->status),
            ],
        ]);
    }

    public function updateGeneralData(Request $request, AppraisalAsset $asset): JsonResponse
    {
        $data = $request->validate([
            'peruntukan' => ['required', 'string', Rule::in(array_column(AppraisalAssetFieldOptions::usageOptions(), 'value'))],
            'title_document' => ['required', 'string', Rule::in(array_column(AppraisalAssetFieldOptions::titleDocumentOptions(), 'value'))],
            'land_shape' => ['required', 'string', Rule::in(array_column(AppraisalAssetFieldOptions::landShapeOptions(), 'value'))],
            'land_position' => ['required', 'string', Rule::in(array_column(AppraisalAssetFieldOptions::landPositionOptions(), 'value'))],
            'land_condition' => ['required', 'string', Rule::in(array_column(AppraisalAssetFieldOptions::landConditionOptions(), 'value'))],
            'topography' => ['required', 'string', Rule::in(array_column(AppraisalAssetFieldOptions::topographyOptions(), 'value'))],
            'frontage_width' => ['required', 'numeric', 'min:0'],
            'access_road_width' => ['required', 'numeric', 'min:0'],
            'build_year' => ['nullable', 'integer', 'min:1900', 'max:' . now()->year],
        ]);

        $asset->update($data);

        return response()->json([
            'message' => 'Data umum aset diperbarui.',
            'asset' => $this->workspace->serializeAssetDetail($asset->fresh(['request', 'comparables'])),
        ]);
    }

    public function searchComparables(Request $request, AppraisalAsset $asset, ComparableDataApi $service): JsonResponse
    {
        $data = $request->validate([
            'range_km' => ['required', 'numeric', 'min:0.1', 'max:100'],
            'limit' => ['required', 'integer', 'min:1', 'max:200'],
        ]);

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

    public function syncComparables(Request $request, AppraisalAsset $asset, ComparableDataApi $service): JsonResponse
    {
        $data = $request->validate([
            'items' => ['required', 'array', 'min:1'],
            'items.*' => ['array'],
        ]);

        $items = collect($data['items'])
            ->filter(fn ($item): bool => is_array($item) && isset($item['id']))
            ->values()
            ->all();

        if (empty($items)) {
            return response()->json(['message' => 'Belum ada pembanding dipilih.'], 422);
        }

        $asset->comparables()->delete();
        $created = $service->upsertComparables($asset, $items);

        $asset->load([
            'comparables' => fn ($query) => $query
                ->withCount('landAdjustments')
                ->orderByRaw('COALESCE(`manual_rank`, `rank`, 9999)')
                ->orderBy('id'),
        ]);

        return response()->json([
            'message' => "Pembanding disimpan: {$created}.",
            'comparables' => $asset->comparables->map(fn (AppraisalAssetComparable $comparable): array => $this->workspace->serializeComparableListItem($comparable))->values(),
        ]);
    }

    public function updateComparable(Request $request, AppraisalAssetComparable $comparable): JsonResponse
    {
        $data = $request->validate([
            'is_selected' => ['required', 'boolean'],
            'manual_rank' => ['nullable', 'integer', 'min:1', 'max:999'],
        ]);

        $comparable->update($data);

        return response()->json([
            'message' => 'Data pembanding diperbarui.',
            'comparable' => $this->workspace->serializeComparableDetail($comparable->fresh(['asset.request', 'landAdjustments.factor'])),
        ]);
    }

    public function previewAdjustment(Request $request, AppraisalAsset $asset): JsonResponse
    {
        $data = $request->validate([
            'adjustment_inputs' => ['nullable', 'array'],
            'custom_adjustment_factors' => ['nullable', 'array'],
        ]);

        $workbench = $this->workspace->makeAdjustmentWorkbench($asset->id);
        $workbench->syncClientState(
            $data['adjustment_inputs'] ?? [],
            $data['custom_adjustment_factors'] ?? [],
        );

        return response()->json([
            'message' => 'Preview adjustment diperbarui.',
            'state' => $workbench->exportReviewerPayload(),
        ]);
    }

    public function saveAdjustment(Request $request, AppraisalAsset $asset): JsonResponse
    {
        $data = $request->validate([
            'adjustment_inputs' => ['nullable', 'array'],
            'custom_adjustment_factors' => ['nullable', 'array'],
        ]);

        try {
            $workbench = $this->workspace->makeAdjustmentWorkbench($asset->id);
            $workbench->syncClientState(
                $data['adjustment_inputs'] ?? [],
                $data['custom_adjustment_factors'] ?? [],
            );

            $result = $workbench->persistAdjustmentData();
        } catch (Throwable $e) {
            return response()->json([
                'message' => $e->getMessage() ?: 'Gagal menyimpan adjustment.',
            ], 422);
        }

        return response()->json([
            'message' => 'Adjustment berhasil disimpan.',
            'result' => $result,
        ]);
    }

}

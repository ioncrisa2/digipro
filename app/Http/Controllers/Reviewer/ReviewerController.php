<?php

namespace App\Http\Controllers\Reviewer;

use App\Enums\AppraisalStatusEnum;
use App\Http\Controllers\Controller;
use App\Models\AppraisalAsset;
use App\Models\AppraisalAssetComparable;
use App\Models\AppraisalRequest;
use App\Services\ComparableDataApi;
use App\Services\Reviewer\BtbWorksheetEngine;
use App\Services\Reviewer\BtbValuationPersistenceService;
use App\Services\Reviewer\ReviewerWorkspaceService;
use App\Support\AppraisalAssetFieldOptions;
use App\Support\ReviewerBtbCatalog;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

class ReviewerController extends Controller
{
    public function __construct(
        private readonly ReviewerWorkspaceService $workspace,
        private readonly BtbWorksheetEngine $btbEngine,
        private readonly BtbValuationPersistenceService $btbPersistence,
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
                    ->flatMap(fn (AppraisalAsset $asset) => $this->workspace->serializeActiveAssetFiles($asset))
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
        $asset->loadMissing(['request.guidelineSet', 'ikkRef']);

        return Inertia::render('Reviewer/Assets/Adjustment', [
            'asset' => [
                'id' => $asset->id,
                'address' => $asset->address,
                'request_number' => $asset->request?->request_number,
                'peruntukan' => $asset->peruntukan,
                'land_area' => $asset->land_area,
                'building_area' => $asset->building_area,
                'building_floors' => $asset->building_floors,
                'build_year' => $asset->build_year,
                'market_value_final' => $asset->market_value_final,
                'adjustment_save_url' => route('reviewer.api.assets.adjustment.save', $asset),
                'adjustment_preview_url' => route('reviewer.api.assets.adjustment.preview', $asset),
                'detail_url' => route('reviewer.assets.show', $asset),
            ],
            'workbench' => $this->workspace->makeAdjustmentWorkbench($asset->id)->exportReviewerPayload(),
        ]);
    }

    public function assetsBtb(AppraisalAsset $asset): Response
    {
        $this->ensureBtbAsset($asset);
        $asset->loadMissing(['request.guidelineSet', 'ikkRef']);

        return Inertia::render('Reviewer/Assets/Btb', [
            'asset' => [
                'id' => $asset->id,
                'address' => $asset->address,
                'request_number' => $asset->request?->request_number,
                'peruntukan' => $asset->peruntukan,
                'land_area' => $asset->land_area,
                'building_area' => $asset->building_area,
                'building_floors' => $asset->building_floors,
                'build_year' => $asset->build_year,
                'market_value_final' => $asset->market_value_final,
                'btb_save_url' => route('reviewer.api.assets.btb.save', $asset),
                'btb_preview_url' => route('reviewer.api.assets.btb.preview', $asset),
                'detail_url' => route('reviewer.assets.show', $asset),
                'land_adjustment_url' => route('reviewer.assets.adjustment', $asset),
            ],
            'btb' => $this->buildBtbPayload($asset),
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
            'general_inputs' => ['nullable', 'array'],
            'general_inputs.*.assumed_discount' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'general_inputs.*.material_quality_adj' => ['nullable', 'numeric', 'min:0.01', 'max:10'],
            'general_inputs.*.maintenance_adj_delta' => ['nullable', 'numeric', 'min:-100', 'max:100'],
        ]);

        $workbench = $this->workspace->makeAdjustmentWorkbench($asset->id);
        $workbench->syncClientState(
            $data['adjustment_inputs'] ?? [],
            $data['custom_adjustment_factors'] ?? [],
            $data['general_inputs'] ?? [],
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
            'general_inputs' => ['nullable', 'array'],
            'general_inputs.*.assumed_discount' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'general_inputs.*.material_quality_adj' => ['nullable', 'numeric', 'min:0.01', 'max:10'],
            'general_inputs.*.maintenance_adj_delta' => ['nullable', 'numeric', 'min:-100', 'max:100'],
        ]);

        try {
            $workbench = $this->workspace->makeAdjustmentWorkbench($asset->id);
            $workbench->syncClientState(
                $data['adjustment_inputs'] ?? [],
                $data['custom_adjustment_factors'] ?? [],
                $data['general_inputs'] ?? [],
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

    public function previewBtb(Request $request, AppraisalAsset $asset): JsonResponse
    {
        $this->ensureBtbAsset($asset);
        $data = $this->validateBtbInput($request);

        return response()->json([
            'message' => 'Preview BTB diperbarui.',
            'btb' => $this->buildBtbPayload($asset, $data['btb_input'] ?? []),
        ]);
    }

    public function saveBtb(Request $request, AppraisalAsset $asset): JsonResponse
    {
        $this->ensureBtbAsset($asset);
        $data = $this->validateBtbInput($request);
        $payload = $this->buildBtbPayload($asset, $data['btb_input'] ?? []);

        if (! data_get($payload, 'state')) {
            return response()->json([
                'message' => 'Worksheet BTB belum memiliki hasil perhitungan yang bisa disimpan.',
            ], 422);
        }

        $result = $this->btbPersistence->persist($asset, (array) $payload['state']);

        return response()->json([
            'message' => 'Worksheet BTB berhasil disimpan.',
            'result' => [
                'btb' => $this->buildBtbPayload($asset->fresh(['request.guidelineSet', 'ikkRef', 'buildingValuation']), $data['btb_input'] ?? []),
                'asset_values' => [
                    'building_value_final' => $result['asset']->building_value_final,
                    'estimated_value_low' => $result['asset']->estimated_value_low,
                    'estimated_value_high' => $result['asset']->estimated_value_high,
                    'market_value_final' => $result['asset']->market_value_final,
                ],
            ],
        ]);
    }

    private function buildBtbPayload(AppraisalAsset $asset, array $input = []): array
    {
        $asset->loadMissing(['request.guidelineSet', 'ikkRef']);

        $usage = $asset->peruntukan;
        $hasBuilding = (float) ($asset->building_area ?? 0) > 0;

        if (! $hasBuilding) {
            return [
                'enabled' => false,
                'reason' => 'Aset ini belum memiliki data bangunan untuk worksheet BTB.',
                'templates' => [],
                'input' => [],
                'state' => null,
            ];
        }

        $guidelineSetId = (int) ($asset->request?->guideline_set_id ?: 0);
        $guidelineYear = (int) ($asset->request?->guidelineSet?->year ?: now()->year);
        $defaultTemplateKey = ReviewerBtbCatalog::defaultTemplateForUsage($usage);
        $templateKey = $this->nonEmptyString(Arr::get($input, 'template_key')) ?? $defaultTemplateKey;
        $template = $templateKey ? ReviewerBtbCatalog::template($templateKey) : null;
        $buildingClass = $this->nonEmptyString(Arr::get($input, 'building_class'))
            ?? ($template['mappi_building_class'] ?? null);
        $floorCount = $this->nullableInt(Arr::get($input, 'floor_count'))
            ?? $asset->building_floors
            ?? ($template['default_floor_count'] ?? null);
        $buildingArea = $this->nullableFloat(Arr::get($input, 'building_area')) ?? $asset->building_area;
        $landArea = $this->nullableFloat(Arr::get($input, 'land_area')) ?? $asset->land_area;
        $effectiveAge = $this->nullableFloat(Arr::get($input, 'effective_age'));
        if ($effectiveAge === null && $asset->build_year) {
            $effectiveAge = max(0, $guidelineYear - (int) $asset->build_year);
        }

        $engineInput = [
            'guideline_set_id' => $guidelineSetId,
            'year' => $guidelineYear,
            'usage' => $usage,
            'template_key' => $templateKey,
            'building_class' => $buildingClass,
            'floor_count' => $floorCount,
            'building_area' => $buildingArea,
            'land_area' => $landArea,
            'effective_age' => $effectiveAge,
            'material_quality_adjustment' => $this->nullableFloat(Arr::get($input, 'material_quality_adjustment')) ?? 1.0,
            'maintenance_adjustment_factor' => $this->nullableFloat(Arr::get($input, 'maintenance_adjustment_factor')) ?? 0.0,
            'market_value' => $this->nullableFloat(Arr::get($input, 'market_value')) ?? $asset->market_value_final,
            'region_code' => $asset->regency_id,
            'ikk_value' => $asset->ikk_value_used,
            'subject_overrides' => (array) Arr::get($input, 'subject_overrides', []),
        ];

        try {
            $state = $guidelineSetId > 0 ? $this->btbEngine->build($engineInput) : null;
        } catch (Throwable $e) {
            $state = null;
        }

        return [
            'enabled' => true,
            'reason' => null,
            'mode' => 'preview',
            'note' => 'Worksheet BTB sudah bisa dipreview dari reviewer. Penyimpanan permanen ke valuation record akan disambungkan pada fase berikutnya.',
            'templates' => collect(ReviewerBtbCatalog::candidateTemplatesForUsage($usage))
                ->map(fn (string $key): array => [
                    'value' => $key,
                    'label' => ReviewerBtbCatalog::template($key)['label'] ?? $key,
                ])
                ->values()
                ->all(),
            'input' => [
                'template_key' => $templateKey,
                'building_class' => $buildingClass,
                'floor_count' => $floorCount,
                'building_area' => $buildingArea,
                'land_area' => $landArea,
                'effective_age' => $effectiveAge,
                'material_quality_adjustment' => $engineInput['material_quality_adjustment'],
                'maintenance_adjustment_factor' => $engineInput['maintenance_adjustment_factor'],
                'market_value' => $engineInput['market_value'],
                'subject_overrides' => $engineInput['subject_overrides'],
            ],
            'state' => $state,
        ];
    }

    private function validateBtbInput(Request $request): array
    {
        return $request->validate([
            'btb_input' => ['nullable', 'array'],
            'btb_input.template_key' => ['nullable', 'string'],
            'btb_input.building_class' => ['nullable', 'string'],
            'btb_input.floor_count' => ['nullable', 'integer', 'min:1', 'max:200'],
            'btb_input.building_area' => ['nullable', 'numeric', 'min:0'],
            'btb_input.land_area' => ['nullable', 'numeric', 'min:0'],
            'btb_input.effective_age' => ['nullable', 'numeric', 'min:0'],
            'btb_input.material_quality_adjustment' => ['nullable', 'numeric', 'min:0.01', 'max:10'],
            'btb_input.maintenance_adjustment_factor' => ['nullable', 'numeric', 'min:-1', 'max:1'],
            'btb_input.market_value' => ['nullable', 'numeric', 'min:0'],
            'btb_input.subject_overrides' => ['nullable', 'array'],
        ]);
    }

    private function ensureBtbAsset(AppraisalAsset $asset): void
    {
        abort_unless($this->workspace->assetHasBtb($asset), 404);
    }

    private function nonEmptyString(mixed $value): ?string
    {
        $text = trim((string) $value);

        return $text === '' ? null : $text;
    }

    private function nullableFloat(mixed $value): ?float
    {
        return is_numeric($value) ? (float) $value : null;
    }

    private function nullableInt(mixed $value): ?int
    {
        return is_numeric($value) ? (int) $value : null;
    }

}

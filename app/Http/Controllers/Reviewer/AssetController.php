<?php

namespace App\Http\Controllers\Reviewer;

use App\Http\Controllers\Controller;
use App\Http\Requests\Reviewer\AssetIndexRequest;
use App\Http\Requests\Reviewer\UpdateAssetGeneralDataRequest;
use App\Models\AppraisalAsset;
use App\Services\Reviewer\BtbPayloadBuilder;
use App\Services\Reviewer\ReviewerWorkspaceService;
use App\Support\AppraisalAssetFieldOptions;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Inertia\Inertia;
use Inertia\Response;

class AssetController extends Controller
{
    public function __construct(
        private readonly ReviewerWorkspaceService $workspace,
        private readonly BtbPayloadBuilder $btbPayloadBuilder,
    ) {
    }

    public function index(AssetIndexRequest $request): Response
    {
        $filters = $request->filters();

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

    public function show(AppraisalAsset $asset): Response
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

    public function adjustment(AppraisalAsset $asset): Response
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

    public function btb(AppraisalAsset $asset): Response
    {
        abort_unless($this->workspace->assetHasBtb($asset), 404);
        $asset->loadMissing(['request.guidelineSet', 'ikkRef', 'buildingValuation']);

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
                'renovation_year' => $asset->renovation_year,
                'market_value_final' => $asset->market_value_final,
                'btb_save_url' => route('reviewer.api.assets.btb.save', $asset),
                'btb_preview_url' => route('reviewer.api.assets.btb.preview', $asset),
                'detail_url' => route('reviewer.assets.show', $asset),
                'land_adjustment_url' => route('reviewer.assets.adjustment', $asset),
            ],
            'btb' => $this->btbPayloadBuilder->build($asset),
        ]);
    }

    public function updateGeneralData(UpdateAssetGeneralDataRequest $request, AppraisalAsset $asset): JsonResponse
    {
        $asset->update($request->validated());

        return response()->json([
            'message' => 'Data umum aset diperbarui.',
            'asset' => $this->workspace->serializeAssetDetail($asset->fresh(['request', 'comparables'])),
        ]);
    }
}

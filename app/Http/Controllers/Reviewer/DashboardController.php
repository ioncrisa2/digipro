<?php

namespace App\Http\Controllers\Reviewer;

use App\Enums\AppraisalStatusEnum;
use App\Http\Controllers\Controller;
use App\Models\AppraisalAsset;
use App\Models\AppraisalAssetComparable;
use App\Models\AppraisalRequest;
use App\Services\Reviewer\ReviewerWorkspaceService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __construct(
        private readonly ReviewerWorkspaceService $workspace,
    ) {
    }

    public function __invoke(): Response
    {
        $reviewerStatuses = $this->workspace->reviewerStatuses();
        $requestBase = AppraisalRequest::query()->whereIn('status', $reviewerStatuses);
        $assetBase = AppraisalAsset::query()
            ->whereHas('request', fn (Builder $query): Builder => $query->whereIn('status', $reviewerStatuses));
        $selectedComparableBase = AppraisalAssetComparable::query()
            ->where('is_selected', true)
            ->whereHas('asset.request', fn (Builder $query): Builder => $query->whereIn('status', $reviewerStatuses));

        $stats = [
            'ready_review' => (clone $requestBase)->where('status', AppraisalStatusEnum::ContractSigned)->count(),
            'in_progress' => (clone $requestBase)->where('status', AppraisalStatusEnum::ValuationOnProgress)->count(),
            'completed' => AppraisalRequest::query()
                ->whereIn('status', [
                    AppraisalStatusEnum::ValuationCompleted,
                    AppraisalStatusEnum::PreviewReady,
                    AppraisalStatusEnum::ReportPreparation,
                    AppraisalStatusEnum::ReportReady,
                    AppraisalStatusEnum::Completed,
                ])->count(),
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
            'total_queue' => (clone $requestBase)->count(),
            'comparables_touched_today' => (clone $selectedComparableBase)->whereDate('updated_at', Carbon::today())->count(),
        ];

        $queuePreview = AppraisalRequest::query()
            ->with('user:id,name')
            ->withCount('assets')
            ->whereIn('status', $reviewerStatuses)
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
            ->whereHas('request', fn (Builder $query): Builder => $query->whereIn('status', $reviewerStatuses))
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

        $featuredReview = AppraisalRequest::query()
            ->with('user:id,name')
            ->withCount('assets')
            ->whereIn('status', $reviewerStatuses)
            ->orderByRaw("CASE
                WHEN status = ? THEN 0
                WHEN status = ? THEN 1
                WHEN status = ? THEN 2
                ELSE 3
            END", [
                AppraisalStatusEnum::ContractSigned->value,
                AppraisalStatusEnum::ValuationOnProgress->value,
                AppraisalStatusEnum::ValuationCompleted->value,
            ])
            ->latest('requested_at')
            ->first();

        $focusSummary = [
            'aset_aktif' => (clone $assetBase)->count(),
            'aset_sudah_ada_range' => (clone $assetBase)
                ->whereNotNull('estimated_value_low')
                ->whereNotNull('estimated_value_high')
                ->count(),
            'aset_sudah_nilai_final' => (clone $assetBase)
                ->whereNotNull('market_value_final')
                ->count(),
            'selected_comparables' => (clone $selectedComparableBase)->count(),
        ];

        return Inertia::render('Reviewer/Dashboard', [
            'stats' => $stats,
            'featuredReview' => $featuredReview ? $this->workspace->serializeReviewListItem($featuredReview) : null,
            'focusSummary' => $focusSummary,
            'queuePreview' => $queuePreview,
            'assetPreview' => $assetPreview,
            'activityPreview' => $activityPreview,
        ]);
    }
}

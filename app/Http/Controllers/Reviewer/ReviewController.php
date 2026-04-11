<?php

namespace App\Http\Controllers\Reviewer;

use App\Enums\AppraisalStatusEnum;
use App\Http\Controllers\Controller;
use App\Http\Requests\Reviewer\ReviewIndexRequest;
use App\Http\Requests\Reviewer\ReviewerActionRequest;
use App\Models\AppraisalAsset;
use App\Models\AppraisalRequest;
use App\Notifications\AppraisalStatusUpdated;
use App\Services\Workflow\AppraisalMarketPreviewService;
use App\Services\Reviewer\ReviewerWorkspaceService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

class ReviewController extends Controller
{
    public function __construct(
        private readonly ReviewerWorkspaceService $workspace,
        private readonly AppraisalMarketPreviewService $marketPreviewService,
    ) {
    }

    public function index(ReviewIndexRequest $request): Response
    {
        $filters = $request->filters();

        $baseQuery = AppraisalRequest::query()
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
            ->when($filters['status'] !== '' && $filters['status'] !== 'all', fn (Builder $query): Builder => $query->where('status', $filters['status']));

        $reviews = (clone $baseQuery)
            ->latest('requested_at')
            ->paginate($filters['per_page'])
            ->withQueryString()
            ->through(fn (AppraisalRequest $record): array => $this->workspace->serializeReviewListItem($record));

        $summaryBase = AppraisalRequest::query()->whereIn('status', $this->workspace->reviewerStatuses());

        return Inertia::render('Reviewer/Reviews/Index', [
            'filters' => $filters,
            'statusOptions' => $this->workspace->reviewStatusOptions(),
            'summary' => [
                'total' => (clone $summaryBase)->count(),
                'siap_review' => (clone $summaryBase)->where('status', AppraisalStatusEnum::ContractSigned)->count(),
                'sedang_review' => (clone $summaryBase)->where('status', AppraisalStatusEnum::ValuationOnProgress)->count(),
                'siap_preview' => (clone $summaryBase)->where('status', AppraisalStatusEnum::ValuationCompleted)->count(),
                'total_aset' => AppraisalAsset::query()
                    ->whereHas('request', fn (Builder $query): Builder => $query->whereIn('status', $this->workspace->reviewerStatuses()))
                    ->count(),
            ],
            'records' => $this->paginatedRecordsPayload($reviews),
        ]);
    }

    public function show(AppraisalRequest $review): Response
    {
        $review->load([
            'user:id,name,email',
            'assets:id,appraisal_request_id,address',
            'payments:id,appraisal_request_id,status,paid_at,updated_at,created_at',
        ])->loadCount('assets');

        /** @var AppraisalAsset|null $primaryAsset */
        $primaryAsset = $review->assets->sortBy('id')->first();

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
                'primary_asset_url' => $primaryAsset ? route('reviewer.assets.show', $primaryAsset) : null,
                'primary_asset_address' => $primaryAsset?->address,
            ],
        ]);
    }

    public function start(ReviewerActionRequest $request, AppraisalRequest $review): JsonResponse
    {
        if ($review->status === AppraisalStatusEnum::ContractSigned) {
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

    public function finish(ReviewerActionRequest $request, AppraisalRequest $review): JsonResponse
    {
        $freshReview = $review;

        if ($review->status === AppraisalStatusEnum::ValuationOnProgress) {
            $oldStatus = $review->status?->label() ?? AppraisalStatusEnum::ValuationOnProgress->label();

            try {
                $this->marketPreviewService->publishPreview($review);
            } catch (Throwable $exception) {
                return response()->json([
                    'message' => $exception->getMessage() ?: 'Gagal mempublikasikan preview kajian pasar.',
                ], 422);
            }

            $freshReview = $review->fresh(['user']);
            if ($freshReview?->user) {
                $freshReview->user->notify(new AppraisalStatusUpdated(
                    appraisalId: (int) $freshReview->id,
                    requestNumber: (string) ($freshReview->request_number ?? ('REQ-' . $freshReview->id)),
                    oldStatus: $oldStatus,
                    newStatus: AppraisalStatusEnum::PreviewReady->label(),
                ));
            }
        }

        return response()->json([
            'message' => 'Preview hasil kajian pasar berhasil dikirim ke customer.',
            'review' => [
                'id' => $freshReview->id,
                'status' => $this->workspace->statusPayload($freshReview->status),
            ],
        ]);
    }
}

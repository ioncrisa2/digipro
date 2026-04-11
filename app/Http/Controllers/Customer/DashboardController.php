<?php

namespace App\Http\Controllers\Customer;

use Carbon\Carbon;
use App\Enums\AppraisalStatusEnum;
use App\Http\Controllers\Controller;
use App\Http\Requests\Customer\CustomerAccessRequest;
use App\Models\AppraisalRequest;
use App\Services\Customer\Payloads\AppraisalPreviewStateBuilder;
use App\Services\Customer\Payloads\AppraisalProgressSummaryBuilder;
use App\Services\Customer\Payloads\AppraisalStatusTimelineBuilder;
use App\Services\Revisions\AppraisalRequestRevisionSubmissionService;
use App\Support\SupportContact;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use App\Models\User;

/**
 * Builds dashboard stats and recent appraisal requests for the user.
 */
class DashboardController extends Controller
{
    public function index(
        CustomerAccessRequest $request,
        AppraisalProgressSummaryBuilder $progressSummaryBuilder,
        AppraisalPreviewStateBuilder $previewStateBuilder,
        AppraisalStatusTimelineBuilder $statusTimelineBuilder,
        AppraisalRequestRevisionSubmissionService $revisionSubmissionService
    )
    {
        $user = Auth::user();

        // Get statistics
        $totalRequests = AppraisalRequest::where('user_id', $user->id)->count();

        // In Progress statuses
        $inProgressStatuses = [
            AppraisalStatusEnum::Submitted,
            AppraisalStatusEnum::DocsIncomplete,
            AppraisalStatusEnum::Verified,
            AppraisalStatusEnum::WaitingOffer,
            AppraisalStatusEnum::OfferSent,
            AppraisalStatusEnum::WaitingSignature,
            AppraisalStatusEnum::ContractSigned,
            AppraisalStatusEnum::CancellationReviewPending,
            AppraisalStatusEnum::ValuationOnProgress,
            AppraisalStatusEnum::PreviewReady,
            AppraisalStatusEnum::ReportPreparation,
        ];

        $inProgress = AppraisalRequest::where('user_id', $user->id)
            ->whereIn('status', $inProgressStatuses)
            ->count();

        $completed = AppraisalRequest::where('user_id', $user->id)
            ->where('status', AppraisalStatusEnum::Completed)
            ->count();

        // Need revision: DocsIncomplete status
        $needRevision = AppraisalRequest::where('user_id', $user->id)
            ->where('status', AppraisalStatusEnum::DocsIncomplete)
            ->count();

        $stats = [
            'total_requests' => $totalRequests,
            'in_progress'    => $inProgress,
            'completed'      => $completed,
            'need_revision'  => $needRevision,
        ];

        $featuredRecord = AppraisalRequest::query()
            ->where('user_id', $user->id)
            ->whereNotIn('status', [AppraisalStatusEnum::Completed->value, AppraisalStatusEnum::Cancelled->value])
            ->withCount([
                'assets',
                'offerNegotiations as negotiation_rounds_used' => fn ($query) => $query->where('action', 'counter_request'),
            ])
            ->with([
                'assets:id,appraisal_request_id,address',
                'latestCancellationRequest' => function ($query): void {
                    $query->select([
                        'appraisal_request_cancellations.id',
                        'appraisal_request_cancellations.appraisal_request_id',
                        'appraisal_request_cancellations.status_before_request',
                        'appraisal_request_cancellations.review_status',
                        'appraisal_request_cancellations.created_at',
                        'appraisal_request_cancellations.reviewed_at',
                    ]);
                },
                'offerNegotiations:id,appraisal_request_id,action,round,offered_fee,expected_fee,selected_fee,reason,meta,created_at',
                'payments:id,appraisal_request_id,amount,method,gateway,external_payment_id,status,paid_at,metadata,updated_at,created_at',
            ])
            ->latest('updated_at')
            ->first();

        if (! $featuredRecord) {
            $featuredRecord = AppraisalRequest::query()
                ->where('user_id', $user->id)
                ->withCount([
                    'assets',
                    'offerNegotiations as negotiation_rounds_used' => fn ($query) => $query->where('action', 'counter_request'),
                ])
                ->with([
                    'assets:id,appraisal_request_id,address',
                    'latestCancellationRequest' => function ($query): void {
                        $query->select([
                            'appraisal_request_cancellations.id',
                            'appraisal_request_cancellations.appraisal_request_id',
                            'appraisal_request_cancellations.status_before_request',
                            'appraisal_request_cancellations.review_status',
                            'appraisal_request_cancellations.created_at',
                            'appraisal_request_cancellations.reviewed_at',
                        ]);
                    },
                    'offerNegotiations:id,appraisal_request_id,action,round,offered_fee,expected_fee,selected_fee,reason,meta,created_at',
                    'payments:id,appraisal_request_id,amount,method,gateway,external_payment_id,status,paid_at,metadata,updated_at,created_at',
                ])
                ->latest('updated_at')
                ->first();
        }

        $recentRequests = AppraisalRequest::where('user_id', $user->id)
            ->with(['assets'])
            ->orderByDesc('created_at')
            ->limit(5)
            ->get()
            ->map(function ($request) {
                return [
                    'id'           => $request->id,
                    'code'         => $request->request_number ?? 'N/A',
                    'property'     => $request->assets->first()?->address ?? 'Alamat tidak tersedia',
                    'asset_count'  => $request->assets->count(),
                    'status'       => $request->status->label(),
                    'status_key'   => $request->status->value,
                    'status_color' => $this->getStatusColor($request->status),
                    'created_at'   => Carbon::parse($request->created_at)->format('d M Y'),
                    'created_diff' => Carbon::parse($request->created_at)->diffForHumans(),
                    'detail_url'   => route('appraisal.show', ['id' => $request->id]),
                ];
            });

        $actionCenter = [
            $this->buildActionCard('need_revision', 'Revisi Perlu Dikirim', $needRevision, 'danger', route('appraisal.list', ['status' => AppraisalStatusEnum::DocsIncomplete->value])),
            $this->buildActionCard(
                'offer_sent',
                'Penawaran Menunggu Respon',
                $this->countByStatus($user->id, AppraisalStatusEnum::OfferSent),
                'info',
                route('appraisal.list', ['status' => AppraisalStatusEnum::OfferSent->value])
            ),
            $this->buildActionCard(
                'waiting_signature',
                'Kontrak Siap Ditandatangani',
                $this->countByStatus($user->id, AppraisalStatusEnum::WaitingSignature),
                'warning',
                route('appraisal.list', ['status' => AppraisalStatusEnum::WaitingSignature->value])
            ),
            $this->buildActionCard(
                'contract_signed',
                'Pembayaran Menunggu',
                $this->countByStatus($user->id, AppraisalStatusEnum::ContractSigned),
                'warning',
                route('appraisal.list', ['status' => AppraisalStatusEnum::ContractSigned->value])
            ),
            $this->buildActionCard(
                'preview_ready',
                'Preview Siap Direview',
                $this->countByStatus($user->id, AppraisalStatusEnum::PreviewReady),
                'info',
                route('appraisal.list', ['status' => AppraisalStatusEnum::PreviewReady->value])
            ),
        ];

        $featuredRequest = null;

        if ($featuredRecord) {
            $statusTimeline = $statusTimelineBuilder->build($featuredRecord);
            $previewState = $previewStateBuilder->build($featuredRecord);
            $revisionSummary = $revisionSubmissionService->buildSummary($featuredRecord);
            $latestPayment = $featuredRecord->payments->sortByDesc('id')->first();
            $reportPdfUrl = null;

            if ($featuredRecord->report_pdf_path && Storage::disk('public')->exists($featuredRecord->report_pdf_path)) {
                $reportPdfUrl = Storage::disk('public')->url($featuredRecord->report_pdf_path);
            }

            $progressSummary = $progressSummaryBuilder->build(
                $featuredRecord,
                $revisionSummary,
                $previewState,
                $latestPayment,
                $statusTimeline,
                $reportPdfUrl
            );

            $featuredRequest = [
                'id' => $featuredRecord->id,
                'mode' => in_array(
                    $featuredRecord->status?->value ?? (string) $featuredRecord->status,
                    [AppraisalStatusEnum::Completed->value, AppraisalStatusEnum::Cancelled->value],
                    true
                ) ? 'latest' : 'active',
                'code' => $featuredRecord->request_number ?? ('REQ-' . $featuredRecord->id),
                'property' => $featuredRecord->assets->first()?->address ?? 'Alamat tidak tersedia',
                'asset_count' => (int) ($featuredRecord->assets_count ?? $featuredRecord->assets->count()),
                'status' => $featuredRecord->status?->label() ?? '-',
                'status_key' => $featuredRecord->status?->value ?? (string) $featuredRecord->status,
                'status_color' => $this->getStatusColor($featuredRecord->status),
                'updated_at' => optional($featuredRecord->updated_at)->format('d M Y H:i'),
                'updated_diff' => optional($featuredRecord->updated_at)?->diffForHumans(),
                'detail_url' => route('appraisal.show', ['id' => $featuredRecord->id]),
                'tracking_url' => route('appraisal.tracking.page', ['id' => $featuredRecord->id]),
                'progress_summary' => $progressSummary,
            ];
        }

        return inertia('Dashboard/DashboardPage', [
            'stats'          => $stats,
            'recentRequests' => $recentRequests,
            'featuredRequest' => $featuredRequest,
            'actionCenter' => $actionCenter,
            'profileCompletionAlert' => $this->buildProfileCompletionAlert($user),
            'supportContact' => SupportContact::payload(),
        ]);
    }

    private function getStatusColor(AppraisalStatusEnum $status): string
    {
        return match($status) {
            AppraisalStatusEnum::Completed => 'success',
            AppraisalStatusEnum::DocsIncomplete => 'danger',
            AppraisalStatusEnum::Cancelled => 'danger',
            AppraisalStatusEnum::CancellationReviewPending => 'warning',
            AppraisalStatusEnum::ValuationOnProgress,
            AppraisalStatusEnum::ValuationCompleted,
            AppraisalStatusEnum::PreviewReady,
            AppraisalStatusEnum::ReportPreparation,
            AppraisalStatusEnum::ReportReady => 'warning',
            AppraisalStatusEnum::Verified,
            AppraisalStatusEnum::WaitingOffer,
            AppraisalStatusEnum::OfferSent,
            AppraisalStatusEnum::WaitingSignature,
            AppraisalStatusEnum::ContractSigned => 'info',
            AppraisalStatusEnum::Draft,
            AppraisalStatusEnum::Submitted => 'secondary',
            default => 'secondary',
        };
    }

    private function countByStatus(int $userId, AppraisalStatusEnum $status): int
    {
        return AppraisalRequest::query()
            ->where('user_id', $userId)
            ->where('status', $status->value)
            ->count();
    }

    private function buildActionCard(string $key, string $label, int $count, string $tone, string $url): array
    {
        return [
            'key' => $key,
            'label' => $label,
            'count' => $count,
            'tone' => $tone,
            'url' => $url,
        ];
    }

    private function buildProfileCompletionAlert(?User $user): ?array
    {
        if (! $user) {
            return null;
        }

        if ($this->hasReadyBillingProfile($user)) {
            return null;
        }

        return [
            'type' => 'warning',
            'message' => 'Lengkapi profil billing terlebih dahulu sebelum membuat permohonan penilaian baru.',
            'action_label' => 'Lengkapi Profil',
            'action_url' => route('profile.edit'),
        ];
    }

    private function hasReadyBillingProfile(?User $user): bool
    {
        if (! $user) {
            return false;
        }

        return filled($user->phone_number)
            && filled($user->billing_recipient_name)
            && filled($user->billing_address_detail);
    }
}

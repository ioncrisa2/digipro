<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\AppraisalRequest;
use App\Enums\AppraisalStatusEnum;

/**
 * Builds dashboard stats and recent appraisal requests for the user.
 */
class DashboardController extends Controller
{
    public function index()
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

        // Get recent requests with relations
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
                    'status_color' => $this->getStatusColor($request->status),
                    'created_at'   => Carbon::parse($request->created_at)->format('d M Y'),
                    'created_diff' => Carbon::parse($request->created_at)->diffForHumans(),
                ];
            });

        return inertia('Dashboard/DashboardPage', [
            'stats'          => $stats,
            'recentRequests' => $recentRequests,
        ]);
    }

    private function getStatusColor(AppraisalStatusEnum $status): string
    {
        return match($status) {
            AppraisalStatusEnum::Completed => 'success',
            AppraisalStatusEnum::DocsIncomplete => 'danger',
            AppraisalStatusEnum::Cancelled => 'danger',
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
}

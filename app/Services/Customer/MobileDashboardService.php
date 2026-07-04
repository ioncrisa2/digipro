<?php

namespace App\Services\Customer;

use App\Enums\AppraisalStatusEnum;
use App\Models\AppraisalAsset;
use App\Models\AppraisalRequest;
use App\Models\User;
use App\Support\SupportContact;

class MobileDashboardService
{
    public function build(User $user): array
    {
        $counts = AppraisalRequest::query()
            ->whereBelongsTo($user)
            ->selectRaw('status, COUNT(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status')
            ->map(static fn (mixed $count): int => (int) $count);

        $inProgressStatuses = [
            AppraisalStatusEnum::Submitted->value,
            AppraisalStatusEnum::DocsIncomplete->value,
            AppraisalStatusEnum::Verified->value,
            AppraisalStatusEnum::WaitingOffer->value,
            AppraisalStatusEnum::OfferSent->value,
            AppraisalStatusEnum::WaitingSignature->value,
            AppraisalStatusEnum::ContractSigned->value,
            AppraisalStatusEnum::CancellationReviewPending->value,
            AppraisalStatusEnum::ValuationOnProgress->value,
            AppraisalStatusEnum::ValuationCompleted->value,
            AppraisalStatusEnum::PreviewReady->value,
            AppraisalStatusEnum::ReportPreparation->value,
            AppraisalStatusEnum::ReportReady->value,
        ];

        $stats = [
            'total_requests' => $counts->sum(),
            'in_progress' => $counts->only($inProgressStatuses)->sum(),
            'completed' => $counts->get(AppraisalStatusEnum::Completed->value, 0),
            'need_revision' => $counts->get(AppraisalStatusEnum::DocsIncomplete->value, 0),
        ];

        $summaryQuery = static fn () => AppraisalRequest::query()
            ->whereBelongsTo($user)
            ->withCount('assets')
            ->selectSub(
                AppraisalAsset::query()
                    ->select('address')
                    ->whereColumn('appraisal_assets.appraisal_request_id', 'appraisal_requests.id')
                    ->oldest('id')
                    ->limit(1),
                'first_asset_address',
            );

        $featured = $summaryQuery()
            ->whereNotIn('status', [
                AppraisalStatusEnum::Completed->value,
                AppraisalStatusEnum::Cancelled->value,
            ])
            ->latest('updated_at')
            ->first() ?? $summaryQuery()->latest('updated_at')->first();

        $recent = $summaryQuery()
            ->latest('requested_at')
            ->latest('id')
            ->limit(5)
            ->get();

        $actions = collect([
            ['key' => 'submit_revision', 'label' => 'Revisi Perlu Dikirim', 'status' => AppraisalStatusEnum::DocsIncomplete, 'tone' => 'warning'],
            ['key' => 'review_offer', 'label' => 'Penawaran Menunggu Respons', 'status' => AppraisalStatusEnum::OfferSent, 'tone' => 'warning'],
            ['key' => 'sign_contract', 'label' => 'Kontrak Siap Ditandatangani', 'status' => AppraisalStatusEnum::WaitingSignature, 'tone' => 'warning'],
            ['key' => 'complete_payment', 'label' => 'Pembayaran Menunggu', 'status' => AppraisalStatusEnum::ContractSigned, 'tone' => 'warning'],
            ['key' => 'review_preview', 'label' => 'Preview Siap Ditinjau', 'status' => AppraisalStatusEnum::PreviewReady, 'tone' => 'info'],
        ])->map(static fn (array $action): array => [
            'action_key' => $action['key'],
            'label' => $action['label'],
            'count' => $counts->get($action['status']->value, 0),
            'tone' => $action['tone'],
            'status_filter' => $action['status']->value,
        ])->values();

        return [
            'stats' => $stats,
            'featured_request' => $featured,
            'recent_requests' => $recent,
            'actions' => $actions,
            'profile_completion_alert' => $this->profileCompletionAlert($user),
            'support_contact' => SupportContact::payload(),
        ];
    }

    private function profileCompletionAlert(User $user): ?array
    {
        if (filled($user->phone_number)
            && filled($user->billing_recipient_name)
            && filled($user->billing_address_detail)) {
            return null;
        }

        return [
            'tone' => 'warning',
            'message' => 'Lengkapi profil billing sebelum membuat permohonan penilaian baru.',
            'action_key' => 'complete_profile',
            'action_label' => 'Lengkapi Profil',
        ];
    }
}

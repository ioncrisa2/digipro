<?php

namespace App\Services\Customer\Payloads;

use App\Enums\AppraisalStatusEnum;
use App\Enums\ReportTypeEnum;
use App\Models\AppraisalAsset;
use App\Models\AppraisalRequest;
use Illuminate\Support\Str;

class CustomerAppraisalIndexBuilder
{
    public function __construct(
        private readonly AppraisalPayloadFormatter $formatter,
    ) {
    }

    public function build(int $userId, string $q, string $status, int $perPage = 10): array
    {
        $base = AppraisalRequest::query()->where('user_id', $userId);

        $pendingStatuses = array_filter([
            AppraisalStatusEnum::Draft->value ?? null,
            AppraisalStatusEnum::Submitted->value ?? null,
            AppraisalStatusEnum::DocsIncomplete->value ?? null,
            AppraisalStatusEnum::Verified->value ?? null,
            AppraisalStatusEnum::WaitingOffer->value ?? null,
            AppraisalStatusEnum::OfferSent->value ?? null,
            AppraisalStatusEnum::WaitingSignature->value ?? null,
        ]);

        $inProgressStatuses = array_filter([
            AppraisalStatusEnum::ContractSigned->value ?? null,
            AppraisalStatusEnum::ValuationOnProgress->value ?? null,
            AppraisalStatusEnum::ValuationCompleted->value ?? null,
            AppraisalStatusEnum::PreviewReady->value ?? null,
            AppraisalStatusEnum::ReportPreparation->value ?? null,
            AppraisalStatusEnum::ReportReady->value ?? null,
        ]);

        $completedStatuses = array_filter([AppraisalStatusEnum::Completed->value ?? null]);
        $rejectedStatuses = array_filter([AppraisalStatusEnum::Cancelled->value ?? null]);

        $stats = [
            'total' => (clone $base)->count(),
            'pending' => $pendingStatuses
                ? (clone $base)->whereIn('status', $pendingStatuses)->count()
                : (clone $base)->where('status', 'pending')->count(),
            'in_progress' => $inProgressStatuses
                ? (clone $base)->whereIn('status', $inProgressStatuses)->count()
                : (clone $base)->where('status', 'in_progress')->count(),
            'completed' => $completedStatuses
                ? (clone $base)->whereIn('status', $completedStatuses)->count()
                : (clone $base)->where('status', 'completed')->count(),
            'rejected' => $rejectedStatuses
                ? (clone $base)->whereIn('status', $rejectedStatuses)->count()
                : (clone $base)->where('status', 'rejected')->count(),
        ];

        $statsCards = [
            ['key' => 'total', 'label' => 'Total Permohonan', 'value' => $stats['total']],
            ['key' => 'pending', 'label' => 'Menunggu Proses', 'value' => $stats['pending']],
            ['key' => 'in_progress', 'label' => 'Sedang Diproses', 'value' => $stats['in_progress']],
            ['key' => 'completed', 'label' => 'Selesai', 'value' => $stats['completed']],
        ];

        $query = AppraisalRequest::query()
            ->where('user_id', $userId)
            ->withCount('assets')
            ->selectSub(
                AppraisalAsset::select('address')
                    ->whereColumn('appraisal_assets.appraisal_request_id', 'appraisal_requests.id')
                    ->orderBy('id')
                    ->limit(1),
                'first_asset_address'
            );

        if ($q !== '') {
            $query->where(function ($builder) use ($q): void {
                $builder->where('request_number', 'like', "%{$q}%")
                    ->orWhere('client_name', 'like', "%{$q}%")
                    ->orWhere('id', $q);
            });
        }

        if ($status !== '' && $status !== 'all') {
            $query->where('status', $status);
        }

        $appraisals = $query
            ->latest('requested_at')
            ->paginate($perPage)
            ->withQueryString()
            ->through(function ($record) {
                $reportTypeValue = $this->formatter->enumBackedValue(ReportTypeEnum::class, $record->report_type);
                $statusValue = $this->formatter->enumBackedValue(AppraisalStatusEnum::class, $record->status);

                return [
                    'id' => $record->id,
                    'request_number' => $record->request_number ?? ('REQ-' . $record->id),
                    'report_type' => $reportTypeValue,
                    'report_type_label' => $this->formatter->enumLabel(ReportTypeEnum::class, $record->report_type)
                        ?? $this->formatter->headlineOrDashValue($reportTypeValue),
                    'assets_count' => (int) $record->assets_count,
                    'status' => $statusValue,
                    'status_label' => $this->formatter->enumLabel(AppraisalStatusEnum::class, $record->status)
                        ?? $this->formatter->headlineOrDashValue($statusValue),
                    'requested_at' => optional($record->requested_at)->toDateString(),
                    'location' => $record->first_asset_address ? Str::limit($record->first_asset_address, 48) : '-',
                    'report_format' => $record->report_format,
                    'physical_copies_count' => (int) ($record->physical_copies_count ?? 0),
                ];
            });

        return [
            'appraisals' => $appraisals,
            'stats' => $stats,
            'statsCards' => $statsCards,
        ];
    }
}

<?php

namespace App\Services\Admin;

use App\Enums\AppraisalStatusEnum;
use App\Models\AppraisalRequest;
use App\Support\Admin\AppraisalRequestAdminPresenter;

class AppraisalRequestIndexBuilder
{
    public function __construct(
        private readonly AppraisalRequestAdminPresenter $presenter,
    ) {
    }

    public function build(array $filters, int $perPage): array
    {
        $records = AppraisalRequest::query()
            ->with('user')
            ->withCount('assets')
            ->withCount([
                'offerNegotiations as negotiation_rounds_used' => fn ($query) => $query->where('action', 'counter_request'),
            ])
            ->when($filters['q'] !== '', function ($query) use ($filters): void {
                $query->where(function ($innerQuery) use ($filters): void {
                    $innerQuery
                        ->where('request_number', 'like', '%' . $filters['q'] . '%')
                        ->orWhere('client_name', 'like', '%' . $filters['q'] . '%')
                        ->orWhereHas('user', fn ($userQuery) => $userQuery->where('name', 'like', '%' . $filters['q'] . '%'));
                });
            })
            ->when($filters['status'] !== 'all', fn ($query) => $query->where('status', $filters['status']))
            ->latest('requested_at')
            ->paginate($perPage)
            ->withQueryString();

        $records->through(fn (AppraisalRequest $record) => $this->presenter->requestTableRow($record));

        return [
            'filters' => $filters,
            'statusOptions' => array_map(
                fn (AppraisalStatusEnum $status) => [
                    'value' => $status->value,
                    'label' => $status->label(),
                ],
                AppraisalStatusEnum::cases(),
            ),
            'summary' => [
                'total' => AppraisalRequest::query()->count(),
                'needs_action' => AppraisalRequest::query()
                    ->whereIn('status', [
                        AppraisalStatusEnum::Submitted,
                        AppraisalStatusEnum::DocsIncomplete,
                        AppraisalStatusEnum::Verified,
                        AppraisalStatusEnum::WaitingOffer,
                    ])
                    ->count(),
                'payment_pending' => AppraisalRequest::query()
                    ->where('status', AppraisalStatusEnum::ContractSigned)
                    ->count(),
            ],
            'records' => $records,
        ];
    }
}

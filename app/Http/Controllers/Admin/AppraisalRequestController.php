<?php

namespace App\Http\Controllers\Admin;

use App\Enums\AppraisalStatusEnum;
use App\Http\Controllers\Admin\Concerns\InteractsWithAppraisalRequests;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AppraisalRequestIndexRequest;
use App\Http\Requests\Admin\UpdateAppraisalRequestBasicRequest;
use App\Models\AppraisalRequest;
use App\Services\Admin\AppraisalContractNumberService;
use App\Services\Admin\AppraisalRequestPageBuilder;
use App\Services\Admin\AppraisalRequestWorkflowService;
use App\Services\Finance\AppraisalBillingService;
use Illuminate\Http\RedirectResponse;
use Inertia\Response;

class AppraisalRequestController extends Controller
{
    use InteractsWithAppraisalRequests;

    public function __construct(
        private readonly AppraisalRequestPageBuilder $pageBuilder,
    ) {
    }

    public function appraisalRequestsIndex(AppraisalRequestIndexRequest $request): Response
    {
        $filters = $request->filters();

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
            ->paginate($request->perPage())
            ->withQueryString();

        $records->through(fn (AppraisalRequest $record) => $this->transformRequestTableRow($record));

        return inertia('Admin/AppraisalRequests/Index', [
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
            'records' => $this->paginatedRecordsPayload($records),
        ]);
    }

    public function appraisalRequestsShow(
        AppraisalRequest $appraisalRequest,
        AppraisalRequestWorkflowService $workflowService,
    ): Response {
        return inertia('Admin/AppraisalRequests/Show', $this->pageBuilder->buildShowPayload(
            $appraisalRequest,
            $workflowService,
        ));
    }

    public function appraisalRequestsEdit(AppraisalRequest $appraisalRequest): Response
    {
        return inertia('Admin/AppraisalRequests/Edit', $this->pageBuilder->buildEditPayload($appraisalRequest));
    }

    public function appraisalRequestsUpdate(
        UpdateAppraisalRequestBasicRequest $request,
        AppraisalRequest $appraisalRequest,
        AppraisalContractNumberService $contractNumberService,
        AppraisalBillingService $billingService
    ): RedirectResponse {
        $validated = $request->validated();
        $contractMeta = $contractNumberService->deriveMetadata($validated['contract_sequence'] ?? null);
        $contractDate = $this->blankToNull($validated['contract_date'] ?? null);
        $contractStatus = array_key_exists('contract_status', $validated)
            ? ($this->blankToNull($validated['contract_status']) ?? 'none')
            : ($appraisalRequest->contract_status?->value ?? $appraisalRequest->contract_status ?? 'none');

        if (($validated['contract_sequence'] ?? null) && $contractDate === null) {
            $contractDate = now()->toDateString();
        }

        $billingAttributes = [];
        if (array_key_exists('billing_dpp_amount', $validated) && $validated['billing_dpp_amount'] !== null) {
            $billingAttributes = $billingService->appraisalAttributesFromDpp(
                (int) $validated['billing_dpp_amount'],
                $appraisalRequest->user,
            );
        } elseif (array_key_exists('fee_total', $validated) && $validated['fee_total'] !== null) {
            $billingAttributes = $billingService->appraisalAttributesFromDpp(
                (int) $billingService->deriveFromGross((int) $validated['fee_total'])['billing_dpp_amount'],
                $appraisalRequest->user,
            );
        }

        $appraisalRequest->update([
            ...$billingAttributes,
            'client_name' => $this->blankToNull($validated['client_name'] ?? null),
            'report_type' => $this->blankToNull($validated['report_type'] ?? null),
            'contract_sequence' => $this->blankToNull($validated['contract_sequence'] ?? null),
            'contract_number' => $contractMeta['contract_number'],
            'contract_office_code' => $contractMeta['contract_office_code'],
            'contract_month' => $contractMeta['contract_month'],
            'contract_year' => $contractMeta['contract_year'],
            'contract_date' => $contractDate,
            'contract_status' => $contractStatus,
            'valuation_duration_days' => $this->blankToNull($validated['valuation_duration_days'] ?? null),
            'offer_validity_days' => $this->blankToNull($validated['offer_validity_days'] ?? null),
            'fee_has_dp' => false,
            'fee_dp_percent' => null,
            'user_request_note' => $this->blankToNull($validated['user_request_note'] ?? null),
            'notes' => $this->blankToNull($validated['notes'] ?? null),
        ]);

        return redirect()
            ->route('admin.appraisal-requests.show', $appraisalRequest)
            ->with('success', 'Informasi dasar request berhasil diperbarui.');
    }
}

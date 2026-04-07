<?php

namespace App\Http\Controllers\Admin;

use App\Enums\AppraisalStatusEnum;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AppraisalRequestCancellationIndexRequest;
use App\Http\Requests\Admin\ApproveAppraisalRequestCancellationRequest;
use App\Http\Requests\Admin\MarkAppraisalRequestCancellationInProgressRequest;
use App\Http\Requests\Admin\RejectAppraisalRequestCancellationRequest;
use App\Models\AppraisalRequestCancellation;
use App\Notifications\AppraisalStatusUpdated;
use App\Services\AppraisalRequestCancellationService;
use Illuminate\Http\RedirectResponse;
use Inertia\Response;

class AppraisalRequestCancellationController extends Controller
{
    public function index(AppraisalRequestCancellationIndexRequest $request): Response
    {
        $filters = $request->filters();

        $records = AppraisalRequestCancellation::query()
            ->with(['appraisalRequest:id,request_number,status', 'user:id,name,email'])
            ->when($filters['q'] !== '', function ($query) use ($filters): void {
                $query->where(function ($innerQuery) use ($filters): void {
                    $innerQuery
                        ->where('reason', 'like', '%' . $filters['q'] . '%')
                        ->orWhere('phone_snapshot', 'like', '%' . $filters['q'] . '%')
                        ->orWhereHas('appraisalRequest', fn ($requestQuery) => $requestQuery->where('request_number', 'like', '%' . $filters['q'] . '%'))
                        ->orWhereHas('user', fn ($userQuery) => $userQuery->where('name', 'like', '%' . $filters['q'] . '%'));
                });
            })
            ->when($filters['review_status'] !== 'all', fn ($query) => $query->where('review_status', $filters['review_status']))
            ->when($filters['status_before'] !== 'all', fn ($query) => $query->where('status_before_request', $filters['status_before']))
            ->latest()
            ->paginate($request->perPage())
            ->withQueryString();

        $records->through(fn (AppraisalRequestCancellation $record) => $this->transformRow($record));

        return inertia('Admin/AppraisalRequestCancellations/Index', [
            'filters' => $filters,
            'reviewStatusOptions' => $this->reviewStatusOptions(),
            'statusBeforeOptions' => array_map(
                fn (AppraisalStatusEnum $status) => [
                    'value' => $status->value,
                    'label' => $status->label(),
                ],
                AppraisalStatusEnum::cases()
            ),
            'summary' => [
                'total' => AppraisalRequestCancellation::query()->count(),
                'pending' => AppraisalRequestCancellation::query()->where('review_status', 'pending')->count(),
                'in_progress' => AppraisalRequestCancellation::query()->where('review_status', 'in_progress')->count(),
                'reviewed' => AppraisalRequestCancellation::query()->whereIn('review_status', ['approved', 'rejected'])->count(),
            ],
            'records' => $this->paginatedRecordsPayload($records),
        ]);
    }

    public function show(AppraisalRequestCancellation $cancellationRequest): Response
    {
        $cancellationRequest->load([
            'user:id,name,email,phone_number,whatsapp_number',
            'reviewedBy:id,name',
            'appraisalRequest:id,user_id,request_number,status,contract_status,requested_at,client_name,fee_total',
            'appraisalRequest.user:id,name,email',
            'appraisalRequest.assets:id,appraisal_request_id,address,asset_type',
        ]);

        return inertia('Admin/AppraisalRequestCancellations/Show', [
            'record' => [
                'id' => $cancellationRequest->id,
                'review_status' => $cancellationRequest->review_status,
                'review_status_label' => $this->reviewStatusLabel($cancellationRequest->review_status),
                'status_before_request' => $cancellationRequest->status_before_request,
                'status_before_request_label' => AppraisalStatusEnum::tryFrom($cancellationRequest->status_before_request)?->label() ?? $cancellationRequest->status_before_request,
                'reason' => $cancellationRequest->reason,
                'review_note' => $cancellationRequest->review_note,
                'phone_snapshot' => $cancellationRequest->phone_snapshot,
                'whatsapp_snapshot' => $cancellationRequest->whatsapp_snapshot,
                'contacted_at' => optional($cancellationRequest->contacted_at)->toDateTimeString(),
                'reviewed_at' => optional($cancellationRequest->reviewed_at)->toDateTimeString(),
                'created_at' => optional($cancellationRequest->created_at)->toDateTimeString(),
                'reviewed_by_name' => $cancellationRequest->reviewedBy?->name,
                'show_request_url' => route('admin.appraisal-requests.show', $cancellationRequest->appraisalRequest),
                'actions' => [
                    'mark_in_progress_url' => route('admin.appraisal-requests.cancellations.in-progress', $cancellationRequest),
                    'approve_url' => route('admin.appraisal-requests.cancellations.approve', $cancellationRequest),
                    'reject_url' => route('admin.appraisal-requests.cancellations.reject', $cancellationRequest),
                ],
            ],
            'customer' => [
                'name' => $cancellationRequest->user?->name ?? '-',
                'email' => $cancellationRequest->user?->email ?? '-',
                'phone_number' => $cancellationRequest->phone_snapshot ?: ($cancellationRequest->user?->phone_number ?? '-'),
                'whatsapp_number' => $cancellationRequest->whatsapp_snapshot ?: ($cancellationRequest->user?->whatsapp_number ?? '-'),
            ],
            'appraisal' => [
                'id' => $cancellationRequest->appraisalRequest?->id,
                'request_number' => $cancellationRequest->appraisalRequest?->request_number ?? '-',
                'status_label' => $cancellationRequest->appraisalRequest?->status?->label() ?? '-',
                'status_value' => $cancellationRequest->appraisalRequest?->status?->value ?? null,
                'client_name' => $cancellationRequest->appraisalRequest?->client_name ?? '-',
                'requested_at' => optional($cancellationRequest->appraisalRequest?->requested_at)->toDateTimeString(),
                'fee_total' => $cancellationRequest->appraisalRequest?->fee_total,
                'assets' => $cancellationRequest->appraisalRequest?->assets
                    ? $cancellationRequest->appraisalRequest->assets->map(fn ($asset) => [
                        'id' => $asset->id,
                        'address' => $asset->address,
                        'asset_type' => $asset->asset_type?->value ?? $asset->asset_type,
                        'asset_type_label' => $asset->asset_type?->label() ?? ($asset->asset_type ?? '-'),
                    ])->values()->all()
                    : [],
            ],
        ]);
    }

    public function markInProgress(
        MarkAppraisalRequestCancellationInProgressRequest $request,
        AppraisalRequestCancellation $cancellationRequest,
        AppraisalRequestCancellationService $cancellationService
    ): RedirectResponse {
        $cancellationService->markInProgress($cancellationRequest, (int) $request->user()->id);

        return back()->with('success', 'Pengajuan pembatalan ditandai sedang dihubungi.');
    }

    public function approve(
        ApproveAppraisalRequestCancellationRequest $request,
        AppraisalRequestCancellation $cancellationRequest,
        AppraisalRequestCancellationService $cancellationService
    ): RedirectResponse {
        $appraisalRequest = $cancellationRequest->appraisalRequest()->with('user')->firstOrFail();
        $reviewNote = $request->validated('review_note');

        $cancellationService->approve($cancellationRequest, (int) $request->user()->id, $reviewNote);

        $appraisalRequest->refresh()->loadMissing('user');
        $appraisalRequest->user?->notify(new AppraisalStatusUpdated(
            appraisalId: (int) $appraisalRequest->id,
            requestNumber: (string) ($appraisalRequest->request_number ?? ('REQ-' . $appraisalRequest->id)),
            oldStatus: AppraisalStatusEnum::CancellationReviewPending->label(),
            newStatus: AppraisalStatusEnum::Cancelled->label(),
            detail: filled($reviewNote) ? (string) $reviewNote : null,
        ));

        return redirect()
            ->route('admin.appraisal-requests.cancellations.show', $cancellationRequest)
            ->with('success', 'Pengajuan pembatalan disetujui dan request dibatalkan.');
    }

    public function reject(
        RejectAppraisalRequestCancellationRequest $request,
        AppraisalRequestCancellation $cancellationRequest,
        AppraisalRequestCancellationService $cancellationService
    ): RedirectResponse {
        $appraisalRequest = $cancellationRequest->appraisalRequest()->with('user')->firstOrFail();
        $reviewNote = (string) $request->validated('review_note');
        $restoredStatus = AppraisalStatusEnum::tryFrom($cancellationRequest->status_before_request)?->label()
            ?? $cancellationRequest->status_before_request;

        $cancellationService->reject($cancellationRequest, (int) $request->user()->id, $reviewNote);

        $appraisalRequest->refresh()->loadMissing('user');
        $appraisalRequest->user?->notify(new AppraisalStatusUpdated(
            appraisalId: (int) $appraisalRequest->id,
            requestNumber: (string) ($appraisalRequest->request_number ?? ('REQ-' . $appraisalRequest->id)),
            oldStatus: AppraisalStatusEnum::CancellationReviewPending->label(),
            newStatus: $restoredStatus,
            detail: $reviewNote,
        ));

        return redirect()
            ->route('admin.appraisal-requests.cancellations.show', $cancellationRequest)
            ->with('success', 'Pengajuan pembatalan ditolak dan status request dikembalikan.');
    }

    private function transformRow(AppraisalRequestCancellation $record): array
    {
        return [
            'id' => $record->id,
            'request_number' => $record->appraisalRequest?->request_number ?? '-',
            'customer_name' => $record->user?->name ?? '-',
            'phone_snapshot' => $record->phone_snapshot,
            'status_before_request' => $record->status_before_request,
            'status_before_request_label' => AppraisalStatusEnum::tryFrom($record->status_before_request)?->label() ?? $record->status_before_request,
            'review_status' => $record->review_status,
            'review_status_label' => $this->reviewStatusLabel($record->review_status),
            'reason_excerpt' => \Illuminate\Support\Str::limit($record->reason, 120),
            'requested_at' => optional($record->created_at)->toDateTimeString(),
            'show_url' => route('admin.appraisal-requests.cancellations.show', $record),
        ];
    }

    private function reviewStatusOptions(): array
    {
        return [
            ['value' => 'pending', 'label' => 'Menunggu Review'],
            ['value' => 'in_progress', 'label' => 'Sedang Dihubungi'],
            ['value' => 'approved', 'label' => 'Disetujui'],
            ['value' => 'rejected', 'label' => 'Ditolak'],
        ];
    }

    private function reviewStatusLabel(string $status): string
    {
        return collect($this->reviewStatusOptions())
            ->firstWhere('value', $status)['label'] ?? $status;
    }
}

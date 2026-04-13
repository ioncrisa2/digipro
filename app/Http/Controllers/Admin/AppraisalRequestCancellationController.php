<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AppraisalRequestCancellationIndexRequest;
use App\Http\Requests\Admin\ApproveAppraisalRequestCancellationRequest;
use App\Http\Requests\Admin\MarkAppraisalRequestCancellationInProgressRequest;
use App\Http\Requests\Admin\RejectAppraisalRequestCancellationRequest;
use App\Models\AppraisalRequestCancellation;
use App\Services\Admin\AdminAppraisalRequestCancellationWorkspaceService;
use Illuminate\Http\RedirectResponse;
use Inertia\Response;

class AppraisalRequestCancellationController extends Controller
{
    public function __construct(
        private readonly AdminAppraisalRequestCancellationWorkspaceService $workspaceService,
    ) {
    }

    public function index(AppraisalRequestCancellationIndexRequest $request): Response
    {
        return inertia('Admin/AppraisalRequestCancellations/Index', $this->workspaceService
            ->indexPayload($request->filters(), $request->perPage()));
    }

    public function show(AppraisalRequestCancellation $cancellationRequest): Response
    {
        return inertia('Admin/AppraisalRequestCancellations/Show', $this->workspaceService
            ->showPayload($cancellationRequest));
    }

    public function markInProgress(
        MarkAppraisalRequestCancellationInProgressRequest $request,
        AppraisalRequestCancellation $cancellationRequest,
    ): RedirectResponse {
        $message = $this->workspaceService->markInProgress($cancellationRequest, (int) $request->user()->id);

        return back()->with('success', $message);
    }

    public function approve(
        ApproveAppraisalRequestCancellationRequest $request,
        AppraisalRequestCancellation $cancellationRequest,
    ): RedirectResponse {
        $message = $this->workspaceService->approve(
            $cancellationRequest,
            (int) $request->user()->id,
            $request->validated('review_note'),
        );

        return redirect()
            ->route('admin.appraisal-requests.cancellations.show', $cancellationRequest)
            ->with('success', $message);
    }

    public function reject(
        RejectAppraisalRequestCancellationRequest $request,
        AppraisalRequestCancellation $cancellationRequest,
    ): RedirectResponse {
        $message = $this->workspaceService->reject(
            $cancellationRequest,
            (int) $request->user()->id,
            (string) $request->validated('review_note'),
        );

        return redirect()
            ->route('admin.appraisal-requests.cancellations.show', $cancellationRequest)
            ->with('success', $message);
    }
}

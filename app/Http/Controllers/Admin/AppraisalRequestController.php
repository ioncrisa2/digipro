<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AppraisalRequestIndexRequest;
use App\Http\Requests\Admin\UpdateAppraisalRequestBasicRequest;
use App\Models\AppraisalRequest;
use App\Services\Admin\AppraisalRequestBasicUpdateService;
use App\Services\Admin\AppraisalRequestIndexBuilder;
use App\Services\Admin\AppraisalRequestPageBuilder;
use App\Services\Admin\AppraisalRequestWorkflowService;
use Illuminate\Http\RedirectResponse;
use Inertia\Response;

class AppraisalRequestController extends Controller
{
    public function __construct(
        private readonly AppraisalRequestPageBuilder $pageBuilder,
        private readonly AppraisalRequestIndexBuilder $indexBuilder,
    ) {
    }

    public function appraisalRequestsIndex(AppraisalRequestIndexRequest $request): Response
    {
        $payload = $this->indexBuilder->build($request->filters(), $request->perPage());

        return inertia('Admin/AppraisalRequests/Index', [
            'filters' => $payload['filters'],
            'statusOptions' => $payload['statusOptions'],
            'summary' => $payload['summary'],
            'records' => $this->paginatedRecordsPayload($payload['records']),
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
        AppraisalRequestBasicUpdateService $updateService,
    ): RedirectResponse {
        $updateService->update($appraisalRequest, $request->validated());

        return redirect()
            ->route('admin.appraisal-requests.show', $appraisalRequest)
            ->with('success', 'Informasi dasar request berhasil diperbarui.');
    }
}

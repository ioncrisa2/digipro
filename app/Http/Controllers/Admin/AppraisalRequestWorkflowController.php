<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AdminActionRequest;
use App\Http\Requests\Admin\RejectAppraisalRevisionItemRequest;
use App\Http\Requests\Admin\StoreAppraisalCancellationRequest;
use App\Http\Requests\Admin\StoreAppraisalContractSignerRequest;
use App\Http\Requests\Admin\StoreAppraisalFieldCorrectionRequest;
use App\Http\Requests\Admin\StoreAppraisalOfferRequest;
use App\Http\Requests\Admin\StoreAppraisalReportConfigurationRequest;
use App\Http\Requests\Admin\StoreAppraisalRequestRevisionBatchRequest;
use App\Http\Requests\Admin\UploadFinalReportRequest;
use App\Http\Requests\Admin\UpdateAppraisalPhysicalReportRequest;
use App\Models\AppraisalRequest;
use App\Models\AppraisalRequestRevisionItem;
use App\Services\Admin\AdminAppraisalRequestWorkflowWorkspaceService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AppraisalRequestWorkflowController extends Controller
{
    public function __construct(
        private readonly AdminAppraisalRequestWorkflowWorkspaceService $workspaceService,
    ) {
    }

    public function storeRevisionBatch(
        StoreAppraisalRequestRevisionBatchRequest $request,
        AppraisalRequest $appraisalRequest,
    ): RedirectResponse {
        return $this->handleAction(fn () => $this->workspaceService->storeRevisionBatch(
            $appraisalRequest,
            (int) $request->user()->id,
            $request->resolvedItems(),
            $request->string('admin_note')->toString()
        ));
    }

    public function sendOffer(
        StoreAppraisalOfferRequest $request,
        AppraisalRequest $appraisalRequest,
    ): RedirectResponse {
        return $this->handleAction(fn () => $this->workspaceService->sendOffer(
            $appraisalRequest,
            (int) $request->user()->id,
            $request->validated()
        ));
    }

    public function approveLatestNegotiation(
        AppraisalRequest $appraisalRequest,
        AdminActionRequest $request
    ): RedirectResponse {
        return $this->handleAction(fn () => $this->workspaceService->approveLatestNegotiation(
            $appraisalRequest,
            (int) $request->user()->id
        ));
    }

    public function verifyDocs(
        AppraisalRequest $appraisalRequest,
        AdminActionRequest $request
    ): RedirectResponse {
        return $this->handleAction(fn () => $this->workspaceService->verifyDocs(
            $appraisalRequest,
            (int) $request->user()->id
        ));
    }

    public function markDocsIncomplete(AppraisalRequest $appraisalRequest): RedirectResponse
    {
        return $this->handleAction(fn () => $this->workspaceService->markDocsIncomplete($appraisalRequest));
    }

    public function markContractSigned(AppraisalRequest $appraisalRequest): RedirectResponse
    {
        return $this->handleAction(fn () => $this->workspaceService->markContractSigned($appraisalRequest));
    }

    public function saveContractSigner(
        StoreAppraisalContractSignerRequest $request,
        AppraisalRequest $appraisalRequest,
    ): RedirectResponse {
        return $this->handleAction(fn () => $this->workspaceService->saveContractSignerConfiguration(
            $appraisalRequest,
            (int) $request->user()->id,
            $request->validated()
        ));
    }

    public function verifyPayment(AppraisalRequest $appraisalRequest): RedirectResponse
    {
        return $this->handleAction(fn () => $this->workspaceService->verifyPayment($appraisalRequest));
    }

    public function updatePhysicalReport(
        UpdateAppraisalPhysicalReportRequest $request,
        AppraisalRequest $appraisalRequest,
    ): RedirectResponse {
        return $this->handleAction(fn () => $this->workspaceService->updatePhysicalReport(
            $appraisalRequest,
            (int) $request->user()->id,
            $request->validated()
        ));
    }

    public function cancelRequest(
        StoreAppraisalCancellationRequest $request,
        AppraisalRequest $appraisalRequest,
    ): RedirectResponse {
        return $this->handleAction(fn () => $this->workspaceService->cancelRequest(
            $appraisalRequest,
            (int) $request->user()->id,
            trim((string) $request->string('reason')->toString())
        ));
    }

    public function downloadReportDraft(
        AppraisalRequest $appraisalRequest,
    ): StreamedResponse|RedirectResponse {
        try {
            $download = $this->workspaceService->resolveDraftDownload($appraisalRequest);

            return Storage::disk((string) $download['disk'])
                ->download((string) $download['path'], (string) $download['download_name']);
        } catch (\RuntimeException $exception) {
            return back()->with('error', $exception->getMessage());
        }
    }

    public function saveReportConfiguration(
        StoreAppraisalReportConfigurationRequest $request,
        AppraisalRequest $appraisalRequest,
    ): RedirectResponse {
        return $this->handleAction(fn () => $this->workspaceService->saveReportConfiguration(
            $appraisalRequest,
            (int) $request->user()->id,
            $request->validated()
        ));
    }

    public function storeFieldCorrection(
        StoreAppraisalFieldCorrectionRequest $request,
        AppraisalRequest $appraisalRequest,
    ): RedirectResponse {
        return $this->handleAction(function () use ($request, $appraisalRequest): string {
            return $this->workspaceService->storeFieldCorrection(
                $appraisalRequest,
                (int) $request->user()->id,
                (string) $request->input('target_key'),
                $request->normalizedValue(),
                $request->string('reason')->toString()
            );
        }, handleValidationErrors: true);
    }

    public function uploadFinalReport(
        UploadFinalReportRequest $request,
        AppraisalRequest $appraisalRequest,
    ): RedirectResponse {
        return $this->handleAction(fn () => $this->workspaceService->uploadFinalReport(
            $appraisalRequest,
            $request->validated()['report_pdf'],
            (int) $request->user()->id
        ));
    }

    public function approveRevisionItem(
        AppraisalRequest $appraisalRequest,
        AppraisalRequestRevisionItem $revisionItem,
        AdminActionRequest $request
    ): RedirectResponse {
        return $this->handleAction(fn () => $this->workspaceService->approveRevisionItem(
            $appraisalRequest,
            $revisionItem,
            (int) $request->user()->id
        ));
    }

    public function rejectRevisionItem(
        AppraisalRequest $appraisalRequest,
        AppraisalRequestRevisionItem $revisionItem,
        RejectAppraisalRevisionItemRequest $request
    ): RedirectResponse {
        return $this->handleAction(fn () => $this->workspaceService->rejectRevisionItem(
            $appraisalRequest,
            $revisionItem,
            (int) $request->user()->id,
            (string) $request->validated('review_note')
        ));
    }

    private function handleAction(callable $callback, bool $handleValidationErrors = false): RedirectResponse
    {
        try {
            return back()->with('success', $callback());
        } catch (\RuntimeException $exception) {
            return back()->with('error', $exception->getMessage());
        } catch (ValidationException $exception) {
            if (! $handleValidationErrors) {
                throw $exception;
            }

            return back()->withErrors($exception->errors())->withInput();
        }
    }
}

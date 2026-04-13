<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AdminActionRequest;
use App\Http\Requests\Admin\RejectAppraisalRevisionItemRequest;
use App\Http\Requests\Admin\StoreAppraisalCancellationRequest;
use App\Http\Requests\Admin\StoreAppraisalFieldCorrectionRequest;
use App\Http\Requests\Admin\StoreAppraisalOfferRequest;
use App\Http\Requests\Admin\StoreAppraisalReportConfigurationRequest;
use App\Http\Requests\Admin\StoreAppraisalRequestRevisionBatchRequest;
use App\Http\Requests\Admin\UploadFinalReportRequest;
use App\Http\Requests\Admin\UpdateAppraisalPhysicalReportRequest;
use App\Models\AppraisalRequest;
use App\Models\AppraisalRequestRevisionItem;
use App\Services\Admin\AppraisalFieldCorrectionService;
use App\Services\Admin\AppraisalRequestAdminWorkflowService;
use App\Services\Admin\AppraisalRequestRevisionReviewService;
use App\Services\Admin\AppraisalRequestRevisionService;
use App\Services\Admin\AppraisalRequestWorkflowService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AppraisalRequestWorkflowController extends Controller
{
    public function storeRevisionBatch(
        StoreAppraisalRequestRevisionBatchRequest $request,
        AppraisalRequest $appraisalRequest,
        AppraisalRequestRevisionService $revisionService
    ): RedirectResponse {
        try {
            $revisionService->createBatch(
                $appraisalRequest,
                (int) $request->user()->id,
                $request->resolvedItems(),
                $request->string('admin_note')->toString()
            );

            return back()->with('success', 'Permintaan revisi berhasil dibuat dan customer perlu memperbaiki item yang diminta.');
        } catch (\RuntimeException $exception) {
            return back()->with('error', $exception->getMessage());
        }
    }

    public function sendOffer(
        StoreAppraisalOfferRequest $request,
        AppraisalRequest $appraisalRequest,
        AppraisalRequestWorkflowService $workflowService
    ): RedirectResponse {
        try {
            $result = $workflowService->sendOffer(
                $appraisalRequest,
                (int) $request->user()->id,
                $request->validated()
            );

            $message = $result['action'] === 'offer_revised'
                ? 'Counter offer berhasil dikirim.'
                : 'Penawaran berhasil dikirim.';

            return back()->with('success', $message);
        } catch (\RuntimeException $exception) {
            return back()->with('error', $exception->getMessage());
        }
    }

    public function approveLatestNegotiation(
        AppraisalRequest $appraisalRequest,
        AppraisalRequestWorkflowService $workflowService,
        AdminActionRequest $request
    ): RedirectResponse {
        try {
            $workflowService->approveLatestNegotiation($appraisalRequest, (int) $request->user()->id);

            return back()->with('success', 'Harapan fee user disetujui. Request langsung masuk ke tahap tanda tangan kontrak.');
        } catch (\RuntimeException $exception) {
            return back()->with('error', $exception->getMessage());
        }
    }

    public function verifyDocs(
        AppraisalRequest $appraisalRequest,
        AppraisalRequestWorkflowService $workflowService,
        AdminActionRequest $request
    ): RedirectResponse {
        try {
            $workflowService->verifyDocs($appraisalRequest, (int) $request->user()->id);

            return back()->with('success', 'Dokumen berhasil diverifikasi. Request masuk ke tahap menunggu penawaran.');
        } catch (\RuntimeException $exception) {
            return back()->with('error', $exception->getMessage());
        }
    }

    public function markDocsIncomplete(
        AppraisalRequest $appraisalRequest,
        AppraisalRequestWorkflowService $workflowService
    ): RedirectResponse {
        try {
            $workflowService->markDocsIncomplete($appraisalRequest);

            return back()->with('success', 'Request berhasil ditandai dokumen kurang.');
        } catch (\RuntimeException $exception) {
            return back()->with('error', $exception->getMessage());
        }
    }

    public function markContractSigned(
        AppraisalRequest $appraisalRequest,
        AppraisalRequestWorkflowService $workflowService
    ): RedirectResponse {
        try {
            $workflowService->markContractSigned($appraisalRequest);

            return back()->with('success', 'Status kontrak berhasil diperbarui menjadi ditandatangani.');
        } catch (\RuntimeException $exception) {
            return back()->with('error', $exception->getMessage());
        }
    }

    public function verifyPayment(
        AppraisalRequest $appraisalRequest,
        AppraisalRequestWorkflowService $workflowService
    ): RedirectResponse {
        try {
            $workflowService->verifyPayment($appraisalRequest);

            return back()->with('success', 'Pembayaran terverifikasi. Request masuk ke proses valuasi.');
        } catch (\RuntimeException $exception) {
            return back()->with('error', $exception->getMessage());
        }
    }

    public function updatePhysicalReport(
        UpdateAppraisalPhysicalReportRequest $request,
        AppraisalRequest $appraisalRequest,
        AppraisalRequestAdminWorkflowService $adminWorkflowService
    ): RedirectResponse {
        try {
            $result = $adminWorkflowService->updatePhysicalReport(
                $appraisalRequest,
                (int) $request->user()->id,
                $request->validated()
            );

            return back()->with('success', (string) ($result['message'] ?? 'Detail pengiriman hard copy berhasil diperbarui.'));
        } catch (\RuntimeException $exception) {
            return back()->with('error', $exception->getMessage());
        }
    }

    public function cancelRequest(
        StoreAppraisalCancellationRequest $request,
        AppraisalRequest $appraisalRequest,
        AppraisalRequestAdminWorkflowService $adminWorkflowService
    ): RedirectResponse {
        try {
            $adminWorkflowService->cancelRequest(
                $appraisalRequest,
                (int) $request->user()->id,
                trim((string) $request->string('reason')->toString())
            );

            return back()->with('success', 'Request berhasil dibatalkan dan alasan pembatalan sudah tersimpan.');
        } catch (\RuntimeException $exception) {
            return back()->with('error', $exception->getMessage());
        }
    }

    public function downloadReportDraft(
        AppraisalRequest $appraisalRequest,
        AppraisalRequestAdminWorkflowService $adminWorkflowService
    ): StreamedResponse|RedirectResponse {
        try {
            $download = $adminWorkflowService->resolveDraftDownload($appraisalRequest);

            return Storage::disk((string) $download['disk'])
                ->download((string) $download['path'], (string) $download['download_name']);
        } catch (\RuntimeException $exception) {
            return back()->with('error', $exception->getMessage());
        }
    }

    public function saveReportConfiguration(
        StoreAppraisalReportConfigurationRequest $request,
        AppraisalRequest $appraisalRequest,
        AppraisalRequestAdminWorkflowService $adminWorkflowService
    ): RedirectResponse {
        try {
            $adminWorkflowService->saveReportConfiguration(
                $appraisalRequest,
                (int) $request->user()->id,
                $request->validated()
            );

            return back()->with('success', 'Konfigurasi report berhasil disimpan dan draft diperbarui.');
        } catch (\RuntimeException $exception) {
            return back()->with('error', $exception->getMessage());
        }
    }

    public function storeFieldCorrection(
        StoreAppraisalFieldCorrectionRequest $request,
        AppraisalRequest $appraisalRequest,
        AppraisalFieldCorrectionService $fieldCorrectionService
    ): RedirectResponse {
        try {
            $fieldCorrectionService->apply(
                $appraisalRequest,
                (int) $request->user()->id,
                (string) $request->input('target_key'),
                $request->normalizedValue(),
                $request->string('reason')->toString()
            );

            return back()->with('success', 'Data request berhasil diperbaiki oleh admin.');
        } catch (\RuntimeException $exception) {
            return back()->with('error', $exception->getMessage());
        } catch (\Illuminate\Validation\ValidationException $exception) {
            return back()->withErrors($exception->errors())->withInput();
        }
    }

    public function uploadFinalReport(
        UploadFinalReportRequest $request,
        AppraisalRequest $appraisalRequest,
        AppraisalRequestAdminWorkflowService $adminWorkflowService
    ): RedirectResponse {
        try {
            $adminWorkflowService->uploadFinalReport(
                $appraisalRequest,
                $request->validated()['report_pdf'],
                (int) $request->user()->id
            );

            return back()->with('success', 'PDF laporan final berhasil diunggah. Request selesai dan laporan sudah bisa diunduh customer.');
        } catch (\RuntimeException $exception) {
            return back()->with('error', $exception->getMessage());
        }
    }

    public function approveRevisionItem(
        AppraisalRequest $appraisalRequest,
        AppraisalRequestRevisionItem $revisionItem,
        AppraisalRequestRevisionReviewService $reviewService,
        AdminActionRequest $request
    ): RedirectResponse {
        try {
            $reviewService->approveItem(
                $appraisalRequest,
                $revisionItem,
                (int) $request->user()->id
            );

            return back()->with('success', 'Dokumen revisi berhasil disetujui.');
        } catch (\RuntimeException $exception) {
            return back()->with('error', $exception->getMessage());
        }
    }

    public function rejectRevisionItem(
        AppraisalRequest $appraisalRequest,
        AppraisalRequestRevisionItem $revisionItem,
        AppraisalRequestRevisionReviewService $reviewService,
        RejectAppraisalRevisionItemRequest $request
    ): RedirectResponse {
        $validated = $request->validated();

        try {
            $reviewService->rejectItem(
                $appraisalRequest,
                $revisionItem,
                (int) $request->user()->id,
                (string) $validated['review_note']
            );

            return back()->with('success', 'Item revisi dibuka kembali dan customer diminta mengunggah ulang dokumen.');
        } catch (\RuntimeException $exception) {
            return back()->with('error', $exception->getMessage());
        }
    }
}

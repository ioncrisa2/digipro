<?php

namespace App\Http\Controllers\Admin;

use App\Enums\AppraisalStatusEnum;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AdminActionRequest;
use App\Http\Requests\Admin\RejectAppraisalRevisionItemRequest;
use App\Http\Requests\Admin\StoreAppraisalCancellationRequest;
use App\Http\Requests\Admin\StoreAppraisalReportConfigurationRequest;
use App\Http\Requests\Admin\StoreAppraisalFieldCorrectionRequest;
use App\Http\Requests\Admin\StoreAppraisalRequestRevisionBatchRequest;
use App\Http\Requests\Admin\StoreAppraisalOfferRequest;
use App\Http\Requests\Admin\UploadFinalReportRequest;
use App\Http\Requests\Admin\UpdateAppraisalPhysicalReportRequest;
use App\Models\AppraisalRequest;
use App\Models\AppraisalRequestRevisionItem;
use App\Models\ReportSigner;
use App\Notifications\AppraisalPhysicalReportUpdated;
use App\Notifications\AppraisalStatusUpdated;
use App\Services\Admin\AppraisalRequestRevisionService;
use App\Services\Admin\AppraisalRequestRevisionReviewService;
use App\Services\Admin\AppraisalRequestWorkflowService;
use App\Services\Admin\AppraisalFieldCorrectionService;
use App\Services\Reports\AppraisalReportPdfService;
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
        AppraisalRequestWorkflowService $workflowService
    ): RedirectResponse {
        try {
            $result = $workflowService->updatePhysicalReport(
                $appraisalRequest,
                (int) $request->user()->id,
                $request->validated()
            );
        } catch (\RuntimeException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        $freshRecord = $appraisalRequest->fresh(['user']);
        if (
            $freshRecord?->user
            && in_array($result['event'] ?? null, ['printed', 'shipped', 'delivered'], true)
        ) {
            $freshRecord->user->notify(new AppraisalPhysicalReportUpdated(
                appraisalId: (int) $freshRecord->id,
                requestNumber: (string) ($freshRecord->request_number ?? ('REQ-' . $freshRecord->id)),
                event: (string) $result['event'],
                courier: $result['courier'] ?? null,
                trackingNumber: $result['tracking_number'] ?? null,
            ));
        }

        return back()->with('success', (string) ($result['message'] ?? 'Detail pengiriman hard copy berhasil diperbarui.'));
    }

    public function cancelRequest(
        StoreAppraisalCancellationRequest $request,
        AppraisalRequest $appraisalRequest,
        AppraisalRequestWorkflowService $workflowService
    ): RedirectResponse {
        $oldStatus = $appraisalRequest->status?->label() ?? (string) $appraisalRequest->status;
        $reason = trim((string) $request->string('reason')->toString());

        try {
            $workflowService->cancelRequest($appraisalRequest, (int) $request->user()->id, $reason);
        } catch (\RuntimeException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        $freshRecord = $appraisalRequest->fresh(['user']);
        if ($freshRecord?->user) {
            $freshRecord->user->notify(new AppraisalStatusUpdated(
                appraisalId: (int) $freshRecord->id,
                requestNumber: (string) ($freshRecord->request_number ?? ('REQ-' . $freshRecord->id)),
                oldStatus: $oldStatus,
                newStatus: AppraisalStatusEnum::Cancelled->label(),
                detail: $reason,
            ));
        }

        return back()->with('success', 'Request berhasil dibatalkan dan alasan pembatalan sudah tersimpan.');
    }

    public function downloadReportDraft(AppraisalRequest $appraisalRequest): StreamedResponse|RedirectResponse
    {
        if (($appraisalRequest->status?->value ?? (string) $appraisalRequest->status) !== AppraisalStatusEnum::ReportPreparation->value) {
            return back()->with('error', 'Draft laporan hanya tersedia saat request berada pada tahap persiapan laporan final.');
        }

        $path = (string) ($appraisalRequest->report_draft_pdf_path ?? '');
        if ($path === '' || ! Storage::disk('public')->exists($path)) {
            return back()->with('error', 'Draft laporan belum tersedia untuk diunduh.');
        }

        $requestNumber = preg_replace('/[^A-Za-z0-9\\-_.]/', '-', (string) ($appraisalRequest->request_number ?? ('REQ-' . $appraisalRequest->id)));

        return Storage::disk('public')->download($path, "Draft-Laporan-{$requestNumber}.pdf");
    }

    public function saveReportConfiguration(
        StoreAppraisalReportConfigurationRequest $request,
        AppraisalRequest $appraisalRequest,
        AppraisalReportPdfService $reportPdfService
    ): RedirectResponse {
        if (($appraisalRequest->status?->value ?? (string) $appraisalRequest->status) !== AppraisalStatusEnum::ReportPreparation->value) {
            return back()->with('error', 'Konfigurasi report hanya bisa diatur saat status persiapan laporan final.');
        }

        $validated = $request->validated();
        $reviewerSigner = ReportSigner::query()->findOrFail($validated['report_reviewer_signer_id']);
        $publicAppraiserSigner = ReportSigner::query()->findOrFail($validated['report_public_appraiser_signer_id']);

        $appraisalRequest->update([
            'report_reviewer_signer_id' => $reviewerSigner->id,
            'report_public_appraiser_signer_id' => $publicAppraiserSigner->id,
            'report_signer_snapshot' => [
                'reviewer' => [
                    'id' => $reviewerSigner->id,
                    'name' => $reviewerSigner->name,
                    'position_title' => $reviewerSigner->position_title,
                    'title_suffix' => $reviewerSigner->title_suffix,
                    'certification_number' => $reviewerSigner->certification_number,
                ],
                'public_appraiser' => [
                    'id' => $publicAppraiserSigner->id,
                    'name' => $publicAppraiserSigner->name,
                    'position_title' => $publicAppraiserSigner->position_title,
                    'title_suffix' => $publicAppraiserSigner->title_suffix,
                    'certification_number' => $publicAppraiserSigner->certification_number,
                ],
                'configured_at' => now()->toDateTimeString(),
                'configured_by' => (int) $request->user()->id,
            ],
        ]);

        try {
            $reportPdfService->generateDraft($appraisalRequest->fresh());
        } catch (\RuntimeException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return back()->with('success', 'Konfigurasi report berhasil disimpan dan draft diperbarui.');
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
        AppraisalReportPdfService $reportPdfService
    ): RedirectResponse {
        $validated = $request->validated();

        $oldStatus = $appraisalRequest->status?->label() ?? AppraisalStatusEnum::ReportPreparation->label();

        try {
            $reportPdfService->storeFinalUploadedPdf(
                $appraisalRequest,
                $validated['report_pdf'],
                (int) $request->user()->id
            );
        } catch (\RuntimeException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        $freshRecord = $appraisalRequest->fresh(['user']);
        if ($freshRecord?->user) {
            $freshRecord->user->notify(new AppraisalStatusUpdated(
                appraisalId: (int) $freshRecord->id,
                requestNumber: (string) ($freshRecord->request_number ?? ('REQ-' . $freshRecord->id)),
                oldStatus: $oldStatus,
                newStatus: AppraisalStatusEnum::Completed->label(),
            ));
        }

        return back()->with('success', 'PDF laporan final berhasil diunggah. Request selesai dan laporan sudah bisa diunduh customer.');
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

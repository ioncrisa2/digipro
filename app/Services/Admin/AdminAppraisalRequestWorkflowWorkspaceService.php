<?php

namespace App\Services\Admin;

use App\Models\AppraisalRequest;
use App\Models\AppraisalRequestRevisionItem;
use Illuminate\Http\UploadedFile;

class AdminAppraisalRequestWorkflowWorkspaceService
{
    public function __construct(
        private readonly AppraisalRequestRevisionService $revisionService,
        private readonly AppraisalRequestWorkflowService $workflowService,
        private readonly AppraisalRequestAdminWorkflowService $adminWorkflowService,
        private readonly AppraisalFieldCorrectionService $fieldCorrectionService,
        private readonly AppraisalRequestRevisionReviewService $revisionReviewService,
    ) {
    }

    public function storeRevisionBatch(
        AppraisalRequest $appraisalRequest,
        int $actorId,
        array $resolvedItems,
        string $adminNote,
    ): string {
        $this->revisionService->createBatch(
            $appraisalRequest,
            $actorId,
            $resolvedItems,
            $adminNote
        );

        return 'Permintaan revisi berhasil dibuat dan customer perlu memperbaiki item yang diminta.';
    }

    public function sendOffer(AppraisalRequest $appraisalRequest, int $actorId, array $validated): string
    {
        $result = $this->workflowService->sendOffer($appraisalRequest, $actorId, $validated);

        return ($result['action'] ?? null) === 'offer_revised'
            ? 'Counter offer berhasil dikirim.'
            : 'Penawaran berhasil dikirim.';
    }

    public function approveLatestNegotiation(AppraisalRequest $appraisalRequest, int $actorId): string
    {
        $this->workflowService->approveLatestNegotiation($appraisalRequest, $actorId);

        return 'Harapan fee user disetujui. Request langsung masuk ke tahap tanda tangan kontrak.';
    }

    public function verifyDocs(AppraisalRequest $appraisalRequest, int $actorId): string
    {
        $this->workflowService->verifyDocs($appraisalRequest, $actorId);

        return 'Dokumen berhasil diverifikasi. Request masuk ke tahap menunggu penawaran.';
    }

    public function markDocsIncomplete(AppraisalRequest $appraisalRequest): string
    {
        $this->workflowService->markDocsIncomplete($appraisalRequest);

        return 'Request berhasil ditandai dokumen kurang.';
    }

    public function markContractSigned(AppraisalRequest $appraisalRequest): string
    {
        $this->workflowService->markContractSigned($appraisalRequest);

        return 'Status kontrak berhasil diperbarui menjadi ditandatangani.';
    }

    public function saveContractSignerConfiguration(AppraisalRequest $appraisalRequest, int $actorId, array $validated): string
    {
        $this->adminWorkflowService->saveContractSignerConfiguration($appraisalRequest, $actorId, $validated);

        return 'Penilai publik untuk kontrak berhasil ditetapkan.';
    }

    public function verifyPayment(AppraisalRequest $appraisalRequest): string
    {
        $this->workflowService->verifyPayment($appraisalRequest);

        return 'Pembayaran terverifikasi. Request masuk ke proses valuasi.';
    }

    public function updatePhysicalReport(AppraisalRequest $appraisalRequest, int $actorId, array $validated): string
    {
        $result = $this->adminWorkflowService->updatePhysicalReport($appraisalRequest, $actorId, $validated);

        return (string) ($result['message'] ?? 'Detail pengiriman hard copy berhasil diperbarui.');
    }

    public function cancelRequest(AppraisalRequest $appraisalRequest, int $actorId, string $reason): string
    {
        $this->adminWorkflowService->cancelRequest($appraisalRequest, $actorId, $reason);

        return 'Request berhasil dibatalkan dan alasan pembatalan sudah tersimpan.';
    }

    public function resolveDraftDownload(AppraisalRequest $appraisalRequest): array
    {
        return $this->adminWorkflowService->resolveDraftDownload($appraisalRequest);
    }

    public function saveReportConfiguration(AppraisalRequest $appraisalRequest, int $actorId, array $validated): string
    {
        $this->adminWorkflowService->saveReportConfiguration($appraisalRequest, $actorId, $validated);

        return 'Konfigurasi report berhasil disimpan dan draft diperbarui.';
    }

    public function storeFieldCorrection(
        AppraisalRequest $appraisalRequest,
        int $actorId,
        string $targetKey,
        mixed $normalizedValue,
        string $reason,
    ): string {
        $this->fieldCorrectionService->apply(
            $appraisalRequest,
            $actorId,
            $targetKey,
            $normalizedValue,
            $reason
        );

        return 'Data request berhasil diperbaiki oleh admin.';
    }

    public function uploadFinalReport(AppraisalRequest $appraisalRequest, UploadedFile $file, int $actorId): string
    {
        $this->adminWorkflowService->uploadFinalReport($appraisalRequest, $file, $actorId);

        return 'PDF laporan final berhasil diunggah. Request selesai dan laporan sudah bisa diunduh customer.';
    }

    public function approveRevisionItem(
        AppraisalRequest $appraisalRequest,
        AppraisalRequestRevisionItem $revisionItem,
        int $actorId,
    ): string {
        $this->revisionReviewService->approveItem($appraisalRequest, $revisionItem, $actorId);

        return 'Dokumen revisi berhasil disetujui.';
    }

    public function rejectRevisionItem(
        AppraisalRequest $appraisalRequest,
        AppraisalRequestRevisionItem $revisionItem,
        int $actorId,
        string $reviewNote,
    ): string {
        $this->revisionReviewService->rejectItem($appraisalRequest, $revisionItem, $actorId, $reviewNote);

        return 'Item revisi dibuka kembali dan customer diminta mengunggah ulang dokumen.';
    }
}

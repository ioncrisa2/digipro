<?php

namespace App\Services\Admin;

use App\Enums\AppraisalStatusEnum;
use App\Models\AppraisalRequest;
use App\Models\ReportSigner;
use App\Notifications\AppraisalPhysicalReportUpdated;
use App\Notifications\AppraisalStatusUpdated;
use App\Services\Reports\AppraisalReportPdfService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class AppraisalRequestAdminWorkflowService
{
    public function __construct(
        private readonly AppraisalRequestWorkflowService $workflowService,
        private readonly AppraisalReportPdfService $reportPdfService,
    ) {
    }

    public function updatePhysicalReport(AppraisalRequest $record, int $actorId, array $data): array
    {
        $result = $this->workflowService->updatePhysicalReport($record, $actorId, $data);
        $freshRecord = $record->fresh(['user']);

        if (
            $freshRecord?->user
            && in_array($result['event'] ?? null, ['printed', 'shipped', 'delivered'], true)
        ) {
            $freshRecord->user->notify(new AppraisalPhysicalReportUpdated(
                appraisalId: (int) $freshRecord->id,
                requestNumber: $this->requestNumber($freshRecord),
                event: (string) $result['event'],
                courier: $result['courier'] ?? null,
                trackingNumber: $result['tracking_number'] ?? null,
            ));
        }

        return $result;
    }

    public function cancelRequest(AppraisalRequest $record, int $actorId, string $reason): void
    {
        $oldStatus = $record->status?->label() ?? (string) $record->status;

        $this->workflowService->cancelRequest($record, $actorId, $reason);

        $this->notifyStatusUpdated(
            $record->fresh(['user']),
            $oldStatus,
            AppraisalStatusEnum::Cancelled->label(),
            $reason,
        );
    }

    public function resolveDraftDownload(AppraisalRequest $record): array
    {
        $this->ensureReportPreparation($record, 'Draft laporan hanya tersedia saat request berada pada tahap persiapan laporan final.');

        $path = (string) ($record->report_draft_pdf_path ?? '');
        if ($path === '' || ! Storage::disk('public')->exists($path)) {
            throw new RuntimeException('Draft laporan belum tersedia untuk diunduh.');
        }

        return [
            'disk' => 'public',
            'path' => $path,
            'download_name' => 'Draft-Laporan-' . $this->safeFileRequestNumber($record) . '.pdf',
        ];
    }

    public function saveReportConfiguration(AppraisalRequest $record, int $actorId, array $validated): void
    {
        $this->ensureReportPreparation($record, 'Konfigurasi report hanya bisa diatur saat status persiapan laporan final.');

        $reviewerSigner = ReportSigner::query()->findOrFail($validated['report_reviewer_signer_id']);
        $publicAppraiserSigner = ReportSigner::query()->findOrFail($validated['report_public_appraiser_signer_id']);

        $record->update([
            'report_reviewer_signer_id' => $reviewerSigner->id,
            'report_public_appraiser_signer_id' => $publicAppraiserSigner->id,
            'report_signer_snapshot' => [
                'reviewer' => $this->signerSnapshot($reviewerSigner),
                'public_appraiser' => $this->signerSnapshot($publicAppraiserSigner),
                'configured_at' => now()->toDateTimeString(),
                'configured_by' => $actorId,
            ],
        ]);

        $this->reportPdfService->generateDraft($record->fresh());
    }

    public function saveContractSignerConfiguration(AppraisalRequest $record, int $actorId, array $validated): void
    {
        $status = $record->status?->value ?? (string) $record->status;
        if ($status !== AppraisalStatusEnum::WaitingSignature->value) {
            throw new RuntimeException('Penandatangan kontrak hanya bisa diatur saat status menunggu tanda tangan.');
        }

        $publicAppraiserSigner = ReportSigner::query()->findOrFail($validated['contract_public_appraiser_signer_id']);

        if ($publicAppraiserSigner->role !== 'public_appraiser' || ! $publicAppraiserSigner->is_active) {
            throw new RuntimeException('Penilai publik yang dipilih tidak valid.');
        }

        if (blank($publicAppraiserSigner->email) || blank($publicAppraiserSigner->user_id)) {
            throw new RuntimeException('Profil penilai publik belum lengkap (email Peruri / user login).');
        }

        $record->update([
            'contract_public_appraiser_signer_id' => $publicAppraiserSigner->id,
            'contract_signer_snapshot' => [
                'public_appraiser' => $this->contractSignerSnapshot($publicAppraiserSigner),
                'configured_at' => now()->toDateTimeString(),
                'configured_by' => $actorId,
            ],
        ]);
    }

    public function uploadFinalReport(AppraisalRequest $record, UploadedFile $file, int $actorId): void
    {
        $oldStatus = $record->status?->label() ?? AppraisalStatusEnum::ReportPreparation->label();

        $this->reportPdfService->storeFinalUploadedPdf($record, $file, $actorId);

        $this->notifyStatusUpdated(
            $record->fresh(['user']),
            $oldStatus,
            AppraisalStatusEnum::Completed->label(),
        );
    }

    private function signerSnapshot(ReportSigner $signer): array
    {
        return [
            'id' => $signer->id,
            'name' => $signer->name,
            'position_title' => $signer->position_title,
            'title_suffix' => $signer->title_suffix,
            'certification_number' => $signer->certification_number,
        ];
    }

    private function contractSignerSnapshot(ReportSigner $signer): array
    {
        return array_merge($this->signerSnapshot($signer), [
            'email' => $signer->email,
            'user_id' => $signer->user_id,
        ]);
    }

    private function ensureReportPreparation(AppraisalRequest $record, string $message): void
    {
        if (($record->status?->value ?? (string) $record->status) !== AppraisalStatusEnum::ReportPreparation->value) {
            throw new RuntimeException($message);
        }
    }

    private function notifyStatusUpdated(
        ?AppraisalRequest $record,
        string $oldStatus,
        string $newStatus,
        ?string $detail = null,
    ): void {
        if (! $record?->user) {
            return;
        }

        $record->user->notify(new AppraisalStatusUpdated(
            appraisalId: (int) $record->id,
            requestNumber: $this->requestNumber($record),
            oldStatus: $oldStatus,
            newStatus: $newStatus,
            detail: $detail,
        ));
    }

    private function requestNumber(AppraisalRequest $record): string
    {
        return (string) ($record->request_number ?? ('REQ-' . $record->id));
    }

    private function safeFileRequestNumber(AppraisalRequest $record): string
    {
        return preg_replace('/[^A-Za-z0-9\\-_.]/', '-', $this->requestNumber($record));
    }
}

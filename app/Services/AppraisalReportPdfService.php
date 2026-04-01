<?php

namespace App\Services;

use App\Enums\AppraisalStatusEnum;
use App\Models\AppraisalRequest;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class AppraisalReportPdfService
{
    public function __construct(
        private readonly AppraisalReportPayloadBuilder $payloadBuilder,
        private readonly AppraisalFinalDocumentService $finalDocumentService
    ) {
    }

    public function generateDraft(AppraisalRequest $record): void
    {
        $snapshot = $record->market_preview_snapshot;

        if (! is_array($snapshot) || empty($snapshot['assets'])) {
            throw new RuntimeException('Snapshot preview belum tersedia untuk membuat draft laporan.');
        }

        $record->loadMissing(['user:id,name,email']);
        $reportPayload = $this->payloadBuilder->build($record);

        $pdfBinary = Pdf::loadView('pdfs.appraisal-market-report-draft', [
            'report' => $reportPayload,
        ])
            ->setPaper('a4', 'portrait')
            ->output();

        $requestNumber = $this->safeRequestNumber($record);
        $path = "appraisal-requests/{$record->id}/reports/draft-report-{$requestNumber}.pdf";

        $this->deleteDraft($record);
        Storage::disk('public')->put($path, $pdfBinary);

        $record->update([
            'report_draft_generated_at' => now(),
            'report_draft_pdf_path' => $path,
            'report_draft_pdf_size' => strlen($pdfBinary),
        ]);
    }

    public function deleteDraft(AppraisalRequest $record): void
    {
        if ($record->report_draft_pdf_path && Storage::disk('public')->exists($record->report_draft_pdf_path)) {
            Storage::disk('public')->delete($record->report_draft_pdf_path);
        }

        $record->forceFill([
            'report_draft_generated_at' => null,
            'report_draft_pdf_path' => null,
            'report_draft_pdf_size' => null,
        ])->save();
    }

    public function storeFinalUploadedPdf(AppraisalRequest $record, UploadedFile $file, int $actorId): void
    {
        if (($record->status?->value ?? (string) $record->status) !== AppraisalStatusEnum::ReportPreparation->value) {
            throw new RuntimeException('Laporan final hanya bisa diupload saat status sedang disiapkan admin.');
        }

        $signerSnapshot = is_array($record->report_signer_snapshot) ? $record->report_signer_snapshot : [];
        if (blank(data_get($signerSnapshot, 'reviewer.name')) || blank(data_get($signerSnapshot, 'public_appraiser.name'))) {
            throw new RuntimeException('Pilih reviewer dan penilai publik report terlebih dahulu sebelum upload PDF final.');
        }

        if ($record->report_pdf_path && Storage::disk('public')->exists($record->report_pdf_path)) {
            Storage::disk('public')->delete($record->report_pdf_path);
        }

        $requestNumber = $this->safeRequestNumber($record);
        $extension = strtolower((string) $file->getClientOriginalExtension()) ?: 'pdf';
        $storedName = "final-report-{$requestNumber}-" . now()->format('YmdHis') . ".{$extension}";
        $storedPath = $file->storeAs("appraisal-requests/{$record->id}/reports/final", $storedName, 'public');

        $this->finalDocumentService->generateAfterPayment(
            $record->fresh(['payments', 'offerNegotiations.user', 'files', 'user', 'assets'])
        );

        $record->update([
            'report_generated_at' => now(),
            'report_generated_by' => $actorId,
            'report_pdf_path' => $storedPath,
            'report_pdf_size' => $file->getSize(),
            'status' => AppraisalStatusEnum::Completed,
        ]);
    }

    private function safeRequestNumber(AppraisalRequest $record): string
    {
        return preg_replace('/[^A-Za-z0-9\-_.]/', '-', (string) ($record->request_number ?? ('REQ-' . $record->id)));
    }
}

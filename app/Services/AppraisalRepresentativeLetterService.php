<?php

namespace App\Services;

use App\Models\AppraisalRequest;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Storage;

class AppraisalRepresentativeLetterService
{
    public function __construct(
        private readonly AppraisalService $appraisalService
    ) {
    }

    public function generateForSignedContract(AppraisalRequest $record, array $signatureMeta = []): void
    {
        $payload = $this->appraisalService->buildRepresentativeLetterPayload($record, $signatureMeta);

        $existingFiles = $record->files()
            ->where('type', 'representative_letter_pdf')
            ->get();

        foreach ($existingFiles as $file) {
            if ($file->path && Storage::disk('public')->exists($file->path)) {
                Storage::disk('public')->delete($file->path);
            }

            $file->delete();
        }

        $pdfBinary = Pdf::loadView('pdfs.appraisal-representative-letter', [
            'doc' => $payload,
        ])->setPaper('a4', 'portrait')->output();

        $requestNumber = preg_replace('/[^A-Za-z0-9\-_.]/', '-', (string) ($record->request_number ?? ('REQ-' . $record->id)));
        $storedName = "representative-letter-{$requestNumber}-" . now()->format('YmdHis') . '.pdf';
        $storedPath = "appraisal-requests/{$record->id}/representative/{$storedName}";

        Storage::disk('public')->put($storedPath, $pdfBinary);

        $record->files()->create([
            'type' => 'representative_letter_pdf',
            'path' => $storedPath,
            'original_name' => "Surat-Representatif-{$requestNumber}.pdf",
            'mime' => 'application/pdf',
            'size' => strlen($pdfBinary),
        ]);
    }
}

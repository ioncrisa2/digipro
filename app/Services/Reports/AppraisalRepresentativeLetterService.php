<?php

namespace App\Services\Reports;

use App\Models\AppraisalRequest;
use App\Services\Customer\AppraisalService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\DB;
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

        $oldPaths = $existingFiles
            ->pluck('path')
            ->filter(fn ($path) => filled($path))
            ->values()
            ->all();

        $pdfBinary = Pdf::loadView('pdfs.appraisal-representative-letter', [
            'doc' => $payload,
        ])->setPaper('a4', 'portrait')->output();

        $requestNumber = preg_replace('/[^A-Za-z0-9\-_.]/', '-', (string) ($record->request_number ?? ('REQ-' . $record->id)));
        $storedName = "representative-letter-{$requestNumber}-" . now()->format('YmdHis') . '.pdf';
        $storedPath = "appraisal-requests/{$record->id}/representative/{$storedName}";

        Storage::disk('public')->put($storedPath, $pdfBinary);

        try {
            DB::transaction(function () use ($record, $existingFiles, $storedPath, $requestNumber, $pdfBinary): void {
                foreach ($existingFiles as $file) {
                    $file->delete();
                }

                $record->files()->create([
                    'type' => 'representative_letter_pdf',
                    'path' => $storedPath,
                    'original_name' => "Surat-Representatif-{$requestNumber}.pdf",
                    'mime' => 'application/pdf',
                    'size' => strlen($pdfBinary),
                ]);
            });
        } catch (\Throwable $e) {
            if (Storage::disk('public')->exists($storedPath)) {
                Storage::disk('public')->delete($storedPath);
            }

            throw $e;
        }

        foreach ($oldPaths as $oldPath) {
            if ($oldPath !== $storedPath && Storage::disk('public')->exists($oldPath)) {
                Storage::disk('public')->delete($oldPath);
            }
        }
    }
}

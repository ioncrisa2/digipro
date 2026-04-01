<?php

namespace App\Services;

use App\Enums\AppraisalStatusEnum;
use App\Models\AppraisalRequest;
use App\Models\ConsentDocument;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Storage;

class AppraisalFinalDocumentService
{
    public function __construct(
        private readonly AppraisalService $appraisalService,
        private readonly AppraisalRepresentativeLetterService $representativeLetterService
    ) {
    }

    public function generateAfterPayment(AppraisalRequest $record): void
    {
        $record->loadMissing(['payments', 'offerNegotiations.user', 'files', 'user', 'assets']);

        if (! $this->isEligible($record)) {
            return;
        }

        $signatureMeta = $this->signatureMeta($record);

        $this->storeAgreementPdf($record);
        $this->storeDisclaimerPdf($record);
        $this->representativeLetterService->generateForSignedContract($record, $signatureMeta);
    }

    private function isEligible(AppraisalRequest $record): bool
    {
        $status = $record->status?->value ?? (string) $record->status;
        $latestPayment = $record->payments->sortByDesc('id')->first();

        return in_array($status, [
            AppraisalStatusEnum::ContractSigned->value,
            AppraisalStatusEnum::ValuationOnProgress->value,
            AppraisalStatusEnum::ValuationCompleted->value,
            AppraisalStatusEnum::ReportReady->value,
            AppraisalStatusEnum::Completed->value,
        ], true) && $latestPayment?->status === 'paid';
    }

    private function signatureMeta(AppraisalRequest $record): array
    {
        $signatureLog = $record->offerNegotiations
            ->where('action', 'contract_sign_mock')
            ->sortByDesc('id')
            ->first();

        return is_array($signatureLog?->meta) ? $signatureLog->meta : [];
    }

    private function storeAgreementPdf(AppraisalRequest $record): void
    {
        $doc = $this->appraisalService->buildContractDocumentPayload($record);
        $requestNumber = $this->safeRequestNumber($record);
        $pdfBinary = Pdf::loadView('pdfs.appraisal-contract-offer', [
            'doc' => array_merge($doc, [
                'title' => 'AGREEMENT LAYANAN DIGIPRO',
                'subtitle' => '(Dokumen agreement final customer)',
            ]),
        ])->setPaper('a4', 'portrait')->output();

        $path = "appraisal-requests/{$record->id}/final-documents/agreement-{$requestNumber}.pdf";

        $this->replaceStoredRequestFile(
            $record,
            'agreement_pdf',
            $path,
            "Agreement-{$requestNumber}.pdf",
            $pdfBinary
        );
    }

    private function storeDisclaimerPdf(AppraisalRequest $record): void
    {
        $consentDocument = $this->resolveConsentDocument($record);
        $payload = [
            'title' => 'DISCLAIMER & PERSETUJUAN DIGIPRO',
            'request_number' => $record->request_number ?? ('REQ-' . $record->id),
            'client_name' => $record->client_name ?: ($record->user?->name ?? '-'),
            'accepted_at' => optional($record->consent_accepted_at)->toDateTimeString() ?: '-',
            'version' => $record->consent_version ?: '-',
            'hash' => $record->consent_hash ?: '-',
            'document_title' => $consentDocument?->title ?? 'Dokumen Consent DigiPro',
            'sections' => is_array($consentDocument?->sections) ? $consentDocument->sections : [],
            'checkbox_label' => $consentDocument?->checkbox_label
                ?? 'Saya telah membaca, memahami, dan menyetujui dokumen ini.',
        ];

        $requestNumber = $this->safeRequestNumber($record);
        $pdfBinary = Pdf::loadView('pdfs.appraisal-disclaimer-document', [
            'doc' => $payload,
        ])->setPaper('a4', 'portrait')->output();

        $path = "appraisal-requests/{$record->id}/final-documents/disclaimer-{$requestNumber}.pdf";

        $this->replaceStoredRequestFile(
            $record,
            'disclaimer_pdf',
            $path,
            "Disclaimer-{$requestNumber}.pdf",
            $pdfBinary
        );
    }

    private function resolveConsentDocument(AppraisalRequest $record): ?ConsentDocument
    {
        if (! $record->consent_version || ! $record->consent_hash) {
            return null;
        }

        return ConsentDocument::query()
            ->where('code', 'appraisal_request_consent')
            ->where('version', $record->consent_version)
            ->where('hash', $record->consent_hash)
            ->first();
    }

    private function replaceStoredRequestFile(
        AppraisalRequest $record,
        string $type,
        string $path,
        string $originalName,
        string $pdfBinary
    ): void {
        $existingFiles = $record->files()
            ->where('type', $type)
            ->get();

        foreach ($existingFiles as $file) {
            if ($file->path && Storage::disk('public')->exists($file->path)) {
                Storage::disk('public')->delete($file->path);
            }

            $file->delete();
        }

        Storage::disk('public')->put($path, $pdfBinary);

        $record->files()->create([
            'type' => $type,
            'path' => $path,
            'original_name' => $originalName,
            'mime' => 'application/pdf',
            'size' => strlen($pdfBinary),
        ]);
    }

    private function safeRequestNumber(AppraisalRequest $record): string
    {
        return preg_replace('/[^A-Za-z0-9\-_.]/', '-', (string) ($record->request_number ?? ('REQ-' . $record->id)));
    }
}

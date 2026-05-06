<?php

namespace App\Services\Reports;

use App\Enums\AppraisalStatusEnum;
use App\Models\AppraisalRequest;
use App\Models\ConsentDocument;
use App\Services\Customer\AppraisalService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\DB;
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
            AppraisalStatusEnum::ReportPreparation->value,
            AppraisalStatusEnum::ReportReady->value,
            AppraisalStatusEnum::Completed->value,
        ], true) && $latestPayment?->status === 'paid';
    }

    private function signatureMeta(AppraisalRequest $record): array
    {
        $envelope = $record->signatureEnvelopes()
            ->where('document_type', 'contract')
            ->where('provider', 'peruri_signit')
            ->with('participants')
            ->first();

        if ($envelope) {
            $customer = $envelope->participants
                ->first(fn ($p) => $p->role === 'customer' && $p->status === 'signed');

            if ($customer) {
                return [
                    'flow' => 'peruri_contract_signature',
                    'provider' => 'peruri_signit',
                    'model' => $envelope->model,
                    'external_envelope_id' => $envelope->external_envelope_id,
                    'external_order_id' => $customer->external_order_id,
                    'signed_at' => optional($customer->signed_at)->toIso8601String(),
                    'signed_by_name' => $customer->name,
                    'signed_by_email' => $customer->email,
                    'document_hash' => $envelope->document_hash,
                    'signed_pdf_path' => $envelope->signed_pdf_path,
                ];
            }
        }

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
                'title' => 'AGREEMENT LAYANAN DIGIPRO BY KJPP HJAR',
                'subtitle' => '(Dokumen agreement final customer)',
            ]),
        ])->setPaper('a4', 'portrait')->output();

        $path = "appraisal-requests/{$record->id}/final-documents/agreement-{$requestNumber}-" . now()->format('YmdHis') . ".pdf";

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
            'title' => 'DISCLAIMER & PERSETUJUAN DIGIPRO BY KJPP HJAR',
            'request_number' => $record->request_number ?? ('REQ-' . $record->id),
            'client_name' => $record->client_name ?: ($record->user?->name ?? '-'),
            'accepted_at' => optional($record->consent_accepted_at)->toDateTimeString() ?: '-',
            'version' => $record->consent_version ?: '-',
            'hash' => $record->consent_hash ?: '-',
            'document_title' => $consentDocument?->title ?? 'Dokumen Consent DigiPro by KJPP HJAR',
            'sections' => is_array($consentDocument?->sections) ? $consentDocument->sections : [],
            'checkbox_label' => $consentDocument?->checkbox_label
                ?? 'Saya telah membaca, memahami, dan menyetujui dokumen ini.',
        ];

        $requestNumber = $this->safeRequestNumber($record);
        $pdfBinary = Pdf::loadView('pdfs.appraisal-disclaimer-document', [
            'doc' => $payload,
        ])->setPaper('a4', 'portrait')->output();

        $path = "appraisal-requests/{$record->id}/final-documents/disclaimer-{$requestNumber}-" . now()->format('YmdHis') . ".pdf";

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

        $oldPaths = $existingFiles
            ->pluck('path')
            ->filter(fn ($filePath) => filled($filePath))
            ->values()
            ->all();

        Storage::disk('public')->put($path, $pdfBinary);

        try {
            DB::transaction(function () use ($record, $type, $path, $originalName, $pdfBinary, $existingFiles): void {
                foreach ($existingFiles as $file) {
                    $file->delete();
                }

                $record->files()->create([
                    'type' => $type,
                    'path' => $path,
                    'original_name' => $originalName,
                    'mime' => 'application/pdf',
                    'size' => strlen($pdfBinary),
                ]);
            });
        } catch (\Throwable $e) {
            if (Storage::disk('public')->exists($path)) {
                Storage::disk('public')->delete($path);
            }

            throw $e;
        }

        foreach ($oldPaths as $oldPath) {
            if ($oldPath !== $path && Storage::disk('public')->exists($oldPath)) {
                Storage::disk('public')->delete($oldPath);
            }
        }
    }

    private function safeRequestNumber(AppraisalRequest $record): string
    {
        return preg_replace('/[^A-Za-z0-9\-_.]/', '-', (string) ($record->request_number ?? ('REQ-' . $record->id)));
    }
}

<?php

namespace App\Services\Signatures;

use App\Models\AppraisalRequest;
use App\Models\ReportSigner;
use App\Models\SignatureEnvelope;
use App\Models\User;
use App\Services\Customer\AppraisalService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class CanvasDemoContractSignatureService
{
    private const DOCUMENT_TYPE = 'contract';

    public function sign(
        User $customer,
        AppraisalRequest $record,
        AppraisalService $appraisalService,
        UploadedFile $customerSignature,
        array $auditContext = [],
    ): SignatureEnvelope {
        $lockKey = "signature:contract:canvas-demo:{$record->id}";

        return Cache::lock($lockKey, 120)->block(10, function () use (
            $customer,
            $record,
            $appraisalService,
            $customerSignature,
            $auditContext,
        ): SignatureEnvelope {
            $record->refresh();
            $record->loadMissing(['user:id,name,email', 'contractPublicAppraiserSigner']);

            $signer = $this->configuredPublicAppraiser($record);
            $envelope = $this->contractEnvelope($record)->loadMissing('participants');

            if ($envelope->status === 'completed'
                && is_string($envelope->signed_pdf_path)
                && Storage::disk($this->documentDisk())->exists($envelope->signed_pdf_path)) {
                return $envelope;
            }

            $original = $this->renderContractPdf($record, $appraisalService);
            $originalHash = 'sha256:'.hash('sha256', $original['binary']);
            $originalPath = $this->storePdf(
                $record,
                $original['binary'],
                'contract-canvas-demo-original',
            );

            $customerImage = $this->storeCustomerSignatureSnapshot($record, $customerSignature);
            $publicImage = $this->storePublicAppraiserSignatureSnapshot($record, $signer);
            $signedAt = now();

            DB::transaction(function () use (
                $envelope,
                $customer,
                $signer,
                $customerImage,
                $publicImage,
                $signedAt,
                $originalHash,
                $originalPath,
                $original,
                $auditContext,
            ): void {
                $customerParticipant = $envelope->participants()->updateOrCreate(
                    ['role' => 'customer'],
                    [
                        'sequence' => 1,
                        'email' => (string) $customer->email,
                        'name' => (string) $customer->name,
                        'external_order_id' => null,
                        'status' => 'signed',
                        'signed_at' => $signedAt,
                        'meta' => array_merge($customerImage, [
                            'provider' => $this->provider(),
                            'method' => 'canvas',
                            'automatic' => false,
                            'reference_id' => "DEMO-CUSTOMER-{$envelope->id}",
                            'audit' => $auditContext,
                        ]),
                    ],
                );

                $publicParticipant = $envelope->participants()->updateOrCreate(
                    ['role' => 'public_appraiser'],
                    [
                        'sequence' => 2,
                        'email' => (string) ($signer->email ?? ''),
                        'name' => (string) $signer->name,
                        'external_order_id' => null,
                        'status' => 'signed',
                        'signed_at' => $signedAt,
                        'meta' => array_merge($publicImage, [
                            'provider' => $this->provider(),
                            'method' => 'admin_specimen',
                            'automatic' => true,
                            'report_signer_id' => $signer->id,
                            'configured_by' => $signer->demo_signature_updated_by,
                            'reference_id' => "DEMO-PUBLIC-{$envelope->id}",
                        ]),
                    ],
                );

                $envelope->update([
                    'model' => 'parallel',
                    'status' => 'processing',
                    'document_hash' => $originalHash,
                    'original_pdf_path' => $originalPath,
                    'signed_pdf_path' => null,
                    'last_error' => null,
                    'meta' => [
                        'provider' => $this->provider(),
                        'document_type' => self::DOCUMENT_TYPE,
                        'demo_only' => true,
                        'automatic_public_appraiser_signature' => true,
                        'document_page_count' => $original['page_count'],
                        'customer_participant_id' => $customerParticipant->id,
                        'public_appraiser_participant_id' => $publicParticipant->id,
                    ],
                ]);
            });

            $record->unsetRelation('signatureEnvelopes');
            $final = $this->renderContractPdf($record, $appraisalService);
            $signedPath = $this->storePdf(
                $record,
                $final['binary'],
                'contract-canvas-demo-signed',
            );
            $finalHash = 'sha256:'.hash('sha256', $final['binary']);

            DB::transaction(function () use ($record, $envelope, $signedPath, $finalHash, $final): void {
                $envelope->update([
                    'status' => 'completed',
                    'signed_pdf_path' => $signedPath,
                    'last_error' => null,
                    'meta' => array_merge((array) $envelope->meta, [
                        'final_document_hash' => $finalHash,
                        'final_document_page_count' => $final['page_count'],
                    ]),
                ]);

                $this->upsertSignedContractFile($record, $signedPath, strlen($final['binary']));
            });

            return $envelope->fresh(['participants']);
        });
    }

    private function configuredPublicAppraiser(AppraisalRequest $record): ReportSigner
    {
        $signer = $record->contractPublicAppraiserSigner;
        if (! $signer || ! $signer->is_active) {
            throw new RuntimeException('Penilai publik kontrak belum ditetapkan atau tidak aktif.');
        }

        $path = (string) $signer->demo_signature_path;
        if ($path === '' || ! Storage::disk($this->signatureDisk())->exists($path)) {
            throw new RuntimeException('Tanda tangan demo penilai publik belum disetel oleh admin.');
        }

        return $signer;
    }

    private function contractEnvelope(AppraisalRequest $record): SignatureEnvelope
    {
        return SignatureEnvelope::query()->firstOrCreate([
            'subject_type' => AppraisalRequest::class,
            'subject_id' => (int) $record->id,
            'document_type' => self::DOCUMENT_TYPE,
            'provider' => $this->provider(),
        ], [
            'model' => 'parallel',
            'status' => 'draft',
            'meta' => ['demo_only' => true],
        ]);
    }

    /** @return array{binary:string,page_count:int} */
    private function renderContractPdf(AppraisalRequest $record, AppraisalService $appraisalService): array
    {
        $doc = $appraisalService->buildContractDocumentPayload($record);
        $pdf = Pdf::loadView('pdfs.appraisal-contract-offer', ['doc' => $doc])
            ->setPaper('a4', 'portrait');

        $binary = $pdf->output();

        return [
            'binary' => $binary,
            'page_count' => max(1, (int) $pdf->getDomPDF()->getCanvas()->get_page_count()),
        ];
    }

    /** @return array<string, mixed> */
    private function storeCustomerSignatureSnapshot(
        AppraisalRequest $record,
        UploadedFile $signature,
    ): array {
        $binary = file_get_contents($signature->getRealPath());
        if ($binary === false) {
            throw new RuntimeException('Tanda tangan customer tidak dapat dibaca.');
        }

        return $this->storeSignatureSnapshot(
            $record,
            'customer',
            $binary,
            (string) $signature->getMimeType(),
        );
    }

    /** @return array<string, mixed> */
    private function storePublicAppraiserSignatureSnapshot(
        AppraisalRequest $record,
        ReportSigner $signer,
    ): array {
        $binary = Storage::disk($this->signatureDisk())->get((string) $signer->demo_signature_path);

        return $this->storeSignatureSnapshot(
            $record,
            'public-appraiser',
            $binary,
            (string) ($signer->demo_signature_mime ?: 'image/png'),
        );
    }

    /** @return array<string, mixed> */
    private function storeSignatureSnapshot(
        AppraisalRequest $record,
        string $role,
        string $binary,
        string $mime,
    ): array {
        $hashValue = hash('sha256', $binary);
        $extension = $mime === 'image/png' ? 'png' : 'jpg';
        $path = "demo-signatures/contracts/{$record->id}/{$role}-{$hashValue}.{$extension}";

        if (! Storage::disk($this->signatureDisk())->put($path, $binary)) {
            throw new RuntimeException('Snapshot tanda tangan demo gagal disimpan.');
        }

        return [
            'signature_disk' => $this->signatureDisk(),
            'signature_path' => $path,
            'signature_mime' => $mime,
            'signature_hash' => "sha256:{$hashValue}",
        ];
    }

    private function storePdf(AppraisalRequest $record, string $binary, string $prefix): string
    {
        $requestNumber = $this->safeRequestNumber($record);
        $path = "appraisal-requests/{$record->id}/contracts/{$prefix}-{$requestNumber}-".now()->format('YmdHisv').'.pdf';

        if (! Storage::disk($this->documentDisk())->put($path, $binary)) {
            throw new RuntimeException('Dokumen kontrak demo gagal disimpan.');
        }

        return $path;
    }

    private function upsertSignedContractFile(AppraisalRequest $record, string $path, int $size): void
    {
        $file = $record->files()
            ->where('type', 'contract_signed_pdf')
            ->latest('id')
            ->first();

        $payload = [
            'type' => 'contract_signed_pdf',
            'path' => $path,
            'original_name' => 'Penawaran-Tertandatangani-'.$this->safeRequestNumber($record).'.pdf',
            'mime' => 'application/pdf',
            'size' => $size,
        ];

        if ($file) {
            $file->update($payload);

            return;
        }

        $record->files()->create($payload);
    }

    private function safeRequestNumber(AppraisalRequest $record): string
    {
        $requestNumber = (string) ($record->request_number ?? ('REQ-'.$record->id));

        return (string) preg_replace('/[^A-Za-z0-9\\-_.]/', '-', $requestNumber);
    }

    private function provider(): string
    {
        return (string) config('signatures.canvas_demo.provider', 'canvas_demo');
    }

    private function signatureDisk(): string
    {
        return (string) config('signatures.canvas_demo.signature_disk', 'local');
    }

    private function documentDisk(): string
    {
        return (string) config('signatures.canvas_demo.document_disk', 'public');
    }
}

<?php

namespace App\Services\Signatures;

use App\Contracts\DigitalSignatureProvider;
use App\Models\AppraisalRequest;
use App\Models\SignatureEnvelope;
use App\Models\SignatureParticipant;
use App\Models\User;
use App\Services\Customer\AppraisalService;
use App\Services\Peruri\PeruriSignerReadinessService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

class ContractSignatureService
{
    private const PROVIDER = 'peruri_signit';
    private const MODEL = 'tier';
    private const DOCUMENT_TYPE = 'contract';

    private const MAX_PDF_SIZE_BYTES = 5_000_000; // Peruri doc: 5MB

    public function __construct(
        private readonly DigitalSignatureProvider $provider,
        private readonly PeruriSignerReadinessService $readinessService,
    ) {
    }

    public function ensureContractSignerConfigured(AppraisalRequest $record): void
    {
        $record->loadMissing(['contractPublicAppraiserSigner', 'user:id,name,email']);

        if (! $record->contract_public_appraiser_signer_id) {
            throw new RuntimeException('Kontrak belum siap ditandatangani; menunggu penetapan penilai publik.');
        }

        $publicAppraiserEmail = (string) ($record->contractPublicAppraiserSigner?->email ?? '');
        if ($publicAppraiserEmail === '') {
            throw new RuntimeException('Penilai publik kontrak belum memiliki email Peruri. Silakan lengkapi profil signer.');
        }

        $customerEmail = $this->customerPeruriEmail($record->user);
        if ($customerEmail === '') {
            throw new RuntimeException('Email customer belum tersedia untuk proses tanda tangan digital.');
        }
    }

    public function customerSignContract(
        User $customer,
        AppraisalRequest $record,
        AppraisalService $appraisalService,
        string $keylaToken,
    ): SignatureEnvelope {
        try {
            $this->ensureContractSignerConfigured($record);

            $record->loadMissing(['contractPublicAppraiserSigner', 'user:id,name,email']);

            $uploaderEmail = $this->uploaderEmail();
            $customerEmail = $this->customerPeruriEmail($customer);
            $publicAppraiserEmail = (string) ($record->contractPublicAppraiserSigner?->email ?? '');

            $this->readinessService->assertReadyForSigning($customerEmail);
            $this->readinessService->assertReadyForSigning($publicAppraiserEmail);
            $this->ensureKeylaTokenVerified($customerEmail, $keylaToken);

            $pdfBinary = $this->renderContractPdfBinary($record, $appraisalService);
            $this->ensurePdfSizeAllowed($pdfBinary);

            $documentHash = 'sha256:' . hash('sha256', $pdfBinary);
            $requestNumber = $this->safeRequestNumber($record);
            $fileName = "Penawaran-{$requestNumber}.pdf";
            $lockKey = "signature:contract:peruri:customer:{$record->id}";

            return Cache::lock($lockKey, 120)->block(10, function () use (
                $record,
                $uploaderEmail,
                $customerEmail,
                $publicAppraiserEmail,
                $pdfBinary,
                $fileName,
                $documentHash,
                $requestNumber,
                $keylaToken,
            ): SignatureEnvelope {
                $envelope = $this->contractEnvelope($record)->loadMissing('participants');

                $customerParticipant = $envelope->participants()
                    ->firstOrCreate(
                        ['role' => 'customer'],
                        [
                            'sequence' => 1,
                            'email' => $customerEmail,
                            'name' => (string) ($record->user?->name ?? $customerEmail),
                            'status' => 'pending',
                            'meta' => [],
                        ],
                    );

                $publicParticipant = $envelope->participants()
                    ->firstOrCreate(
                        ['role' => 'public_appraiser'],
                        [
                            'sequence' => 2,
                            'email' => $publicAppraiserEmail,
                            'name' => (string) ($record->contractPublicAppraiserSigner?->name ?? $publicAppraiserEmail),
                            'status' => 'pending',
                            'meta' => [],
                        ],
                    );

                if ($customerParticipant->status === 'signed') {
                    return $envelope->fresh(['participants']);
                }

                if ($envelope->external_envelope_id && ! $customerParticipant->external_order_id) {
                    $status = $this->provider->checkStatusByOrderType(
                        orderId: (string) $envelope->external_envelope_id,
                        orderType: 'TIER',
                        uploaderEmail: $uploaderEmail,
                    );

                    $orderId = $this->extractSignerOrderId($status, $customerEmail, preferredSequence: 1);
                    if ($orderId !== null) {
                        $customerParticipant->update(['external_order_id' => $orderId]);
                    }
                }

                if (! $envelope->original_pdf_path) {
                    $originalPath = $this->storeOriginalPdf($record, $pdfBinary, $requestNumber);
                    $envelope->update(['original_pdf_path' => $originalPath]);
                }

                if (! $envelope->external_envelope_id || ! $customerParticipant->external_order_id) {
                    $started = $this->provider->startTierEnvelope(
                        uploaderEmail: $uploaderEmail,
                        fileName: $fileName,
                        pdfBinary: $pdfBinary,
                        signerEmails: [$customerEmail, $publicAppraiserEmail],
                        payload: [
                            'appraisal_request_id' => (int) $record->id,
                            'request_number' => $requestNumber,
                            'document_type' => self::DOCUMENT_TYPE,
                        ],
                    );

                    DB::transaction(function () use (
                        $envelope,
                        $started,
                        $uploaderEmail,
                        $fileName,
                        $documentHash,
                        $customerParticipant,
                        $publicParticipant,
                        $customerEmail,
                        $publicAppraiserEmail,
                    ): void {
                        $envelope->update([
                            'external_envelope_id' => $started['order_id_tier'],
                            'uploader_email' => $uploaderEmail,
                            'status' => 'awaiting_customer',
                            'document_hash' => $documentHash,
                            'last_error' => null,
                            'meta' => array_merge((array) ($envelope->meta ?? []), [
                                'provider' => self::PROVIDER,
                                'model' => self::MODEL,
                                'document_type' => self::DOCUMENT_TYPE,
                                'file_name' => $fileName,
                            ]),
                        ]);

                        $customerParticipant->update([
                            'external_order_id' => $started['first_order_id'],
                            'email' => $customerEmail,
                        ]);

                        $publicParticipant->update([
                            'email' => $publicAppraiserEmail,
                        ]);
                    });

                    $envelope->refresh();
                    $customerParticipant->refresh();
                }

                $customerCoords = (array) config('peruri.coordinates.contract.customer', []);
                $this->provider->setSignatureCoordinate(
                    orderId: (string) $customerParticipant->external_order_id,
                    signerEmail: $customerEmail,
                    coords: $this->normalizeCoords($customerCoords),
                    visible: true,
                );

                $this->provider->signTierWithKeylaToken(
                    orderId: (string) $customerParticipant->external_order_id,
                    keylaToken: $keylaToken,
                );

                DB::transaction(function () use ($envelope, $customerParticipant, $documentHash): void {
                    $customerParticipant->update([
                        'status' => 'signed',
                        'signed_at' => now(),
                        'meta' => array_merge((array) ($customerParticipant->meta ?? []), [
                            'provider' => self::PROVIDER,
                            'order_id' => $customerParticipant->external_order_id,
                            'envelope_id' => $envelope->external_envelope_id,
                            'document_hash' => $documentHash,
                        ]),
                    ]);

                    $envelope->update([
                        'status' => 'awaiting_internal',
                        'last_error' => null,
                    ]);
                });

                return $envelope->fresh(['participants']);
            });
        } catch (RuntimeException $exception) {
            $this->markContractEnvelopeFailed($record, $exception->getMessage());
            throw $exception;
        } catch (Throwable $e) {
            $this->markContractEnvelopeFailed($record, 'Terjadi kesalahan saat memproses tanda tangan digital.');
            throw $e;
        }
    }

    public function publicAppraiserSignContract(
        User $actor,
        AppraisalRequest $record,
        string $keylaToken,
    ): SignatureEnvelope {
        try {
            $record->loadMissing(['contractPublicAppraiserSigner', 'user:id,name,email']);

            $signer = $record->contractPublicAppraiserSigner;
            if (! $signer) {
                throw new RuntimeException('Penilai publik untuk kontrak belum ditetapkan.');
            }

            if (! $signer->user_id || (int) $signer->user_id !== (int) $actor->id) {
                throw new RuntimeException('Akun Anda tidak ditetapkan sebagai penandatangan kontrak ini.');
            }

            $signerEmail = (string) ($signer->email ?? '');
            if ($signerEmail === '') {
                throw new RuntimeException('Profil signer belum memiliki email Peruri.');
            }

            $uploaderEmail = $this->uploaderEmail();
            $this->readinessService->assertReadyForSigning($signerEmail);
            $this->ensureKeylaTokenVerified($signerEmail, $keylaToken);

            $lockKey = "signature:contract:peruri:public_appraiser:{$record->id}";

            return Cache::lock($lockKey, 120)->block(10, function () use (
                $actor,
                $record,
                $uploaderEmail,
                $signerEmail,
                $keylaToken,
            ): SignatureEnvelope {
                $envelope = $this->contractEnvelope($record)->loadMissing('participants');

                if (! $envelope->external_envelope_id) {
                    throw new RuntimeException('Envelope Peruri belum terbentuk untuk kontrak ini.');
                }

                /** @var SignatureParticipant $publicParticipant */
                $publicParticipant = $envelope->participants()
                    ->firstOrCreate(
                        ['role' => 'public_appraiser'],
                        [
                            'sequence' => 2,
                            'email' => $signerEmail,
                            'name' => (string) ($record->contractPublicAppraiserSigner?->name ?? $signerEmail),
                            'status' => 'pending',
                            'meta' => [],
                        ],
                    );

                if ($publicParticipant->status === 'signed' && $envelope->status === 'completed') {
                    return $envelope;
                }

                if (! $publicParticipant->external_order_id) {
                    $status = $this->provider->checkStatusByOrderType(
                        orderId: (string) $envelope->external_envelope_id,
                        orderType: 'TIER',
                        uploaderEmail: $uploaderEmail,
                    );

                    $orderId = $this->extractSignerOrderId($status, $signerEmail, preferredSequence: 2);
                    if ($orderId === null) {
                        throw new RuntimeException('Order ID penandatangan internal belum tersedia. Pastikan customer sudah menandatangani terlebih dahulu.');
                    }

                    $publicParticipant->update([
                        'external_order_id' => $orderId,
                    ]);
                }

                $coords = (array) config('peruri.coordinates.contract.public_appraiser', []);
                $this->provider->setSignatureCoordinate(
                    orderId: (string) $publicParticipant->external_order_id,
                    signerEmail: $signerEmail,
                    coords: $this->normalizeCoords($coords),
                    visible: true,
                );

                $this->provider->signTierWithKeylaToken(
                    orderId: (string) $publicParticipant->external_order_id,
                    keylaToken: $keylaToken,
                );

                DB::transaction(function () use ($envelope, $publicParticipant): void {
                    $publicParticipant->update([
                        'status' => 'signed',
                        'signed_at' => now(),
                        'meta' => array_merge((array) ($publicParticipant->meta ?? []), [
                            'provider' => self::PROVIDER,
                            'order_id' => $publicParticipant->external_order_id,
                            'envelope_id' => $envelope->external_envelope_id,
                        ]),
                    ]);

                    $envelope->update([
                        'status' => 'awaiting_internal',
                        'last_error' => null,
                    ]);
                });

                $base64Signed = $this->provider->downloadTierDocument(
                    orderIdTier: (string) $envelope->external_envelope_id,
                    uploaderEmail: $uploaderEmail,
                );

                $signedBinary = base64_decode($base64Signed, true);
                if ($signedBinary === false) {
                    throw new RuntimeException('Gagal memproses dokumen hasil tanda tangan dari Peruri.');
                }

                $signedPath = $this->storeSignedPdf($record, $signedBinary);
                $this->upsertSignedContractFile($record, $signedPath, strlen($signedBinary));

                $envelope->update([
                    'status' => 'completed',
                    'signed_pdf_path' => $signedPath,
                    'last_error' => null,
                ]);

                $this->logPublicAppraiserSigned((int) $actor->id, $record, $envelope, $publicParticipant);

                return $envelope->fresh(['participants']);
            });
        } catch (RuntimeException $exception) {
            $this->markContractEnvelopeFailed($record, $exception->getMessage());
            throw $exception;
        } catch (Throwable $e) {
            $this->markContractEnvelopeFailed($record, 'Terjadi kesalahan saat memproses tanda tangan internal.');
            throw $e;
        }
    }

    private function contractEnvelope(AppraisalRequest $record): SignatureEnvelope
    {
        return SignatureEnvelope::query()->firstOrCreate([
            'subject_type' => AppraisalRequest::class,
            'subject_id' => (int) $record->id,
            'document_type' => self::DOCUMENT_TYPE,
            'provider' => self::PROVIDER,
        ], [
            'model' => self::MODEL,
            'uploader_email' => $this->uploaderEmail(),
            'status' => 'draft',
            'meta' => [],
        ]);
    }

    private function ensureKeylaTokenVerified(string $email, string $keylaToken): void
    {
        $this->provider->verifyKeylaToken($email, $keylaToken);
    }

    private function uploaderEmail(): string
    {
        $email = (string) config('peruri.uploader_email', '');
        if ($email === '') {
            throw new RuntimeException('Konfigurasi PERURI_UPLOADER_EMAIL belum diisi.');
        }

        return $email;
    }

    private function customerPeruriEmail(?User $customer): string
    {
        if (! $customer) {
            return '';
        }

        $customer->loadMissing('signatureProfile');

        return trim((string) ($customer->signatureProfile?->peruri_email ?: $customer->email));
    }

    private function renderContractPdfBinary(AppraisalRequest $record, AppraisalService $appraisalService): string
    {
        $doc = $appraisalService->buildContractDocumentPayload($record);

        return Pdf::loadView('pdfs.appraisal-contract-offer', [
            'doc' => $doc,
        ])->setPaper('a4', 'portrait')->output();
    }

    private function ensurePdfSizeAllowed(string $pdfBinary): void
    {
        if (strlen($pdfBinary) > self::MAX_PDF_SIZE_BYTES) {
            throw new RuntimeException('Ukuran dokumen melebihi batas maksimal (5MB). Silakan hubungi support.');
        }
    }

    private function safeRequestNumber(AppraisalRequest $record): string
    {
        $requestNumber = (string) ($record->request_number ?? ('REQ-' . $record->id));

        return preg_replace('/[^A-Za-z0-9\\-_.]/', '-', $requestNumber);
    }

    private function storeOriginalPdf(AppraisalRequest $record, string $pdfBinary, string $safeRequestNumber): string
    {
        $storedName = "contract-original-{$safeRequestNumber}-" . now()->format('YmdHis') . '.pdf';
        $storedPath = "appraisal-requests/{$record->id}/contracts/{$storedName}";

        Storage::disk('public')->put($storedPath, $pdfBinary);

        return $storedPath;
    }

    private function storeSignedPdf(AppraisalRequest $record, string $pdfBinary): string
    {
        $safeRequestNumber = $this->safeRequestNumber($record);
        $storedName = "contract-peruri-signed-{$safeRequestNumber}-" . now()->format('YmdHis') . '.pdf';
        $storedPath = "appraisal-requests/{$record->id}/contracts/{$storedName}";

        Storage::disk('public')->put($storedPath, $pdfBinary);

        return $storedPath;
    }

    private function upsertSignedContractFile(AppraisalRequest $record, string $path, int $size): void
    {
        $safeRequestNumber = $this->safeRequestNumber($record);

        $file = $record->files()
            ->where('type', 'contract_signed_pdf')
            ->orderByDesc('id')
            ->first();

        $payload = [
            'type' => 'contract_signed_pdf',
            'path' => $path,
            'original_name' => "Penawaran-Tertandatangani-{$safeRequestNumber}.pdf",
            'mime' => 'application/pdf',
            'size' => $size,
        ];

        if ($file) {
            $file->update($payload);
            return;
        }

        $record->files()->create($payload);
    }

    /**
     * @param  array<string, mixed>  $response
     */
    private function extractSignerOrderId(array $response, string $email, ?int $preferredSequence = null): ?string
    {
        $signers = data_get($response, 'data.signer');
        if (! is_array($signers)) {
            return null;
        }

        $normalizedEmail = mb_strtolower(trim($email));

        $candidates = [];
        foreach ($signers as $signer) {
            if (! is_array($signer)) {
                continue;
            }

            $signerEmail = mb_strtolower(trim((string) ($signer['email'] ?? '')));
            if ($signerEmail === '' || $signerEmail !== $normalizedEmail) {
                continue;
            }

            $orderId = (string) ($signer['orderId'] ?? $signer['order_id'] ?? '');
            if ($orderId === '') {
                // Some responses nest orderId fields; attempt a best-effort lookup.
                $orderId = (string) data_get($signer, 'orderId.orderId', '');
            }

            if ($orderId === '') {
                continue;
            }

            $sequence = is_numeric($signer['sequence'] ?? null) ? (int) $signer['sequence'] : null;
            $candidates[] = [
                'order_id' => $orderId,
                'sequence' => $sequence,
            ];
        }

        if (empty($candidates)) {
            return null;
        }

        if ($preferredSequence !== null) {
            foreach ($candidates as $candidate) {
                if ($candidate['sequence'] === $preferredSequence) {
                    return $candidate['order_id'];
                }
            }
        }

        return (string) $candidates[0]['order_id'];
    }

    /**
     * @param  array<string, mixed>  $coords
     * @return array{page:int,lower_left_x:int,lower_left_y:int,upper_right_x:int,upper_right_y:int}
     */
    private function normalizeCoords(array $coords): array
    {
        return [
            'page' => (int) ($coords['page'] ?? 1),
            'lower_left_x' => (int) ($coords['lower_left_x'] ?? 0),
            'lower_left_y' => (int) ($coords['lower_left_y'] ?? 0),
            'upper_right_x' => (int) ($coords['upper_right_x'] ?? 0),
            'upper_right_y' => (int) ($coords['upper_right_y'] ?? 0),
        ];
    }

    private function markContractEnvelopeFailed(AppraisalRequest $record, string $message): void
    {
        try {
            $envelope = SignatureEnvelope::query()->firstOrCreate([
                'subject_type' => AppraisalRequest::class,
                'subject_id' => (int) $record->id,
                'document_type' => self::DOCUMENT_TYPE,
                'provider' => self::PROVIDER,
            ], [
                'model' => self::MODEL,
                'uploader_email' => $this->uploaderEmail(),
                'status' => 'failed',
                'meta' => [],
            ]);

            $envelope->update([
                'status' => 'failed',
                'last_error' => $message,
            ]);
        } catch (Throwable) {
            // Best-effort only.
        }
    }

    private function logPublicAppraiserSigned(
        int $actorId,
        AppraisalRequest $record,
        SignatureEnvelope $envelope,
        SignatureParticipant $participant,
    ): void {
        $record->offerNegotiations()->create([
            'user_id' => $actorId,
            'action' => 'contract_sign_peruri_public_appraiser',
            'round' => (int) $record->offerNegotiations()
                ->where('action', 'counter_request')
                ->count(),
            'offered_fee' => $record->fee_total,
            'selected_fee' => $record->fee_total,
            'reason' => 'Peruri SIGN-IT contract signing (KEYLA) by public appraiser.',
            'meta' => [
                'flow' => 'peruri_contract_signature',
                'provider' => self::PROVIDER,
                'model' => self::MODEL,
                'signature_envelope_id' => $envelope->id,
                'external_envelope_id' => $envelope->external_envelope_id,
                'external_order_id' => $participant->external_order_id,
                'signed_pdf_path' => $envelope->signed_pdf_path,
                'document_hash' => $envelope->document_hash,
                'signed_at' => optional($participant->signed_at)->toIso8601String(),
            ],
        ]);
    }
}

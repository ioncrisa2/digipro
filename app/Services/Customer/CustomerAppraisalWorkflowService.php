<?php

namespace App\Services\Customer;

use App\Enums\AppraisalStatusEnum;
use App\Enums\ContractStatusEnum;
use App\Models\AppraisalRequest;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

class CustomerAppraisalWorkflowService
{
    private const MAX_NEGOTIATION_ROUNDS = 3;

    public function resolveUserAppraisalRequest(Request $request, int $id): AppraisalRequest
    {
        return AppraisalRequest::query()
            ->where('user_id', $request->user()->id)
            ->findOrFail($id);
    }

    public function isContractAccessibleStatus(string $status): bool
    {
        return in_array($status, [
            AppraisalStatusEnum::WaitingSignature->value,
            AppraisalStatusEnum::ContractSigned->value,
            AppraisalStatusEnum::ValuationOnProgress->value,
            AppraisalStatusEnum::ValuationCompleted->value,
            AppraisalStatusEnum::PreviewReady->value,
            AppraisalStatusEnum::ReportPreparation->value,
            AppraisalStatusEnum::ReportReady->value,
            AppraisalStatusEnum::Completed->value,
        ], true);
    }

    public function acceptOffer(AppraisalRequest $record, int $userId): void
    {
        $this->ensureOfferCanBeAnswered($record, 'Penawaran belum dapat disetujui pada status saat ini.');

        $record->update([
            'status' => AppraisalStatusEnum::WaitingSignature,
            'contract_status' => ContractStatusEnum::WaitingSignature,
        ]);

        $record->offerNegotiations()->create([
            'user_id' => $userId,
            'action' => 'accept_offer',
            'round' => $this->countNegotiationRounds($record),
            'offered_fee' => $record->fee_total,
            'selected_fee' => $record->fee_total,
            'meta' => ['flow' => 'direct_accept'],
        ]);
    }

    public function submitOfferNegotiation(
        AppraisalRequest $record,
        int $userId,
        ?int $expectedFee,
        string $reason,
    ): int {
        $this->ensureOfferCanBeAnswered($record, 'Negosiasi hanya dapat diajukan saat penawaran berstatus dikirim.');

        $roundsUsed = $this->countNegotiationRounds($record);
        if ($roundsUsed >= self::MAX_NEGOTIATION_ROUNDS) {
            throw new RuntimeException('Batas negosiasi maksimal 3 putaran telah tercapai.');
        }

        $round = $roundsUsed + 1;

        $record->offerNegotiations()->create([
            'user_id' => $userId,
            'action' => 'counter_request',
            'round' => $round,
            'offered_fee' => $record->fee_total,
            'expected_fee' => $expectedFee,
            'reason' => trim($reason),
            'meta' => [
                'status_before' => $this->statusValue($record),
                'contract_status_before' => $record->contract_status?->value ?? $record->contract_status,
            ],
        ]);

        $record->update([
            'status' => AppraisalStatusEnum::WaitingOffer,
            'contract_status' => ContractStatusEnum::Negotiation,
        ]);

        return $round;
    }

    public function selectOffer(AppraisalRequest $record, int $userId, int $selectedFee, ?string $reason): void
    {
        if ($this->statusValue($record) !== AppraisalStatusEnum::OfferSent->value) {
            throw new RuntimeException('Pemilihan penawaran hanya dapat dilakukan saat status penawaran aktif.');
        }

        $roundsUsed = $this->countNegotiationRounds($record);
        if ($roundsUsed < self::MAX_NEGOTIATION_ROUNDS) {
            throw new RuntimeException('Pemilihan akhir penawaran tersedia setelah 3 putaran negosiasi.');
        }

        if (! in_array($selectedFee, $this->offeredFeeOptions($record), true)) {
            throw new RuntimeException('Fee terpilih tidak termasuk dalam riwayat penawaran yang tersedia.');
        }

        $record->offerNegotiations()->create([
            'user_id' => $userId,
            'action' => 'accept_offer',
            'round' => $roundsUsed,
            'offered_fee' => $record->fee_total,
            'selected_fee' => $selectedFee,
            'reason' => $reason !== null ? trim($reason) : null,
            'meta' => ['flow' => 'offer_selection_after_limit'],
        ]);

        $record->update([
            'fee_total' => $selectedFee,
            'status' => AppraisalStatusEnum::WaitingSignature,
            'contract_status' => ContractStatusEnum::WaitingSignature,
        ]);
    }

    public function signContract(Request $request, AppraisalRequest $record, AppraisalService $appraisalService): void
    {
        if ($this->statusValue($record) !== AppraisalStatusEnum::WaitingSignature->value) {
            throw new RuntimeException('Status saat ini tidak dapat menandatangani kontrak.');
        }

        $snapshot = $this->createSignedContractSnapshot($request, $record, $appraisalService);

        $record->offerNegotiations()->create([
            'user_id' => $request->user()->id,
            'action' => 'contract_sign_mock',
            'round' => $this->countNegotiationRounds($record),
            'offered_fee' => $record->fee_total,
            'selected_fee' => $record->fee_total,
            'reason' => 'Mock digital signature (clickwrap).',
            'meta' => [
                'flow' => 'mock_contract_signature',
                'provider' => 'mock',
                'method' => 'clickwrap',
                'signature_id' => $snapshot['signature_id'],
                'signed_at' => $snapshot['signed_at'],
                'signed_by_name' => $snapshot['signed_by_name'],
                'signed_by_email' => $snapshot['signed_by_email'],
                'ip' => $snapshot['ip'],
                'user_agent' => $snapshot['user_agent'],
                'document_hash' => $snapshot['document_hash'],
                'signed_pdf_path' => $snapshot['signed_pdf_path'],
            ],
        ]);

        $record->update([
            'status' => AppraisalStatusEnum::ContractSigned,
            'contract_status' => ContractStatusEnum::ContractSigned,
        ]);
    }

    /**
     * @return array<int, int>
     */
    private function offeredFeeOptions(AppraisalRequest $record): array
    {
        $fees = $record->offerNegotiations()
            ->where('action', 'counter_request')
            ->whereNotNull('offered_fee')
            ->pluck('offered_fee')
            ->map(fn ($fee): int => (int) $fee)
            ->values();

        if ($record->fee_total !== null) {
            $fees->push((int) $record->fee_total);
        }

        return $fees->unique()->values()->all();
    }

    private function ensureOfferCanBeAnswered(AppraisalRequest $record, string $invalidStatusMessage): void
    {
        if ($this->statusValue($record) !== AppraisalStatusEnum::OfferSent->value) {
            throw new RuntimeException($invalidStatusMessage);
        }

        if (empty($record->contract_number) || empty($record->fee_total)) {
            throw new RuntimeException('Data penawaran belum lengkap. Hubungi admin untuk pembaruan penawaran.');
        }
    }

    private function createSignedContractSnapshot(
        Request $request,
        AppraisalRequest $record,
        AppraisalService $appraisalService,
    ): array {
        $signedAt = now();
        $signatureId = (string) Str::uuid();
        $signerName = (string) ($request->user()?->name ?? '-');
        $signerEmail = (string) ($request->user()?->email ?? '-');
        $userAgent = substr((string) $request->userAgent(), 0, 255);
        $ipAddress = (string) $request->ip();

        $doc = $appraisalService->buildContractDocumentPayload($record);
        $doc['accepted_at'] = $signedAt->toDateTimeString();
        $doc['signature'] = array_merge((array) ($doc['signature'] ?? []), [
            'is_signed' => true,
            'signed_at' => $signedAt->toDateTimeString(),
            'signed_by_name' => $signerName,
            'signed_by_email' => $signerEmail,
            'signature_id' => $signatureId,
            'method' => 'clickwrap',
            'provider' => 'mock',
        ]);

        $pdfBinary = Pdf::loadView('pdfs.appraisal-contract-offer', [
            'doc' => $doc,
        ])
            ->setPaper('a4', 'portrait')
            ->output();

        $documentHash = 'sha256:' . hash('sha256', $pdfBinary);
        $requestNumber = preg_replace('/[^A-Za-z0-9\-_.]/', '-', (string) ($record->request_number ?? ('REQ-' . $record->id)));
        $storedName = "signed-contract-{$requestNumber}-{$signedAt->format('YmdHis')}.pdf";
        $storedPath = "appraisal-requests/{$record->id}/contracts/{$storedName}";

        Storage::disk('public')->put($storedPath, $pdfBinary);

        $record->files()->create([
            'type' => 'contract_signed_pdf',
            'path' => $storedPath,
            'original_name' => "Penawaran-Tertandatangani-{$requestNumber}.pdf",
            'mime' => 'application/pdf',
            'size' => strlen($pdfBinary),
        ]);

        return [
            'signature_id' => $signatureId,
            'signed_at' => $signedAt->toIso8601String(),
            'signed_by_name' => $signerName,
            'signed_by_email' => $signerEmail,
            'ip' => $ipAddress,
            'user_agent' => $userAgent,
            'document_hash' => $documentHash,
            'signed_pdf_path' => $storedPath,
        ];
    }

    private function countNegotiationRounds(AppraisalRequest $record): int
    {
        return (int) $record->offerNegotiations()
            ->where('action', 'counter_request')
            ->count();
    }

    private function statusValue(AppraisalRequest $record): string
    {
        return $record->status?->value ?? (string) $record->status;
    }
}

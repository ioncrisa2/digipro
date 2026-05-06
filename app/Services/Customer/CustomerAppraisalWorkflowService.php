<?php

namespace App\Services\Customer;

use App\Enums\AppraisalStatusEnum;
use App\Enums\ContractStatusEnum;
use App\Models\AppraisalRequest;
use App\Services\Signatures\ContractSignatureService;
use Illuminate\Http\Request;
use RuntimeException;

class CustomerAppraisalWorkflowService
{
    private const MAX_NEGOTIATION_ROUNDS = 3;

    public function __construct(
        private readonly ContractSignatureService $contractSignatureService,
    ) {
    }

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

        $keylaToken = trim((string) $request->input('keyla_token'));
        if ($keylaToken === '') {
            throw new RuntimeException('Token KEYLA wajib diisi.');
        }

        $envelope = $this->contractSignatureService->customerSignContract(
            $request->user(),
            $record,
            $appraisalService,
            $keylaToken,
        );

        $customerParticipant = $envelope->participants
            ->firstWhere('role', 'customer');

        $record->offerNegotiations()->create([
            'user_id' => $request->user()->id,
            'action' => 'contract_sign_peruri_customer',
            'round' => $this->countNegotiationRounds($record),
            'offered_fee' => $record->fee_total,
            'selected_fee' => $record->fee_total,
            'reason' => 'Peruri SIGN-IT contract signing (KEYLA).',
            'meta' => [
                'flow' => 'peruri_contract_signature',
                'provider' => 'peruri_signit',
                'model' => 'tier',
                'signature_envelope_id' => $envelope->id,
                'external_envelope_id' => $envelope->external_envelope_id,
                'external_order_id' => $customerParticipant?->external_order_id,
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

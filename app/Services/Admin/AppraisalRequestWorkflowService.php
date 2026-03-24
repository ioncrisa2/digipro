<?php

namespace App\Services\Admin;

use App\Enums\AppraisalStatusEnum;
use App\Enums\ContractStatusEnum;
use App\Models\AppraisalRequest;
use App\Models\AppraisalOfferNegotiation;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class AppraisalRequestWorkflowService
{
    public function __construct(
        private readonly AppraisalContractNumberService $contractNumberService
    ) {
    }

    public function canVerifyDocs(AppraisalRequest $record): bool
    {
        if ($this->hasOpenRevisionBatch($record)) {
            return false;
        }

        return in_array($this->statusValue($record), [
            AppraisalStatusEnum::Submitted->value,
            AppraisalStatusEnum::DocsIncomplete->value,
        ], true);
    }

    public function canMarkDocsIncomplete(AppraisalRequest $record): bool
    {
        if ($this->hasOpenRevisionBatch($record)) {
            return false;
        }

        return in_array($this->statusValue($record), [
            AppraisalStatusEnum::Submitted->value,
            AppraisalStatusEnum::WaitingOffer->value,
            AppraisalStatusEnum::OfferSent->value,
        ], true);
    }

    public function canMarkContractSigned(AppraisalRequest $record): bool
    {
        return $this->statusValue($record) === AppraisalStatusEnum::WaitingSignature->value;
    }

    public function canVerifyPayment(AppraisalRequest $record): bool
    {
        if ($this->statusValue($record) !== AppraisalStatusEnum::ContractSigned->value) {
            return false;
        }

        $latestPayment = $record->payments()
            ->latest('id')
            ->first(['method', 'status']);

        if (! $latestPayment) {
            return false;
        }

        return $latestPayment->method === 'gateway'
            && $latestPayment->status === 'paid';
    }

    public function canSendOffer(AppraisalRequest $record): bool
    {
        return in_array($this->statusValue($record), [
            AppraisalStatusEnum::Verified->value,
            AppraisalStatusEnum::WaitingOffer->value,
            AppraisalStatusEnum::OfferSent->value,
        ], true);
    }

    public function canApproveLatestNegotiation(AppraisalRequest $record): bool
    {
        return $this->statusValue($record) === AppraisalStatusEnum::WaitingOffer->value
            && $this->latestCounterRequest($record)?->expected_fee !== null;
    }

    public function resolveOfferDefaults(AppraisalRequest $record): array
    {
        $latestCounter = $this->latestCounterRequest($record);
        $defaultFee = $record->fee_total !== null ? (int) $record->fee_total : null;

        if (
            $this->statusValue($record) === AppraisalStatusEnum::WaitingOffer->value
            && $latestCounter?->expected_fee !== null
        ) {
            $defaultFee = (int) $latestCounter->expected_fee;
        }

        return [
            'fee_total' => $defaultFee,
            'fee_has_dp' => (bool) $record->fee_has_dp,
            'fee_dp_percent' => $record->fee_dp_percent,
            'contract_sequence' => $record->contract_sequence,
            'offer_validity_days' => $record->offer_validity_days,
        ];
    }

    public function paymentVerificationState(AppraisalRequest $record): array
    {
        if ($this->statusValue($record) !== AppraisalStatusEnum::ContractSigned->value) {
            return [
                'show' => false,
                'ready' => false,
                'message' => null,
            ];
        }

        $latestPayment = $record->payments()
            ->latest('id')
            ->first(['id', 'method', 'status']);

        if (! $latestPayment) {
            return [
                'show' => true,
                'ready' => false,
                'message' => 'Belum ada pembayaran yang bisa diverifikasi untuk request ini.',
            ];
        }

        if ($latestPayment->method !== 'gateway') {
            return [
                'show' => true,
                'ready' => false,
                'message' => 'Pembayaran aktif untuk request ini harus menggunakan Midtrans.',
            ];
        }

        if ($latestPayment->status !== 'paid') {
            return [
                'show' => true,
                'ready' => false,
                'message' => 'Pembayaran Midtrans terbaru belum berstatus Dibayar.',
            ];
        }

        return [
            'show' => true,
            'ready' => true,
            'message' => 'Pembayaran Midtrans siap diverifikasi dan request bisa masuk ke proses valuasi.',
        ];
    }

    public function verifyDocs(AppraisalRequest $record, ?int $actorId = null): void
    {
        if (! $this->canVerifyDocs($record)) {
            throw new RuntimeException('Verifikasi dokumen hanya tersedia untuk request yang baru masuk atau sebelumnya ditandai dokumen kurang.');
        }

        DB::transaction(function () use ($record, $actorId): void {
            $record->update([
                'status' => AppraisalStatusEnum::WaitingOffer,
                'verified_at' => now(),
            ]);

            $submittedBatches = $record->revisionBatches()
                ->where('status', 'submitted')
                ->with('items')
                ->get();

            foreach ($submittedBatches as $batch) {
                $batch->update([
                    'status' => 'reviewed',
                    'reviewed_by' => $actorId,
                    'reviewed_at' => now(),
                    'resolved_at' => now(),
                ]);

                $batch->items()
                    ->whereIn('status', ['pending', 'reuploaded'])
                    ->update([
                        'status' => 'approved',
                    ]);
            }
        });
    }

    public function markDocsIncomplete(AppraisalRequest $record): void
    {
        if (! $this->canMarkDocsIncomplete($record)) {
            throw new RuntimeException('Status dokumen kurang hanya bisa diberikan saat request masih pada tahap review dokumen atau penawaran awal.');
        }

        $record->update([
            'status' => AppraisalStatusEnum::DocsIncomplete,
        ]);
    }

    public function markContractSigned(AppraisalRequest $record): void
    {
        if (! $this->canMarkContractSigned($record)) {
            throw new RuntimeException('Kontrak hanya bisa ditandai ditandatangani saat request sedang menunggu tanda tangan.');
        }

        $record->update([
            'status' => AppraisalStatusEnum::ContractSigned,
            'contract_status' => ContractStatusEnum::ContractSigned,
        ]);
    }

    public function verifyPayment(AppraisalRequest $record): void
    {
        if (! $this->canVerifyPayment($record)) {
            throw new RuntimeException('Pembayaran belum siap diverifikasi. Pastikan pembayaran Midtrans terbaru sudah berstatus Dibayar.');
        }

        $record->update([
            'status' => AppraisalStatusEnum::ValuationOnProgress,
        ]);
    }

    public function sendOffer(AppraisalRequest $record, int $actorId, array $data): array
    {
        if (! $this->canSendOffer($record)) {
            throw new RuntimeException('Penawaran hanya bisa dikirim saat request sudah terverifikasi, menunggu penawaran, atau masih pada fase penawaran aktif.');
        }

        $contractMeta = $this->contractNumberService->deriveMetadata($data['contract_sequence'] ?? null);
        if ($contractMeta['contract_number'] === null) {
            throw new RuntimeException('No. Penawaran wajib diisi sebelum penawaran dapat dikirim.');
        }

        $statusBefore = $this->statusValue($record);
        $contractStatusBefore = $this->contractStatusValue($record);
        $actionType = $contractStatusBefore === ContractStatusEnum::Negotiation->value ? 'offer_revised' : 'offer_sent';
        $round = $this->countNegotiationRounds($record);
        $feeTotal = (int) $data['fee_total'];
        $feeHasDp = (bool) ($data['fee_has_dp'] ?? false);
        $feeDpPercent = $feeHasDp ? ($data['fee_dp_percent'] ?? null) : null;
        $contractDate = optional($record->contract_date)->toDateString() ?: now()->toDateString();

        DB::transaction(function () use (
            $record,
            $actorId,
            $data,
            $contractMeta,
            $statusBefore,
            $contractStatusBefore,
            $actionType,
            $round,
            $feeTotal,
            $feeHasDp,
            $feeDpPercent,
            $contractDate
        ): void {
            $record->update([
                'fee_total' => $feeTotal,
                'fee_has_dp' => $feeHasDp,
                'fee_dp_percent' => $feeDpPercent,
                'contract_sequence' => $data['contract_sequence'],
                'contract_office_code' => $contractMeta['contract_office_code'],
                'contract_month' => $contractMeta['contract_month'],
                'contract_year' => $contractMeta['contract_year'],
                'contract_date' => $contractDate,
                'contract_number' => $contractMeta['contract_number'],
                'offer_validity_days' => $data['offer_validity_days'] ?? null,
                'status' => AppraisalStatusEnum::OfferSent,
                'contract_status' => ContractStatusEnum::SentToClient,
            ]);

            $record->offerNegotiations()->create([
                'user_id' => $actorId,
                'action' => $actionType,
                'round' => $round > 0 ? $round : null,
                'offered_fee' => $feeTotal,
                'meta' => [
                    'status_before' => $statusBefore,
                    'contract_status_before' => $contractStatusBefore,
                    'status_after' => AppraisalStatusEnum::OfferSent->value,
                    'contract_status_after' => ContractStatusEnum::SentToClient->value,
                ],
            ]);
        });

        return [
            'action' => $actionType,
            'fee_total' => $feeTotal,
        ];
    }

    public function approveLatestNegotiation(AppraisalRequest $record, int $actorId): array
    {
        if (! $this->canApproveLatestNegotiation($record)) {
            throw new RuntimeException('Persetujuan negosiasi hanya tersedia saat request menunggu penawaran dan user sudah mengirim harapan fee.');
        }

        $latestCounter = $this->latestCounterRequest($record);
        if (! $latestCounter instanceof AppraisalOfferNegotiation) {
            throw new RuntimeException('Data negosiasi terbaru tidak ditemukan.');
        }

        $approvedFee = (int) $latestCounter->expected_fee;
        $contractMeta = $this->contractNumberService->deriveMetadata($record->contract_sequence);
        if ($contractMeta['contract_number'] === null) {
            throw new RuntimeException('No. Penawaran wajib diisi terlebih dahulu sebelum harapan fee user bisa disetujui.');
        }

        $statusBefore = $this->statusValue($record);
        $contractStatusBefore = $this->contractStatusValue($record);
        $round = $this->countNegotiationRounds($record);
        $contractDate = optional($record->contract_date)->toDateString() ?: now()->toDateString();

        DB::transaction(function () use (
            $record,
            $actorId,
            $latestCounter,
            $approvedFee,
            $contractMeta,
            $statusBefore,
            $contractStatusBefore,
            $round,
            $contractDate
        ): void {
            $record->update([
                'fee_total' => $approvedFee,
                'contract_office_code' => $contractMeta['contract_office_code'],
                'contract_month' => $contractMeta['contract_month'],
                'contract_year' => $contractMeta['contract_year'],
                'contract_date' => $contractDate,
                'contract_number' => $contractMeta['contract_number'],
                'status' => AppraisalStatusEnum::OfferSent,
                'contract_status' => ContractStatusEnum::SentToClient,
            ]);

            $record->offerNegotiations()->create([
                'user_id' => $actorId,
                'action' => 'offer_revised',
                'round' => $round > 0 ? $round : null,
                'offered_fee' => $approvedFee,
                'expected_fee' => $approvedFee,
                'meta' => [
                    'flow' => 'approve_latest_counter_request',
                    'counter_request_id' => $latestCounter->id,
                    'status_before' => $statusBefore,
                    'contract_status_before' => $contractStatusBefore,
                    'status_after' => AppraisalStatusEnum::OfferSent->value,
                    'contract_status_after' => ContractStatusEnum::SentToClient->value,
                ],
            ]);
        });

        return [
            'fee_total' => $approvedFee,
            'counter_request_id' => $latestCounter->id,
        ];
    }

    public function latestCounterRequest(AppraisalRequest $record): ?AppraisalOfferNegotiation
    {
        return $record->offerNegotiations()
            ->where('action', 'counter_request')
            ->latest('id')
            ->first();
    }

    private function statusValue(AppraisalRequest $record): ?string
    {
        return $record->status?->value ?? $record->status;
    }

    private function contractStatusValue(AppraisalRequest $record): ?string
    {
        return $record->contract_status?->value ?? $record->contract_status;
    }

    private function countNegotiationRounds(AppraisalRequest $record): int
    {
        return (int) $record->offerNegotiations()
            ->where('action', 'counter_request')
            ->count();
    }

    private function hasOpenRevisionBatch(AppraisalRequest $record): bool
    {
        if ($record->relationLoaded('revisionBatches')) {
            return $record->revisionBatches->contains(fn ($batch) => $batch->status === 'open');
        }

        return $record->revisionBatches()
            ->where('status', 'open')
            ->exists();
    }
}

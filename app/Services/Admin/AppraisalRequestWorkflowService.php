<?php

namespace App\Services\Admin;

use App\Enums\AppraisalStatusEnum;
use App\Enums\ContractStatusEnum;
use App\Models\AppraisalRequest;
use App\Models\AppraisalOfferNegotiation;
use App\Services\Finance\AppraisalBillingService;
use App\Notifications\AppraisalOfferNotification;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class AppraisalRequestWorkflowService
{
    public function __construct(
        private readonly AppraisalContractNumberService $contractNumberService,
        private readonly AppraisalBillingService $billingService,
    ) {
    }

    public function canVerifyDocs(AppraisalRequest $record): bool
    {
        $state = $this->verifyDocsState($record);

        return ($state['show'] ?? false) && ($state['ready'] ?? false);
    }

    public function verifyDocsState(AppraisalRequest $record): array
    {
        $statusAllowed = in_array($this->statusValue($record), [
            AppraisalStatusEnum::Submitted->value,
            AppraisalStatusEnum::DocsIncomplete->value,
        ], true);

        if (! $statusAllowed) {
            return [
                'show' => false,
                'ready' => false,
                'message' => 'Verifikasi dokumen hanya tersedia untuk request yang baru masuk atau sebelumnya ditandai dokumen kurang.',
            ];
        }

        if ($this->hasPendingRevisionWork($record)) {
            return [
                'show' => true,
                'ready' => false,
                'message' => 'Masih ada item revisi data/dokumen yang belum selesai ditinjau. Selesaikan review revisi per-item terlebih dahulu.',
            ];
        }

        return [
            'show' => true,
            'ready' => true,
            'message' => null,
        ];
    }

    public function canMarkDocsIncomplete(AppraisalRequest $record): bool
    {
        if ($this->hasPendingRevisionWork($record)) {
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

    public function canCancelRequest(AppraisalRequest $record): bool
    {
        if ($record->report_generated_at !== null) {
            return false;
        }

        return ! in_array($this->statusValue($record), [
            AppraisalStatusEnum::CancellationReviewPending->value,
            AppraisalStatusEnum::Cancelled->value,
            AppraisalStatusEnum::ReportReady->value,
            AppraisalStatusEnum::Completed->value,
        ], true);
    }

    public function physicalReportState(AppraisalRequest $record): array
    {
        $requiresPhysicalReport = ($record->report_format ?? 'digital') !== 'digital'
            || (int) ($record->physical_copies_count ?? 0) > 0;

        if (! $requiresPhysicalReport) {
            return [
                'show' => false,
                'ready' => false,
                'message' => 'Permohonan ini tidak meminta pengiriman hard copy.',
            ];
        }

        if (! $record->report_generated_at && ! filled($record->report_pdf_path)) {
            return [
                'show' => true,
                'ready' => false,
                'message' => 'Pengiriman hard copy dapat dicatat setelah laporan final tersedia.',
            ];
        }

        return [
            'show' => true,
            'ready' => true,
            'message' => null,
        ];
    }

    public function resolveOfferDefaults(AppraisalRequest $record): array
    {
        $latestCounter = $this->latestCounterRequest($record);
        $defaultDpp = $record->billing_dpp_amount !== null
            ? (int) $record->billing_dpp_amount
            : ($record->fee_total !== null
                ? (int) $this->billingService->deriveFromGross((int) $record->fee_total)['billing_dpp_amount']
                : null);

        if (
            $this->statusValue($record) === AppraisalStatusEnum::WaitingOffer->value
            && $latestCounter?->expected_fee !== null
        ) {
            $defaultDpp = (int) $this->billingService
                ->deriveFromGross((int) $latestCounter->expected_fee)['billing_dpp_amount'];
        }

        return [
            'billing_dpp_amount' => $defaultDpp,
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
            throw new RuntimeException($this->verifyDocsState($record)['message'] ?? 'Verifikasi dokumen belum tersedia untuk request ini.');
        }

        DB::transaction(function () use ($record): void {
            $record->update([
                'status' => AppraisalStatusEnum::WaitingOffer,
                'verified_at' => now(),
            ]);
        });
    }

    public function markDocsIncomplete(AppraisalRequest $record): void
    {
        if (! $this->canMarkDocsIncomplete($record)) {
            throw new RuntimeException('Status dokumen kurang hanya bisa diberikan saat request masih pada tahap review dokumen atau penawaran awal.');
        }

        DB::transaction(function () use ($record): void {
            $record->update([
                'status' => AppraisalStatusEnum::DocsIncomplete,
            ]);
        });
    }

    public function markContractSigned(AppraisalRequest $record): void
    {
        if (! $this->canMarkContractSigned($record)) {
            throw new RuntimeException('Kontrak hanya bisa ditandai ditandatangani saat request sedang menunggu tanda tangan.');
        }

        DB::transaction(function () use ($record): void {
            $record->update([
                'status' => AppraisalStatusEnum::ContractSigned,
                'contract_status' => ContractStatusEnum::ContractSigned,
            ]);
        });
    }

    public function verifyPayment(AppraisalRequest $record): void
    {
        if (! $this->canVerifyPayment($record)) {
            throw new RuntimeException('Pembayaran belum siap diverifikasi. Pastikan pembayaran Midtrans terbaru sudah berstatus Dibayar.');
        }

        DB::transaction(function () use ($record): void {
            $record->update([
                'status' => AppraisalStatusEnum::ValuationOnProgress,
            ]);
        });
    }

    public function cancelRequest(AppraisalRequest $record, int $actorId, string $reason): void
    {
        if (! $this->canCancelRequest($record)) {
            throw new RuntimeException('Request ini tidak bisa dibatalkan lagi dari workspace admin.');
        }

        $statusBefore = $this->statusValue($record);
        $contractStatusBefore = $this->contractStatusValue($record);

        DB::transaction(function () use ($record, $actorId, $reason, $statusBefore, $contractStatusBefore): void {
            $record->update([
                'status' => AppraisalStatusEnum::Cancelled,
                'contract_status' => ContractStatusEnum::Cancelled,
                'cancelled_by' => $actorId,
                'cancelled_at' => now(),
                'cancellation_reason' => $reason,
            ]);

            $record->offerNegotiations()->create([
                'user_id' => $actorId,
                'action' => 'cancelled',
                'round' => $this->countNegotiationRounds($record),
                'offered_fee' => $record->fee_total,
                'reason' => $reason,
                'meta' => [
                    'flow' => 'admin_request_cancelled',
                    'status_before' => $statusBefore,
                    'contract_status_before' => $contractStatusBefore,
                    'status_after' => AppraisalStatusEnum::Cancelled->value,
                    'contract_status_after' => ContractStatusEnum::Cancelled->value,
                ],
            ]);
        });
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
        $billingPayload = $this->resolveOfferBillingPayload($record, $data);
        $feeTotal = (int) $billingPayload['billing_total_amount'];
        $contractDate = optional($record->contract_date)->toDateString() ?: now()->toDateString();

        DB::transaction(function () use (
            $record,
            $actorId,
            $data,
            $billingPayload,
            $contractMeta,
            $statusBefore,
            $contractStatusBefore,
            $actionType,
            $round,
            $feeTotal,
            $contractDate
        ): void {
            $record->update([
                ...$billingPayload,
                'fee_has_dp' => false,
                'fee_dp_percent' => null,
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

        $record->loadMissing('user');
        $record->user?->notify(new AppraisalOfferNotification(
            appraisalId: (int) $record->id,
            requestNumber: (string) ($record->request_number ?? ('REQ-' . $record->id)),
            mode: $actionType === 'offer_revised' ? 'revised' : 'sent',
            feeTotal: $feeTotal,
        ));

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
        $billingPayload = $this->billingService->deriveFromGross($approvedFee);
        $contractMeta = $this->contractNumberService->deriveMetadata($record->contract_sequence);
        if ($contractMeta['contract_number'] === null) {
            throw new RuntimeException('No. Penawaran wajib diisi terlebih dahulu sebelum harapan fee user bisa disetujui.');
        }

        $statusBefore = $this->statusValue($record);
        $contractStatusBefore = $this->contractStatusValue($record);
        $round = $this->countNegotiationRounds($record);
        $contractDate = optional($record->contract_date)->toDateString() ?: now()->toDateString();
        $offeredFeeBefore = (int) ($record->fee_total ?? 0);

        DB::transaction(function () use (
            $record,
            $actorId,
            $latestCounter,
            $approvedFee,
            $offeredFeeBefore,
            $billingPayload,
            $contractMeta,
            $statusBefore,
            $contractStatusBefore,
            $round,
            $contractDate
        ): void {
            $record->update([
                ...$billingPayload,
                'fee_total' => $approvedFee,
                'contract_office_code' => $contractMeta['contract_office_code'],
                'contract_month' => $contractMeta['contract_month'],
                'contract_year' => $contractMeta['contract_year'],
                'contract_date' => $contractDate,
                'contract_number' => $contractMeta['contract_number'],
                'status' => AppraisalStatusEnum::WaitingSignature,
                'contract_status' => ContractStatusEnum::WaitingSignature,
            ]);

            $record->offerNegotiations()->create([
                'user_id' => $actorId,
                'action' => 'accepted',
                'round' => $round > 0 ? $round : null,
                'offered_fee' => $offeredFeeBefore,
                'expected_fee' => $approvedFee,
                'selected_fee' => $approvedFee,
                'meta' => [
                    'flow' => 'approve_latest_counter_request',
                    'counter_request_id' => $latestCounter->id,
                    'status_before' => $statusBefore,
                    'contract_status_before' => $contractStatusBefore,
                    'status_after' => AppraisalStatusEnum::WaitingSignature->value,
                    'contract_status_after' => ContractStatusEnum::WaitingSignature->value,
                ],
            ]);
        });

        $record->loadMissing('user');
        $record->user?->notify(new AppraisalOfferNotification(
            appraisalId: (int) $record->id,
            requestNumber: (string) ($record->request_number ?? ('REQ-' . $record->id)),
            mode: 'finalized',
            feeTotal: $approvedFee,
        ));

        return [
            'action' => 'accepted',
            'fee_total' => $approvedFee,
            'counter_request_id' => $latestCounter->id,
        ];
    }

    public function updatePhysicalReport(AppraisalRequest $record, int $actorId, array $data): array
    {
        $state = $this->physicalReportState($record);

        if (! ($state['show'] ?? false)) {
            throw new RuntimeException($state['message'] ?? 'Permohonan ini tidak memakai hard copy.');
        }

        if (! ($state['ready'] ?? false)) {
            throw new RuntimeException($state['message'] ?? 'Pengiriman hard copy belum bisa dicatat.');
        }

        $action = (string) ($data['action'] ?? 'save_details');

        return DB::transaction(function () use ($record, $actorId, $data, $action): array {
            $attributes = [
                'physical_report_courier' => $data['courier'] ?? $record->physical_report_courier,
                'physical_report_tracking_number' => $data['tracking_number'] ?? $record->physical_report_tracking_number,
                'physical_report_notes' => $data['notes'] ?? $record->physical_report_notes,
            ];

            $event = 'details_saved';
            $message = 'Detail pengiriman hard copy berhasil diperbarui.';

            if ($action === 'mark_printed') {
                $attributes['physical_report_printed_at'] = $record->physical_report_printed_at ?? now();
                $attributes['physical_report_printed_by'] = $record->physical_report_printed_by ?? $actorId;
                $event = 'printed';
                $message = 'Hard copy berhasil ditandai sudah dicetak.';
            }

            if ($action === 'mark_shipped') {
                $attributes['physical_report_printed_at'] = $record->physical_report_printed_at ?? now();
                $attributes['physical_report_printed_by'] = $record->physical_report_printed_by ?? $actorId;
                $attributes['physical_report_shipped_at'] = $record->physical_report_shipped_at ?? now();
                $event = 'shipped';
                $message = 'Hard copy berhasil ditandai sudah dikirim.';
            }

            if ($action === 'mark_delivered') {
                if (! $record->physical_report_shipped_at) {
                    throw new RuntimeException('Hard copy harus ditandai dikirim terlebih dahulu sebelum bisa ditandai diterima.');
                }

                $attributes['physical_report_delivered_at'] = $record->physical_report_delivered_at ?? now();
                $event = 'delivered';
                $message = 'Hard copy berhasil ditandai sudah diterima.';
            }

            $record->update($attributes);

            return [
                'event' => $event,
                'message' => $message,
                'courier' => $record->fresh()?->physical_report_courier,
                'tracking_number' => $record->fresh()?->physical_report_tracking_number,
            ];
        });
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

    private function resolveOfferBillingPayload(AppraisalRequest $record, array $data): array
    {
        if (isset($data['billing_dpp_amount']) && $data['billing_dpp_amount'] !== null) {
            return $this->billingService->appraisalAttributesFromDpp(
                (int) $data['billing_dpp_amount'],
                $record->user
            );
        }

        if (isset($data['fee_total']) && $data['fee_total'] !== null) {
            return $this->billingService->appraisalAttributesFromDpp(
                (int) $this->billingService->deriveFromGross((int) $data['fee_total'])['billing_dpp_amount'],
                $record->user
            );
        }

        throw new RuntimeException('Nilai penawaran wajib diisi sebelum penawaran dapat dikirim.');
    }

    private function hasPendingRevisionWork(AppraisalRequest $record): bool
    {
        if ($record->relationLoaded('revisionBatches')) {
            return $record->revisionBatches
                ->flatMap(fn ($batch) => $batch->items ?? collect())
                ->contains(fn ($item) => in_array((string) $item->status, ['pending', 'reuploaded', 'rejected'], true));
        }

        return $record->revisionBatches()
            ->whereIn('status', ['open', 'submitted'])
            ->whereHas('items', fn ($query) => $query->whereIn('status', ['pending', 'reuploaded', 'rejected']))
            ->exists();
    }
}

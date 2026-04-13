<?php

namespace App\Services\Payments;

use App\Enums\AppraisalStatusEnum;
use App\Models\AppraisalRequest;
use App\Models\Payment;
use App\Notifications\AppraisalPaymentStatusNotification;
use App\Services\Admin\AdminNotificationService;
use App\Services\Finance\AppraisalBillingService;
use App\Services\Reports\AppraisalFinalDocumentService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class CustomerPaymentWorkflowService
{
    public function __construct(
        private readonly MidtransSnapService $midtrans,
        private readonly AppraisalBillingService $billingService,
        private readonly AppraisalFinalDocumentService $finalDocumentService,
        private readonly AdminNotificationService $adminNotificationService,
    ) {
    }

    public function resolveUserRequest(Request $request, int $id): AppraisalRequest
    {
        return AppraisalRequest::query()
            ->where('user_id', $request->user()->id)
            ->findOrFail($id);
    }

    public function latestPayment(AppraisalRequest $record): ?Payment
    {
        return Payment::query()
            ->where('appraisal_request_id', $record->id)
            ->latest('id')
            ->first();
    }

    public function resolvePaymentForAppraisalPage(AppraisalRequest $record): ?Payment
    {
        $payment = $this->latestPayment($record);
        if (! $payment) {
            return null;
        }

        $this->expireStaleGatewayPayment($payment);

        return $this->syncPendingGatewayPayment($payment->fresh());
    }

    public function resolvePaymentForInvoicePage(AppraisalRequest $record): ?Payment
    {
        $payment = $this->latestPayment($record);

        return $payment ? $this->syncPendingGatewayPayment($payment) : null;
    }

    public function isPaymentAccessibleStatus(string $status): bool
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

    public function createPendingMidtransPayment(AppraisalRequest $record): Payment
    {
        return DB::transaction(function () use ($record): Payment {
            $billingSummary = $this->billingService->summary($record);

            $payment = Payment::create([
                'appraisal_request_id' => $record->id,
                'amount' => (int) $billingSummary['total_tagihan'],
                'method' => 'gateway',
                'gateway' => 'midtrans',
                'status' => 'pending',
                'proof_type' => 'gateway_id',
                'metadata' => [
                    'source' => 'appraisal_midtrans_session',
                    'created_at' => now()->toDateTimeString(),
                ],
            ]);

            $payment->update([
                'metadata' => array_merge((array) $payment->metadata, [
                    'invoice_number' => $this->billingService->invoiceNumber($record, $payment),
                ]),
            ]);

            return $payment->fresh();
        });
    }

    public function markMidtransSessionCreationFailed(Payment $payment, Throwable $exception): void
    {
        Log::error('Midtrans session creation failed.', [
            'appraisal_request_id' => $payment->appraisal_request_id,
            'payment_id' => $payment->id,
            'error' => $exception->getMessage(),
        ]);

        $metadata = is_array($payment->metadata) ? $payment->metadata : [];
        $metadata['gateway_error'] = $exception->getMessage();
        $metadata['gateway_error_at'] = now()->toDateTimeString();

        DB::transaction(function () use ($payment, $metadata): void {
            $payment->update([
                'status' => 'failed',
                'metadata' => $metadata,
            ]);
        });
    }

    public function finalizeMidtransSession(Payment $payment, array $transaction): Payment
    {
        $metadata = array_merge((array) $payment->metadata, [
            'checkout' => [
                'snap_token' => $transaction['snap_token'],
                'redirect_url' => $transaction['redirect_url'],
                'expires_at' => $transaction['expires_at'],
                'created_at' => now()->toIso8601String(),
                'enabled_payments' => $this->midtrans->enabledPayments(),
            ],
            'gateway_details' => [
                'label' => 'Midtrans Snap',
                'payment_type' => null,
                'expiry_time' => $transaction['expires_at'],
            ],
            'gateway_request' => $transaction['payload'],
        ]);

        return DB::transaction(function () use ($payment, $transaction, $metadata): Payment {
            $payment->update([
                'gateway' => 'midtrans',
                'external_payment_id' => $transaction['order_id'],
                'status' => 'pending',
                'proof_type' => 'gateway_id',
                'metadata' => $metadata,
            ]);

            return $payment->fresh();
        });
    }

    public function expireStaleGatewayPayment(Payment $payment): void
    {
        if ($payment->method !== 'gateway' || $payment->gateway !== 'midtrans' || $payment->status !== 'pending') {
            return;
        }

        $expiresAt = data_get($payment->metadata, 'checkout.expires_at');
        if (! filled($expiresAt)) {
            return;
        }

        try {
            if (! now()->greaterThan(Carbon::parse((string) $expiresAt))) {
                return;
            }
        } catch (Throwable) {
            return;
        }

        $metadata = is_array($payment->metadata) ? $payment->metadata : [];
        data_set($metadata, 'gateway_details.transaction_status', 'expire');
        data_set($metadata, 'gateway_details.expiry_time', $expiresAt);
        data_set($metadata, 'last_expired_at', now()->toDateTimeString());

        $payment->update([
            'status' => 'expired',
            'metadata' => $metadata,
        ]);
    }

    public function syncPendingGatewayPayment(?Payment $payment): ?Payment
    {
        if (! $payment || $payment->method !== 'gateway' || $payment->gateway !== 'midtrans' || $payment->status !== 'pending') {
            return $payment;
        }

        if (! filled($payment->external_payment_id)) {
            return $payment;
        }

        try {
            $payload = $this->midtrans->transactionStatus((string) $payment->external_payment_id);
        } catch (Throwable $exception) {
            Log::warning('Midtrans status sync failed.', [
                'payment_id' => $payment->id,
                'order_id' => $payment->external_payment_id,
                'error' => $exception->getMessage(),
            ]);

            return $payment;
        }

        return $this->applyMidtransStatusUpdate($payment, $payload, 'status_sync');
    }

    public function applyMidtransStatusUpdate(Payment $payment, array $payload, string $source): Payment
    {
        $incomingStatus = $this->midtrans->mapTransactionStatus($payload);
        $nextStatus = $this->resolveIncomingStatus($payment->status, $incomingStatus);
        $becamePaid = $payment->status !== 'paid' && $nextStatus === 'paid';
        $metadata = is_array($payment->metadata) ? $payment->metadata : [];
        $notificationMetadata = $this->midtrans->notificationMetadata($payload);

        $metadata['notification'] = $notificationMetadata;
        $metadata['gateway_details'] = array_filter(array_merge(
            (array) data_get($metadata, 'gateway_details', []),
            $notificationMetadata,
        ), static fn ($value) => $value !== null && $value !== '');

        if ($source === 'webhook') {
            $metadata['last_webhook_received_at'] = now()->toDateTimeString();
        } else {
            $metadata['last_status_sync_at'] = now()->toDateTimeString();
        }

        if (filled(data_get($notificationMetadata, 'expiry_time'))) {
            data_set($metadata, 'checkout.expires_at', data_get($notificationMetadata, 'expiry_time'));
        }

        $paidAt = $payment->paid_at;
        if ($nextStatus === 'paid' && ! $paidAt) {
            $paidAt = data_get($notificationMetadata, 'settlement_time')
                ?? data_get($notificationMetadata, 'transaction_time')
                ?? now();
        }

        $updatedPayment = DB::transaction(function () use ($payment, $nextStatus, $paidAt, $metadata): Payment {
            /** @var Payment $lockedPayment */
            $lockedPayment = Payment::query()
                ->with('appraisalRequest.user')
                ->lockForUpdate()
                ->findOrFail($payment->id);

            $lockedPayment->update([
                'status' => $nextStatus,
                'paid_at' => $nextStatus === 'paid' ? $paidAt : $lockedPayment->paid_at,
                'metadata' => $metadata,
            ]);

            $appraisal = $lockedPayment->appraisalRequest;
            if (
                $appraisal
                && $nextStatus === 'paid'
                && (($appraisal->status?->value ?? $appraisal->status) === AppraisalStatusEnum::ContractSigned->value)
            ) {
                $appraisal->update([
                    'status' => AppraisalStatusEnum::ValuationOnProgress,
                ]);
            }

            return $lockedPayment->fresh(['appraisalRequest.user']);
        });

        $appraisal = $updatedPayment->appraisalRequest;

        if ($nextStatus === 'paid' && $appraisal) {
            $this->finalDocumentService->generateAfterPayment($appraisal->fresh([
                'payments',
                'offerNegotiations.user',
                'files',
                'user',
                'assets',
            ]));
        }

        if ($becamePaid && $appraisal) {
            $requestNumber = $appraisal->request_number ?? ('REQ-' . ($appraisal->id ?? '-'));
            if ($appraisal->user) {
                $appraisal->user->notify(new AppraisalPaymentStatusNotification(
                    appraisalId: (int) $appraisal->id,
                    requestNumber: (string) $requestNumber,
                    status: 'verified',
                ));
            }

            $this->notifyAdminsPaymentConfirmed($updatedPayment->fresh());
        }

        return $updatedPayment->fresh();
    }

    /**
     * @return array{blocked: bool, message: string}
     */
    public function replacePendingMidtransAttempt(Payment $payment): array
    {
        if (! $this->midtrans->hasReusableSession($payment)) {
            return [
                'blocked' => false,
                'message' => 'Sesi aktif tidak ditemukan.',
            ];
        }

        $orderId = (string) $payment->external_payment_id;

        if ($orderId !== '') {
            try {
                $this->midtrans->cancelTransaction($orderId);
            } catch (Throwable $exception) {
                Log::warning('Midtrans active payment replacement failed.', [
                    'payment_id' => $payment->id,
                    'order_id' => $orderId,
                    'error' => $exception->getMessage(),
                ]);

                return [
                    'blocked' => true,
                    'message' => 'Sesi pembayaran aktif belum bisa diganti. Coba refresh status atau tunggu hingga sesi lama dibatalkan.',
                ];
            }
        }

        $metadata = is_array($payment->metadata) ? $payment->metadata : [];
        data_set($metadata, 'replaced_at', now()->toDateTimeString());
        data_set($metadata, 'replaced_reason', 'user_switch_payment_method');
        data_set($metadata, 'gateway_details.transaction_status', 'cancel');
        data_set($metadata, 'last_canceled_at', now()->toDateTimeString());

        DB::transaction(function () use ($payment, $metadata): void {
            $payment->update([
                'status' => 'failed',
                'metadata' => $metadata,
            ]);
        });

        return [
            'blocked' => false,
            'message' => 'Sesi aktif berhasil diganti.',
        ];
    }

    private function resolveIncomingStatus(string $currentStatus, string $incomingStatus): string
    {
        if ($currentStatus === 'refunded') {
            return 'refunded';
        }

        if ($currentStatus === 'paid' && $incomingStatus !== 'refunded') {
            return 'paid';
        }

        if (in_array($currentStatus, ['failed', 'expired'], true) && $incomingStatus === 'pending') {
            return $currentStatus;
        }

        return $incomingStatus;
    }

    private function notifyAdminsPaymentConfirmed(Payment $payment): void
    {
        $appraisal = $payment->appraisalRequest;
        $requestNumber = $appraisal?->request_number ?? ('REQ-' . ($appraisal?->id ?? '-'));
        $body = "{$requestNumber} pembayaran terkonfirmasi dan masuk Proses Valuasi Berjalan.";

        $targetUrl = null;
        if ($appraisal?->id) {
            try {
                $targetUrl = route('admin.appraisal-requests.show', ['appraisalRequest' => $appraisal->id]);
            } catch (Throwable) {
                $targetUrl = null;
            }
        }

        $this->adminNotificationService->notifyAdmins(
            'Pembayaran terkonfirmasi',
            $body,
            $targetUrl,
            'heroicon-o-banknotes',
        );
    }
}

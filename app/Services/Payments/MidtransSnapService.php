<?php

namespace App\Services\Payments;

use App\Models\AppraisalRequest;
use App\Models\Payment;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Midtrans\Config as MidtransConfig;
use Midtrans\Snap;
use Midtrans\Transaction;

class MidtransSnapService
{
    public function __construct()
    {
        $this->configure();
    }

    public function clientKey(): ?string
    {
        return config('payment.midtrans.client_key');
    }

    public function merchantId(): ?string
    {
        return config('payment.midtrans.merchant_id');
    }

    public function serverKey(): ?string
    {
        return config('payment.midtrans.server_key');
    }

    public function isProduction(): bool
    {
        return (bool) config('payment.midtrans.is_production', false);
    }

    public function sessionExpiryHours(): int
    {
        return max(1, (int) config('payment.midtrans.session_expiry_hours', 24));
    }

    public function enabledPayments(): array
    {
        return array_values(array_filter((array) config('payment.midtrans.enabled_payments', [])));
    }

    public function snapScriptUrl(): string
    {
        return $this->isProduction()
            ? 'https://app.midtrans.com/snap/snap.js'
            : 'https://app.sandbox.midtrans.com/snap/snap.js';
    }

    public function ensureConfigured(): void
    {
        if (blank($this->clientKey()) || blank($this->serverKey()) || blank($this->merchantId())) {
            throw new \RuntimeException('Konfigurasi Midtrans belum lengkap.');
        }
    }

    public function hasReusableSession(Payment $payment): bool
    {
        if ($payment->method !== 'gateway' || $payment->gateway !== 'midtrans' || $payment->status !== 'pending') {
            return false;
        }

        $snapToken = data_get($payment->metadata, 'checkout.snap_token');
        $expiresAt = data_get($payment->metadata, 'checkout.expires_at');

        if (! filled($snapToken) || ! filled($payment->external_payment_id) || ! filled($expiresAt)) {
            return false;
        }

        try {
            return Carbon::parse((string) $expiresAt)->isFuture();
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function createTransaction(AppraisalRequest $request, Payment $payment): array
    {
        $this->ensureConfigured();

        $orderId = $payment->external_payment_id ?: $this->generateOrderId($request, $payment);
        $expiresAt = now()->addHours($this->sessionExpiryHours());

        $params = [
            'transaction_details' => [
                'order_id' => $orderId,
                'gross_amount' => (int) $payment->amount,
            ],
            'item_details' => [
                [
                    'id' => 'appraisal-fee-' . $request->id,
                    'price' => (int) $payment->amount,
                    'quantity' => 1,
                    'name' => 'Biaya layanan penilaian properti ' . ($request->request_number ?? ('REQ-' . $request->id)),
                ],
            ],
            'customer_details' => [
                'first_name' => (string) ($request->client_name ?: ($request->user?->name ?? 'Customer')),
                'email' => (string) ($request->user?->email ?? 'no-reply@example.com'),
                'phone' => (string) data_get($request->user, 'phone', ''),
            ],
            'enabled_payments' => $this->enabledPayments(),
            'expiry' => [
                'start_time' => now()->format('Y-m-d H:i:s O'),
                'unit' => 'hour',
                'duration' => $this->sessionExpiryHours(),
            ],
            'custom_field1' => (string) ($request->request_number ?? ('REQ-' . $request->id)),
            'custom_field2' => (string) ($request->contract_number ?? '-'),
            'custom_field3' => (string) $payment->id,
            'callbacks' => [
                'finish' => route('appraisal.payment.page', ['id' => $request->id]),
                'pending' => route('appraisal.payment.page', ['id' => $request->id]),
                'error' => route('appraisal.payment.page', ['id' => $request->id]),
            ],
        ];

        $transaction = Snap::createTransaction($params);

        return [
            'order_id' => $orderId,
            'snap_token' => (string) $transaction->token,
            'redirect_url' => (string) $transaction->redirect_url,
            'expires_at' => $expiresAt->toIso8601String(),
            'payload' => $params,
        ];
    }

    public function cancelTransaction(string $orderId): string
    {
        $this->ensureConfigured();

        return (string) Transaction::cancel($orderId);
    }

    /**
     * @return array<string, mixed>
     */
    public function transactionStatus(string $orderId): array
    {
        $this->ensureConfigured();

        $response = Transaction::status($orderId);

        return json_decode(json_encode($response), true) ?: [];
    }

    public function verifyNotificationSignature(array $payload): bool
    {
        $signature = (string) ($payload['signature_key'] ?? '');
        $orderId = (string) ($payload['order_id'] ?? '');
        $statusCode = (string) ($payload['status_code'] ?? '');
        $grossAmount = (string) ($payload['gross_amount'] ?? '');
        $serverKey = (string) $this->serverKey();

        if ($signature === '' || $orderId === '' || $statusCode === '' || $grossAmount === '' || $serverKey === '') {
            return false;
        }

        $expected = hash('sha512', $orderId . $statusCode . $grossAmount . $serverKey);

        return hash_equals($expected, $signature);
    }

    public function mapTransactionStatus(array $payload): string
    {
        $transactionStatus = strtolower((string) ($payload['transaction_status'] ?? ''));
        $fraudStatus = strtolower((string) ($payload['fraud_status'] ?? ''));

        return match ($transactionStatus) {
            'capture' => $fraudStatus === 'challenge' ? 'pending' : 'paid',
            'settlement' => 'paid',
            'pending' => 'pending',
            'deny', 'cancel' => 'failed',
            'expire' => 'expired',
            'refund', 'partial_refund', 'chargeback', 'partial_chargeback' => 'refunded',
            default => 'pending',
        };
    }

    public function paymentStatusLabel(?Payment $payment): string
    {
        if (! $payment) {
            return 'Menunggu Pembayaran';
        }

        return match ($payment->status) {
            'paid' => 'Dibayar',
            'failed' => 'Gagal',
            'expired' => 'Kedaluwarsa',
            'rejected' => 'Ditolak',
            'refunded' => 'Refund',
            default => $payment->method === 'manual' && filled($payment->proof_file_path)
                ? 'Menunggu Verifikasi'
                : 'Menunggu Pembayaran',
        };
    }

    public function paymentMethodLabel(?Payment $payment): string
    {
        if (! $payment) {
            return 'Midtrans Snap';
        }

        if ($payment->method === 'manual') {
            return 'Transfer Bank';
        }

        $details = $this->gatewayDetailsFromMetadata(is_array($payment->metadata) ? $payment->metadata : []);

        return $details['label'] ?? 'Midtrans Snap';
    }

    /**
     * @return array<string, mixed>
     */
    public function gatewayDetailsFromMetadata(array $metadata): array
    {
        $paymentType = strtolower((string) data_get($metadata, 'gateway_details.payment_type', data_get($metadata, 'notification.payment_type', '')));
        $vaNumbers = data_get($metadata, 'gateway_details.va_numbers', data_get($metadata, 'notification.va_numbers', []));
        $permataVaNumber = data_get($metadata, 'gateway_details.permata_va_number', data_get($metadata, 'notification.permata_va_number'));
        $billKey = data_get($metadata, 'gateway_details.bill_key', data_get($metadata, 'notification.bill_key'));
        $billerCode = data_get($metadata, 'gateway_details.biller_code', data_get($metadata, 'notification.biller_code'));
        $paymentCode = data_get($metadata, 'gateway_details.payment_code', data_get($metadata, 'notification.payment_code'));
        $store = data_get($metadata, 'gateway_details.store', data_get($metadata, 'notification.store'));
        $acquirer = data_get($metadata, 'gateway_details.acquirer', data_get($metadata, 'notification.acquirer'));
        $transactionId = data_get($metadata, 'gateway_details.transaction_id', data_get($metadata, 'notification.transaction_id'));
        $transactionStatus = data_get($metadata, 'gateway_details.transaction_status', data_get($metadata, 'notification.transaction_status'));
        $expiryTime = data_get($metadata, 'gateway_details.expiry_time', data_get($metadata, 'notification.expiry_time', data_get($metadata, 'checkout.expires_at')));
        $bank = null;
        $reference = null;
        $accountHolder = null;

        if (is_array($vaNumbers) && isset($vaNumbers[0]) && is_array($vaNumbers[0])) {
            $bank = data_get($vaNumbers, '0.bank');
            $reference = data_get($vaNumbers, '0.va_number');
        } elseif (filled($permataVaNumber)) {
            $bank = 'permata';
            $reference = $permataVaNumber;
        } elseif ($paymentType === 'echannel') {
            $bank = 'mandiri';
            $reference = filled($billKey) ? trim($billKey . ' / ' . $billerCode) : $billerCode;
        } elseif (filled($paymentCode)) {
            $reference = $paymentCode;
        }

        $label = match ($paymentType) {
            'bank_transfer' => filled($bank) ? strtoupper((string) $bank) . ' Virtual Account' : 'Virtual Account',
            'echannel' => 'Mandiri Bill Payment',
            'gopay' => 'GoPay',
            'shopeepay' => 'ShopeePay',
            'qris' => 'QRIS',
            default => 'Midtrans Snap',
        };

        if ($paymentType === 'qris' && filled($acquirer)) {
            $accountHolder = strtoupper((string) $acquirer);
        }

        if (in_array($paymentType, ['gopay', 'shopeepay'], true) && filled($store)) {
            $accountHolder = strtoupper((string) $store);
        }

        return [
            'payment_type' => $paymentType ?: null,
            'label' => $label,
            'bank' => $bank ? strtoupper((string) $bank) : null,
            'reference' => $reference ? (string) $reference : null,
            'account_holder' => $accountHolder,
            'store' => $store,
            'acquirer' => $acquirer,
            'transaction_id' => $transactionId,
            'transaction_status' => $transactionStatus,
            'expiry_time' => $expiryTime,
            'va_numbers' => is_array($vaNumbers) ? $vaNumbers : [],
            'bill_key' => $billKey,
            'biller_code' => $billerCode,
            'payment_code' => $paymentCode,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function notificationMetadata(array $payload): array
    {
        return [
            'transaction_id' => data_get($payload, 'transaction_id'),
            'transaction_status' => data_get($payload, 'transaction_status'),
            'status_code' => data_get($payload, 'status_code'),
            'status_message' => data_get($payload, 'status_message'),
            'payment_type' => data_get($payload, 'payment_type'),
            'fraud_status' => data_get($payload, 'fraud_status'),
            'gross_amount' => data_get($payload, 'gross_amount'),
            'transaction_time' => data_get($payload, 'transaction_time'),
            'settlement_time' => data_get($payload, 'settlement_time'),
            'expiry_time' => data_get($payload, 'expiry_time'),
            'currency' => data_get($payload, 'currency'),
            'merchant_id' => data_get($payload, 'merchant_id'),
            'acquirer' => data_get($payload, 'acquirer'),
            'store' => data_get($payload, 'store'),
            'va_numbers' => data_get($payload, 'va_numbers', []),
            'permata_va_number' => data_get($payload, 'permata_va_number'),
            'bill_key' => data_get($payload, 'bill_key'),
            'biller_code' => data_get($payload, 'biller_code'),
            'payment_code' => data_get($payload, 'payment_code'),
            'pdf_url' => data_get($payload, 'pdf_url'),
            'signature_key' => data_get($payload, 'signature_key'),
        ];
    }

    private function generateOrderId(AppraisalRequest $request, Payment $payment): string
    {
        $baseRequestNumber = Str::upper((string) ($request->request_number ?? ('REQ-' . $request->id)));
        $safeRequestNumber = preg_replace('/[^A-Z0-9]+/', '-', $baseRequestNumber);

        return trim(sprintf(
            'DIGIPRO-%s-PAY-%d-%s',
            $safeRequestNumber,
            $payment->id,
            now()->format('YmdHis')
        ), '-');
    }

    private function configure(): void
    {
        MidtransConfig::$serverKey = $this->serverKey();
        MidtransConfig::$clientKey = $this->clientKey();
        MidtransConfig::$isProduction = $this->isProduction();
        MidtransConfig::$isSanitized = true;
        MidtransConfig::$is3ds = true;
    }
}

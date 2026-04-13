<?php

namespace App\Services\Payments;

use App\Models\Payment;

class AdminPaymentUpdateService
{
    public function update(Payment $payment, array $validated): void
    {
        $payment->forceFill([
            'amount' => (int) $validated['amount'],
            'status' => $validated['status'],
            'gateway' => $validated['gateway'] ?: ($payment->gateway ?: 'midtrans'),
            'external_payment_id' => $validated['external_payment_id'] ?: null,
            'paid_at' => $validated['paid_at'] ?? null,
            'metadata' => $this->decodePaymentMetadata($validated['metadata_json'] ?? null),
        ])->save();
    }

    private function decodePaymentMetadata(?string $metadata): ?array
    {
        if (blank($metadata)) {
            return null;
        }

        $decoded = json_decode((string) $metadata, true);

        return is_array($decoded) ? $decoded : null;
    }
}

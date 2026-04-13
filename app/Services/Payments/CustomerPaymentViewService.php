<?php

namespace App\Services\Payments;

use App\Models\AppraisalRequest;
use App\Models\Payment;
use App\Services\Finance\AppraisalBillingService;
use Illuminate\Support\Carbon;

class CustomerPaymentViewService
{
    public function __construct(
        private readonly MidtransSnapService $midtrans,
        private readonly AppraisalBillingService $billingService,
    ) {
    }

    public function buildIndexPaymentCard(AppraisalRequest $record, ?Payment $payment): array
    {
        $gatewayDetails = $this->resolveGatewayDetails($payment);
        $billingSummary = $this->billingService->summary($record, $payment);
        $invoiceNumber = $billingSummary['nomor_invoice'];

        return [
            'id' => $record->id,
            'invoice_number' => $invoiceNumber,
            'request_number' => $record->request_number ?? ('REQ-' . $record->id),
            'client' => $record->client_name ?: ($record->user?->name ?? '-'),
            'amount' => $this->formatIDR((int) $billingSummary['total_tagihan']),
            'status' => $this->midtrans->paymentStatusLabel($payment),
            'is_paid' => $payment?->status === 'paid',
            'invoice_pdf_url' => route('appraisal.invoice.pdf', ['id' => $record->id]),
            'due_date' => $this->resolveDueDate($payment, $record),
            'method' => $this->resolvePaymentMethodLabel($payment),
            'bank' => $gatewayDetails['bank'] ?? $gatewayDetails['label'] ?? '-',
            'va' => $gatewayDetails['reference'] ?? '-',
            'updated_at' => optional($payment?->updated_at ?? $record->updated_at)->toDateString(),
            'order_id' => $payment?->external_payment_id,
            'gateway_details' => $gatewayDetails,
            'documents' => [[
                'label' => 'Invoice Pembayaran',
                'name' => "{$invoiceNumber}.pdf",
                'type' => 'invoice',
                'size' => '-',
            ]],
            'billing_summary' => $billingSummary,
        ];
    }

    public function buildAppraisalRequestPayload(AppraisalRequest $record, ?Payment $payment): array
    {
        $status = $record->status?->value ?? (string) $record->status;

        return [
            'id' => $record->id,
            'request_number' => $record->request_number ?? ('REQ-' . $record->id),
            'contract_number' => $record->contract_number,
            'status' => $status,
            'status_label' => $record->status?->label() ?? $status,
            'fee_total' => (int) ($record->fee_total ?? 0),
            'invoice_number' => $this->billingService->invoiceNumber($record, $payment),
            'billing_summary' => $this->billingService->summary($record, $payment),
        ];
    }

    public function buildPaymentPayload(AppraisalRequest $record, ?Payment $payment, bool $includeCheckout = false): array
    {
        $gatewayDetails = $this->resolveGatewayDetails($payment);
        $metadata = is_array($payment?->metadata) ? $payment->metadata : [];
        $checkout = $includeCheckout ? [
            'snap_token' => data_get($metadata, 'checkout.snap_token'),
            'redirect_url' => data_get($metadata, 'checkout.redirect_url'),
            'expires_at' => data_get($metadata, 'checkout.expires_at'),
            'created_at' => data_get($metadata, 'checkout.created_at'),
        ] : null;

        return [
            'id' => $payment?->id,
            'status' => $payment?->status,
            'status_label' => $this->midtrans->paymentStatusLabel($payment),
            'amount' => (int) ($payment?->amount ?? $record->fee_total ?? 0),
            'method' => $this->resolvePaymentMethodLabel($payment),
            'paid_at' => optional($payment?->paid_at)->toDateTimeString(),
            'invoice_number' => $this->billingService->invoiceNumber($record, $payment),
            'external_payment_id' => $payment?->external_payment_id,
            'checkout' => $checkout,
            'gateway_details' => $gatewayDetails,
            'metadata' => $metadata,
            'billing_summary' => $this->billingService->summary($record, $payment),
        ];
    }

    public function buildInvoiceRequestPayload(AppraisalRequest $record, string $clientName, ?Payment $payment): array
    {
        return [
            'id' => $record->id,
            'request_number' => $record->request_number ?? ('REQ-' . $record->id),
            'contract_number' => $record->contract_number,
            'status' => $record->status?->value ?? (string) $record->status,
            'status_label' => $record->status?->label() ?? (string) $record->status,
            'fee_total' => (int) ($record->fee_total ?? 0),
            'client_name' => $record->client_name ?: $clientName,
            'billing_summary' => $this->billingService->summary($record, $payment),
        ];
    }

    public function buildInvoicePaymentPayload(AppraisalRequest $record, Payment $payment): array
    {
        return [
            'id' => $payment->id,
            'invoice_number' => $this->billingService->invoiceNumber($record, $payment),
            'status' => $payment->status,
            'status_label' => $this->midtrans->paymentStatusLabel($payment),
            'amount' => (int) ($payment->amount ?? 0),
            'method' => $this->resolvePaymentMethodLabel($payment),
            'paid_at' => optional($payment->paid_at)->toDateTimeString(),
            'invoice_number_internal' => $this->billingService->invoiceNumber($record, $payment),
            'external_payment_id' => $payment->external_payment_id,
            'gateway_details' => $this->resolveGatewayDetails($payment),
            'metadata' => $payment->metadata,
            'billing_summary' => $this->billingService->summary($record, $payment),
        ];
    }

    public function buildInvoiceDocumentPayload(AppraisalRequest $record, Payment $payment, string $clientName): array
    {
        $billingSummary = $this->billingService->summary($record, $payment);
        $invoiceNumber = $billingSummary['nomor_invoice'];

        return [
            'invoice_number' => $invoiceNumber,
            'request_number' => $record->request_number ?? ('REQ-' . $record->id),
            'contract_number' => $record->contract_number ?: '-',
            'issued_at' => optional($payment->paid_at ?? $payment->updated_at)->toDateTimeString(),
            'client_name' => $record->client_name ?: $clientName,
            'amount' => (int) ($payment->amount ?? $record->fee_total ?? 0),
            'method' => $this->resolvePaymentMethodLabel($payment),
            'status_label' => 'LUNAS',
            'payment_status' => $payment->status,
            'gateway_details' => $this->resolveGatewayDetails($payment),
            'company_name' => config('app.name', 'DigiPro by KJPP HJAR'),
            'external_payment_id' => $payment->external_payment_id,
            'billing_summary' => $billingSummary,
        ];
    }

    public function resolveGatewayDetails(?Payment $payment): ?array
    {
        if (! $payment || $payment->method !== 'gateway') {
            return null;
        }

        return $this->midtrans->gatewayDetailsFromMetadata(is_array($payment->metadata) ? $payment->metadata : []);
    }

    public function resolvePaymentMethodLabel(?Payment $payment): string
    {
        return $this->midtrans->paymentMethodLabel($payment);
    }

    public function resolveDueDate(?Payment $payment, AppraisalRequest $record): ?string
    {
        $expiresAt = data_get($payment?->metadata, 'checkout.expires_at');
        if (filled($expiresAt)) {
            try {
                return Carbon::parse((string) $expiresAt)->toDateString();
            } catch (\Throwable) {
                // Fallback below when the gateway timestamp is malformed.
            }
        }

        return optional(($payment?->created_at ?? $record->requested_at ?? now())->copy()->addDays(3))->toDateString();
    }

    private function formatIDR(int $amount): string
    {
        return 'Rp ' . number_format($amount, 0, ',', '.');
    }
}

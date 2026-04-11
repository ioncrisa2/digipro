<?php

namespace App\Http\Controllers\Customer;

use App\Enums\AppraisalStatusEnum;
use App\Http\Controllers\Controller;
use App\Http\Requests\Customer\CreateMidtransSessionRequest;
use App\Http\Requests\Customer\CustomerAccessRequest;
use App\Http\Requests\Customer\MidtransNotificationRequest;
use App\Models\AppraisalRequest;
use App\Models\Payment;
use App\Notifications\AppraisalPaymentStatusNotification;
use App\Services\Admin\AdminNotificationService;
use App\Services\Finance\AppraisalBillingService;
use App\Services\Reports\AppraisalFinalDocumentService;
use App\Services\Payments\MidtransSnapService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Handles Midtrans Snap payment pages, sessions, and invoice flows.
 */
class PaymentController extends Controller
{
    public function index(CustomerAccessRequest $request)
    {
        $records = AppraisalRequest::query()
            ->where('user_id', $request->user()->id)
            ->whereIn('status', [
                AppraisalStatusEnum::ContractSigned->value,
                AppraisalStatusEnum::ValuationOnProgress->value,
                AppraisalStatusEnum::ValuationCompleted->value,
                AppraisalStatusEnum::PreviewReady->value,
                AppraisalStatusEnum::ReportPreparation->value,
                AppraisalStatusEnum::ReportReady->value,
                AppraisalStatusEnum::Completed->value,
            ])
            ->with([
                'payments' => fn ($q) => $q->latest('id'),
                'user:id,name,email',
            ])
            ->latest('requested_at')
            ->get();

        $payments = $records->map(function (AppraisalRequest $record) {
            $payment = $record->payments->sortByDesc('id')->first();
            $gatewayDetails = $this->resolveGatewayDetails($payment);
            $billingSummary = $this->billingService()->summary($record, $payment);
            $invoiceNumber = $billingSummary['nomor_invoice'];

            return [
                'id' => $record->id,
                'invoice_number' => $invoiceNumber,
                'request_number' => $record->request_number ?? ('REQ-' . $record->id),
                'client' => $record->client_name ?: ($record->user?->name ?? '-'),
                'amount' => $this->formatIDR((int) $billingSummary['total_tagihan']),
                'status' => $this->midtrans()->paymentStatusLabel($payment),
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
        })->values()->all();

        return inertia('Payments/Index', [
            'payments' => $payments,
        ]);
    }

    public function show(CustomerAccessRequest $request, int $id)
    {
        $record = $this->resolveUserRequest($request, $id);
        $status = $record->status?->value ?? (string) $record->status;

        if (! $this->isPaymentAccessibleStatus($status)) {
            return redirect()
                ->route('appraisal.show', ['id' => $record->id])
                ->with('error', 'Pembayaran belum dapat diakses pada status saat ini.');
        }

        $payment = $this->latestPayment($record);

        if ($payment && $payment->status === 'paid') {
            return redirect()->route('appraisal.invoice.page', ['id' => $record->id]);
        }

        return redirect()->route('appraisal.payment.page', ['id' => $record->id]);
    }

    public function appraisalPage(CustomerAccessRequest $request, int $id)
    {
        $record = $this->resolveUserRequest($request, $id);
        $status = $record->status?->value ?? (string) $record->status;

        if (! $this->isPaymentAccessibleStatus($status)) {
            return redirect()
                ->route('appraisal.show', ['id' => $record->id])
                ->with('error', 'Pembayaran belum dapat diakses pada status saat ini.');
        }

        $payment = $this->latestPayment($record);
        if ($payment) {
            $this->expireStaleGatewayPayment($payment);
            $payment = $this->syncPendingGatewayPayment($payment->fresh());
        }

        if ($payment && $payment->status === 'paid') {
            return redirect()
                ->route('appraisal.invoice.page', ['id' => $record->id]);
        }

        $activeCheckout = $payment && $this->midtrans()->hasReusableSession($payment);

        return inertia('Penilaian/Payment', [
            'request' => [
                'id' => $record->id,
                'request_number' => $record->request_number ?? ('REQ-' . $record->id),
                'contract_number' => $record->contract_number,
                'status' => $status,
                'status_label' => $record->status?->label() ?? $status,
                'fee_total' => (int) ($record->fee_total ?? 0),
                'invoice_number' => $this->billingService()->invoiceNumber($record, $payment),
                'billing_summary' => $this->billingService()->summary($record, $payment),
            ],
            'payment' => $this->buildPaymentPayload($record, $payment, $activeCheckout),
            'midtrans' => [
                'client_key' => $this->midtrans()->clientKey(),
                'snap_script_url' => $this->midtrans()->snapScriptUrl(),
                'create_session_url' => route('appraisal.payment.session', ['id' => $record->id]),
                'configured' => filled($this->midtrans()->clientKey()) && filled($this->midtrans()->merchantId()),
            ],
            'canStartCheckout' => $status === AppraisalStatusEnum::ContractSigned->value,
        ]);
    }

    public function createMidtransSession(CreateMidtransSessionRequest $request, int $id): JsonResponse
    {
        $record = $this->resolveUserRequest($request, $id);
        $status = $record->status?->value ?? (string) $record->status;
        $forceNewAttempt = $request->forceNewAttempt();

        if ($status !== AppraisalStatusEnum::ContractSigned->value) {
            return response()->json([
                'message' => 'Session pembayaran hanya bisa dibuat saat kontrak sudah ditandatangani.',
            ], 422);
        }

        $latestPayment = $this->latestPayment($record);

        if ($latestPayment?->status === 'paid') {
            return response()->json([
                'message' => 'Pembayaran sudah terverifikasi.',
                'redirect_url' => route('appraisal.invoice.page', ['id' => $record->id]),
            ], 409);
        }

        if ($latestPayment) {
            $this->expireStaleGatewayPayment($latestPayment);
            $latestPayment = $latestPayment->fresh();
        }

        if ($latestPayment && $this->midtrans()->hasReusableSession($latestPayment)) {
            if ($forceNewAttempt) {
                $replacementResult = $this->replacePendingMidtransAttempt($latestPayment);
                if ($replacementResult['blocked']) {
                    return response()->json([
                        'message' => $replacementResult['message'],
                    ], 409);
                }

                $latestPayment = null;
            } else {
                return response()->json([
                    'message' => 'Session Midtrans aktif digunakan kembali.',
                    'payment' => $this->buildPaymentPayload($record, $latestPayment, true),
                ]);
            }
        }

        $payment = DB::transaction(function () use ($record): Payment {
            $billingSummary = $this->billingService()->summary($record);

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
                    'invoice_number' => $this->buildInvoiceNumber($record, $payment),
                ]),
            ]);

            return $payment->fresh();
        });

        try {
            $transaction = $this->midtrans()->createTransaction($record->loadMissing('user'), $payment);
        } catch (\Throwable $e) {
            Log::error('Midtrans session creation failed.', [
                'appraisal_request_id' => $record->id,
                'payment_id' => $payment->id,
                'error' => $e->getMessage(),
            ]);

            $metadata = is_array($payment->metadata) ? $payment->metadata : [];
            $metadata['gateway_error'] = $e->getMessage();
            $metadata['gateway_error_at'] = now()->toDateTimeString();

            DB::transaction(function () use ($payment, $metadata): void {
                $payment->update([
                    'status' => 'failed',
                    'metadata' => $metadata,
                ]);
            });

            return response()->json([
                'message' => 'Gagal membuat sesi pembayaran Midtrans.',
            ], 500);
        }

        $metadata = array_merge((array) $payment->metadata, [
            'checkout' => [
                'snap_token' => $transaction['snap_token'],
                'redirect_url' => $transaction['redirect_url'],
                'expires_at' => $transaction['expires_at'],
                'created_at' => now()->toIso8601String(),
                'enabled_payments' => $this->midtrans()->enabledPayments(),
            ],
            'gateway_details' => [
                'label' => 'Midtrans Snap',
                'payment_type' => null,
                'expiry_time' => $transaction['expires_at'],
            ],
            'gateway_request' => $transaction['payload'],
        ]);

        $payment = DB::transaction(function () use ($payment, $transaction, $metadata): Payment {
            $payment->update([
                'gateway' => 'midtrans',
                'external_payment_id' => $transaction['order_id'],
                'status' => 'pending',
                'proof_type' => 'gateway_id',
                'metadata' => $metadata,
            ]);

            return $payment->fresh();
        });

        return response()->json([
            'message' => $forceNewAttempt
                ? 'Metode pembayaran diganti dan sesi Midtrans baru berhasil dibuat.'
                : 'Session pembayaran Midtrans berhasil dibuat.',
            'payment' => $this->buildPaymentPayload($record, $payment, true),
        ]);
    }

    public function midtransNotification(MidtransNotificationRequest $request): JsonResponse
    {
        $payload = $request->payload();

        if (! $this->midtrans()->verifyNotificationSignature($payload)) {
            Log::warning('Midtrans notification signature invalid.', [
                'order_id' => data_get($payload, 'order_id'),
            ]);

            return response()->json([
                'message' => 'Signature tidak valid.',
            ], 403);
        }

        $payment = Payment::query()
            ->where('gateway', 'midtrans')
            ->where('external_payment_id', (string) data_get($payload, 'order_id'))
            ->latest('id')
            ->first();

        if (! $payment) {
            return response()->json([
                'message' => 'Payment tidak ditemukan.',
            ], 404);
        }

        $this->applyMidtransStatusUpdate($payment, $payload, 'webhook');

        return response()->json([
            'message' => 'Notification diproses.',
        ]);
    }

    public function invoicePage(CustomerAccessRequest $request, int $id)
    {
        $record = $this->resolveUserRequest($request, $id);
        $payment = $this->latestPayment($record);
        if ($payment) {
            $payment = $this->syncPendingGatewayPayment($payment);
        }

        if (! $payment) {
            return redirect()
                ->route('appraisal.payment.page', ['id' => $record->id])
                ->with('error', 'Data pembayaran belum tersedia.');
        }

        if ($payment->status !== 'paid') {
            return redirect()
                ->route('appraisal.payment.page', ['id' => $record->id])
                ->with('info', 'Invoice tersedia setelah pembayaran terverifikasi.');
        }

        $gatewayDetails = $this->resolveGatewayDetails($payment);

        return inertia('Penilaian/Invoice', [
            'request' => [
                'id' => $record->id,
                'request_number' => $record->request_number ?? ('REQ-' . $record->id),
                'contract_number' => $record->contract_number,
                'status' => $record->status?->value ?? (string) $record->status,
                'status_label' => $record->status?->label() ?? (string) $record->status,
                'fee_total' => (int) ($record->fee_total ?? 0),
                'client_name' => $record->client_name ?: ($request->user()->name ?? '-'),
                'billing_summary' => $this->billingService()->summary($record, $payment),
            ],
            'payment' => [
                'id' => $payment->id,
                'invoice_number' => $this->billingService()->invoiceNumber($record, $payment),
                'status' => $payment->status,
                'status_label' => $this->midtrans()->paymentStatusLabel($payment),
                'amount' => (int) ($payment->amount ?? 0),
                'method' => $this->resolvePaymentMethodLabel($payment),
                'paid_at' => optional($payment->paid_at)->toDateTimeString(),
                'invoice_number_internal' => $this->resolveInvoiceNumber($record, $payment),
                'external_payment_id' => $payment->external_payment_id,
                'gateway_details' => $gatewayDetails,
                'metadata' => $payment->metadata,
                'billing_summary' => $this->billingService()->summary($record, $payment),
            ],
        ]);
    }

    public function downloadInvoicePdf(CustomerAccessRequest $request, int $id)
    {
        $record = $this->resolveUserRequest($request, $id);
        $payment = $this->latestPayment($record);

        if (! $payment) {
            return redirect()
                ->route('appraisal.payment.page', ['id' => $record->id])
                ->with('error', 'Data pembayaran belum tersedia.');
        }

        if ($payment->status !== 'paid') {
            return redirect()
                ->route('appraisal.payment.page', ['id' => $record->id])
                ->with('info', 'Invoice PDF tersedia setelah pembayaran terverifikasi.');
        }

        $gatewayDetails = $this->resolveGatewayDetails($payment);
        $billingSummary = $this->billingService()->summary($record, $payment);
        $invoiceNumber = $billingSummary['nomor_invoice'];
        $invoice = [
            'invoice_number' => $invoiceNumber,
            'request_number' => $record->request_number ?? ('REQ-' . $record->id),
            'contract_number' => $record->contract_number ?: '-',
            'issued_at' => optional($payment->paid_at ?? $payment->updated_at)->toDateTimeString(),
            'client_name' => $record->client_name ?: ($request->user()->name ?? '-'),
            'amount' => (int) ($payment->amount ?? $record->fee_total ?? 0),
            'method' => $this->resolvePaymentMethodLabel($payment),
            'status_label' => 'LUNAS',
            'payment_status' => $payment->status,
            'gateway_details' => $gatewayDetails,
            'company_name' => config('app.name', 'DigiPro by KJPP HJAR'),
            'external_payment_id' => $payment->external_payment_id,
            'billing_summary' => $billingSummary,
        ];

        $safeInvoiceNumber = preg_replace('/[^A-Za-z0-9\-_.]/', '-', (string) $invoiceNumber);
        $fileName = "Invoice-{$safeInvoiceNumber}.pdf";

        return Pdf::loadView('pdfs.appraisal-invoice', [
            'invoice' => $invoice,
        ])
            ->setPaper('a4', 'portrait')
            ->download($fileName);
    }

    private function latestPayment(AppraisalRequest $record): ?Payment
    {
        return Payment::query()
            ->where('appraisal_request_id', $record->id)
            ->latest('id')
            ->first();
    }

    /**
     * @return array<string, mixed>
     */
    private function buildPaymentPayload(AppraisalRequest $record, ?Payment $payment, bool $includeCheckout = false): array
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
            'status_label' => $this->midtrans()->paymentStatusLabel($payment),
            'amount' => (int) ($payment?->amount ?? $record->fee_total ?? 0),
            'method' => $this->resolvePaymentMethodLabel($payment),
            'paid_at' => optional($payment?->paid_at)->toDateTimeString(),
            'invoice_number' => $this->billingService()->invoiceNumber($record, $payment),
            'external_payment_id' => $payment?->external_payment_id,
            'checkout' => $checkout,
            'gateway_details' => $gatewayDetails,
            'metadata' => $metadata,
            'billing_summary' => $this->billingService()->summary($record, $payment),
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function resolveGatewayDetails(?Payment $payment): ?array
    {
        if (! $payment || $payment->method !== 'gateway') {
            return null;
        }

        return $this->midtrans()->gatewayDetailsFromMetadata(is_array($payment->metadata) ? $payment->metadata : []);
    }

    private function resolvePaymentMethodLabel(?Payment $payment): string
    {
        return $this->midtrans()->paymentMethodLabel($payment);
    }

    private function resolveDueDate(?Payment $payment, AppraisalRequest $record): ?string
    {
        $expiresAt = data_get($payment?->metadata, 'checkout.expires_at');
        if (filled($expiresAt)) {
            try {
                return Carbon::parse((string) $expiresAt)->toDateString();
            } catch (\Throwable) {
                // fallback below
            }
        }

        return optional(($payment?->created_at ?? $record->requested_at ?? now())->copy()->addDays(3))->toDateString();
    }

    private function expireStaleGatewayPayment(Payment $payment): void
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
        } catch (\Throwable) {
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

    private function isPaymentAccessibleStatus(string $status): bool
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

    private function resolveInvoiceNumber(AppraisalRequest $record, ?Payment $payment): string
    {
        return $this->billingService()->invoiceNumber($record, $payment);
    }

    private function buildInvoiceNumber(AppraisalRequest $record, ?Payment $payment = null): string
    {
        $year = now()->format('Y');
        $sequence = str_pad((string) ($payment?->id ?? $record->id), 5, '0', STR_PAD_LEFT);

        return "INV-{$year}-{$sequence}";
    }

    private function formatIDR(int $amount): string
    {
        return 'Rp ' . number_format($amount, 0, ',', '.');
    }

    private function formatBytes(int $bytes): string
    {
        if ($bytes <= 0) {
            return '-';
        }

        $units = ['B', 'KB', 'MB', 'GB'];
        $power = min((int) floor(log($bytes, 1024)), count($units) - 1);
        $size = $bytes / (1024 ** $power);

        return number_format($size, $power === 0 ? 0 : 2, ',', '.') . ' ' . $units[$power];
    }

    private function resolveUserRequest(Request $request, int $id): AppraisalRequest
    {
        return AppraisalRequest::query()
            ->where('user_id', $request->user()->id)
            ->findOrFail($id);
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

    private function syncPendingGatewayPayment(?Payment $payment): ?Payment
    {
        if (! $payment || $payment->method !== 'gateway' || $payment->gateway !== 'midtrans' || $payment->status !== 'pending') {
            return $payment;
        }

        if (! filled($payment->external_payment_id)) {
            return $payment;
        }

        try {
            $payload = $this->midtrans()->transactionStatus((string) $payment->external_payment_id);
        } catch (\Throwable $e) {
            Log::warning('Midtrans status sync failed.', [
                'payment_id' => $payment->id,
                'order_id' => $payment->external_payment_id,
                'error' => $e->getMessage(),
            ]);

            return $payment;
        }

        return $this->applyMidtransStatusUpdate($payment, $payload, 'status_sync');
    }

    private function applyMidtransStatusUpdate(Payment $payment, array $payload, string $source): Payment
    {
        $incomingStatus = $this->midtrans()->mapTransactionStatus($payload);
        $nextStatus = $this->resolveIncomingStatus($payment->status, $incomingStatus);
        $becamePaid = $payment->status !== 'paid' && $nextStatus === 'paid';
        $metadata = is_array($payment->metadata) ? $payment->metadata : [];
        $notificationMetadata = $this->midtrans()->notificationMetadata($payload);

        $metadata['notification'] = $notificationMetadata;
        $metadata['gateway_details'] = array_filter(array_merge(
            (array) data_get($metadata, 'gateway_details', []),
            $notificationMetadata
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
            app(AppraisalFinalDocumentService::class)->generateAfterPayment($appraisal->fresh([
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
    private function replacePendingMidtransAttempt(Payment $payment): array
    {
        if (! $this->midtrans()->hasReusableSession($payment)) {
            return [
                'blocked' => false,
                'message' => 'Sesi aktif tidak ditemukan.',
            ];
        }

        $orderId = (string) $payment->external_payment_id;

        if ($orderId !== '') {
            try {
                $this->midtrans()->cancelTransaction($orderId);
            } catch (\Throwable $e) {
                Log::warning('Midtrans active payment replacement failed.', [
                    'payment_id' => $payment->id,
                    'order_id' => $orderId,
                    'error' => $e->getMessage(),
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

    private function midtrans(): MidtransSnapService
    {
        return app(MidtransSnapService::class);
    }

    private function billingService(): AppraisalBillingService
    {
        return app(AppraisalBillingService::class);
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
            } catch (\Throwable) {
                $targetUrl = null;
            }
        }

        app(AdminNotificationService::class)->notifyAdmins(
            'Pembayaran terkonfirmasi',
            $body,
            $targetUrl,
            'heroicon-o-banknotes',
        );
    }
}

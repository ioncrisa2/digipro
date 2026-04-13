<?php

namespace App\Http\Controllers\Customer;

use App\Enums\AppraisalStatusEnum;
use App\Http\Controllers\Controller;
use App\Http\Requests\Customer\CreateMidtransSessionRequest;
use App\Http\Requests\Customer\CustomerAccessRequest;
use App\Http\Requests\Customer\MidtransNotificationRequest;
use App\Models\AppraisalRequest;
use App\Models\Payment;
use App\Services\Payments\CustomerPaymentViewService;
use App\Services\Payments\CustomerPaymentWorkflowService;
use App\Services\Payments\MidtransSnapService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

/**
 * Handles Midtrans Snap payment pages, sessions, and invoice flows.
 */
class PaymentController extends Controller
{
    public function __construct(
        private readonly MidtransSnapService $midtrans,
        private readonly CustomerPaymentViewService $paymentViewService,
        private readonly CustomerPaymentWorkflowService $workflowService,
    ) {
    }

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
                'payments' => fn ($query) => $query->latest('id'),
                'user:id,name,email',
            ])
            ->latest('requested_at')
            ->get();

        $payments = $records->map(function (AppraisalRequest $record) {
            $payment = $record->payments->sortByDesc('id')->first();

            return $this->paymentViewService->buildIndexPaymentCard($record, $payment);
        })->values()->all();

        return inertia('Payments/Index', [
            'payments' => $payments,
        ]);
    }

    public function show(CustomerAccessRequest $request, int $id)
    {
        $record = $this->workflowService->resolveUserRequest($request, $id);
        $status = $record->status?->value ?? (string) $record->status;

        if (! $this->workflowService->isPaymentAccessibleStatus($status)) {
            return redirect()
                ->route('appraisal.show', ['id' => $record->id])
                ->with('error', 'Pembayaran belum dapat diakses pada status saat ini.');
        }

        $payment = $this->workflowService->latestPayment($record);

        if ($payment && $payment->status === 'paid') {
            return redirect()->route('appraisal.invoice.page', ['id' => $record->id]);
        }

        return redirect()->route('appraisal.payment.page', ['id' => $record->id]);
    }

    public function appraisalPage(CustomerAccessRequest $request, int $id)
    {
        $record = $this->workflowService->resolveUserRequest($request, $id);
        $status = $record->status?->value ?? (string) $record->status;

        if (! $this->workflowService->isPaymentAccessibleStatus($status)) {
            return redirect()
                ->route('appraisal.show', ['id' => $record->id])
                ->with('error', 'Pembayaran belum dapat diakses pada status saat ini.');
        }

        $payment = $this->workflowService->resolvePaymentForAppraisalPage($record);

        if ($payment && $payment->status === 'paid') {
            return redirect()->route('appraisal.invoice.page', ['id' => $record->id]);
        }

        $activeCheckout = $payment && $this->midtrans->hasReusableSession($payment);

        return inertia('Penilaian/Payment', [
            'request' => $this->paymentViewService->buildAppraisalRequestPayload($record, $payment),
            'payment' => $this->paymentViewService->buildPaymentPayload($record, $payment, $activeCheckout),
            'midtrans' => [
                'client_key' => $this->midtrans->clientKey(),
                'snap_script_url' => $this->midtrans->snapScriptUrl(),
                'create_session_url' => route('appraisal.payment.session', ['id' => $record->id]),
                'configured' => filled($this->midtrans->clientKey()) && filled($this->midtrans->merchantId()),
            ],
            'canStartCheckout' => $status === AppraisalStatusEnum::ContractSigned->value,
        ]);
    }

    public function createMidtransSession(CreateMidtransSessionRequest $request, int $id): JsonResponse
    {
        $record = $this->workflowService->resolveUserRequest($request, $id);
        $status = $record->status?->value ?? (string) $record->status;
        $forceNewAttempt = $request->forceNewAttempt();

        if ($status !== AppraisalStatusEnum::ContractSigned->value) {
            return response()->json([
                'message' => 'Session pembayaran hanya bisa dibuat saat kontrak sudah ditandatangani.',
            ], 422);
        }

        $latestPayment = $this->workflowService->latestPayment($record);

        if ($latestPayment?->status === 'paid') {
            return response()->json([
                'message' => 'Pembayaran sudah terverifikasi.',
                'redirect_url' => route('appraisal.invoice.page', ['id' => $record->id]),
            ], 409);
        }

        if ($latestPayment) {
            $this->workflowService->expireStaleGatewayPayment($latestPayment);
            $latestPayment = $latestPayment->fresh();
        }

        if ($latestPayment && $this->midtrans->hasReusableSession($latestPayment)) {
            if ($forceNewAttempt) {
                $replacementResult = $this->workflowService->replacePendingMidtransAttempt($latestPayment);
                if ($replacementResult['blocked']) {
                    return response()->json([
                        'message' => $replacementResult['message'],
                    ], 409);
                }

                $latestPayment = null;
            } else {
                return response()->json([
                    'message' => 'Session Midtrans aktif digunakan kembali.',
                    'payment' => $this->paymentViewService->buildPaymentPayload($record, $latestPayment, true),
                ]);
            }
        }

        $payment = $this->workflowService->createPendingMidtransPayment($record);

        try {
            $transaction = $this->midtrans->createTransaction($record->loadMissing('user'), $payment);
        } catch (\Throwable $exception) {
            $this->workflowService->markMidtransSessionCreationFailed($payment, $exception);

            return response()->json([
                'message' => 'Gagal membuat sesi pembayaran Midtrans.',
            ], 500);
        }

        $payment = $this->workflowService->finalizeMidtransSession($payment, $transaction);

        return response()->json([
            'message' => $forceNewAttempt
                ? 'Metode pembayaran diganti dan sesi Midtrans baru berhasil dibuat.'
                : 'Session pembayaran Midtrans berhasil dibuat.',
            'payment' => $this->paymentViewService->buildPaymentPayload($record, $payment, true),
        ]);
    }

    public function midtransNotification(MidtransNotificationRequest $request): JsonResponse
    {
        $payload = $request->payload();

        if (! $this->midtrans->verifyNotificationSignature($payload)) {
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

        $this->workflowService->applyMidtransStatusUpdate($payment, $payload, 'webhook');

        return response()->json([
            'message' => 'Notification diproses.',
        ]);
    }

    public function invoicePage(CustomerAccessRequest $request, int $id)
    {
        $record = $this->workflowService->resolveUserRequest($request, $id);
        $payment = $this->workflowService->resolvePaymentForInvoicePage($record);

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

        return inertia('Penilaian/Invoice', [
            'request' => $this->paymentViewService->buildInvoiceRequestPayload(
                $record,
                $request->user()->name ?? '-',
                $payment,
            ),
            'payment' => $this->paymentViewService->buildInvoicePaymentPayload($record, $payment),
        ]);
    }

    public function downloadInvoicePdf(CustomerAccessRequest $request, int $id)
    {
        $record = $this->workflowService->resolveUserRequest($request, $id);
        $payment = $this->workflowService->latestPayment($record);

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

        $invoice = $this->paymentViewService->buildInvoiceDocumentPayload(
            $record,
            $payment,
            $request->user()->name ?? '-',
        );

        $safeInvoiceNumber = preg_replace('/[^A-Za-z0-9\\-_.]/', '-', (string) $invoice['invoice_number']);
        $fileName = "Invoice-{$safeInvoiceNumber}.pdf";

        return Pdf::loadView('pdfs.appraisal-invoice', [
            'invoice' => $invoice,
        ])
            ->setPaper('a4', 'portrait')
            ->download($fileName);
    }
}

<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\BillingIndexRequest;
use App\Http\Requests\Admin\PaymentIndexRequest;
use App\Http\Requests\Admin\UpdateAppraisalBillingRequest;
use App\Http\Requests\Admin\UpdatePaymentRequest;
use App\Models\AppraisalRequest;
use App\Models\Payment;
use App\Services\Finance\AdminBillingUpdateService;
use App\Services\Finance\AdminBillingViewService;
use App\Services\Payments\AdminPaymentUpdateService;
use App\Services\Payments\AdminPaymentViewService;
use Illuminate\Http\RedirectResponse;
use Inertia\Response;

class FinanceController extends Controller
{
    public function __construct(
        private readonly AdminBillingViewService $billingViewService,
        private readonly AdminPaymentViewService $paymentViewService,
    ) {
    }

    public function billingsIndex(BillingIndexRequest $request): Response
    {
        $payload = $this->billingViewService->buildBillingsIndexPayload($request->filters(), $request->perPage());

        return inertia('Admin/Finance/Billings/Index', [
            'filters' => $payload['filters'],
            'statusOptions' => $payload['statusOptions'],
            'summary' => $payload['summary'],
            'records' => $this->paginatedRecordsPayload($payload['records']),
        ]);
    }

    public function billingsShow(AppraisalRequest $appraisalRequest): Response
    {
        return inertia('Admin/Finance/Billings/Show', $this->billingViewService->buildBillingShowPayload($appraisalRequest));
    }

    public function billingsEdit(AppraisalRequest $appraisalRequest): Response
    {
        return inertia('Admin/Finance/Billings/Edit', $this->billingViewService->buildBillingEditPayload($appraisalRequest));
    }

    public function billingsUpdate(
        UpdateAppraisalBillingRequest $request,
        AppraisalRequest $appraisalRequest,
        AdminBillingUpdateService $updateService,
    ): RedirectResponse
    {
        $updateService->update($appraisalRequest, $request->validated(), [
            'billing_invoice_file' => $request->file('billing_invoice_file'),
            'tax_invoice_file' => $request->file('tax_invoice_file'),
            'withholding_receipt_file' => $request->file('withholding_receipt_file'),
        ]);

        return redirect()
            ->route('admin.finance.billings.show', $appraisalRequest)
            ->with('success', 'Tagihan dan dokumen pajak berhasil diperbarui.');
    }

    public function taxInvoicesIndex(BillingIndexRequest $request): Response
    {
        $payload = $this->billingViewService->buildTaxInvoicesIndexPayload($request->filters(), $request->perPage());

        return inertia('Admin/Finance/TaxInvoices/Index', [
            'title' => $payload['title'],
            'description' => $payload['description'],
            'filters' => $payload['filters'],
            'records' => $this->paginatedRecordsPayload($payload['records']),
        ]);
    }

    public function withholdingReceiptsIndex(BillingIndexRequest $request): Response
    {
        $payload = $this->billingViewService->buildWithholdingReceiptsIndexPayload($request->filters(), $request->perPage());

        return inertia('Admin/Finance/WithholdingReceipts/Index', [
            'title' => $payload['title'],
            'description' => $payload['description'],
            'filters' => $payload['filters'],
            'records' => $this->paginatedRecordsPayload($payload['records']),
        ]);
    }

    public function paymentsIndex(PaymentIndexRequest $request): Response
    {
        $payload = $this->paymentViewService->buildIndexPayload($request->filters(), $request->perPage());

        return inertia('Admin/Payments/Index', [
            'filters' => $payload['filters'],
            'statusOptions' => $payload['statusOptions'],
            'summary' => $payload['summary'],
            'records' => $this->paginatedRecordsPayload($payload['records']),
        ]);
    }

    public function paymentsShow(Payment $payment): Response
    {
        return inertia('Admin/Payments/Show', $this->paymentViewService->buildShowPayload($payment));
    }

    public function paymentsEdit(Payment $payment): Response
    {
        return inertia('Admin/Payments/Edit', $this->paymentViewService->buildEditPayload($payment));
    }

    public function paymentsUpdate(
        UpdatePaymentRequest $request,
        Payment $payment,
        AdminPaymentUpdateService $updateService,
    ): RedirectResponse
    {
        abort_unless($this->paymentViewService->canEdit($payment), 403);
        $updateService->update($payment, $request->validated());

        return redirect()
            ->route('admin.finance.payments.show', $payment)
            ->with('success', 'Pembayaran berhasil diperbarui.');
    }

}

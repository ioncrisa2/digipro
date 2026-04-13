<?php

namespace App\Services\Finance;

use App\Enums\FinanceDocumentStatusEnum;
use App\Models\AppraisalRequest;
use App\Models\Payment;
use App\Services\Payments\MidtransSnapService;

class AdminBillingViewService
{
    public function __construct(
        private readonly AppraisalBillingService $billingService,
        private readonly MidtransSnapService $midtrans,
    ) {
    }

    public function buildBillingsIndexPayload(array $filters, int $perPage): array
    {
        $records = $this->billingBaseQuery($filters)
            ->latest('requested_at')
            ->paginate($perPage)
            ->withQueryString();

        $records->through(fn (AppraisalRequest $record) => $this->billingRow($record));

        return [
            'filters' => $filters,
            'statusOptions' => $this->billingService->financeDocumentStatusOptions(),
            'summary' => [
                'total' => AppraisalRequest::query()->count(),
                'draft' => AppraisalRequest::query()->where('finance_document_status', FinanceDocumentStatusEnum::Draft->value)->count(),
                'invoice_ready' => AppraisalRequest::query()->whereNotNull('billing_invoice_number')->count(),
                'complete' => AppraisalRequest::query()->where('finance_document_status', FinanceDocumentStatusEnum::Complete->value)->count(),
            ],
            'records' => $records,
        ];
    }

    public function buildBillingShowPayload(AppraisalRequest $appraisalRequest): array
    {
        $appraisalRequest->loadMissing(['user:id,name,email,phone_number']);
        $latestPayment = $appraisalRequest->payments()->latest('id')->first();

        return [
            'record' => $this->billingDetail($appraisalRequest, $latestPayment),
            'indexUrl' => route('admin.finance.billings.index'),
            'editUrl' => route('admin.finance.billings.edit', $appraisalRequest),
            'paymentsUrl' => route('admin.finance.payments.index'),
        ];
    }

    public function buildBillingEditPayload(AppraisalRequest $appraisalRequest): array
    {
        $appraisalRequest->loadMissing(['user:id,name,email,phone_number']);
        $latestPayment = $appraisalRequest->payments()->latest('id')->first();
        $summary = $this->billingService->summary($appraisalRequest, $latestPayment);

        return [
            'record' => array_merge($this->billingDetail($appraisalRequest, $latestPayment), [
                'finance_billing_name' => $appraisalRequest->finance_billing_name,
                'finance_billing_address' => $appraisalRequest->finance_billing_address,
                'finance_tax_identity_type' => $appraisalRequest->finance_tax_identity_type?->value ?? $appraisalRequest->finance_tax_identity_type,
                'finance_tax_identity_number' => $appraisalRequest->finance_tax_identity_number,
                'finance_billing_email' => $appraisalRequest->finance_billing_email,
                'billing_invoice_number' => $appraisalRequest->billing_invoice_number,
                'billing_invoice_date' => optional($appraisalRequest->billing_invoice_date)->toDateString(),
                'tax_invoice_number' => $appraisalRequest->tax_invoice_number,
                'tax_invoice_date' => optional($appraisalRequest->tax_invoice_date)->toDateString(),
                'withholding_receipt_number' => $appraisalRequest->withholding_receipt_number,
                'withholding_receipt_date' => optional($appraisalRequest->withholding_receipt_date)->toDateString(),
                'finance_document_status' => $appraisalRequest->finance_document_status?->value ?? $appraisalRequest->finance_document_status ?? FinanceDocumentStatusEnum::Draft->value,
                'billing_dpp_amount' => $summary['nilai_jasa_dpp'],
                'billing_withholding_tax_type' => $summary['jenis_pph_dipotong'],
            ]),
            'statusOptions' => $this->billingService->financeDocumentStatusOptions(),
            'withholdingTypeOptions' => $this->billingService->withholdingTaxTypeOptions(),
            'taxIdentityTypeOptions' => $this->billingService->taxIdentityTypeOptions(),
            'indexUrl' => route('admin.finance.billings.index'),
            'showUrl' => route('admin.finance.billings.show', $appraisalRequest),
        ];
    }

    public function buildTaxInvoicesIndexPayload(array $filters, int $perPage): array
    {
        $records = $this->billingBaseQuery($filters)
            ->whereNotNull('tax_invoice_number')
            ->latest('tax_invoice_date')
            ->paginate($perPage)
            ->withQueryString();

        $records->through(fn (AppraisalRequest $record) => $this->billingRow($record));

        return [
            'title' => 'Faktur Pajak',
            'description' => 'Daftar faktur pajak yang sudah diinput admin finance.',
            'filters' => $filters,
            'records' => $records,
        ];
    }

    public function buildWithholdingReceiptsIndexPayload(array $filters, int $perPage): array
    {
        $records = $this->billingBaseQuery($filters)
            ->whereNotNull('withholding_receipt_number')
            ->latest('withholding_receipt_date')
            ->paginate($perPage)
            ->withQueryString();

        $records->through(fn (AppraisalRequest $record) => $this->billingRow($record));

        return [
            'title' => 'Bukti Potong',
            'description' => 'Daftar bukti potong PPh 23 yang tercatat pada tiap pekerjaan.',
            'filters' => $filters,
            'records' => $records,
        ];
    }

    private function billingBaseQuery(array $filters)
    {
        return AppraisalRequest::query()
            ->with(['user:id,name,email', 'payments' => fn ($query) => $query->latest('id')])
            ->when(($filters['q'] ?? '') !== '', function ($query) use ($filters): void {
                $query->where(function ($innerQuery) use ($filters): void {
                    $innerQuery
                        ->where('request_number', 'like', '%' . $filters['q'] . '%')
                        ->orWhere('client_name', 'like', '%' . $filters['q'] . '%')
                        ->orWhere('billing_invoice_number', 'like', '%' . $filters['q'] . '%')
                        ->orWhere('tax_invoice_number', 'like', '%' . $filters['q'] . '%')
                        ->orWhere('withholding_receipt_number', 'like', '%' . $filters['q'] . '%')
                        ->orWhereHas('user', fn ($userQuery) => $userQuery->where('name', 'like', '%' . $filters['q'] . '%'));
                });
            })
            ->when(($filters['status'] ?? 'all') !== 'all', fn ($query) => $query->where('finance_document_status', $filters['status']))
            ->when(($filters['doc'] ?? 'all') !== 'all', function ($query) use ($filters): void {
                match ($filters['doc']) {
                    'invoice' => $query->whereNotNull('billing_invoice_number'),
                    'tax' => $query->whereNotNull('tax_invoice_number'),
                    'withholding' => $query->whereNotNull('withholding_receipt_number'),
                    'missing' => $query->where(function ($missingQuery): void {
                        $missingQuery
                            ->whereNull('billing_invoice_number')
                            ->orWhereNull('tax_invoice_number')
                            ->orWhereNull('withholding_receipt_number');
                    }),
                    default => null,
                };
            });
    }

    private function billingRow(AppraisalRequest $record): array
    {
        $payment = $record->payments->sortByDesc('id')->first();
        $summary = $this->billingService->summary($record, $payment);

        return [
            'id' => $record->id,
            'request_number' => $record->request_number ?? ('REQ-' . $record->id),
            'customer_name' => $record->client_name ?: ($record->user?->name ?? '-'),
            'requester_name' => $record->user?->name ?? '-',
            'status_dokumen_keuangan' => $summary['status_dokumen_keuangan'],
            'status_dokumen_keuangan_label' => $summary['status_dokumen_keuangan_label'],
            'nilai_jasa_dpp' => $summary['nilai_jasa_dpp'],
            'nilai_ppn' => $summary['nilai_ppn'],
            'total_tagihan' => $summary['total_tagihan'],
            'nilai_pph_dipotong' => $summary['nilai_pph_dipotong'],
            'total_transfer_customer' => $summary['total_transfer_customer'],
            'nomor_invoice' => $summary['nomor_invoice'],
            'nomor_faktur_pajak' => $summary['nomor_faktur_pajak'],
            'nomor_bukti_potong' => $summary['nomor_bukti_potong'],
            'show_url' => route('admin.finance.billings.show', $record),
            'edit_url' => route('admin.finance.billings.edit', $record),
            'payment_show_url' => $payment ? route('admin.finance.payments.show', $payment) : null,
            'updated_at' => $record->updated_at?->toIso8601String(),
        ];
    }

    private function billingDetail(AppraisalRequest $record, ?Payment $payment): array
    {
        $summary = $this->billingService->summary($record, $payment);

        return [
            'id' => $record->id,
            'request_number' => $record->request_number ?? ('REQ-' . $record->id),
            'customer_name' => $record->client_name ?: ($record->user?->name ?? '-'),
            'requester_name' => $record->user?->name ?? '-',
            'requester_email' => $record->user?->email ?? '-',
            'requester_phone' => $record->user?->phone_number ?? '-',
            'requested_at' => $record->requested_at?->toIso8601String(),
            'ringkasan_tagihan' => $summary,
            'payment' => $payment ? [
                'id' => $payment->id,
                'amount' => (int) $payment->amount,
                'status' => $payment->status,
                'status_label' => $this->midtrans->paymentStatusLabel($payment),
                'paid_at' => $payment->paid_at?->toIso8601String(),
                'show_url' => route('admin.finance.payments.show', $payment),
            ] : null,
            'request_show_url' => route('admin.appraisal-requests.show', $record),
        ];
    }
}

<?php

namespace App\Http\Controllers\Admin;

use App\Enums\FinanceDocumentStatusEnum;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\BillingIndexRequest;
use App\Http\Requests\Admin\PaymentIndexRequest;
use App\Http\Requests\Admin\UpdateAppraisalBillingRequest;
use App\Http\Requests\Admin\UpdatePaymentRequest;
use App\Models\AppraisalRequest;
use App\Models\Payment;
use App\Services\Finance\AppraisalBillingService;
use App\Services\Payments\MidtransSnapService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Response;

class FinanceController extends Controller
{
    public function __construct(
        private readonly AppraisalBillingService $billingService,
    ) {
    }

    public function billingsIndex(BillingIndexRequest $request): Response
    {
        $filters = $request->filters();
        $records = $this->billingBaseQuery($filters)
            ->latest('requested_at')
            ->paginate($request->perPage())
            ->withQueryString();

        $records->through(fn (AppraisalRequest $record) => $this->transformBillingRow($record));

        return inertia('Admin/Finance/Billings/Index', [
            'filters' => $filters,
            'statusOptions' => $this->billingService->financeDocumentStatusOptions(),
            'summary' => [
                'total' => AppraisalRequest::query()->count(),
                'draft' => AppraisalRequest::query()->where('finance_document_status', FinanceDocumentStatusEnum::Draft->value)->count(),
                'invoice_ready' => AppraisalRequest::query()->whereNotNull('billing_invoice_number')->count(),
                'complete' => AppraisalRequest::query()->where('finance_document_status', FinanceDocumentStatusEnum::Complete->value)->count(),
            ],
            'records' => $this->paginatedRecordsPayload($records),
        ]);
    }

    public function billingsShow(AppraisalRequest $appraisalRequest): Response
    {
        $appraisalRequest->loadMissing(['user:id,name,email,phone_number']);
        $latestPayment = $appraisalRequest->payments()->latest('id')->first();

        return inertia('Admin/Finance/Billings/Show', [
            'record' => $this->transformBillingDetail($appraisalRequest, $latestPayment),
            'indexUrl' => route('admin.finance.billings.index'),
            'editUrl' => route('admin.finance.billings.edit', $appraisalRequest),
            'paymentsUrl' => route('admin.finance.payments.index'),
        ]);
    }

    public function billingsEdit(AppraisalRequest $appraisalRequest): Response
    {
        $appraisalRequest->loadMissing(['user:id,name,email,phone_number']);
        $latestPayment = $appraisalRequest->payments()->latest('id')->first();
        $summary = $this->billingService->summary($appraisalRequest, $latestPayment);

        return inertia('Admin/Finance/Billings/Edit', [
            'record' => array_merge($this->transformBillingDetail($appraisalRequest, $latestPayment), [
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
        ]);
    }

    public function billingsUpdate(UpdateAppraisalBillingRequest $request, AppraisalRequest $appraisalRequest): RedirectResponse
    {
        $validated = $request->validated();
        $billingAttributes = $this->billingService->appraisalAttributesFromDpp(
            (int) $validated['billing_dpp_amount'],
            $appraisalRequest->user,
            [
                'billing_withholding_tax_type' => $validated['billing_withholding_tax_type'],
            ]
        );

        $payload = array_merge($billingAttributes, [
            'finance_billing_name' => $validated['finance_billing_name'],
            'finance_billing_address' => $validated['finance_billing_address'],
            'finance_tax_identity_type' => $validated['finance_tax_identity_type'] ?? null,
            'finance_tax_identity_number' => $validated['finance_tax_identity_number'] ?? null,
            'finance_billing_email' => $validated['finance_billing_email'] ?? null,
            'billing_invoice_number' => $validated['billing_invoice_number'] ?? null,
            'billing_invoice_date' => $validated['billing_invoice_date'] ?? null,
            'tax_invoice_number' => $validated['tax_invoice_number'] ?? null,
            'tax_invoice_date' => $validated['tax_invoice_date'] ?? null,
            'withholding_receipt_number' => $validated['withholding_receipt_number'] ?? null,
            'withholding_receipt_date' => $validated['withholding_receipt_date'] ?? null,
            'finance_document_status' => $validated['finance_document_status'],
        ]);

        if ($request->hasFile('billing_invoice_file')) {
            $payload['billing_invoice_file_path'] = $request->file('billing_invoice_file')
                ->store("finance/{$appraisalRequest->id}/invoice", 'public');
        }

        if ($request->hasFile('tax_invoice_file')) {
            $payload['tax_invoice_file_path'] = $request->file('tax_invoice_file')
                ->store("finance/{$appraisalRequest->id}/faktur-pajak", 'public');
        }

        if ($request->hasFile('withholding_receipt_file')) {
            $payload['withholding_receipt_file_path'] = $request->file('withholding_receipt_file')
                ->store("finance/{$appraisalRequest->id}/bukti-potong", 'public');
        }

        $appraisalRequest->update($payload);

        return redirect()
            ->route('admin.finance.billings.show', $appraisalRequest)
            ->with('success', 'Tagihan dan dokumen pajak berhasil diperbarui.');
    }

    public function taxInvoicesIndex(BillingIndexRequest $request): Response
    {
        $filters = $request->filters();
        $records = $this->billingBaseQuery($filters)
            ->whereNotNull('tax_invoice_number')
            ->latest('tax_invoice_date')
            ->paginate($request->perPage())
            ->withQueryString();

        $records->through(fn (AppraisalRequest $record) => $this->transformBillingRow($record));

        return inertia('Admin/Finance/TaxInvoices/Index', [
            'title' => 'Faktur Pajak',
            'description' => 'Daftar faktur pajak yang sudah diinput admin finance.',
            'filters' => $filters,
            'records' => $this->paginatedRecordsPayload($records),
        ]);
    }

    public function withholdingReceiptsIndex(BillingIndexRequest $request): Response
    {
        $filters = $request->filters();
        $records = $this->billingBaseQuery($filters)
            ->whereNotNull('withholding_receipt_number')
            ->latest('withholding_receipt_date')
            ->paginate($request->perPage())
            ->withQueryString();

        $records->through(fn (AppraisalRequest $record) => $this->transformBillingRow($record));

        return inertia('Admin/Finance/WithholdingReceipts/Index', [
            'title' => 'Bukti Potong',
            'description' => 'Daftar bukti potong PPh 23 yang tercatat pada tiap pekerjaan.',
            'filters' => $filters,
            'records' => $this->paginatedRecordsPayload($records),
        ]);
    }

    public function paymentsIndex(PaymentIndexRequest $request, MidtransSnapService $midtrans): Response
    {
        $filters = $request->filters();

        $records = Payment::query()
            ->with(['appraisalRequest.user'])
            ->when($filters['q'] !== '', function ($query) use ($filters): void {
                $query->where(function ($innerQuery) use ($filters): void {
                    $innerQuery
                        ->where('external_payment_id', 'like', '%' . $filters['q'] . '%')
                        ->orWhereHas('appraisalRequest', function ($requestQuery) use ($filters): void {
                            $requestQuery
                                ->where('request_number', 'like', '%' . $filters['q'] . '%')
                                ->orWhere('client_name', 'like', '%' . $filters['q'] . '%')
                                ->orWhere('billing_invoice_number', 'like', '%' . $filters['q'] . '%')
                                ->orWhere('tax_invoice_number', 'like', '%' . $filters['q'] . '%')
                                ->orWhere('withholding_receipt_number', 'like', '%' . $filters['q'] . '%')
                                ->orWhereHas('user', fn ($userQuery) => $userQuery->where('name', 'like', '%' . $filters['q'] . '%'));
                        });
                });
            })
            ->when($filters['status'] !== 'all', fn ($query) => $query->where('status', $filters['status']))
            ->latest('created_at')
            ->paginate($request->perPage())
            ->withQueryString();

        $records->through(fn (Payment $payment) => $this->transformPaymentRow($payment, $midtrans));

        return inertia('Admin/Payments/Index', [
            'filters' => $filters,
            'statusOptions' => [
                ['value' => 'all', 'label' => 'Semua Status'],
                ['value' => 'pending', 'label' => 'Menunggu'],
                ['value' => 'paid', 'label' => 'Dibayar'],
                ['value' => 'failed', 'label' => 'Gagal'],
                ['value' => 'expired', 'label' => 'Kedaluwarsa'],
                ['value' => 'rejected', 'label' => 'Ditolak'],
                ['value' => 'refunded', 'label' => 'Refund'],
            ],
            'summary' => [
                'total' => Payment::query()->count(),
                'pending' => Payment::query()->where('status', 'pending')->count(),
                'paid' => Payment::query()->where('status', 'paid')->count(),
                'exceptions' => Payment::query()->whereIn('status', ['failed', 'expired', 'rejected', 'refunded'])->count(),
            ],
            'records' => $this->paginatedRecordsPayload($records),
        ]);
    }

    public function paymentsShow(Payment $payment, MidtransSnapService $midtrans): Response
    {
        $payment->loadMissing(['appraisalRequest.user']);
        $requestRecord = $payment->appraisalRequest;
        $gatewayDetails = $midtrans->gatewayDetailsFromMetadata(is_array($payment->metadata) ? $payment->metadata : []);
        $proofFileUrl = filled($payment->proof_file_path) && Storage::disk('public')->exists($payment->proof_file_path)
            ? Storage::disk('public')->url($payment->proof_file_path)
            : null;

        return inertia('Admin/Payments/Show', [
            'record' => [
                'id' => $payment->id,
                'invoice_number' => $this->paymentInvoiceNumber($payment, $requestRecord),
                'amount' => (int) $payment->amount,
                'method' => $payment->method,
                'method_label' => $midtrans->paymentMethodLabel($payment),
                'status' => $payment->status,
                'status_label' => $midtrans->paymentStatusLabel($payment),
                'gateway' => $payment->gateway,
                'external_payment_id' => $payment->external_payment_id,
                'paid_at' => $payment->paid_at?->toIso8601String(),
                'proof_original_name' => $payment->proof_original_name,
                'proof_mime' => $payment->proof_mime,
                'proof_size' => (int) ($payment->proof_size ?? 0),
                'proof_size_label' => $this->formatBytes($payment->proof_size),
                'proof_type' => $payment->proof_type,
                'proof_url' => $proofFileUrl,
                'metadata_lines' => $this->paymentMetadataLines($payment->metadata),
                'metadata_json' => $this->formatPaymentMetadataJson($payment->metadata),
                'request_number' => $requestRecord?->request_number ?? ('REQ-' . $payment->appraisal_request_id),
                'requester_name' => $requestRecord?->user?->name ?? '-',
                'client_name' => $requestRecord?->client_name ?: ($requestRecord?->user?->name ?? '-'),
                'request_show_url' => $requestRecord ? route('admin.appraisal-requests.show', $requestRecord) : null,
                'can_edit' => $this->canEditPayment($payment),
                'edit_url' => $this->canEditPayment($payment) ? route('admin.finance.payments.edit', $payment) : null,
                'created_at' => $payment->created_at?->toIso8601String(),
                'updated_at' => $payment->updated_at?->toIso8601String(),
                'ringkasan_tagihan' => $requestRecord ? $this->billingService->summary($requestRecord, $payment) : null,
            ],
            'gatewayDetails' => $gatewayDetails,
            'indexUrl' => route('admin.finance.payments.index'),
        ]);
    }

    public function paymentsEdit(Payment $payment): Response
    {
        abort_unless($this->canEditPayment($payment), 403);

        $payment->loadMissing(['appraisalRequest.user']);
        $requestRecord = $payment->appraisalRequest;

        return inertia('Admin/Payments/Edit', [
            'record' => [
                'id' => $payment->id,
                'invoice_number' => $this->paymentInvoiceNumber($payment, $requestRecord),
                'method' => $payment->method,
                'method_label' => $payment->method === 'gateway' ? 'Midtrans Gateway' : 'Gateway Legacy',
                'amount' => (int) $payment->amount,
                'status' => $payment->status,
                'gateway' => $payment->gateway,
                'external_payment_id' => $payment->external_payment_id,
                'paid_at' => $payment->paid_at?->format('Y-m-d\\TH:i'),
                'metadata_json' => $this->formatPaymentMetadataJson($payment->metadata),
                'request_number' => $requestRecord?->request_number ?? ('REQ-' . $payment->appraisal_request_id),
                'requester_name' => $requestRecord?->user?->name ?? '-',
                'client_name' => $requestRecord?->client_name ?: ($requestRecord?->user?->name ?? '-'),
                'show_url' => route('admin.finance.payments.show', $payment),
                'request_show_url' => $requestRecord ? route('admin.appraisal-requests.show', $requestRecord) : null,
                'ringkasan_tagihan' => $requestRecord ? $this->billingService->summary($requestRecord, $payment) : null,
            ],
            'statusOptions' => [
                ['value' => 'pending', 'label' => 'Menunggu'],
                ['value' => 'paid', 'label' => 'Dibayar'],
                ['value' => 'failed', 'label' => 'Gagal'],
                ['value' => 'expired', 'label' => 'Kedaluwarsa'],
                ['value' => 'rejected', 'label' => 'Ditolak'],
                ['value' => 'refunded', 'label' => 'Refund'],
            ],
            'indexUrl' => route('admin.finance.payments.index'),
        ]);
    }

    public function paymentsUpdate(UpdatePaymentRequest $request, Payment $payment): RedirectResponse
    {
        abort_unless($this->canEditPayment($payment), 403);

        $validated = $request->validated();

        $payment->forceFill([
            'amount' => (int) $validated['amount'],
            'status' => $validated['status'],
            'gateway' => $validated['gateway'] ?: ($payment->gateway ?: 'midtrans'),
            'external_payment_id' => $validated['external_payment_id'] ?: null,
            'paid_at' => $validated['paid_at'] ?? null,
            'metadata' => $this->decodePaymentMetadata($validated['metadata_json'] ?? null),
        ])->save();

        return redirect()
            ->route('admin.finance.payments.show', $payment)
            ->with('success', 'Pembayaran berhasil diperbarui.');
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

    private function transformBillingRow(AppraisalRequest $record): array
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

    private function transformBillingDetail(AppraisalRequest $record, ?Payment $payment): array
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
                'status_label' => app(MidtransSnapService::class)->paymentStatusLabel($payment),
                'paid_at' => $payment->paid_at?->toIso8601String(),
                'show_url' => route('admin.finance.payments.show', $payment),
            ] : null,
            'request_show_url' => route('admin.appraisal-requests.show', $record),
        ];
    }

    private function transformPaymentRow(Payment $payment, MidtransSnapService $midtrans): array
    {
        $payment->loadMissing(['appraisalRequest.user']);
        $requestRecord = $payment->appraisalRequest;
        $gatewayDetails = $midtrans->gatewayDetailsFromMetadata(is_array($payment->metadata) ? $payment->metadata : []);
        $billingSummary = $requestRecord ? $this->billingService->summary($requestRecord, $payment) : null;

        return [
            'id' => $payment->id,
            'invoice_number' => $this->paymentInvoiceNumber($payment, $requestRecord),
            'request_number' => $requestRecord?->request_number ?? ('REQ-' . $payment->appraisal_request_id),
            'client_name' => $requestRecord?->client_name ?: ($requestRecord?->user?->name ?? '-'),
            'requester_name' => $requestRecord?->user?->name ?? '-',
            'amount' => (int) $payment->amount,
            'method' => $payment->method,
            'method_label' => $midtrans->paymentMethodLabel($payment),
            'status' => $payment->status,
            'status_label' => $midtrans->paymentStatusLabel($payment),
            'gateway' => $payment->gateway,
            'bank_label' => $gatewayDetails['bank'] ?? $gatewayDetails['label'] ?? '-',
            'reference' => $gatewayDetails['reference'] ?? null,
            'external_payment_id' => $payment->external_payment_id,
            'paid_at' => $payment->paid_at?->toIso8601String(),
            'updated_at' => $payment->updated_at?->toIso8601String(),
            'show_url' => route('admin.finance.payments.show', $payment),
            'edit_url' => $this->canEditPayment($payment) ? route('admin.finance.payments.edit', $payment) : null,
            'request_show_url' => $requestRecord ? route('admin.appraisal-requests.show', $requestRecord) : null,
            'ringkasan_tagihan' => $billingSummary,
        ];
    }

    private function canEditPayment(Payment $payment): bool
    {
        return in_array($payment->status, ['pending', 'failed', 'expired', 'rejected', 'refunded'], true);
    }

    private function paymentInvoiceNumber(Payment $payment, ?AppraisalRequest $requestRecord = null): string
    {
        if ($requestRecord) {
            return $this->billingService->invoiceNumber($requestRecord, $payment);
        }

        $invoice = data_get($payment->metadata, 'invoice_number');

        if (filled($invoice)) {
            return (string) $invoice;
        }

        return 'INV-' . now()->format('Y') . '-' . str_pad((string) $payment->id, 5, '0', STR_PAD_LEFT);
    }

    /**
     * @return array<int, string>
     */
    private function paymentMetadataLines(mixed $metadata): array
    {
        if (! is_array($metadata) || empty($metadata)) {
            return ['-'];
        }

        $lines = [];
        $flatten = function (array $data, string $prefix = '') use (&$flatten, &$lines): void {
            foreach ($data as $key => $value) {
                $path = $prefix === '' ? (string) $key : "{$prefix}.{$key}";

                if (is_array($value)) {
                    $flatten($value, $path);
                    continue;
                }

                $label = ucwords(str_replace(['.', '_'], [' > ', ' '], $path));
                $text = match (true) {
                    $value === null => '-',
                    is_bool($value) => $value ? 'Ya' : 'Tidak',
                    default => (string) $value,
                };

                $lines[] = "{$label}: {$text}";
            }
        };

        $flatten($metadata);

        return empty($lines) ? ['-'] : $lines;
    }

    private function formatPaymentMetadataJson(mixed $metadata): string
    {
        if (! is_array($metadata) || empty($metadata)) {
            return '';
        }

        return (string) json_encode($metadata, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }

    private function decodePaymentMetadata(?string $metadata): ?array
    {
        if (blank($metadata)) {
            return null;
        }

        $decoded = json_decode((string) $metadata, true);

        return is_array($decoded) ? $decoded : null;
    }

    private function formatBytes(mixed $bytes): string
    {
        if (! is_numeric($bytes) || (float) $bytes <= 0) {
            return '0 B';
        }

        $number = (float) $bytes;
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $index = (int) floor(log($number, 1024));
        $index = min($index, count($units) - 1);
        $value = $number / (1024 ** $index);

        return sprintf('%s %s', number_format($value, $index === 0 ? 0 : 2), $units[$index]);
    }
}

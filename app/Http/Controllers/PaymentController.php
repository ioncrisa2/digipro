<?php

namespace App\Http\Controllers;

use App\Enums\AppraisalStatusEnum;
use App\Models\AppraisalRequest;
use App\Models\OfficeBankAccount;
use App\Models\Payment;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

/**
 * Handles user payment pages: upload proof and invoice/receipt.
 */
class PaymentController extends Controller
{
    public function index(Request $request)
    {
        $records = AppraisalRequest::query()
            ->where('user_id', $request->user()->id)
            ->whereIn('status', [
                AppraisalStatusEnum::ContractSigned->value,
                AppraisalStatusEnum::ValuationOnProgress->value,
                AppraisalStatusEnum::ValuationCompleted->value,
                AppraisalStatusEnum::ReportReady->value,
                AppraisalStatusEnum::Completed->value,
            ])
            ->with([
                'payments' => fn ($q) => $q->latest('id'),
            ])
            ->latest('requested_at')
            ->get();

        $payments = $records->map(function (AppraisalRequest $record) {
            $payment = $record->payments->sortByDesc('id')->first();
            $invoiceNumber = $this->resolveInvoiceNumber($record, $payment);
            $statusLabel = $this->paymentStatusLabel($payment);
            $amount = (int) ($payment?->amount ?? $record->fee_total ?? 0);
            $selectedBankName = data_get($payment?->metadata, 'selected_bank_account.bank_name', '-');
            $selectedBankAccount = data_get($payment?->metadata, 'selected_bank_account.account_number', '-');
            $proofName = $payment?->proof_original_name;

            return [
                'id' => $record->id,
                'invoice_number' => $invoiceNumber,
                'request_number' => $record->request_number ?? ('REQ-' . $record->id),
                'client' => $record->client_name ?: ($record->user?->name ?? '-'),
                'amount' => $this->formatIDR($amount),
                'status' => $statusLabel,
                'is_paid' => $payment?->status === 'paid',
                'invoice_pdf_url' => route('appraisal.invoice.pdf', ['id' => $record->id]),
                'due_date' => optional(($payment?->created_at ?? $record->requested_at ?? now())->copy()->addDays(3))->toDateString(),
                'method' => 'Transfer Bank',
                'bank' => $selectedBankName ?: '-',
                'va' => $selectedBankAccount ?: '-',
                'updated_at' => optional($payment?->updated_at ?? $record->updated_at)->toDateString(),
                'documents' => array_values(array_filter([
                    [
                        'label' => 'Invoice Pembayaran',
                        'name' => "{$invoiceNumber}.pdf",
                        'type' => 'invoice',
                        'size' => '-',
                    ],
                    $proofName ? [
                        'label' => 'Bukti Pembayaran',
                        'name' => $proofName,
                        'type' => 'receipt',
                        'size' => $payment?->proof_size ? $this->formatBytes((int) $payment->proof_size) : '-',
                    ] : null,
                ])),
            ];
        })->values()->all();

        return inertia('Payments/Index', [
            'payments' => $payments,
        ]);
    }

    public function show(Request $request, int $id)
    {
        $record = $this->resolveUserRequest($request, $id);
        $status = $record->status?->value ?? (string) $record->status;

        if (! $this->isPaymentAccessibleStatus($status)) {
            return redirect()
                ->route('appraisal.show', ['id' => $record->id])
                ->with('error', 'Pembayaran belum dapat diakses pada status saat ini.');
        }

        $payment = Payment::query()
            ->where('appraisal_request_id', $record->id)
            ->latest('id')
            ->first();

        if ($payment && $payment->status === 'paid') {
            return redirect()->route('appraisal.invoice.page', ['id' => $record->id]);
        }

        return redirect()->route('appraisal.payment.page', ['id' => $record->id]);
    }

    public function appraisalPage(Request $request, int $id)
    {
        $record = $this->resolveUserRequest($request, $id);
        $status = $record->status?->value ?? (string) $record->status;

        if (! $this->isPaymentAccessibleStatus($status)) {
            return redirect()
                ->route('appraisal.show', ['id' => $record->id])
                ->with('error', 'Pembayaran belum dapat diakses pada status saat ini.');
        }

        $payment = $this->ensurePaymentRecord($record);

        if ($payment->status === 'paid') {
            return redirect()
                ->route('appraisal.invoice.page', ['id' => $record->id]);
        }

        $accounts = OfficeBankAccount::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get(['id', 'bank_name', 'account_number', 'account_holder', 'branch', 'currency', 'notes']);

        $selectedBankAccount = null;
        $selectedBankId = data_get($payment->metadata, 'selected_bank_account.id');
        if ($selectedBankId) {
            $selectedBankAccount = $accounts->firstWhere('id', (int) $selectedBankId);
        }

        $proofUrl = null;
        if ($payment->proof_file_path && Storage::disk('public')->exists($payment->proof_file_path)) {
            $proofUrl = Storage::disk('public')->url($payment->proof_file_path);
        }

        $paymentStatusLabel = match ($payment->status) {
            'paid' => 'Dibayar',
            'failed' => 'Gagal',
            'rejected' => 'Ditolak',
            'refunded' => 'Refund',
            default => 'Menunggu Verifikasi',
        };

        $canUploadProof = in_array($status, [
            AppraisalStatusEnum::ContractSigned->value,
            AppraisalStatusEnum::ValuationOnProgress->value,
            AppraisalStatusEnum::ValuationCompleted->value,
            AppraisalStatusEnum::ReportReady->value,
            AppraisalStatusEnum::Completed->value,
        ], true) && $payment->status !== 'paid';

        return inertia('Penilaian/Payment', [
            'request' => [
                'id' => $record->id,
                'request_number' => $record->request_number ?? ('REQ-' . $record->id),
                'contract_number' => $record->contract_number,
                'status' => $status,
                'status_label' => $record->status?->label() ?? $status,
                'fee_total' => (int) ($record->fee_total ?? 0),
                'invoice_number' => $this->resolveInvoiceNumber($record, $payment),
            ],
            'payment' => [
                'id' => $payment->id,
                'status' => $payment->status,
                'status_label' => $paymentStatusLabel,
                'amount' => (int) ($payment->amount ?? 0),
                'method' => $payment->method,
                'paid_at' => optional($payment->paid_at)->toDateTimeString(),
                'proof_file_path' => $payment->proof_file_path,
                'proof_original_name' => $payment->proof_original_name,
                'proof_size' => $payment->proof_size,
                'proof_uploaded_at' => optional($payment->updated_at)->toDateTimeString(),
                'proof_url' => $proofUrl,
                'invoice_number' => $this->resolveInvoiceNumber($record, $payment),
                'rejected_reason' => data_get($payment->metadata, 'admin_rejected_reason'),
                'selected_bank_account' => $selectedBankAccount ? [
                    'id' => $selectedBankAccount->id,
                    'bank_name' => $selectedBankAccount->bank_name,
                    'account_number' => $selectedBankAccount->account_number,
                    'account_holder' => $selectedBankAccount->account_holder,
                ] : null,
                'metadata' => $payment->metadata,
            ],
            'bankAccounts' => $accounts
                ->map(fn (OfficeBankAccount $account) => [
                    'id' => $account->id,
                    'bank_name' => $account->bank_name,
                    'account_number' => $account->account_number,
                    'account_holder' => $account->account_holder,
                    'branch' => $account->branch,
                    'currency' => $account->currency,
                    'notes' => $account->notes,
                ])
                ->values()
                ->all(),
            'canUploadProof' => $canUploadProof,
        ]);
    }

    public function invoicePage(Request $request, int $id)
    {
        $record = $this->resolveUserRequest($request, $id);
        $payment = Payment::query()
            ->where('appraisal_request_id', $record->id)
            ->latest('id')
            ->first();

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

        $invoiceNumber = $this->resolveInvoiceNumber($record, $payment);
        $proofUrl = null;
        if ($payment->proof_file_path && Storage::disk('public')->exists($payment->proof_file_path)) {
            $proofUrl = Storage::disk('public')->url($payment->proof_file_path);
        }

        $selectedBank = data_get($payment->metadata, 'selected_bank_account');

        return inertia('Penilaian/Invoice', [
            'request' => [
                'id' => $record->id,
                'request_number' => $record->request_number ?? ('REQ-' . $record->id),
                'contract_number' => $record->contract_number,
                'status' => $record->status?->value ?? (string) $record->status,
                'status_label' => $record->status?->label() ?? (string) $record->status,
                'fee_total' => (int) ($record->fee_total ?? 0),
                'client_name' => $record->client_name ?: ($request->user()->name ?? '-'),
            ],
            'payment' => [
                'id' => $payment->id,
                'invoice_number' => $invoiceNumber,
                'status' => $payment->status,
                'status_label' => 'Dibayar',
                'amount' => (int) ($payment->amount ?? 0),
                'method' => $payment->method,
                'paid_at' => optional($payment->paid_at)->toDateTimeString(),
                'proof_file_path' => $payment->proof_file_path,
                'proof_original_name' => $payment->proof_original_name,
                'proof_size' => $payment->proof_size,
                'proof_url' => $proofUrl,
                'selected_bank_account' => is_array($selectedBank) ? [
                    'bank_name' => data_get($selectedBank, 'bank_name'),
                    'account_number' => data_get($selectedBank, 'account_number'),
                    'account_holder' => data_get($selectedBank, 'account_holder'),
                ] : null,
                'metadata' => $payment->metadata,
            ],
        ]);
    }

    public function downloadInvoicePdf(Request $request, int $id)
    {
        $record = $this->resolveUserRequest($request, $id);
        $payment = Payment::query()
            ->where('appraisal_request_id', $record->id)
            ->latest('id')
            ->first();

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

        $invoiceNumber = $this->resolveInvoiceNumber($record, $payment);
        $selectedBank = data_get($payment->metadata, 'selected_bank_account');

        $invoice = [
            'invoice_number' => $invoiceNumber,
            'request_number' => $record->request_number ?? ('REQ-' . $record->id),
            'contract_number' => $record->contract_number ?: '-',
            'issued_at' => optional($payment->paid_at ?? $payment->updated_at)->toDateTimeString(),
            'client_name' => $record->client_name ?: ($request->user()->name ?? '-'),
            'amount' => (int) ($payment->amount ?? $record->fee_total ?? 0),
            'method' => $payment->method ?: 'manual',
            'status_label' => 'LUNAS',
            'payment_status' => $payment->status,
            'selected_bank_account' => is_array($selectedBank) ? [
                'bank_name' => data_get($selectedBank, 'bank_name'),
                'account_number' => data_get($selectedBank, 'account_number'),
                'account_holder' => data_get($selectedBank, 'account_holder'),
            ] : null,
            'company_name' => config('app.name', 'DigiPro'),
        ];

        $safeInvoiceNumber = preg_replace('/[^A-Za-z0-9\-_.]/', '-', (string) $invoiceNumber);
        $fileName = "Invoice-{$safeInvoiceNumber}.pdf";

        return Pdf::loadView('pdfs.appraisal-invoice', [
            'invoice' => $invoice,
        ])
            ->setPaper('a4', 'portrait')
            ->download($fileName);
    }

    public function uploadProof(Request $request, int $id)
    {
        $record = $this->resolveUserRequest($request, $id);
        $status = $record->status?->value ?? (string) $record->status;

        if (! $this->isPaymentAccessibleStatus($status)) {
            return redirect()
                ->route('appraisal.payment.page', ['id' => $record->id])
                ->with('error', 'Bukti pembayaran hanya dapat diunggah setelah kontrak ditandatangani.');
        }

        $data = $request->validate([
            'office_bank_account_id' => [
                'required',
                'integer',
                Rule::exists('office_bank_accounts', 'id')->where(fn ($q) => $q->where('is_active', true)),
            ],
            'proof_file' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:15360'],
            'transfer_note' => ['nullable', 'string', 'max:1000'],
            'transfer_date' => ['nullable', 'date'],
            'transfer_amount' => ['nullable', 'integer', 'min:0'],
        ], [
            'office_bank_account_id.required' => 'Pilih rekening tujuan transfer terlebih dahulu.',
            'office_bank_account_id.exists' => 'Rekening tujuan tidak valid.',
            'proof_file.required' => 'Bukti pembayaran wajib diunggah.',
            'proof_file.mimes' => 'Format bukti pembayaran harus PDF/JPG/JPEG/PNG.',
            'proof_file.max' => 'Ukuran bukti pembayaran maksimal 15 MB.',
        ]);

        $payment = $this->ensurePaymentRecord($record);

        if ($payment->status === 'paid') {
            return redirect()
                ->route('appraisal.invoice.page', ['id' => $record->id])
                ->with('info', 'Pembayaran sudah terverifikasi. Unggah ulang bukti tidak diperlukan.');
        }

        if ($payment->proof_file_path && Storage::disk('public')->exists($payment->proof_file_path)) {
            Storage::disk('public')->delete($payment->proof_file_path);
        }

        $file = $data['proof_file'];
        $storedPath = $file->store("payment-proofs/{$record->id}", 'public');

        $selectedBank = OfficeBankAccount::query()->findOrFail((int) $data['office_bank_account_id']);
        $metadata = array_merge((array) $payment->metadata, [
            'selected_bank_account' => [
                'id' => $selectedBank->id,
                'bank_name' => $selectedBank->bank_name,
                'account_number' => $selectedBank->account_number,
                'account_holder' => $selectedBank->account_holder,
            ],
            'transfer_note' => $data['transfer_note'] ?? null,
            'transfer_date' => $data['transfer_date'] ?? null,
            'transfer_amount' => $data['transfer_amount'] ?? null,
            'submitted_at' => now()->toDateTimeString(),
            'submitted_by_user_id' => $request->user()->id,
        ]);

        $payment->update([
            'amount' => (int) ($record->fee_total ?? $payment->amount ?? 0),
            'method' => 'manual',
            'status' => 'pending',
            'proof_file_path' => $storedPath,
            'proof_original_name' => $file->getClientOriginalName(),
            'proof_mime' => $file->getMimeType(),
            'proof_size' => $file->getSize(),
            'proof_type' => 'upload',
            'metadata' => $metadata,
            'paid_at' => null,
        ]);

        return redirect()
            ->route('appraisal.payment.page', ['id' => $record->id])
            ->with('success', 'Bukti pembayaran berhasil diunggah. Menunggu verifikasi admin.');
    }

    private function ensurePaymentRecord(AppraisalRequest $record): Payment
    {
        $payment = Payment::query()
            ->where('appraisal_request_id', $record->id)
            ->latest('id')
            ->first();

        if (! $payment) {
            $payment = Payment::create([
                'appraisal_request_id' => $record->id,
                'amount' => (int) ($record->fee_total ?? 0),
                'method' => 'manual',
                'status' => 'pending',
                'metadata' => [
                    'source' => 'appraisal_payment_page',
                    'created_at' => now()->toDateTimeString(),
                ],
            ]);
        }

        $metadata = is_array($payment->metadata) ? $payment->metadata : [];
        if (! filled(data_get($metadata, 'invoice_number'))) {
            $metadata['invoice_number'] = $this->buildInvoiceNumber($record, $payment);
            $payment->update(['metadata' => $metadata]);
        }

        if ((int) ($payment->amount ?? 0) <= 0 && (int) ($record->fee_total ?? 0) > 0) {
            $payment->update([
                'amount' => (int) $record->fee_total,
            ]);
        }

        return $payment->refresh();
    }

    private function isPaymentAccessibleStatus(string $status): bool
    {
        return in_array($status, [
            AppraisalStatusEnum::WaitingSignature->value,
            AppraisalStatusEnum::ContractSigned->value,
            AppraisalStatusEnum::ValuationOnProgress->value,
            AppraisalStatusEnum::ValuationCompleted->value,
            AppraisalStatusEnum::ReportReady->value,
            AppraisalStatusEnum::Completed->value,
        ], true);
    }

    private function paymentStatusLabel(?Payment $payment): string
    {
        if (! $payment) {
            return 'Menunggu Pembayaran';
        }

        return match ($payment->status) {
            'paid' => 'Dibayar',
            'failed' => 'Gagal',
            'rejected' => 'Ditolak',
            'refunded' => 'Refund',
            default => filled($payment->proof_file_path)
                ? 'Menunggu Verifikasi'
                : 'Menunggu Pembayaran',
        };
    }

    private function resolveInvoiceNumber(AppraisalRequest $record, ?Payment $payment): string
    {
        $invoice = data_get($payment?->metadata, 'invoice_number');
        if (filled($invoice)) {
            return (string) $invoice;
        }

        return $this->buildInvoiceNumber($record, $payment);
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
}

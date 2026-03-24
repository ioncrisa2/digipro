<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreOfficeBankAccountRequest;
use App\Http\Requests\Admin\UpdatePaymentRequest;
use App\Models\OfficeBankAccount;
use App\Models\Payment;
use App\Services\Payments\MidtransSnapService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Response;

class FinanceController extends Controller
{
    public function paymentsIndex(Request $request, MidtransSnapService $midtrans): Response
    {
        $filters = [
            'q' => trim((string) $request->query('q', '')),
            'status' => (string) $request->query('status', 'all'),
            'method' => (string) $request->query('method', 'all'),
            'per_page' => (string) $this->adminPerPage($request),
        ];

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
                                ->orWhereHas('user', fn ($userQuery) => $userQuery->where('name', 'like', '%' . $filters['q'] . '%'));
                        });
                });
            })
            ->when($filters['status'] !== 'all', fn ($query) => $query->where('status', $filters['status']))
            ->when($filters['method'] !== 'all', fn ($query) => $query->where('method', $filters['method']))
            ->latest('created_at')
            ->paginate($this->adminPerPage($request))
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
            'methodOptions' => [
                ['value' => 'all', 'label' => 'Semua Metode'],
                ['value' => 'gateway', 'label' => 'Gateway / Midtrans'],
                ['value' => 'manual', 'label' => 'Legacy Manual'],
            ],
            'summary' => [
                'total' => Payment::query()->count(),
                'pending' => Payment::query()->where('status', 'pending')->count(),
                'paid' => Payment::query()->where('status', 'paid')->count(),
                'active_bank_accounts' => OfficeBankAccount::query()->where('is_active', true)->count(),
            ],
            'records' => $this->paginatedRecordsPayload($records),
            'officeBankAccountsUrl' => route('admin.finance.office-bank-accounts.index'),
        ]);
    }

    public function paymentsShow(Payment $payment, MidtransSnapService $midtrans): Response
    {
        $payment->loadMissing(['appraisalRequest.user']);

        $gatewayDetails = $midtrans->gatewayDetailsFromMetadata(is_array($payment->metadata) ? $payment->metadata : []);
        $proofFileUrl = filled($payment->proof_file_path) && Storage::disk('public')->exists($payment->proof_file_path)
            ? Storage::disk('public')->url($payment->proof_file_path)
            : null;

        return inertia('Admin/Payments/Show', [
            'record' => [
                'id' => $payment->id,
                'invoice_number' => $this->paymentInvoiceNumber($payment),
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
                'request_number' => $payment->appraisalRequest?->request_number ?? ('REQ-' . $payment->appraisal_request_id),
                'requester_name' => $payment->appraisalRequest?->user?->name ?? '-',
                'client_name' => $payment->appraisalRequest?->client_name ?: ($payment->appraisalRequest?->user?->name ?? '-'),
                'request_show_url' => $payment->appraisalRequest
                    ? route('admin.appraisal-requests.show', $payment->appraisalRequest)
                    : null,
                'created_at' => $payment->created_at?->toIso8601String(),
                'updated_at' => $payment->updated_at?->toIso8601String(),
            ],
            'gatewayDetails' => $gatewayDetails,
            'officeBankAccountsUrl' => route('admin.finance.office-bank-accounts.index'),
            'indexUrl' => route('admin.finance.payments.index'),
        ]);
    }

    public function paymentsEdit(Payment $payment): Response
    {
        $payment->loadMissing(['appraisalRequest.user']);

        return inertia('Admin/Payments/Edit', [
            'record' => [
                'id' => $payment->id,
                'invoice_number' => $this->paymentInvoiceNumber($payment),
                'method' => $payment->method,
                'method_label' => $payment->method === 'gateway' ? 'Midtrans Gateway' : 'Legacy Manual',
                'amount' => (int) $payment->amount,
                'status' => $payment->status,
                'gateway' => $payment->gateway,
                'external_payment_id' => $payment->external_payment_id,
                'paid_at' => $payment->paid_at?->format('Y-m-d\\TH:i'),
                'metadata_json' => $this->formatPaymentMetadataJson($payment->metadata),
                'request_number' => $payment->appraisalRequest?->request_number ?? ('REQ-' . $payment->appraisal_request_id),
                'requester_name' => $payment->appraisalRequest?->user?->name ?? '-',
                'client_name' => $payment->appraisalRequest?->client_name ?: ($payment->appraisalRequest?->user?->name ?? '-'),
                'show_url' => route('admin.finance.payments.show', $payment),
                'request_show_url' => $payment->appraisalRequest
                    ? route('admin.appraisal-requests.show', $payment->appraisalRequest)
                    : null,
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
        $validated = $request->validated();

        $payment->forceFill([
            'amount' => (int) $validated['amount'],
            'status' => $validated['status'],
            'gateway' => $validated['gateway'] ?: 'midtrans',
            'external_payment_id' => $validated['external_payment_id'] ?: null,
            'paid_at' => $validated['paid_at'] ?? null,
            'metadata' => $this->decodePaymentMetadata($validated['metadata_json'] ?? null),
        ])->save();

        return redirect()
            ->route('admin.finance.payments.show', $payment)
            ->with('success', 'Pembayaran berhasil diperbarui.');
    }

    public function officeBankAccountsIndex(Request $request): Response
    {
        $filters = [
            'q' => trim((string) $request->query('q', '')),
            'status' => (string) $request->query('status', 'all'),
        ];

        $records = OfficeBankAccount::query()
            ->when($filters['q'] !== '', function ($query) use ($filters): void {
                $query->where(function ($innerQuery) use ($filters): void {
                    $innerQuery
                        ->where('bank_name', 'like', '%' . $filters['q'] . '%')
                        ->orWhere('account_number', 'like', '%' . $filters['q'] . '%')
                        ->orWhere('account_holder', 'like', '%' . $filters['q'] . '%');
                });
            })
            ->when($filters['status'] === 'active', fn ($query) => $query->where('is_active', true))
            ->when($filters['status'] === 'inactive', fn ($query) => $query->where('is_active', false))
            ->orderBy('sort_order')
            ->orderBy('bank_name')
            ->get()
            ->map(fn (OfficeBankAccount $account) => $this->transformOfficeBankAccountRow($account))
            ->values();

        return inertia('Admin/OfficeBankAccounts/Index', [
            'filters' => $filters,
            'statusOptions' => [
                ['value' => 'all', 'label' => 'Semua Status'],
                ['value' => 'active', 'label' => 'Aktif'],
                ['value' => 'inactive', 'label' => 'Nonaktif'],
            ],
            'summary' => [
                'total' => OfficeBankAccount::query()->count(),
                'active' => OfficeBankAccount::query()->where('is_active', true)->count(),
                'inactive' => OfficeBankAccount::query()->where('is_active', false)->count(),
            ],
            'records' => $records,
            'createUrl' => route('admin.finance.office-bank-accounts.create'),
            'paymentsUrl' => route('admin.finance.payments.index'),
        ]);
    }

    public function officeBankAccountsCreate(): Response
    {
        return inertia('Admin/OfficeBankAccounts/Form', [
            'mode' => 'create',
            'record' => [
                'bank_name' => '',
                'account_number' => '',
                'account_holder' => '',
                'branch' => '',
                'currency' => 'IDR',
                'notes' => '',
                'is_active' => true,
                'sort_order' => 0,
            ],
            'indexUrl' => route('admin.finance.office-bank-accounts.index'),
            'submitUrl' => route('admin.finance.office-bank-accounts.store'),
        ]);
    }

    public function officeBankAccountsStore(StoreOfficeBankAccountRequest $request): RedirectResponse
    {
        OfficeBankAccount::query()->create($request->validated());

        return redirect()
            ->route('admin.finance.office-bank-accounts.index')
            ->with('success', 'Rekening kantor berhasil ditambahkan.');
    }

    public function officeBankAccountsEdit(OfficeBankAccount $officeBankAccount): Response
    {
        return inertia('Admin/OfficeBankAccounts/Form', [
            'mode' => 'edit',
            'record' => [
                'id' => $officeBankAccount->id,
                'bank_name' => $officeBankAccount->bank_name,
                'account_number' => $officeBankAccount->account_number,
                'account_holder' => $officeBankAccount->account_holder,
                'branch' => $officeBankAccount->branch,
                'currency' => $officeBankAccount->currency,
                'notes' => $officeBankAccount->notes,
                'is_active' => (bool) $officeBankAccount->is_active,
                'sort_order' => (int) $officeBankAccount->sort_order,
            ],
            'indexUrl' => route('admin.finance.office-bank-accounts.index'),
            'submitUrl' => route('admin.finance.office-bank-accounts.update', $officeBankAccount),
        ]);
    }

    public function officeBankAccountsUpdate(
        StoreOfficeBankAccountRequest $request,
        OfficeBankAccount $officeBankAccount
    ): RedirectResponse {
        $officeBankAccount->update($request->validated());

        return redirect()
            ->route('admin.finance.office-bank-accounts.index')
            ->with('success', 'Rekening kantor berhasil diperbarui.');
    }

    public function officeBankAccountsDestroy(OfficeBankAccount $officeBankAccount): RedirectResponse
    {
        $officeBankAccount->delete();

        return redirect()
            ->route('admin.finance.office-bank-accounts.index')
            ->with('success', 'Rekening kantor berhasil dihapus.');
    }

    private function transformPaymentRow(Payment $payment, MidtransSnapService $midtrans): array
    {
        $payment->loadMissing(['appraisalRequest.user']);
        $requestRecord = $payment->appraisalRequest;
        $gatewayDetails = $midtrans->gatewayDetailsFromMetadata(is_array($payment->metadata) ? $payment->metadata : []);

        return [
            'id' => $payment->id,
            'invoice_number' => $this->paymentInvoiceNumber($payment),
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
            'edit_url' => route('admin.finance.payments.edit', $payment),
            'request_show_url' => $requestRecord ? route('admin.appraisal-requests.show', $requestRecord) : null,
        ];
    }

    private function transformOfficeBankAccountRow(OfficeBankAccount $account): array
    {
        return [
            'id' => $account->id,
            'bank_name' => $account->bank_name,
            'account_number' => $account->account_number,
            'account_holder' => $account->account_holder,
            'branch' => $account->branch,
            'currency' => $account->currency,
            'is_active' => (bool) $account->is_active,
            'sort_order' => (int) $account->sort_order,
            'notes' => $account->notes,
            'updated_at' => $account->updated_at?->toIso8601String(),
            'edit_url' => route('admin.finance.office-bank-accounts.edit', $account),
            'destroy_url' => route('admin.finance.office-bank-accounts.destroy', $account),
        ];
    }

    private function paymentInvoiceNumber(Payment $payment): string
    {
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

    private function legacyPaymentUrl(Payment $payment): ?string
    {
        return null;
    }

    private function legacyOfficeBankAccountUrl(OfficeBankAccount $account): ?string
    {
        return null;
    }

    protected function paginatedRecordsPayload(object $records): array
    {
        return [
            'data' => $records->items(),
            'meta' => [
                'from' => $records->firstItem(),
                'to' => $records->lastItem(),
                'total' => $records->total(),
                'current_page' => $records->currentPage(),
                'last_page' => $records->lastPage(),
                'per_page' => $records->perPage(),
                'links' => $records->linkCollection()->toArray(),
            ],
        ];
    }
}

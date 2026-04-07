<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\PaymentIndexRequest;
use App\Http\Requests\Admin\UpdatePaymentRequest;
use App\Models\Payment;
use App\Services\Payments\MidtransSnapService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Response;

class FinanceController extends Controller
{
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
                'metadata_json' => $this->formatPaymentMetadataJson($payment->metadata),
                'request_number' => $payment->appraisalRequest?->request_number ?? ('REQ-' . $payment->appraisal_request_id),
                'requester_name' => $payment->appraisalRequest?->user?->name ?? '-',
                'client_name' => $payment->appraisalRequest?->client_name ?: ($payment->appraisalRequest?->user?->name ?? '-'),
                'request_show_url' => $payment->appraisalRequest
                    ? route('admin.appraisal-requests.show', $payment->appraisalRequest)
                    : null,
                'can_edit' => $this->canEditPayment($payment),
                'edit_url' => $this->canEditPayment($payment)
                    ? route('admin.finance.payments.edit', $payment)
                    : null,
                'created_at' => $payment->created_at?->toIso8601String(),
                'updated_at' => $payment->updated_at?->toIso8601String(),
            ],
            'gatewayDetails' => $gatewayDetails,
            'indexUrl' => route('admin.finance.payments.index'),
        ]);
    }

    public function paymentsEdit(Payment $payment): Response
    {
        abort_unless($this->canEditPayment($payment), 403);

        $payment->loadMissing(['appraisalRequest.user']);

        return inertia('Admin/Payments/Edit', [
            'record' => [
                'id' => $payment->id,
                'invoice_number' => $this->paymentInvoiceNumber($payment),
                'method' => $payment->method,
                'method_label' => $payment->method === 'gateway' ? 'Midtrans Gateway' : 'Gateway Legacy',
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
            'edit_url' => $this->canEditPayment($payment)
                ? route('admin.finance.payments.edit', $payment)
                : null,
            'request_show_url' => $requestRecord ? route('admin.appraisal-requests.show', $requestRecord) : null,
        ];
    }

    private function canEditPayment(Payment $payment): bool
    {
        return in_array($payment->status, ['pending', 'failed', 'expired', 'rejected', 'refunded'], true);
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
}

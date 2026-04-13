<?php

namespace App\Services\Payments;

use App\Models\AppraisalRequest;
use App\Models\Payment;
use App\Services\Finance\AppraisalBillingService;
use Illuminate\Support\Facades\Storage;

class AdminPaymentViewService
{
    public function __construct(
        private readonly MidtransSnapService $midtrans,
        private readonly AppraisalBillingService $billingService,
    ) {
    }

    public function buildIndexPayload(array $filters, int $perPage): array
    {
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
            ->paginate($perPage)
            ->withQueryString();

        $records->through(fn (Payment $payment) => $this->paymentRow($payment));

        return [
            'filters' => $filters,
            'statusOptions' => $this->statusOptions(includeAll: true),
            'summary' => [
                'total' => Payment::query()->count(),
                'pending' => Payment::query()->where('status', 'pending')->count(),
                'paid' => Payment::query()->where('status', 'paid')->count(),
                'exceptions' => Payment::query()->whereIn('status', ['failed', 'expired', 'rejected', 'refunded'])->count(),
            ],
            'records' => $records,
        ];
    }

    public function buildShowPayload(Payment $payment): array
    {
        $payment->loadMissing(['appraisalRequest.user']);
        $requestRecord = $payment->appraisalRequest;
        $proofFileUrl = filled($payment->proof_file_path) && Storage::disk('public')->exists($payment->proof_file_path)
            ? Storage::disk('public')->url($payment->proof_file_path)
            : null;

        return [
            'record' => [
                'id' => $payment->id,
                'invoice_number' => $this->invoiceNumber($payment, $requestRecord),
                'amount' => (int) $payment->amount,
                'method' => $payment->method,
                'method_label' => $this->midtrans->paymentMethodLabel($payment),
                'status' => $payment->status,
                'status_label' => $this->midtrans->paymentStatusLabel($payment),
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
                'can_edit' => $this->canEdit($payment),
                'edit_url' => $this->canEdit($payment) ? route('admin.finance.payments.edit', $payment) : null,
                'created_at' => $payment->created_at?->toIso8601String(),
                'updated_at' => $payment->updated_at?->toIso8601String(),
                'ringkasan_tagihan' => $requestRecord ? $this->billingService->summary($requestRecord, $payment) : null,
            ],
            'gatewayDetails' => $this->midtrans->gatewayDetailsFromMetadata(is_array($payment->metadata) ? $payment->metadata : []),
            'indexUrl' => route('admin.finance.payments.index'),
        ];
    }

    public function buildEditPayload(Payment $payment): array
    {
        abort_unless($this->canEdit($payment), 403);

        $payment->loadMissing(['appraisalRequest.user']);
        $requestRecord = $payment->appraisalRequest;

        return [
            'record' => [
                'id' => $payment->id,
                'invoice_number' => $this->invoiceNumber($payment, $requestRecord),
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
            'statusOptions' => $this->statusOptions(),
            'indexUrl' => route('admin.finance.payments.index'),
        ];
    }

    public function canEdit(Payment $payment): bool
    {
        return in_array($payment->status, ['pending', 'failed', 'expired', 'rejected', 'refunded'], true);
    }

    public function paymentStatusLabel(Payment $payment): string
    {
        return $this->midtrans->paymentStatusLabel($payment);
    }

    private function paymentRow(Payment $payment): array
    {
        $payment->loadMissing(['appraisalRequest.user']);
        $requestRecord = $payment->appraisalRequest;
        $gatewayDetails = $this->midtrans->gatewayDetailsFromMetadata(is_array($payment->metadata) ? $payment->metadata : []);
        $billingSummary = $requestRecord ? $this->billingService->summary($requestRecord, $payment) : null;

        return [
            'id' => $payment->id,
            'invoice_number' => $this->invoiceNumber($payment, $requestRecord),
            'request_number' => $requestRecord?->request_number ?? ('REQ-' . $payment->appraisal_request_id),
            'client_name' => $requestRecord?->client_name ?: ($requestRecord?->user?->name ?? '-'),
            'requester_name' => $requestRecord?->user?->name ?? '-',
            'amount' => (int) $payment->amount,
            'method' => $payment->method,
            'method_label' => $this->midtrans->paymentMethodLabel($payment),
            'status' => $payment->status,
            'status_label' => $this->midtrans->paymentStatusLabel($payment),
            'gateway' => $payment->gateway,
            'bank_label' => $gatewayDetails['bank'] ?? $gatewayDetails['label'] ?? '-',
            'reference' => $gatewayDetails['reference'] ?? null,
            'external_payment_id' => $payment->external_payment_id,
            'paid_at' => $payment->paid_at?->toIso8601String(),
            'updated_at' => $payment->updated_at?->toIso8601String(),
            'show_url' => route('admin.finance.payments.show', $payment),
            'edit_url' => $this->canEdit($payment) ? route('admin.finance.payments.edit', $payment) : null,
            'request_show_url' => $requestRecord ? route('admin.appraisal-requests.show', $requestRecord) : null,
            'ringkasan_tagihan' => $billingSummary,
        ];
    }

    private function invoiceNumber(Payment $payment, ?AppraisalRequest $requestRecord = null): string
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
     * @return array<int, array{value: string, label: string}>
     */
    private function statusOptions(bool $includeAll = false): array
    {
        $options = [
            ['value' => 'pending', 'label' => 'Menunggu'],
            ['value' => 'paid', 'label' => 'Dibayar'],
            ['value' => 'failed', 'label' => 'Gagal'],
            ['value' => 'expired', 'label' => 'Kedaluwarsa'],
            ['value' => 'rejected', 'label' => 'Ditolak'],
            ['value' => 'refunded', 'label' => 'Refund'],
        ];

        if (! $includeAll) {
            return $options;
        }

        return [
            ['value' => 'all', 'label' => 'Semua Status'],
            ...$options,
        ];
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

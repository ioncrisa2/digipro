<?php

namespace App\Services\Customer\Payloads;

use App\Enums\AppraisalStatusEnum;
use App\Models\AppraisalRequest;

class AppraisalStatusTimelineBuilder
{
    public function __construct(
        private readonly AppraisalPayloadFormatter $formatter,
    ) {
    }

    public function build(AppraisalRequest $record): array
    {
        $entries = [];
        $requestNumber = $record->request_number ?? ('REQ-' . $record->id);

        $append = function (
            string $key,
            string $title,
            string $description,
            mixed $at,
            string $type = 'default'
        ) use (&$entries): void {
            $time = $this->formatter->timelineDateTimeString($at);

            if ($time === null) {
                return;
            }

            $entries[] = [
                'key' => $key,
                'title' => $title,
                'description' => $description,
                'at' => $time,
                'type' => $type,
            ];
        };

        $append(
            'request_submitted',
            'Permohonan Dikirim',
            "Permohonan {$requestNumber} berhasil dikirim.",
            $record->requested_at ?? $record->created_at,
            'submitted'
        );

        $latestCancellationRequest = $record->latestCancellationRequest;

        if ($latestCancellationRequest) {
            $append(
                'cancellation_request_submitted',
                'Pengajuan Pembatalan Dikirim',
                'Customer mengajukan pembatalan dan menunggu review admin.',
                $latestCancellationRequest->created_at,
                'warning'
            );

            if ($latestCancellationRequest->contacted_at) {
                $append(
                    'cancellation_request_contacted',
                    'Admin Sedang Menghubungi Customer',
                    'Admin menindaklanjuti pengajuan pembatalan melalui kontak customer yang tersedia.',
                    $latestCancellationRequest->contacted_at,
                    'info'
                );
            }

            if ($latestCancellationRequest->review_status === 'rejected') {
                $description = 'Pengajuan pembatalan ditolak dan permohonan dikembalikan ke status sebelumnya.';

                if (filled($latestCancellationRequest->review_note)) {
                    $description .= ' Catatan admin: ' . $latestCancellationRequest->review_note;
                }

                $append(
                    'cancellation_request_rejected',
                    'Pengajuan Pembatalan Ditolak',
                    $description,
                    $latestCancellationRequest->reviewed_at ?? $latestCancellationRequest->updated_at,
                    'danger'
                );
            }
        }

        if ($record->verified_at) {
            $append(
                'docs_verified',
                'Dokumen Diverifikasi',
                'Dokumen awal telah diverifikasi oleh admin.',
                $record->verified_at,
                'success'
            );
        }

        $record->offerNegotiations
            ->sortBy('created_at')
            ->each(function ($item) use ($append): void {
                $action = (string) $item->action;
                $offeredFee = is_numeric($item->offered_fee) ? $this->formatter->formatRupiah((int) $item->offered_fee) : null;
                $expectedFee = is_numeric($item->expected_fee) ? $this->formatter->formatRupiah((int) $item->expected_fee) : null;

                if ($action === 'offer_sent') {
                    $append(
                        "offer_sent_{$item->id}",
                        'Penawaran Dikirim',
                        $offeredFee ? "Admin mengirim penawaran fee {$offeredFee}." : 'Admin mengirim penawaran.',
                        $item->created_at,
                        'offer'
                    );
                    return;
                }

                if ($action === 'offer_revised') {
                    $append(
                        "offer_revised_{$item->id}",
                        'Penawaran Direvisi',
                        $offeredFee ? "Admin mengirim revisi penawaran fee {$offeredFee}." : 'Admin mengirim revisi penawaran.',
                        $item->created_at,
                        'offer'
                    );
                    return;
                }

                if ($action === 'counter_request') {
                    $append(
                        "counter_request_{$item->id}",
                        'Negosiasi Diajukan',
                        $expectedFee
                            ? "Anda mengajukan negosiasi fee {$expectedFee}."
                            : 'Anda mengajukan negosiasi penawaran.',
                        $item->created_at,
                        'warning'
                    );
                    return;
                }

                if ($action === 'accept_offer') {
                    $selectedFee = is_numeric($item->selected_fee)
                        ? $this->formatter->formatRupiah((int) $item->selected_fee)
                        : $offeredFee;

                    $append(
                        "accept_offer_{$item->id}",
                        'Penawaran Disetujui',
                        $selectedFee ? "Anda menyetujui penawaran fee {$selectedFee}." : 'Anda menyetujui penawaran.',
                        $item->created_at,
                        'success'
                    );
                    return;
                }

                if ($action === 'accepted') {
                    $selectedFee = is_numeric($item->selected_fee)
                        ? $this->formatter->formatRupiah((int) $item->selected_fee)
                        : $expectedFee;

                    $append(
                        "accepted_{$item->id}",
                        'Negosiasi Disetujui Admin',
                        $selectedFee
                            ? "Admin menyetujui fee hasil negosiasi sebesar {$selectedFee} dan request masuk ke tahap tanda tangan kontrak."
                            : 'Admin menyetujui hasil negosiasi dan request masuk ke tahap tanda tangan kontrak.',
                        $item->created_at,
                        'success'
                    );
                    return;
                }

                if ($action === 'contract_sign_peruri_customer') {
                    $append(
                        "contract_signed_customer_{$item->id}",
                        'Kontrak Ditandatangani',
                        'Anda menandatangani kontrak secara digital melalui Peruri SIGN-IT. Menunggu tanda tangan Penilai Publik.',
                        $item->created_at,
                        'success'
                    );
                    return;
                }

                if ($action === 'contract_sign_peruri_public_appraiser') {
                    $append(
                        "contract_signed_public_appraiser_{$item->id}",
                        'Kontrak Disahkan Penilai Publik',
                        'Kontrak telah ditandatangani Penilai Publik. PDF final siap diunduh.',
                        $item->created_at,
                        'success'
                    );
                    return;
                }

                if ($action === 'cancel_request') {
                    $append(
                        "request_cancelled_{$item->id}",
                        'Permohonan Dibatalkan',
                        'Permohonan dibatalkan oleh user.',
                        $item->created_at,
                        'danger'
                    );
                    return;
                }

                if ($action === 'cancelled' && data_get($item->meta, 'flow') === 'admin_request_cancelled') {
                    $description = 'Permohonan dibatalkan oleh sistem.';

                    if (filled($item->reason)) {
                        $description .= ' Alasan: ' . $item->reason;
                    }

                    $append(
                        "admin_request_cancelled_{$item->id}",
                        'Permohonan Dibatalkan Sistem',
                        $description,
                        $item->created_at,
                        'danger'
                    );
                }
            });

        $latestPayment = $record->payments->sortByDesc('id')->first();

        if ($latestPayment) {
            if ($latestPayment->method === 'gateway' && $latestPayment->status === 'pending') {
                $append(
                    'payment_session_created',
                    'Sesi Pembayaran Dibuat',
                    'Session pembayaran Midtrans sudah dibuat dan menunggu penyelesaian pembayaran.',
                    data_get($latestPayment->metadata, 'checkout.created_at') ?? $latestPayment->created_at,
                    'payment'
                );
            }

            if ($latestPayment->status === 'paid') {
                $append(
                    'payment_verified',
                    'Pembayaran Terkonfirmasi',
                    'Pembayaran berhasil dikonfirmasi Midtrans. Proses penilaian dimulai.',
                    $latestPayment->paid_at ?? $latestPayment->updated_at,
                    'success'
                );
            }

            if ($latestPayment->status === 'rejected') {
                $append(
                    'payment_rejected',
                    'Pembayaran Ditolak',
                    'Transaksi pembayaran ditolak oleh gateway atau tidak dapat diproses.',
                    data_get($latestPayment->metadata, 'admin_rejected_at') ?? $latestPayment->updated_at,
                    'danger'
                );
            }

            if ($latestPayment->status === 'failed') {
                $append(
                    'payment_failed',
                    'Pembayaran Gagal',
                    'Transaksi pembayaran tidak berhasil diproses. Anda dapat membuat sesi pembayaran baru.',
                    data_get($latestPayment->metadata, 'last_webhook_received_at') ?? $latestPayment->updated_at,
                    'danger'
                );
            }

            if ($latestPayment->status === 'expired') {
                $append(
                    'payment_expired',
                    'Pembayaran Kedaluwarsa',
                    'Sesi pembayaran telah kedaluwarsa. Anda dapat membuat sesi pembayaran baru.',
                    data_get($latestPayment->metadata, 'last_expired_at')
                        ?? data_get($latestPayment->metadata, 'gateway_details.expiry_time')
                        ?? $latestPayment->updated_at,
                    'warning'
                );
            }
        }

        if ($record->billing_invoice_date) {
            $invoiceNumber = filled($record->billing_invoice_number)
                ? ' Nomor invoice: ' . $record->billing_invoice_number . '.'
                : '';

            $append(
                'billing_invoice_issued',
                'Invoice Tagihan Terbit',
                'Admin finance menerbitkan invoice tagihan untuk pekerjaan ini.' . $invoiceNumber,
                $record->billing_invoice_date,
                'info'
            );
        }

        if ($record->tax_invoice_date) {
            $taxInvoiceNumber = filled($record->tax_invoice_number)
                ? ' Nomor faktur: ' . $record->tax_invoice_number . '.'
                : '';

            $append(
                'tax_invoice_recorded',
                'Faktur Pajak Terinput',
                'Admin finance mencatat faktur pajak untuk transaksi ini.' . $taxInvoiceNumber,
                $record->tax_invoice_date,
                'info'
            );
        }

        if ($record->withholding_receipt_date) {
            $receiptNumber = filled($record->withholding_receipt_number)
                ? ' Nomor bukti potong: ' . $record->withholding_receipt_number . '.'
                : '';

            $append(
                'withholding_receipt_recorded',
                'Bukti Potong Terinput',
                'Admin finance mencatat bukti potong PPh untuk transaksi ini.' . $receiptNumber,
                $record->withholding_receipt_date,
                'info'
            );
        }

        if ($record->report_generated_at) {
            $append(
                'report_ready',
                'Laporan Siap',
                'Laporan kajian pasar sudah tersedia untuk diunduh.',
                $record->report_generated_at,
                'success'
            );
        }

        if ($record->physical_report_printed_at) {
            $description = 'Laporan fisik selesai dicetak dan masuk antrean pengiriman.';

            if ($record->physicalReportPrintedBy?->name) {
                $description = 'Laporan fisik selesai dicetak oleh ' . $record->physicalReportPrintedBy->name . ' dan masuk antrean pengiriman.';
            }

            $append(
                'physical_report_printed',
                'Hard Copy Dicetak',
                $description,
                $record->physical_report_printed_at,
                'info'
            );
        }

        if ($record->physical_report_shipped_at) {
            $courier = filled($record->physical_report_courier) ? $record->physical_report_courier : 'kurir';
            $tracking = filled($record->physical_report_tracking_number)
                ? ' Nomor resi: ' . $record->physical_report_tracking_number . '.'
                : '';

            $append(
                'physical_report_shipped',
                'Hard Copy Dikirim',
                'Laporan fisik dikirim melalui ' . $courier . '.' . $tracking,
                $record->physical_report_shipped_at,
                'info'
            );
        }

        if ($record->physical_report_delivered_at) {
            $append(
                'physical_report_delivered',
                'Hard Copy Diterima',
                'Laporan fisik ditandai sudah diterima oleh customer.',
                $record->physical_report_delivered_at,
                'success'
            );
        }

        if ($record->market_preview_published_at) {
            $append(
                'market_preview_published',
                'Preview Kajian Dipublikasikan',
                'Customer dapat meninjau hasil kajian pasar dalam bentuk range sebelum laporan final disiapkan.',
                $record->market_preview_published_at,
                'info'
            );
        }

        if ($record->market_preview_appeal_submitted_at) {
            $append(
                'market_preview_appeal',
                'Banding Diajukan',
                'Customer menggunakan kesempatan banding dan meminta reviewer memperbarui hasil preview.',
                $record->market_preview_appeal_submitted_at,
                'warning'
            );
        }

        if ($record->market_preview_approved_at) {
            $append(
                'market_preview_approved',
                'Preview Disetujui Customer',
                'Customer menyetujui preview hasil kajian pasar dan request masuk ke tahap persiapan laporan final.',
                $record->market_preview_approved_at,
                'success'
            );
        }

        if ($record->report_draft_generated_at) {
            $append(
                'report_preparation',
                'Admin Menyiapkan Laporan Final',
                'Draft laporan sudah disiapkan. Admin akan melengkapi QR/barcode P2PK/ELSA dan tanda tangan sebelum upload final.',
                $record->report_draft_generated_at,
                'info'
            );
        }

        if (($record->status?->value ?? $record->status) === AppraisalStatusEnum::Completed->value) {
            $append(
                'request_completed',
                'Permohonan Selesai',
                'Seluruh proses penilaian telah selesai.',
                $record->updated_at,
                'success'
            );
        }

        if (($record->status?->value ?? $record->status) === AppraisalStatusEnum::CancellationReviewPending->value) {
            $append(
                'cancellation_review_pending',
                'Menunggu Review Pembatalan',
                'Permohonan sedang ditahan sementara sampai admin menyelesaikan review pembatalan.',
                $latestCancellationRequest?->created_at ?? $record->updated_at,
                'warning'
            );
        }

        if (($record->status?->value ?? $record->status) === AppraisalStatusEnum::Cancelled->value) {
            $description = 'Permohonan berada pada status dibatalkan.';

            if (filled($record->cancellation_reason)) {
                $description .= ' Alasan: ' . $record->cancellation_reason;
            }

            $append(
                'request_cancelled_status',
                'Status Dibatalkan',
                $description,
                $record->cancelled_at ?? $record->updated_at,
                'danger'
            );
        }

        usort($entries, function (array $a, array $b): int {
            $left = strtotime((string) ($a['at'] ?? '')) ?: 0;
            $right = strtotime((string) ($b['at'] ?? '')) ?: 0;

            return $left <=> $right;
        });

        return array_values($entries);
    }
}

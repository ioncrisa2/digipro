<?php

namespace App\Services;

use App\Models\AppraisalRequest;

class AppraisalPhysicalReportSummaryBuilder
{
    public function build(AppraisalRequest $record): array
    {
        $reportFormat = (string) ($record->report_format ?? 'digital');
        $copiesCount = (int) ($record->physical_copies_count ?? 0);
        $needsPhysicalReport = $reportFormat !== 'digital' || $copiesCount > 0;

        [$state, $stateLabel, $stateDescription] = $this->resolveState($record, $needsPhysicalReport);

        return [
            'needs_physical_report' => $needsPhysicalReport,
            'report_format' => $reportFormat,
            'report_format_label' => match ($reportFormat) {
                'physical' => 'Hard Copy',
                'both' => 'Digital + Hard Copy',
                default => 'Digital',
            },
            'copies_count' => $copiesCount,
            'delivery_recipient_name' => $record->report_delivery_recipient_name,
            'delivery_recipient_phone' => $record->report_delivery_recipient_phone,
            'delivery_address' => $record->report_delivery_address,
            'courier' => $record->physical_report_courier,
            'tracking_number' => $record->physical_report_tracking_number,
            'notes' => $record->physical_report_notes,
            'printed_at' => optional($record->physical_report_printed_at)->toDateTimeString(),
            'printed_by_name' => $record->physicalReportPrintedBy?->name,
            'shipped_at' => optional($record->physical_report_shipped_at)->toDateTimeString(),
            'delivered_at' => optional($record->physical_report_delivered_at)->toDateTimeString(),
            'state' => $state,
            'state_label' => $stateLabel,
            'state_description' => $stateDescription,
        ];
    }

    private function resolveState(AppraisalRequest $record, bool $needsPhysicalReport): array
    {
        if (! $needsPhysicalReport) {
            return ['digital_only', 'Digital Only', 'Permohonan ini tidak meminta pengiriman laporan fisik.'];
        }

        if ($record->physical_report_delivered_at) {
            return ['delivered', 'Sudah Diterima', 'Laporan fisik sudah ditandai diterima oleh customer.'];
        }

        if ($record->physical_report_shipped_at) {
            return ['shipped', 'Sedang Dikirim', 'Laporan fisik sudah dikirim dan sedang dalam proses pengantaran.'];
        }

        if ($record->physical_report_printed_at) {
            return ['printed', 'Siap Dikirim', 'Laporan fisik sudah dicetak dan siap dikirim oleh admin.'];
        }

        if ($record->report_generated_at || $record->report_pdf_path) {
            return ['ready_to_print', 'Menunggu Proses Cetak', 'Laporan final tersedia dan hard copy belum diproses untuk cetak.'];
        }

        return ['waiting_final_report', 'Menunggu Laporan Final', 'Pengiriman hard copy akan dimulai setelah laporan final selesai disiapkan.'];
    }
}

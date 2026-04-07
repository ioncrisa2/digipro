<?php

namespace App\Notifications;

class AppraisalOfferNotification extends DatabaseNotification
{
    public function __construct(
        public int $appraisalId,
        public string $requestNumber,
        public string $mode,
        public int $feeTotal,
    ) {
    }

    protected function databasePayload(object $notifiable): array
    {
        $title = match ($this->mode) {
            'finalized' => 'Negosiasi disetujui admin',
            'revised' => 'Revisi penawaran tersedia',
            default => 'Penawaran baru tersedia',
        };

        $message = match ($this->mode) {
            'finalized' => "Admin menyetujui hasil negosiasi untuk {$this->requestNumber} sebesar Rp " . number_format($this->feeTotal, 0, ',', '.') . ". Lanjutkan ke tanda tangan kontrak.",
            'revised' => "Admin mengirim revisi penawaran untuk {$this->requestNumber} sebesar Rp " . number_format($this->feeTotal, 0, ',', '.') . ".",
            default => "Penawaran baru untuk {$this->requestNumber} sudah tersedia sebesar Rp " . number_format($this->feeTotal, 0, ',', '.') . ".",
        };

        return [
            'title' => $title,
            'message' => $message,
            'url' => $this->mode === 'finalized'
                ? route('appraisal.contract.page', ['id' => $this->appraisalId])
                : route('appraisal.offer.page', ['id' => $this->appraisalId]),
            'appraisal_id' => $this->appraisalId,
            'mode' => $this->mode,
            'fee_total' => $this->feeTotal,
        ];
    }
}

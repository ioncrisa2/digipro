<?php

namespace App\Notifications;

class AppraisalPaymentStatusNotification extends DatabaseNotification
{
    public function __construct(
        public int $appraisalId,
        public string $requestNumber,
        public string $status,
        public ?string $reason = null,
    ) {
    }

    protected function databasePayload(object $notifiable): array
    {
        $isVerified = $this->status === 'verified';

        $title = $isVerified
            ? 'Pembayaran terverifikasi'
            : 'Bukti pembayaran ditolak';

        $message = $isVerified
            ? "Pembayaran untuk {$this->requestNumber} sudah dikonfirmasi sistem. Status permohonan masuk Proses Valuasi Berjalan."
            : "Bukti pembayaran untuk {$this->requestNumber} ditolak admin."
                . ($this->reason ? " Alasan: {$this->reason}" : '');

        return [
            'title' => $title,
            'message' => $message,
            'url' => route('appraisal.show', $this->appraisalId),
            'appraisal_id' => $this->appraisalId,
            'payment_status' => $this->status,
            'reason' => $this->reason,
        ];
    }
}

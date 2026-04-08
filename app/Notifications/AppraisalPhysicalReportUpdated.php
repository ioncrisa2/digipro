<?php

namespace App\Notifications;

class AppraisalPhysicalReportUpdated extends DatabaseNotification
{
    public function __construct(
        public int $appraisalId,
        public string $requestNumber,
        public string $event,
        public ?string $courier = null,
        public ?string $trackingNumber = null,
    ) {
    }

    protected function databasePayload(object $notifiable): array
    {
        $title = match ($this->event) {
            'printed' => 'Hard copy sedang diproses',
            'delivered' => 'Hard copy telah diterima',
            default => 'Hard copy telah dikirim',
        };

        $message = match ($this->event) {
            'printed' => "Laporan fisik untuk {$this->requestNumber} sudah dicetak dan sedang disiapkan untuk pengiriman.",
            'delivered' => "Laporan fisik untuk {$this->requestNumber} telah ditandai diterima.",
            default => "Laporan fisik untuk {$this->requestNumber} sudah dikirim.",
        };

        if ($this->event === 'shipped' && filled($this->courier)) {
            $message .= ' Kurir: ' . $this->courier . '.';
        }

        if ($this->event === 'shipped' && filled($this->trackingNumber)) {
            $message .= ' Resi: ' . $this->trackingNumber . '.';
        }

        return [
            'title' => $title,
            'message' => $message,
            'url' => route('appraisal.tracking.page', $this->appraisalId),
            'appraisal_id' => $this->appraisalId,
            'event' => $this->event,
            'courier' => $this->courier,
            'tracking_number' => $this->trackingNumber,
        ];
    }
}

<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class AppraisalStatusUpdated extends Notification
{
    use Queueable;

    public function __construct(
        public int $appraisalId,
        public string $requestNumber,
        public string $oldStatus,
        public string $newStatus,
        public ?string $detail = null,
    ) {
    }

    public function via($notifiable): array
    {
        return ['database'];
    }

    public function toDatabase($notifiable): array
    {
        $message = "Permohonan {$this->requestNumber} berubah: {$this->oldStatus} -> {$this->newStatus}.";

        if (filled($this->detail)) {
            $message .= ' Alasan: ' . $this->detail;
        }

        return [
            'title' => 'Status permohonan diperbarui',
            'message' => $message,
            'url' => route('appraisal.show', $this->appraisalId),
            'appraisal_id' => $this->appraisalId,
            'old_status' => $this->oldStatus,
            'new_status' => $this->newStatus,
            'detail' => $this->detail,
        ];
    }
}

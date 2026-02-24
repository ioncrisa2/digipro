<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class AppraisalStatusUpdated extends Notification
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    public function __construct(
        public int $appraisalId,
        public string $requestNumber,
        public string $oldStatus,
        public string $newStatus
    ) {
        //
    }

    public function via($notifiable): array
    {
        return ['database'];
    }

    public function toDatabase($notifiable): array
    {
        return [
            'title'         => 'Status permohonan diperbarui',
            'message'       => "Permohonan {$this->requestNumber} berubah: {$this->oldStatus} → {$this->newStatus}.",
            'url'           => route('appraisal.show', $this->appraisalId),
            'appraisal_id'  => $this->appraisalId,
            'old_status'    => $this->oldStatus,
            'new_status'    => $this->newStatus,
        ];
    }
}

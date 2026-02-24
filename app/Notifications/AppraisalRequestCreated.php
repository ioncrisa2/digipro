<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class AppraisalRequestCreated extends Notification
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    public function __construct(
        public int $appraisalId,
        public string $requestNumber
    ) {}

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toDatabase($notifiable): array
    {
        return [
            'title'         => 'Permohonan berhasil dibuat',
            'message'       => "Permohonan {$this->requestNumber} berhasil dibuat.",
            'url'           => route('appraisal.show', $this->appraisalId),
            'appraisal_id'  => $this->appraisalId,
        ];
    }
}

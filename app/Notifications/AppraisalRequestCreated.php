<?php

namespace App\Notifications;

class AppraisalRequestCreated extends DatabaseNotification
{
    public function __construct(
        public int $appraisalId,
        public string $requestNumber
    ) {
    }

    protected function databasePayload(object $notifiable): array
    {
        return [
            'title'         => 'Permohonan berhasil dibuat',
            'message'       => "Permohonan {$this->requestNumber} berhasil dibuat.",
            'url'           => route('appraisal.show', $this->appraisalId),
            'appraisal_id'  => $this->appraisalId,
        ];
    }
}

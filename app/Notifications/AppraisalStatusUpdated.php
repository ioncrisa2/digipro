<?php

namespace App\Notifications;

class AppraisalStatusUpdated extends DatabaseNotification
{
    public function __construct(
        public int $appraisalId,
        public string $requestNumber,
        public string $oldStatus,
        public string $newStatus,
        public ?string $detail = null,
    ) {
    }

    protected function databasePayload(object $notifiable): array
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

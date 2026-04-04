<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class AppraisalRevisionRequestedNotification extends Notification
{
    use Queueable;

    public function __construct(
        public int $appraisalId,
        public int $revisionBatchId,
        public string $requestNumber,
        public int $itemsCount,
        public ?string $adminNote = null,
    ) {
    }

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toDatabase(object $notifiable): array
    {
        $message = "Admin meminta revisi {$this->itemsCount} data/dokumen untuk {$this->requestNumber}.";

        if ($this->adminNote) {
            $message .= " Catatan: {$this->adminNote}";
        }

        return [
            'title' => 'Revisi data atau dokumen diperlukan',
            'message' => $message,
            'url' => route('appraisal.revisions.page', ['id' => $this->appraisalId]),
            'appraisal_id' => $this->appraisalId,
            'revision_batch_id' => $this->revisionBatchId,
            'items_count' => $this->itemsCount,
            'admin_note' => $this->adminNote,
        ];
    }
}

<?php

namespace App\Notifications;

class AppraisalRevisionRequestedNotification extends DatabaseNotification
{
    public function __construct(
        public int $appraisalId,
        public int $revisionBatchId,
        public string $requestNumber,
        public int $itemsCount,
        public ?string $adminNote = null,
    ) {
    }

    protected function databasePayload(object $notifiable): array
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

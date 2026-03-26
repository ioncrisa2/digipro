<?php

namespace App\Services\Admin;

use App\Enums\AppraisalStatusEnum;
use App\Models\AppraisalRequest;
use App\Models\AppraisalRequestRevisionBatch;
use App\Models\AppraisalRequestRevisionItem;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class AppraisalRequestRevisionReviewService
{
    public function approveItem(
        AppraisalRequest $record,
        AppraisalRequestRevisionItem $item,
        int $actorId
    ): AppraisalRequestRevisionItem {
        $this->guardItemBelongsToRequest($record, $item);

        if ((string) $item->status !== 'reuploaded') {
            throw new RuntimeException('Hanya item revisi yang sudah diunggah ulang customer yang bisa disetujui.');
        }

        if (! $item->replacement_request_file_id && ! $item->replacement_asset_file_id) {
            throw new RuntimeException('Belum ada file pengganti yang bisa disetujui untuk item revisi ini.');
        }

        return DB::transaction(function () use ($item, $actorId): AppraisalRequestRevisionItem {
            $item->update([
                'status' => 'approved',
                'reviewed_by' => $actorId,
                'reviewed_at' => now(),
                'review_note' => null,
            ]);

            $this->syncBatchState($item->revisionBatch, $actorId);

            return $item->fresh([
                'revisionBatch',
                'originalRequestFile',
                'originalAssetFile',
                'replacementRequestFile',
                'replacementAssetFile',
            ]);
        });
    }

    public function rejectItem(
        AppraisalRequest $record,
        AppraisalRequestRevisionItem $item,
        int $actorId,
        string $reviewNote
    ): AppraisalRequestRevisionItem {
        $this->guardItemBelongsToRequest($record, $item);

        if ((string) $item->status !== 'reuploaded') {
            throw new RuntimeException('Hanya item revisi yang sudah diunggah ulang customer yang bisa diminta revisi lagi.');
        }

        $note = trim($reviewNote);
        if ($note === '') {
            throw new RuntimeException('Catatan revisi ulang wajib diisi sebelum item ditolak.');
        }

        return DB::transaction(function () use ($record, $item, $actorId, $note): AppraisalRequestRevisionItem {
            $item->update([
                'status' => 'rejected',
                'reviewed_by' => $actorId,
                'reviewed_at' => now(),
                'review_note' => $note,
            ]);

            $record->update([
                'status' => AppraisalStatusEnum::DocsIncomplete,
                'verified_at' => null,
            ]);

            $this->syncBatchState($item->revisionBatch, $actorId);

            return $item->fresh([
                'revisionBatch',
                'originalRequestFile',
                'originalAssetFile',
                'replacementRequestFile',
                'replacementAssetFile',
            ]);
        });
    }

    public function syncBatchState(AppraisalRequestRevisionBatch $batch, ?int $actorId = null): void
    {
        $batch->loadMissing('items', 'appraisalRequest');

        $statuses = $batch->items
            ->pluck('status')
            ->map(fn ($status) => (string) $status)
            ->values();

        if ($statuses->isEmpty()) {
            $batch->update([
                'status' => 'cancelled',
                'reviewed_by' => null,
                'reviewed_at' => null,
                'resolved_at' => now(),
            ]);

            return;
        }

        if ($statuses->every(fn ($status) => $status === 'approved')) {
            $batch->update([
                'status' => 'reviewed',
                'reviewed_by' => $actorId,
                'reviewed_at' => now(),
                'resolved_at' => now(),
            ]);

            return;
        }

        if ($statuses->contains(fn ($status) => in_array($status, ['pending', 'rejected'], true))) {
            $batch->update([
                'status' => 'open',
                'reviewed_by' => null,
                'reviewed_at' => null,
                'resolved_at' => null,
            ]);

            return;
        }

        $batch->update([
            'status' => 'submitted',
            'reviewed_by' => null,
            'reviewed_at' => null,
            'resolved_at' => null,
        ]);
    }

    private function guardItemBelongsToRequest(AppraisalRequest $record, AppraisalRequestRevisionItem $item): void
    {
        $item->loadMissing('revisionBatch');

        if ((int) $item->revisionBatch?->appraisal_request_id !== (int) $record->id) {
            throw new RuntimeException('Item revisi tidak valid untuk request ini.');
        }
    }
}

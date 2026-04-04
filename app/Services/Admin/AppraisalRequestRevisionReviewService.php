<?php

namespace App\Services\Admin;

use App\Enums\AppraisalStatusEnum;
use App\Models\AppraisalFieldChangeLog;
use App\Models\AppraisalRequest;
use App\Models\AppraisalRequestRevisionBatch;
use App\Models\AppraisalRequestRevisionItem;
use App\Services\Revisions\AppraisalRevisionFieldRegistry;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class AppraisalRequestRevisionReviewService
{
    public function __construct(
        private readonly AppraisalRevisionFieldRegistry $fieldRegistry
    ) {
    }

    public function approveItem(
        AppraisalRequest $record,
        AppraisalRequestRevisionItem $item,
        int $actorId
    ): AppraisalRequestRevisionItem {
        $this->guardItemBelongsToRequest($record, $item);

        if ((string) $item->status !== 'reuploaded') {
            throw new RuntimeException('Hanya item revisi yang sudah diunggah ulang customer yang bisa disetujui.');
        }

        if (
            ! in_array((string) $item->item_type, ['asset_field', 'request_field'], true)
            && ! $item->replacement_request_file_id
            && ! $item->replacement_asset_file_id
        ) {
            throw new RuntimeException('Belum ada file pengganti yang bisa disetujui untuk item revisi ini.');
        }

        return DB::transaction(function () use ($record, $item, $actorId): AppraisalRequestRevisionItem {
            if (in_array((string) $item->item_type, ['asset_field', 'request_field'], true)) {
                $this->applyFieldRevision($record, $item, $actorId);
            }

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

    private function applyFieldRevision(AppraisalRequest $record, AppraisalRequestRevisionItem $item, int $actorId): void
    {
        $fieldKey = (string) ($item->requested_field_key ?: $item->requested_file_type);
        $newValue = data_get($item->replacement_value, 'value');

        if ($fieldKey === '' || ! is_array($item->replacement_value)) {
            throw new RuntimeException('Nilai revisi data belum tersedia untuk item ini.');
        }

        if ((string) $item->item_type === 'asset_field') {
            $asset = $item->appraisalAsset;
            if (! $asset) {
                throw new RuntimeException('Aset target revisi tidak ditemukan.');
            }

            $this->fieldRegistry->apply($asset, $fieldKey, $newValue);

            AppraisalFieldChangeLog::query()->create([
                'appraisal_request_id' => $record->id,
                'appraisal_asset_id' => $asset->id,
                'revision_batch_id' => $item->revision_batch_id,
                'revision_item_id' => $item->id,
                'changed_by' => $actorId,
                'change_source' => 'customer_revision',
                'field_key' => $fieldKey,
                'field_label' => $this->fieldRegistry->definition($fieldKey)['label'],
                'old_value' => $item->original_value,
                'new_value' => $item->replacement_value,
                'reason' => $item->issue_note,
            ]);

            return;
        }

        throw new RuntimeException('Jenis revisi data belum didukung untuk approval ini.');
    }
}

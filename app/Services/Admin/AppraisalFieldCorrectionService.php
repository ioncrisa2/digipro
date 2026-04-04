<?php

namespace App\Services\Admin;

use App\Models\AppraisalAsset;
use App\Models\AppraisalFieldChangeLog;
use App\Models\AppraisalRequest;
use App\Services\Revisions\AppraisalRevisionFieldRegistry;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class AppraisalFieldCorrectionService
{
    public function __construct(
        private readonly AppraisalRevisionFieldRegistry $fieldRegistry
    ) {
    }

    public function apply(AppraisalRequest $record, int $actorId, string $targetKey, mixed $value, ?string $reason = null): void
    {
        $target = $this->fieldRegistry->targetFromKey($record, $targetKey);
        if ($target === null) {
            throw new RuntimeException('Target koreksi data tidak valid.');
        }

        /** @var AppraisalAsset $asset */
        $asset = $target['asset'];
        $fieldKey = (string) $target['requested_field_key'];
        $normalized = $this->fieldRegistry->validateAndNormalize($fieldKey, $value);
        $oldSnapshot = $this->fieldRegistry->snapshot($fieldKey, $asset->{$fieldKey});
        $newSnapshot = $this->fieldRegistry->snapshot($fieldKey, $normalized);

        DB::transaction(function () use ($record, $asset, $actorId, $fieldKey, $normalized, $reason, $oldSnapshot, $newSnapshot): void {
            $this->fieldRegistry->apply($asset, $fieldKey, $normalized);

            AppraisalFieldChangeLog::query()->create([
                'appraisal_request_id' => $record->id,
                'appraisal_asset_id' => $asset->id,
                'revision_batch_id' => null,
                'revision_item_id' => null,
                'changed_by' => $actorId,
                'change_source' => 'admin_direct',
                'field_key' => $fieldKey,
                'field_label' => $this->fieldRegistry->definition($fieldKey)['label'],
                'old_value' => $oldSnapshot,
                'new_value' => $newSnapshot,
                'reason' => is_string($reason) && trim($reason) !== '' ? trim($reason) : null,
            ]);
        });
    }
}

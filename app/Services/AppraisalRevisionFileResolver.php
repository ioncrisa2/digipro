<?php

namespace App\Services;

use App\Models\AppraisalAsset;
use App\Models\AppraisalRequest;
use App\Models\AppraisalRequestRevisionItem;
use Illuminate\Support\Collection;

class AppraisalRevisionFileResolver
{
    public function approvedItemsForRequest(AppraisalRequest $record): Collection
    {
        if ($record->relationLoaded('revisionBatches')) {
            return $record->revisionBatches
                ->flatMap(fn ($batch) => $batch->items ?? collect())
                ->filter(fn ($item) => (string) $item->status === 'approved')
                ->values();
        }

        return AppraisalRequestRevisionItem::query()
            ->where('status', 'approved')
            ->whereHas('revisionBatch', fn ($query) => $query->where('appraisal_request_id', $record->id))
            ->with([
                'originalRequestFile',
                'originalAssetFile',
                'replacementRequestFile',
                'replacementAssetFile',
            ])
            ->get();
    }

    public function activeRequestFiles(AppraisalRequest $record, ?Collection $approvedItems = null): Collection
    {
        $files = $record->relationLoaded('files')
            ? $record->files
            : $record->files()->get();

        return $this->filterActiveFiles(
            $files,
            ($approvedItems ?? $this->approvedItemsForRequest($record))
                ->where('item_type', 'request_file')
                ->values(),
            'original_request_file_id',
            'replacement_request_file_id'
        );
    }

    public function activeAssetFilesByRequest(AppraisalRequest $record, ?Collection $approvedItems = null): array
    {
        $approved = $approvedItems ?? $this->approvedItemsForRequest($record);
        $assets = $record->relationLoaded('assets')
            ? $record->assets
            : $record->assets()->with('files')->get();

        return $assets
            ->mapWithKeys(function (AppraisalAsset $asset) use ($approved): array {
                $items = $approved
                    ->where('appraisal_asset_id', $asset->id)
                    ->whereIn('item_type', ['asset_document', 'asset_photo'])
                    ->values();

                return [
                    $asset->id => $this->filterActiveFiles(
                        $asset->relationLoaded('files') ? $asset->files : $asset->files()->get(),
                        $items,
                        'original_asset_file_id',
                        'replacement_asset_file_id'
                    ),
                ];
            })
            ->all();
    }

    public function activeAssetFiles(AppraisalAsset $asset, ?Collection $approvedItems = null): Collection
    {
        $record = $asset->relationLoaded('request') ? $asset->request : $asset->request()->first();
        if (! $record) {
            return $asset->relationLoaded('files') ? $asset->files->values() : $asset->files()->get();
        }

        $approved = $approvedItems ?? $this->approvedItemsForRequest($record);

        return $this->filterActiveFiles(
            $asset->relationLoaded('files') ? $asset->files : $asset->files()->get(),
            $approved
                ->where('appraisal_asset_id', $asset->id)
                ->whereIn('item_type', ['asset_document', 'asset_photo'])
                ->values(),
            'original_asset_file_id',
            'replacement_asset_file_id'
        );
    }

    private function filterActiveFiles(
        Collection $files,
        Collection $approvedItems,
        string $originalKey,
        string $replacementKey
    ): Collection {
        $allReplacementIds = $approvedItems
            ->pluck($replacementKey)
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->values();

        $supersededOriginalIds = $approvedItems
            ->pluck($originalKey)
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->values();

        return $files
            ->reject(function ($file) use ($supersededOriginalIds, $allReplacementIds): bool {
                $fileId = (int) $file->id;

                if ($supersededOriginalIds->contains($fileId)) {
                    return true;
                }

                if ($this->isRevisionStoredFile((string) $file->path) && ! $allReplacementIds->contains($fileId)) {
                    return true;
                }

                return false;
            })
            ->sortByDesc('created_at')
            ->values();
    }

    private function isRevisionStoredFile(string $path): bool
    {
        $normalized = str_replace('\\', '/', strtolower($path));

        return str_contains($normalized, '/revisions/');
    }
}

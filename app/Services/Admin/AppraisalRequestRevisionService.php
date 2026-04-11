<?php

namespace App\Services\Admin;

use App\Enums\AppraisalStatusEnum;
use App\Models\AppraisalAsset;
use App\Models\AppraisalRequest;
use App\Models\AppraisalRequestRevisionBatch;
use App\Notifications\AppraisalRevisionRequestedNotification;
use App\Services\Revisions\AppraisalRevisionFileResolver;
use App\Services\Revisions\AppraisalRevisionFieldRegistry;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

class AppraisalRequestRevisionService
{
    public function __construct(
        private readonly AppraisalRevisionFileResolver $fileResolver,
        private readonly AppraisalRevisionFieldRegistry $fieldRegistry
    ) {
    }

    public function canCreateBatch(AppraisalRequest $record): bool
    {
        if ($this->hasSubmittedBatchAwaitingReview($record)) {
            return false;
        }

        return in_array($this->statusValue($record), [
            AppraisalStatusEnum::Submitted->value,
            AppraisalStatusEnum::DocsIncomplete->value,
            AppraisalStatusEnum::WaitingOffer->value,
            AppraisalStatusEnum::OfferSent->value,
        ], true);
    }

    public function creationState(AppraisalRequest $record): array
    {
        if ($this->hasSubmittedBatchAwaitingReview($record)) {
            return [
                'can_create' => false,
                'message' => 'Masih ada batch revisi yang sudah diunggah ulang customer dan menunggu review admin per-item.',
            ];
        }

        if (! in_array($this->statusValue($record), [
            AppraisalStatusEnum::Submitted->value,
            AppraisalStatusEnum::DocsIncomplete->value,
            AppraisalStatusEnum::WaitingOffer->value,
            AppraisalStatusEnum::OfferSent->value,
        ], true)) {
            return [
                'can_create' => false,
                'message' => 'Permintaan revisi data atau dokumen hanya bisa dibuat saat request masih berada pada tahap verifikasi administrasi atau penawaran awal.',
            ];
        }

        return [
            'can_create' => true,
            'message' => $this->hasOpenBatch($record)
                ? 'Tambahkan item revisi ke batch yang masih terbuka untuk request ini.'
                : 'Klik tombol revisi pada data, dokumen, atau foto yang perlu diperbaiki customer.',
        ];
    }

    public function hasOpenBatch(AppraisalRequest $record): bool
    {
        if ($record->relationLoaded('revisionBatches')) {
            return $record->revisionBatches->contains(fn ($batch) => $batch->status === 'open');
        }

        return $record->revisionBatches()
            ->where('status', 'open')
            ->exists();
    }

    public function hasSubmittedBatchAwaitingReview(AppraisalRequest $record): bool
    {
        if ($record->relationLoaded('revisionBatches')) {
            return $record->revisionBatches->contains(
                fn ($batch) => (string) $batch->status === 'submitted'
            );
        }

        return $record->revisionBatches()
            ->where('status', 'submitted')
            ->exists();
    }

    public function buildTargetOptions(AppraisalRequest $record): array
    {
        $record->loadMissing(['files', 'assets.files']);
        $approvedItems = $this->fileResolver->approvedItemsForRequest($record);
        $activeRequestFiles = $this->fileResolver->activeRequestFiles($record, $approvedItems);
        $activeAssetFiles = $this->fileResolver->activeAssetFilesByRequest($record, $approvedItems);

        $options = [];
        foreach ($activeRequestFiles as $file) {
            $options[] = [
                'key' => "request_file:existing:{$file->id}",
                'item_type' => 'request_file',
                'requested_file_type' => (string) $file->type,
                'appraisal_asset_id' => null,
                'original_request_file_id' => (int) $file->id,
                'original_asset_file_id' => null,
                'label' => '[Request] ' . $this->requestFileTypeLabel($file->type),
                'description' => $file->original_name ?: basename((string) $file->path),
                'kind' => 'existing',
            ];
        }

        foreach ($record->assets->sortBy('id')->values() as $index => $asset) {
            $assetLabelPrefix = sprintf('[Aset #%d] ', $index + 1);
            $files = collect($activeAssetFiles[$asset->id] ?? [])
                ->sortByDesc('created_at')
                ->values();
            $existingAssetTypes = $files
                ->pluck('type')
                ->filter()
                ->map(fn ($type) => (string) $type)
                ->unique()
                ->values()
                ->all();

            foreach ($files as $file) {
                $itemType = str_starts_with((string) $file->type, 'photo_') ? 'asset_photo' : 'asset_document';

                $options[] = [
                    'key' => "{$itemType}:existing:{$file->id}",
                    'item_type' => $itemType,
                    'requested_file_type' => (string) $file->type,
                    'appraisal_asset_id' => (int) $asset->id,
                    'original_request_file_id' => null,
                    'original_asset_file_id' => (int) $file->id,
                    'label' => $assetLabelPrefix . $this->assetFileTypeLabel($file->type),
                    'description' => ($file->original_name ?: basename((string) $file->path)) . ' • ' . $this->assetSummary($asset),
                    'kind' => 'existing',
                ];
            }

            foreach ($this->expectedAssetDocumentTypes() as $type) {
                if (in_array($type, $existingAssetTypes, true)) {
                    continue;
                }

                $options[] = [
                    'key' => "asset_document:missing:{$asset->id}:{$type}",
                    'item_type' => 'asset_document',
                    'requested_file_type' => $type,
                    'appraisal_asset_id' => (int) $asset->id,
                    'original_request_file_id' => null,
                    'original_asset_file_id' => null,
                    'label' => $assetLabelPrefix . $this->assetFileTypeLabel($type),
                    'description' => 'Belum diunggah customer • ' . $this->assetSummary($asset),
                    'kind' => 'missing',
                ];
            }

            foreach ($this->expectedAssetPhotoTypes() as $type) {
                if (in_array($type, $existingAssetTypes, true)) {
                    continue;
                }

                $options[] = [
                    'key' => "asset_photo:missing:{$asset->id}:{$type}",
                    'item_type' => 'asset_photo',
                    'requested_file_type' => $type,
                    'appraisal_asset_id' => (int) $asset->id,
                    'original_request_file_id' => null,
                    'original_asset_file_id' => null,
                    'label' => $assetLabelPrefix . $this->assetFileTypeLabel($type),
                    'description' => 'Belum diunggah customer • ' . $this->assetSummary($asset),
                    'kind' => 'missing',
                ];
            }
        }

        foreach ($this->fieldRegistry->buildTargetOptions($record) as $option) {
            $options[] = $option;
        }

        return array_values($options);
    }

    public function targetOptionMap(AppraisalRequest $record): array
    {
        $map = [];

        foreach ($this->buildTargetOptions($record) as $option) {
            $map[$option['key']] = $option;
        }

        return $map;
    }

    public function createBatch(
        AppraisalRequest $record,
        int $actorId,
        array $items,
        ?string $adminNote = null
    ): AppraisalRequestRevisionBatch {
        if (! $this->canCreateBatch($record)) {
            throw new RuntimeException($this->creationState($record)['message']);
        }

        $batch = DB::transaction(function () use ($record, $actorId, $items, $adminNote): AppraisalRequestRevisionBatch {
            $batch = $record->revisionBatches()
                ->where('status', 'open')
                ->latest('id')
                ->first();

            if (! $batch) {
                $batch = $record->revisionBatches()->create([
                    'created_by' => $actorId,
                    'status' => 'open',
                    'admin_note' => $this->normalizeNullableString($adminNote),
                ]);
            } elseif ($this->normalizeNullableString($adminNote) && blank($batch->admin_note)) {
                $batch->update([
                    'admin_note' => $this->normalizeNullableString($adminNote),
                ]);
            }

            $existingKeys = $batch->items()
                ->get()
                ->map(fn ($item) => $this->itemIdentityKey([
                    'item_type' => $item->item_type,
                    'requested_file_type' => $item->requested_file_type,
                    'appraisal_asset_id' => $item->appraisal_asset_id,
                    'original_request_file_id' => $item->original_request_file_id,
                    'original_asset_file_id' => $item->original_asset_file_id,
                ]))
                ->all();

            $pendingRows = [];

            foreach ($items as $item) {
                $itemKey = $this->itemIdentityKey($item);

                if (in_array($itemKey, $existingKeys, true)) {
                    throw new RuntimeException('Item revisi ini sudah ada di batch revisi yang masih terbuka.');
                }

                $existingKeys[] = $itemKey;

                $pendingRows[] = [
                    'appraisal_asset_id' => $item['appraisal_asset_id'],
                    'item_type' => $item['item_type'],
                    'requested_file_type' => $item['requested_file_type'],
                    'requested_field_key' => $item['requested_field_key'] ?? null,
                    'status' => 'pending',
                    'issue_note' => $item['issue_note'],
                    'original_value' => $item['original_value'] ?? null,
                    'original_request_file_id' => $item['original_request_file_id'],
                    'original_asset_file_id' => $item['original_asset_file_id'],
                    'replacement_request_file_id' => null,
                    'replacement_asset_file_id' => null,
                    'replacement_value' => null,
                ];
            }

            $batch->items()->createMany($pendingRows);

            if ($this->statusValue($record) !== AppraisalStatusEnum::DocsIncomplete->value) {
                $record->update([
                    'status' => AppraisalStatusEnum::DocsIncomplete,
                ]);
            }

            return $batch->load([
                'creator',
                'items.appraisalAsset',
                'items.originalRequestFile',
                'items.originalAssetFile',
                'items.replacementRequestFile',
                'items.replacementAssetFile',
            ]);
        });

        $record->loadMissing('user');

        if ($record->user) {
            $record->user->notify(new AppraisalRevisionRequestedNotification(
                (int) $record->id,
                (int) $batch->id,
                (string) ($record->request_number ?? ('REQ-' . $record->id)),
                count($items),
                $this->normalizeNullableString($adminNote),
            ));
        }

        return $batch;
    }

    private function expectedAssetDocumentTypes(): array
    {
        return ['doc_pbb', 'doc_imb', 'doc_certs'];
    }

    private function expectedAssetPhotoTypes(): array
    {
        return ['photo_access_road', 'photo_front', 'photo_interior'];
    }

    private function requestFileTypeLabel(?string $type): string
    {
        return match ((string) $type) {
            'agreement_pdf' => 'Agreement DigiPro by KJPP HJAR',
            'contract_signed_pdf' => 'PDF Kontrak Ditandatangani',
            'disclaimer_pdf' => 'Disclaimer DigiPro by KJPP HJAR',
            'npwp' => 'NPWP',
            'representative' => 'Surat Kuasa',
            'permission' => 'Surat Izin',
            'other_request_document' => 'Lampiran Request',
            default => Str::headline((string) $type),
        };
    }

    private function assetFileTypeLabel(?string $type): string
    {
        return match ((string) $type) {
            'doc_pbb' => 'PBB',
            'doc_imb' => 'IMB / PBG',
            'doc_certs' => 'Sertifikat',
            'photo_access_road' => 'Foto Akses Jalan',
            'photo_front' => 'Foto Depan',
            'photo_interior' => 'Foto Dalam',
            default => Str::headline((string) $type),
        };
    }

    private function assetSummary(AppraisalAsset $asset): string
    {
        return $asset->address ?: ('Aset ID #' . $asset->id);
    }

    private function normalizeNullableString(?string $value): ?string
    {
        $value = is_string($value) ? trim($value) : null;

        return $value === '' ? null : $value;
    }

    private function statusValue(AppraisalRequest $record): ?string
    {
        return $record->status?->value ?? $record->status;
    }

    private function itemIdentityKey(array $item): string
    {
        return implode(':', [
            (string) ($item['item_type'] ?? ''),
            (string) ($item['requested_file_type'] ?? ''),
            (string) ($item['appraisal_asset_id'] ?? ''),
            (string) ($item['original_request_file_id'] ?? ''),
            (string) ($item['original_asset_file_id'] ?? ''),
        ]);
    }
}

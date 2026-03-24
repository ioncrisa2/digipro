<?php

namespace App\Services\Admin;

use App\Enums\AppraisalStatusEnum;
use App\Models\AppraisalAsset;
use App\Models\AppraisalRequest;
use App\Models\AppraisalRequestRevisionBatch;
use App\Notifications\AppraisalRevisionRequestedNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

class AppraisalRequestRevisionService
{
    public function canCreateBatch(AppraisalRequest $record): bool
    {
        return in_array($this->statusValue($record), [
            AppraisalStatusEnum::Submitted->value,
            AppraisalStatusEnum::DocsIncomplete->value,
            AppraisalStatusEnum::WaitingOffer->value,
            AppraisalStatusEnum::OfferSent->value,
        ], true) && ! $this->hasOpenBatch($record);
    }

    public function creationState(AppraisalRequest $record): array
    {
        if ($this->hasOpenBatch($record)) {
            return [
                'can_create' => false,
                'message' => 'Masih ada batch revisi dokumen yang terbuka untuk request ini. Tunggu customer mengunggah ulang dokumen sebelum membuat batch baru.',
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
                'message' => 'Permintaan revisi dokumen hanya bisa dibuat saat request masih berada pada tahap verifikasi administrasi atau penawaran awal.',
            ];
        }

        return [
            'can_create' => true,
            'message' => 'Pilih dokumen atau foto yang harus diperbaiki customer, lalu beri catatan per item.',
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

    public function buildTargetOptions(AppraisalRequest $record): array
    {
        $record->loadMissing(['files', 'assets.files']);

        $options = [];
        $existingRequestTypes = $record->files
            ->pluck('type')
            ->filter()
            ->map(fn ($type) => (string) $type)
            ->unique()
            ->values()
            ->all();

        foreach ($record->files->sortByDesc('created_at')->values() as $file) {
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

        foreach ($this->expectedRequestFileTypes() as $type) {
            if (in_array($type, $existingRequestTypes, true)) {
                continue;
            }

            $options[] = [
                'key' => "request_file:missing:{$type}",
                'item_type' => 'request_file',
                'requested_file_type' => $type,
                'appraisal_asset_id' => null,
                'original_request_file_id' => null,
                'original_asset_file_id' => null,
                'label' => '[Request] ' . $this->requestFileTypeLabel($type),
                'description' => 'Belum diunggah customer',
                'kind' => 'missing',
            ];
        }

        foreach ($record->assets->sortBy('id')->values() as $index => $asset) {
            $assetLabelPrefix = sprintf('[Aset #%d] ', $index + 1);
            $files = $asset->files->sortByDesc('created_at')->values();
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

        return DB::transaction(function () use ($record, $actorId, $items, $adminNote): AppraisalRequestRevisionBatch {
            $batch = $record->revisionBatches()->create([
                'created_by' => $actorId,
                'status' => 'open',
                'admin_note' => $this->normalizeNullableString($adminNote),
            ]);

            $batch->items()->createMany(array_map(function (array $item): array {
                return [
                    'appraisal_asset_id' => $item['appraisal_asset_id'],
                    'item_type' => $item['item_type'],
                    'requested_file_type' => $item['requested_file_type'],
                    'status' => 'pending',
                    'issue_note' => $item['issue_note'],
                    'original_request_file_id' => $item['original_request_file_id'],
                    'original_asset_file_id' => $item['original_asset_file_id'],
                    'replacement_request_file_id' => null,
                    'replacement_asset_file_id' => null,
                ];
            }, $items));

            if ($this->statusValue($record) !== AppraisalStatusEnum::DocsIncomplete->value) {
                $record->update([
                    'status' => AppraisalStatusEnum::DocsIncomplete,
                ]);
            }

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

            return $batch->load([
                'creator',
                'items.appraisalAsset',
                'items.originalRequestFile',
                'items.originalAssetFile',
                'items.replacementRequestFile',
                'items.replacementAssetFile',
            ]);
        });
    }

    private function expectedRequestFileTypes(): array
    {
        return ['npwp', 'representative', 'permission', 'other_request_document'];
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
            'contract_signed_pdf' => 'PDF Kontrak Ditandatangani',
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
}

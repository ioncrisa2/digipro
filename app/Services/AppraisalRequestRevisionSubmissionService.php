<?php

namespace App\Services;

use App\Enums\AppraisalStatusEnum;
use App\Models\AppraisalRequest;
use App\Models\AppraisalRequestRevisionBatch;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

class AppraisalRequestRevisionSubmissionService
{
    public function resolveOpenBatch(AppraisalRequest $record): ?AppraisalRequestRevisionBatch
    {
        return $record->revisionBatches()
            ->with([
                'items.appraisalAsset',
                'items.originalRequestFile',
                'items.originalAssetFile',
                'items.replacementRequestFile',
                'items.replacementAssetFile',
            ])
            ->where('status', 'open')
            ->latest('id')
            ->first();
    }

    public function buildSummary(AppraisalRequest $record): array
    {
        $batch = $this->resolveOpenBatch($record);

        return [
            'has_open_batch' => $batch !== null,
            'open_batch_id' => $batch?->id,
            'items_count' => $batch?->items->count() ?? 0,
            'created_at' => $batch?->created_at?->toDateTimeString(),
            'page_url' => $batch ? route('appraisal.revisions.page', ['id' => $record->id]) : null,
        ];
    }

    public function buildPagePayload(AppraisalRequest $record): array
    {
        $batch = $this->resolveOpenBatch($record);
        if (! $batch) {
            throw new RuntimeException('Tidak ada permintaan revisi dokumen yang sedang aktif untuk request ini.');
        }

        return [
            'record' => [
                'id' => $record->id,
                'request_number' => $record->request_number ?? ('REQ-' . $record->id),
                'status' => $record->status?->value ?? $record->status,
                'status_label' => $record->status?->label() ?? '-',
                'client_name' => $record->client_name ?? '-',
            ],
            'batch' => [
                'id' => $batch->id,
                'admin_note' => $batch->admin_note,
                'created_at' => $batch->created_at?->toDateTimeString(),
                'items' => $batch->items->map(fn ($item) => [
                    'id' => $item->id,
                    'status' => $item->status,
                    'target_label' => $this->targetLabel($item),
                    'asset_address' => $item->appraisalAsset?->address,
                    'issue_note' => $item->issue_note,
                    'accept' => $this->acceptForItemType((string) $item->item_type),
                    'original_file' => $this->serializeFile($item->originalRequestFile ?? $item->originalAssetFile),
                    'replacement_file' => $this->serializeFile($item->replacementRequestFile ?? $item->replacementAssetFile),
                ])->values(),
            ],
            'submit_url' => route('appraisal.revisions.submit', ['id' => $record->id]),
            'back_url' => route('appraisal.show', ['id' => $record->id]),
        ];
    }

    public function submitOpenBatch(AppraisalRequest $record, int $actorId, array $replacementFiles): AppraisalRequestRevisionBatch
    {
        $batch = $this->resolveOpenBatch($record);
        if (! $batch) {
            throw new RuntimeException('Tidak ada permintaan revisi dokumen yang aktif untuk request ini.');
        }

        foreach ($batch->items as $item) {
            if (! array_key_exists($item->id, $replacementFiles) || ! $replacementFiles[$item->id] instanceof UploadedFile) {
                throw new RuntimeException('Semua dokumen revisi yang diminta harus diunggah sebelum dikirim.');
            }
        }

        return DB::transaction(function () use ($record, $actorId, $batch, $replacementFiles): AppraisalRequestRevisionBatch {
            foreach ($batch->items as $item) {
                $file = $replacementFiles[$item->id];

                if ($item->item_type === 'request_file') {
                    $stored = $record->files()->create([
                        'type' => $item->requested_file_type,
                        'path' => $this->storeFile(
                            $file,
                            "appraisal-requests/{$record->id}/revisions/batch-{$batch->id}/request",
                            'revision-' . $item->requested_file_type
                        ),
                        'original_name' => $file->getClientOriginalName(),
                        'mime' => $file->getMimeType(),
                        'size' => $file->getSize(),
                    ]);

                    $item->update([
                        'replacement_request_file_id' => $stored->id,
                        'replacement_asset_file_id' => null,
                        'status' => 'reuploaded',
                    ]);

                    continue;
                }

                $asset = $item->appraisalAsset;
                if (! $asset) {
                    throw new RuntimeException('Aset revisi untuk salah satu item tidak ditemukan.');
                }

                $stored = $asset->files()->create([
                    'type' => $item->requested_file_type,
                    'path' => $this->storeFile(
                        $file,
                        "appraisal-requests/{$record->id}/revisions/batch-{$batch->id}/assets/{$asset->id}",
                        'revision-' . $item->requested_file_type
                    ),
                    'original_name' => $file->getClientOriginalName(),
                    'mime' => $file->getMimeType(),
                    'size' => $file->getSize(),
                ]);

                $item->update([
                    'replacement_asset_file_id' => $stored->id,
                    'replacement_request_file_id' => null,
                    'status' => 'reuploaded',
                ]);
            }

            $batch->update([
                'status' => 'submitted',
                'submitted_by' => $actorId,
                'submitted_at' => now(),
            ]);

            $record->update([
                'status' => AppraisalStatusEnum::Submitted,
                'verified_at' => null,
            ]);

            return $batch->fresh([
                'items.appraisalAsset',
                'items.originalRequestFile',
                'items.originalAssetFile',
                'items.replacementRequestFile',
                'items.replacementAssetFile',
            ]);
        });
    }

    private function storeFile(UploadedFile $file, string $directory, string $prefix): string
    {
        $extension = $file->getClientOriginalExtension() ?: 'bin';
        $name = $prefix . '-' . Str::uuid() . '.' . $extension;

        return $file->storeAs($directory, $name, 'public');
    }

    private function serializeFile(object|null $file): ?array
    {
        if (! $file || ! $file->path) {
            return null;
        }

        return [
            'id' => $file->id,
            'original_name' => $file->original_name ?: basename((string) $file->path),
            'mime' => $file->mime,
            'url' => Storage::disk('public')->url($file->path),
            'created_at' => $file->created_at?->toDateTimeString(),
        ];
    }

    private function targetLabel(object $item): string
    {
        $prefix = match ((string) $item->item_type) {
            'request_file' => 'Dokumen Request',
            'asset_document' => 'Dokumen Aset',
            'asset_photo' => 'Foto Aset',
            default => 'Dokumen',
        };

        $typeLabel = match ((string) $item->requested_file_type) {
            'npwp' => 'NPWP',
            'representative' => 'Surat Kuasa',
            'permission' => 'Surat Izin',
            'other_request_document' => 'Lampiran Request',
            'doc_pbb' => 'PBB',
            'doc_imb' => 'IMB / PBG',
            'doc_certs' => 'Sertifikat',
            'photo_access_road' => 'Foto Akses Jalan',
            'photo_front' => 'Foto Depan',
            'photo_interior' => 'Foto Dalam',
            default => Str::headline((string) $item->requested_file_type),
        };

        return "{$prefix}: {$typeLabel}";
    }

    private function acceptForItemType(string $itemType): string
    {
        return $itemType === 'asset_photo'
            ? '.jpg,.jpeg,.png,.webp'
            : '.pdf,.jpg,.jpeg,.png';
    }
}

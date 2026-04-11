<?php

namespace App\Services\Backups;

use App\Models\AppraisalAsset;
use App\Models\AppraisalAssetFile;
use App\Models\AppraisalFieldChangeLog;
use App\Models\AppraisalOfferNegotiation;
use App\Models\AppraisalRequest;
use App\Models\AppraisalRequestFile;
use App\Models\AppraisalRequestRevisionBatch;
use App\Models\AppraisalRequestRevisionItem;
use App\Models\ConstructionCostIndex;
use App\Models\GuidelineSet;
use App\Models\Payment;
use App\Models\ReportSigner;
use App\Models\User;
use App\Services\Customer\Payloads\AppraisalPayloadFormatter;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;
use ZipArchive;

class AppraisalRequestBackupService
{
    private const BACKUP_TYPE = 'appraisal_request_v1';
    private const SCHEMA_VERSION = 1;
    private const SOURCE_APP = 'digipro';

    private const EXCLUDED_CATEGORIES = [
        'legal_final_documents',
        'report_pdfs',
        'billing_upload_documents',
        'tax_invoice_documents',
        'withholding_receipt_documents',
        'secondary_content_media',
    ];

    private const INCLUDED_CATEGORIES = [
        'client_request_uploads',
        'asset_documents_and_photos',
        'revision_file_history',
        'request_and_asset_metadata',
        'payment_metadata',
        'negotiation_metadata',
        'field_change_logs',
    ];

    private const REQUIRED_DATA_FILES = [
        'data/request.json',
        'data/assets.json',
        'data/request_files.json',
        'data/asset_files.json',
        'data/revision_batches.json',
        'data/revision_items.json',
        'data/payments.json',
        'data/offer_negotiations.json',
        'data/field_change_logs.json',
    ];

    public function __construct(
        private readonly AppraisalPayloadFormatter $formatter,
    ) {
    }

    public function createBackupZip(AppraisalRequest $record): array
    {
        $this->loadBackupRelations($record);

        $requestFiles = $record->files
            ->filter(fn (AppraisalRequestFile $file) => in_array((string) $file->type, $this->formatter->customerRequestFileTypes(), true))
            ->sortBy('id')
            ->values();
        $assetFiles = $record->assets
            ->flatMap(fn (AppraisalAsset $asset) => $asset->files)
            ->sortBy('id')
            ->values();
        $revisionBatches = $record->revisionBatches->sortBy('id')->values();
        $revisionItems = $revisionBatches->flatMap(fn (AppraisalRequestRevisionBatch $batch) => $batch->items)->sortBy('id')->values();
        $payments = $record->payments->sortBy('id')->values();
        $offerNegotiations = $record->offerNegotiations->sortBy('id')->values();
        $fieldChangeLogs = $record->fieldChangeLogs->sortBy('id')->values();

        $uuid = (string) Str::uuid();
        $buildRelative = "backups/tmp/export-{$uuid}";
        $zipRelative = "backups/tmp/export-{$uuid}.zip";
        $buildPath = Storage::disk('local')->path($buildRelative);
        $zipPath = Storage::disk('local')->path($zipRelative);

        File::ensureDirectoryExists($buildPath);
        File::ensureDirectoryExists(dirname($zipPath));

        $checksums = [];
        $binaryFileCount = 0;

        try {
            $requestPayload = $this->serializeRequest($record);
            $assetsPayload = $record->assets
                ->sortBy('id')
                ->values()
                ->map(fn (AppraisalAsset $asset) => $this->serializeModel($asset))
                ->all();
            $requestFilesPayload = $requestFiles
                ->map(fn (AppraisalRequestFile $file) => $this->serializeRequestFile($file))
                ->all();
            $assetFilesPayload = $assetFiles
                ->map(fn (AppraisalAssetFile $file) => $this->serializeAssetFile($file))
                ->all();
            $revisionBatchesPayload = $revisionBatches
                ->map(fn (AppraisalRequestRevisionBatch $batch) => $this->serializeModel($batch))
                ->all();
            $revisionItemsPayload = $revisionItems
                ->map(fn (AppraisalRequestRevisionItem $item) => $this->serializeModel($item))
                ->all();
            $paymentsPayload = $payments
                ->map(fn (Payment $payment) => $this->serializeModel($payment))
                ->all();
            $offerNegotiationsPayload = $offerNegotiations
                ->map(fn (AppraisalOfferNegotiation $negotiation) => $this->serializeModel($negotiation))
                ->all();
            $fieldChangeLogsPayload = $fieldChangeLogs
                ->map(fn (AppraisalFieldChangeLog $log) => $this->serializeModel($log))
                ->all();

            $dataFiles = [
                'data/request.json' => $requestPayload,
                'data/assets.json' => $assetsPayload,
                'data/request_files.json' => $requestFilesPayload,
                'data/asset_files.json' => $assetFilesPayload,
                'data/revision_batches.json' => $revisionBatchesPayload,
                'data/revision_items.json' => $revisionItemsPayload,
                'data/payments.json' => $paymentsPayload,
                'data/offer_negotiations.json' => $offerNegotiationsPayload,
                'data/field_change_logs.json' => $fieldChangeLogsPayload,
            ];

            foreach ($dataFiles as $relativePath => $payload) {
                $fullPath = $buildPath . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relativePath);
                File::ensureDirectoryExists(dirname($fullPath));
                File::put($fullPath, json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
                $checksums[$relativePath] = hash_file('sha256', $fullPath);
            }

            foreach ($requestFilesPayload as $fileRow) {
                $this->copyBinaryIntoBackup($buildPath, $fileRow['path'], $fileRow['backup_entry']);
                $checksums[$fileRow['backup_entry']] = hash_file('sha256', $buildPath . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $fileRow['backup_entry']));
                $binaryFileCount++;
            }

            foreach ($assetFilesPayload as $fileRow) {
                $this->copyBinaryIntoBackup($buildPath, $fileRow['path'], $fileRow['backup_entry']);
                $checksums[$fileRow['backup_entry']] = hash_file('sha256', $buildPath . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $fileRow['backup_entry']));
                $binaryFileCount++;
            }

            $manifest = [
                'backup_type' => self::BACKUP_TYPE,
                'schema_version' => self::SCHEMA_VERSION,
                'generated_at' => now()->toIso8601String(),
                'source_app' => self::SOURCE_APP,
                'request_number' => $record->request_number,
                'source_request_id' => (int) $record->id,
                'categories_included' => self::INCLUDED_CATEGORIES,
                'excluded_categories' => self::EXCLUDED_CATEGORIES,
                'file_count' => $binaryFileCount,
                'data_files' => array_keys($dataFiles),
                'checksums' => $checksums,
            ];

            File::put(
                $buildPath . DIRECTORY_SEPARATOR . 'manifest.json',
                json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
            );

            $zip = new ZipArchive();
            if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
                throw new RuntimeException('Gagal membuat arsip backup ZIP.');
            }

            foreach ($this->listRelativeFiles($buildPath) as $relativePath) {
                $zip->addFile(
                    $buildPath . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relativePath),
                    $relativePath
                );
            }

            $zip->close();
        } catch (Throwable $e) {
            File::delete($zipPath);
            File::deleteDirectory($buildPath);

            throw $e;
        }

        File::deleteDirectory($buildPath);

        return [
            'path' => $zipPath,
            'download_name' => 'backup-appraisal-' . $this->safeName($record->request_number ?: ('REQ-' . $record->id)) . '.zip',
        ];
    }

    public function restoreFromUploadedZip(UploadedFile $uploadedFile): array
    {
        $uuid = (string) Str::uuid();
        $zipRelative = "backups/tmp/import-{$uuid}.zip";
        $extractRelative = "backups/tmp/extract-{$uuid}";
        $zipPath = Storage::disk('local')->path($zipRelative);
        $extractPath = Storage::disk('local')->path($extractRelative);

        File::ensureDirectoryExists(dirname($zipPath));
        File::ensureDirectoryExists($extractPath);
        File::copy($uploadedFile->getRealPath(), $zipPath);

        try {
            $zip = new ZipArchive();
            if ($zip->open($zipPath) !== true) {
                throw new RuntimeException('File ZIP backup tidak dapat dibuka.');
            }

            if (! $zip->extractTo($extractPath)) {
                $zip->close();
                throw new RuntimeException('File ZIP backup tidak dapat diekstrak.');
            }

            $zip->close();

            $manifestPath = $extractPath . DIRECTORY_SEPARATOR . 'manifest.json';
            if (! File::exists($manifestPath)) {
                throw new RuntimeException('Manifest backup tidak ditemukan.');
            }

            $manifest = json_decode((string) File::get($manifestPath), true);
            if (! is_array($manifest)) {
                throw new RuntimeException('Manifest backup tidak valid.');
            }

            $this->validateManifest($manifest);
            $this->validateChecksums($extractPath, $manifest);

            $datasets = $this->loadDatasets($extractPath);
            $this->validateDatasets($datasets, $manifest);

            return $this->restoreDatasets($extractPath, $datasets);
        } finally {
            File::delete($zipPath);
            File::deleteDirectory($extractPath);
        }
    }

    private function loadBackupRelations(AppraisalRequest $record): void
    {
        $record->loadMissing([
            'assets',
            'assets.files',
            'files',
            'payments',
            'offerNegotiations',
            'revisionBatches',
            'revisionBatches.items',
            'fieldChangeLogs',
        ]);
    }

    private function serializeRequest(AppraisalRequest $record): array
    {
        return [
            ...$this->serializeModel($record),
            'backup_scope' => 'appraisal_request',
        ];
    }

    private function serializeRequestFile(AppraisalRequestFile $file): array
    {
        $payload = $this->serializeModel($file);
        $payload['backup_entry'] = $this->requestFileEntryPath($file);
        $payload['backup_scope'] = str_contains($payload['backup_entry'], 'files/revisions/') ? 'revision' : 'request';

        return $payload;
    }

    private function serializeAssetFile(AppraisalAssetFile $file): array
    {
        $payload = $this->serializeModel($file);
        $payload['backup_entry'] = $this->assetFileEntryPath($file);
        $payload['backup_scope'] = str_contains($payload['backup_entry'], 'files/revisions/') ? 'revision' : 'asset';

        return $payload;
    }

    private function serializeModel(object $model): array
    {
        if (! method_exists($model, 'attributesToArray')) {
            throw new RuntimeException('Object backup tidak mendukung serialisasi atribut.');
        }

        $attributes = $model->attributesToArray();
        $attributes['original_id'] = (int) Arr::pull($attributes, 'id');

        return $attributes;
    }

    private function requestFileEntryPath(AppraisalRequestFile $file): string
    {
        $fileName = $this->backupFileName(
            (int) $file->id,
            (string) $file->type,
            (string) ($file->original_name ?: basename((string) $file->path))
        );

        if ($this->isRevisionPath((string) $file->path)) {
            return "files/revisions/request/{$fileName}";
        }

        return "files/request/{$fileName}";
    }

    private function assetFileEntryPath(AppraisalAssetFile $file): string
    {
        $fileName = $this->backupFileName(
            (int) $file->id,
            (string) $file->type,
            (string) ($file->original_name ?: basename((string) $file->path))
        );
        $assetId = (int) $file->appraisal_asset_id;

        if ($this->isRevisionPath((string) $file->path)) {
            return "files/revisions/assets/{$assetId}/{$fileName}";
        }

        return "files/assets/{$assetId}/{$fileName}";
    }

    private function backupFileName(int $id, string $type, string $originalName): string
    {
        $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION) ?: pathinfo($type, PATHINFO_EXTENSION) ?: 'bin');
        $baseName = Str::limit(Str::slug(pathinfo($originalName, PATHINFO_FILENAME) ?: $type, '-'), 80, '');

        if ($baseName === '') {
            $baseName = Str::slug($type, '-');
        }

        return "{$id}-{$baseName}.{$extension}";
    }

    private function copyBinaryIntoBackup(string $buildPath, string $storedPath, string $backupEntry): void
    {
        if ($storedPath === '' || ! Storage::disk('public')->exists($storedPath)) {
            throw new RuntimeException("File sumber backup tidak ditemukan: {$storedPath}");
        }

        $targetPath = $buildPath . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $backupEntry);
        File::ensureDirectoryExists(dirname($targetPath));
        File::copy(Storage::disk('public')->path($storedPath), $targetPath);
    }

    private function listRelativeFiles(string $rootPath): array
    {
        return collect(File::allFiles($rootPath))
            ->map(function (\SplFileInfo $file) use ($rootPath): string {
                $relative = str_replace($rootPath . DIRECTORY_SEPARATOR, '', $file->getPathname());

                return str_replace(DIRECTORY_SEPARATOR, '/', $relative);
            })
            ->sort()
            ->values()
            ->all();
    }

    private function validateManifest(array $manifest): void
    {
        if (($manifest['backup_type'] ?? null) !== self::BACKUP_TYPE) {
            throw new RuntimeException('Tipe backup tidak dikenali.');
        }

        if ((int) ($manifest['schema_version'] ?? 0) !== self::SCHEMA_VERSION) {
            throw new RuntimeException('Versi schema backup tidak didukung.');
        }

        if (($manifest['source_app'] ?? null) !== self::SOURCE_APP) {
            throw new RuntimeException('Backup bukan berasal dari aplikasi ini.');
        }

        if (! filled($manifest['request_number'] ?? null)) {
            throw new RuntimeException('Manifest backup tidak memiliki request number.');
        }

        foreach (self::REQUIRED_DATA_FILES as $requiredFile) {
            if (! in_array($requiredFile, $manifest['data_files'] ?? [], true)) {
                throw new RuntimeException("Data backup wajib tidak ditemukan: {$requiredFile}");
            }
        }
    }

    private function validateChecksums(string $extractPath, array $manifest): void
    {
        $checksums = $manifest['checksums'] ?? [];

        if (! is_array($checksums) || $checksums === []) {
            throw new RuntimeException('Manifest backup tidak memiliki checksum.');
        }

        foreach ($checksums as $relativePath => $expectedHash) {
            $fullPath = $extractPath . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relativePath);

            if (! File::exists($fullPath)) {
                throw new RuntimeException("Entry backup tidak ditemukan: {$relativePath}");
            }

            if (hash_file('sha256', $fullPath) !== $expectedHash) {
                throw new RuntimeException("Checksum backup tidak cocok untuk {$relativePath}");
            }
        }
    }

    private function loadDatasets(string $extractPath): array
    {
        $datasets = [];

        foreach (self::REQUIRED_DATA_FILES as $relativePath) {
            $fullPath = $extractPath . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relativePath);
            $decoded = json_decode((string) File::get($fullPath), true);

            if (! is_array($decoded)) {
                throw new RuntimeException("Dataset backup tidak valid: {$relativePath}");
            }

            $datasets[$relativePath] = $decoded;
        }

        return [
            'request' => $datasets['data/request.json'],
            'assets' => $datasets['data/assets.json'],
            'request_files' => $datasets['data/request_files.json'],
            'asset_files' => $datasets['data/asset_files.json'],
            'revision_batches' => $datasets['data/revision_batches.json'],
            'revision_items' => $datasets['data/revision_items.json'],
            'payments' => $datasets['data/payments.json'],
            'offer_negotiations' => $datasets['data/offer_negotiations.json'],
            'field_change_logs' => $datasets['data/field_change_logs.json'],
        ];
    }

    private function validateDatasets(array $datasets, array $manifest): void
    {
        if (! is_array($datasets['request'] ?? null)) {
            throw new RuntimeException('Dataset request backup tidak valid.');
        }

        if (($datasets['request']['request_number'] ?? null) !== ($manifest['request_number'] ?? null)) {
            throw new RuntimeException('Request number pada manifest tidak cocok dengan snapshot data.');
        }

        foreach ([
            'assets',
            'request_files',
            'asset_files',
            'revision_batches',
            'revision_items',
            'payments',
            'offer_negotiations',
            'field_change_logs',
        ] as $key) {
            if (! is_array($datasets[$key] ?? null)) {
                throw new RuntimeException("Dataset backup tidak valid: {$key}");
            }
        }

        $assetIds = collect($datasets['assets'])
            ->map(fn (array $row) => $this->requireOriginalId($row, 'assets'))
            ->all();
        $requestFileIds = collect($datasets['request_files'])
            ->map(fn (array $row) => $this->requireOriginalId($row, 'request_files'))
            ->all();
        $assetFileIds = collect($datasets['asset_files'])
            ->map(fn (array $row) => $this->requireOriginalId($row, 'asset_files'))
            ->all();
        $revisionBatchIds = collect($datasets['revision_batches'])
            ->map(fn (array $row) => $this->requireOriginalId($row, 'revision_batches'))
            ->all();
        $revisionItemIds = collect($datasets['revision_items'])
            ->map(fn (array $row) => $this->requireOriginalId($row, 'revision_items'))
            ->all();

        if (count($assetIds) !== count(array_unique($assetIds))) {
            throw new RuntimeException('Dataset asset backup memiliki original_id duplikat.');
        }

        if (count($requestFileIds) !== count(array_unique($requestFileIds))) {
            throw new RuntimeException('Dataset request file backup memiliki original_id duplikat.');
        }

        if (count($assetFileIds) !== count(array_unique($assetFileIds))) {
            throw new RuntimeException('Dataset asset file backup memiliki original_id duplikat.');
        }

        foreach ($datasets['request_files'] as $row) {
            if (! filled($row['backup_entry'] ?? null)) {
                throw new RuntimeException('Snapshot request file tidak memiliki entry file backup.');
            }
        }

        foreach ($datasets['asset_files'] as $row) {
            if (! filled($row['backup_entry'] ?? null)) {
                throw new RuntimeException('Snapshot asset file tidak memiliki entry file backup.');
            }

            if (! in_array((int) ($row['appraisal_asset_id'] ?? 0), $assetIds, true)) {
                throw new RuntimeException('Snapshot asset file mengacu ke asset yang tidak ada.');
            }
        }

        foreach ($datasets['revision_items'] as $row) {
            if (! in_array((int) ($row['revision_batch_id'] ?? 0), $revisionBatchIds, true)) {
                throw new RuntimeException('Snapshot revision item mengacu ke batch yang tidak ada.');
            }

            if (
                isset($row['appraisal_asset_id'])
                && $row['appraisal_asset_id'] !== null
                && ! in_array((int) $row['appraisal_asset_id'], $assetIds, true)
            ) {
                throw new RuntimeException('Snapshot revision item mengacu ke asset yang tidak ada.');
            }

            foreach ([
                'original_request_file_id' => $requestFileIds,
                'replacement_request_file_id' => $requestFileIds,
                'original_asset_file_id' => $assetFileIds,
                'replacement_asset_file_id' => $assetFileIds,
            ] as $column => $availableIds) {
                if (
                    isset($row[$column])
                    && $row[$column] !== null
                    && ! in_array((int) $row[$column], $availableIds, true)
                ) {
                    throw new RuntimeException("Snapshot revision item memiliki referensi file tidak valid pada {$column}.");
                }
            }
        }

        foreach ($datasets['field_change_logs'] as $row) {
            if (isset($row['revision_batch_id']) && $row['revision_batch_id'] !== null && ! in_array((int) $row['revision_batch_id'], $revisionBatchIds, true)) {
                throw new RuntimeException('Snapshot field change log mengacu ke revision batch yang tidak ada.');
            }

            if (isset($row['revision_item_id']) && $row['revision_item_id'] !== null && ! in_array((int) $row['revision_item_id'], $revisionItemIds, true)) {
                throw new RuntimeException('Snapshot field change log mengacu ke revision item yang tidak ada.');
            }
        }

        $binaryEntries = collect([...$datasets['request_files'], ...$datasets['asset_files']])->count();
        if ((int) ($manifest['file_count'] ?? -1) !== $binaryEntries) {
            throw new RuntimeException('Jumlah file pada manifest tidak cocok dengan isi dataset backup.');
        }
    }

    private function restoreDatasets(string $extractPath, array $datasets): array
    {
        $requestPayload = $this->normalizeRequestPayload($datasets['request']);

        if (AppraisalRequest::query()->where('request_number', $requestPayload['request_number'])->exists()) {
            throw new RuntimeException("Restore ditolak karena request {$requestPayload['request_number']} masih ada di database.");
        }

        if (
            filled($requestPayload['contract_number'] ?? null)
            && AppraisalRequest::query()->where('contract_number', $requestPayload['contract_number'])->exists()
        ) {
            throw new RuntimeException("Restore ditolak karena nomor kontrak {$requestPayload['contract_number']} sudah dipakai request lain.");
        }

        $writtenFiles = [];

        try {
            return DB::transaction(function () use ($extractPath, $datasets, $requestPayload, &$writtenFiles): array {
                $requestRecord = new AppraisalRequest();
                $requestRecord->forceFill($requestPayload);
                $requestRecord->saveQuietly();

                $assetIdMap = [];
                foreach ($datasets['assets'] as $assetRow) {
                    $asset = new AppraisalAsset();
                    $asset->forceFill($this->normalizeAssetPayload($assetRow, (int) $requestRecord->id));
                    $asset->saveQuietly();

                    $assetIdMap[(int) $assetRow['original_id']] = (int) $asset->id;
                }

                $requestFileIdMap = [];
                foreach ($datasets['request_files'] as $fileRow) {
                    $path = $this->restorePublicFile(
                        $extractPath,
                        (string) $fileRow['backup_entry'],
                        $this->newRequestFilePath(
                            (int) $requestRecord->id,
                            (string) ($fileRow['type'] ?? 'file'),
                            (string) ($fileRow['original_name'] ?? ''),
                            (string) ($fileRow['backup_entry'] ?? '')
                        ),
                        $writtenFiles
                    );

                    $requestFile = new AppraisalRequestFile();
                    $requestFile->forceFill(
                        $this->normalizeRequestFilePayload($fileRow, (int) $requestRecord->id, $path)
                    );
                    $requestFile->saveQuietly();

                    $requestFileIdMap[(int) $fileRow['original_id']] = (int) $requestFile->id;
                }

                $assetFileIdMap = [];
                foreach ($datasets['asset_files'] as $fileRow) {
                    $originalAssetId = (int) ($fileRow['appraisal_asset_id'] ?? 0);
                    $newAssetId = $assetIdMap[$originalAssetId] ?? null;

                    if (! $newAssetId) {
                        throw new RuntimeException('Restore asset file gagal karena mapping asset tidak ditemukan.');
                    }

                    $path = $this->restorePublicFile(
                        $extractPath,
                        (string) $fileRow['backup_entry'],
                        $this->newAssetFilePath(
                            (int) $requestRecord->id,
                            $newAssetId,
                            (string) ($fileRow['type'] ?? 'file'),
                            (string) ($fileRow['original_name'] ?? ''),
                            (string) ($fileRow['backup_entry'] ?? '')
                        ),
                        $writtenFiles
                    );

                    $assetFile = new AppraisalAssetFile();
                    $assetFile->forceFill(
                        $this->normalizeAssetFilePayload($fileRow, $newAssetId, $path)
                    );
                    $assetFile->saveQuietly();

                    $assetFileIdMap[(int) $fileRow['original_id']] = (int) $assetFile->id;
                }

                $revisionBatchIdMap = [];
                foreach ($datasets['revision_batches'] as $batchRow) {
                    $batch = new AppraisalRequestRevisionBatch();
                    $batch->forceFill(
                        $this->normalizeRevisionBatchPayload($batchRow, (int) $requestRecord->id)
                    );
                    $batch->saveQuietly();

                    $revisionBatchIdMap[(int) $batchRow['original_id']] = (int) $batch->id;
                }

                $revisionItemIdMap = [];
                foreach ($datasets['revision_items'] as $itemRow) {
                    $item = new AppraisalRequestRevisionItem();
                    $item->forceFill(
                        $this->normalizeRevisionItemPayload(
                            $itemRow,
                            $assetIdMap,
                            $requestFileIdMap,
                            $assetFileIdMap,
                            $revisionBatchIdMap
                        )
                    );
                    $item->saveQuietly();

                    $revisionItemIdMap[(int) $itemRow['original_id']] = (int) $item->id;
                }

                foreach ($datasets['payments'] as $paymentRow) {
                    $payment = new Payment();
                    $payment->forceFill(
                        $this->normalizePaymentPayload($paymentRow, (int) $requestRecord->id)
                    );
                    $payment->saveQuietly();
                }

                foreach ($datasets['offer_negotiations'] as $negotiationRow) {
                    $negotiation = new AppraisalOfferNegotiation();
                    $negotiation->forceFill(
                        $this->normalizeOfferNegotiationPayload($negotiationRow, (int) $requestRecord->id)
                    );
                    $negotiation->saveQuietly();
                }

                foreach ($datasets['field_change_logs'] as $logRow) {
                    $log = new AppraisalFieldChangeLog();
                    $log->forceFill(
                        $this->normalizeFieldChangeLogPayload(
                            $logRow,
                            (int) $requestRecord->id,
                            $assetIdMap,
                            $revisionBatchIdMap,
                            $revisionItemIdMap
                        )
                    );
                    $log->saveQuietly();
                }

                return [
                    'request_id' => (int) $requestRecord->id,
                    'request_number' => (string) $requestRecord->request_number,
                    'assets_count' => count($assetIdMap),
                    'request_files_count' => count($requestFileIdMap),
                    'asset_files_count' => count($assetFileIdMap),
                    'revision_items_count' => count($revisionItemIdMap),
                ];
            });
        } catch (Throwable $e) {
            foreach ($writtenFiles as $path) {
                Storage::disk('public')->delete($path);
            }

            throw $e;
        }
    }

    private function normalizeRequestPayload(array $payload): array
    {
        $requestNumber = (string) ($payload['request_number'] ?? '');
        $userId = (int) ($payload['user_id'] ?? 0);

        if ($requestNumber === '') {
            throw new RuntimeException('Snapshot request tidak memiliki request number.');
        }

        if (! User::query()->whereKey($userId)->exists()) {
            throw new RuntimeException('Restore ditolak karena pemilik request asli sudah tidak tersedia.');
        }

        return [
            ...Arr::except($payload, ['original_id', 'backup_scope']),
            'user_id' => $userId,
            'guideline_set_id' => $this->existingIdOrNull(GuidelineSet::class, $payload['guideline_set_id'] ?? null),
            'report_generated_by' => $this->existingIdOrNull(User::class, $payload['report_generated_by'] ?? null),
            'cancelled_by' => $this->existingIdOrNull(User::class, $payload['cancelled_by'] ?? null),
            'physical_report_printed_by' => $this->existingIdOrNull(User::class, $payload['physical_report_printed_by'] ?? null),
            'report_reviewer_signer_id' => $this->existingIdOrNull(ReportSigner::class, $payload['report_reviewer_signer_id'] ?? null),
            'report_public_appraiser_signer_id' => $this->existingIdOrNull(ReportSigner::class, $payload['report_public_appraiser_signer_id'] ?? null),
            'report_draft_generated_at' => null,
            'report_draft_pdf_path' => null,
            'report_draft_pdf_size' => null,
            'report_generated_at' => null,
            'report_pdf_path' => null,
            'report_pdf_size' => null,
            'billing_invoice_file_path' => null,
            'tax_invoice_file_path' => null,
            'withholding_receipt_file_path' => null,
        ];
    }

    private function normalizeAssetPayload(array $payload, int $requestId): array
    {
        return [
            ...Arr::except($payload, ['original_id']),
            'appraisal_request_id' => $requestId,
            'ikk_ref_id' => $this->existingIdOrNull(ConstructionCostIndex::class, $payload['ikk_ref_id'] ?? null),
        ];
    }

    private function normalizeRequestFilePayload(array $payload, int $requestId, string $path): array
    {
        return [
            ...Arr::except($payload, ['original_id', 'backup_entry', 'backup_scope']),
            'appraisal_request_id' => $requestId,
            'path' => $path,
        ];
    }

    private function normalizeAssetFilePayload(array $payload, int $assetId, string $path): array
    {
        return [
            ...Arr::except($payload, ['original_id', 'backup_entry', 'backup_scope']),
            'appraisal_asset_id' => $assetId,
            'path' => $path,
        ];
    }

    private function normalizeRevisionBatchPayload(array $payload, int $requestId): array
    {
        return [
            ...Arr::except($payload, ['original_id']),
            'appraisal_request_id' => $requestId,
            'created_by' => $this->existingIdOrNull(User::class, $payload['created_by'] ?? null),
            'submitted_by' => $this->existingIdOrNull(User::class, $payload['submitted_by'] ?? null),
            'reviewed_by' => $this->existingIdOrNull(User::class, $payload['reviewed_by'] ?? null),
        ];
    }

    private function normalizeRevisionItemPayload(
        array $payload,
        array $assetIdMap,
        array $requestFileIdMap,
        array $assetFileIdMap,
        array $revisionBatchIdMap
    ): array {
        $revisionBatchId = $revisionBatchIdMap[(int) ($payload['revision_batch_id'] ?? 0)] ?? null;

        if (! $revisionBatchId) {
            throw new RuntimeException('Restore revision item gagal karena mapping batch tidak ditemukan.');
        }

        return [
            ...Arr::except($payload, ['original_id']),
            'revision_batch_id' => $revisionBatchId,
            'appraisal_asset_id' => isset($payload['appraisal_asset_id']) && $payload['appraisal_asset_id'] !== null
                ? ($assetIdMap[(int) $payload['appraisal_asset_id']] ?? null)
                : null,
            'original_request_file_id' => isset($payload['original_request_file_id']) && $payload['original_request_file_id'] !== null
                ? ($requestFileIdMap[(int) $payload['original_request_file_id']] ?? null)
                : null,
            'replacement_request_file_id' => isset($payload['replacement_request_file_id']) && $payload['replacement_request_file_id'] !== null
                ? ($requestFileIdMap[(int) $payload['replacement_request_file_id']] ?? null)
                : null,
            'original_asset_file_id' => isset($payload['original_asset_file_id']) && $payload['original_asset_file_id'] !== null
                ? ($assetFileIdMap[(int) $payload['original_asset_file_id']] ?? null)
                : null,
            'replacement_asset_file_id' => isset($payload['replacement_asset_file_id']) && $payload['replacement_asset_file_id'] !== null
                ? ($assetFileIdMap[(int) $payload['replacement_asset_file_id']] ?? null)
                : null,
            'reviewed_by' => $this->existingIdOrNull(User::class, $payload['reviewed_by'] ?? null),
        ];
    }

    private function normalizePaymentPayload(array $payload, int $requestId): array
    {
        return [
            ...Arr::except($payload, ['original_id']),
            'appraisal_request_id' => $requestId,
            'proof_file_path' => null,
            'proof_original_name' => null,
            'proof_mime' => null,
            'proof_size' => null,
        ];
    }

    private function normalizeOfferNegotiationPayload(array $payload, int $requestId): array
    {
        return [
            ...Arr::except($payload, ['original_id']),
            'appraisal_request_id' => $requestId,
            'user_id' => $this->existingIdOrNull(User::class, $payload['user_id'] ?? null),
        ];
    }

    private function normalizeFieldChangeLogPayload(
        array $payload,
        int $requestId,
        array $assetIdMap,
        array $revisionBatchIdMap,
        array $revisionItemIdMap
    ): array {
        return [
            ...Arr::except($payload, ['original_id']),
            'appraisal_request_id' => $requestId,
            'appraisal_asset_id' => isset($payload['appraisal_asset_id']) && $payload['appraisal_asset_id'] !== null
                ? ($assetIdMap[(int) $payload['appraisal_asset_id']] ?? null)
                : null,
            'revision_batch_id' => isset($payload['revision_batch_id']) && $payload['revision_batch_id'] !== null
                ? ($revisionBatchIdMap[(int) $payload['revision_batch_id']] ?? null)
                : null,
            'revision_item_id' => isset($payload['revision_item_id']) && $payload['revision_item_id'] !== null
                ? ($revisionItemIdMap[(int) $payload['revision_item_id']] ?? null)
                : null,
            'changed_by' => $this->existingIdOrNull(User::class, $payload['changed_by'] ?? null),
        ];
    }

    private function restorePublicFile(
        string $extractPath,
        string $backupEntry,
        string $newRelativePath,
        array &$writtenFiles
    ): string {
        $sourcePath = $extractPath . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $backupEntry);
        if (! File::exists($sourcePath)) {
            throw new RuntimeException("File backup tidak ditemukan untuk restore: {$backupEntry}");
        }

        $targetPath = Storage::disk('public')->path($newRelativePath);
        File::ensureDirectoryExists(dirname($targetPath));
        File::copy($sourcePath, $targetPath);
        $writtenFiles[] = $newRelativePath;

        return $newRelativePath;
    }

    private function newRequestFilePath(int $requestId, string $type, string $originalName, string $backupEntry): string
    {
        $extension = $this->fileExtension($originalName, $backupEntry);
        $fileName = Str::slug($type ?: 'file', '-') . '-' . Str::uuid() . '.' . $extension;
        $directory = $this->isRevisionEntry($backupEntry)
            ? "appraisal-requests/{$requestId}/revisions/restored/request/{$type}"
            : "appraisal-requests/{$requestId}/request/{$type}";

        return "{$directory}/{$fileName}";
    }

    private function newAssetFilePath(int $requestId, int $assetId, string $type, string $originalName, string $backupEntry): string
    {
        $extension = $this->fileExtension($originalName, $backupEntry);
        $fileName = Str::slug($type ?: 'file', '-') . '-' . Str::uuid() . '.' . $extension;

        if ($this->isRevisionEntry($backupEntry)) {
            return "appraisal-requests/{$requestId}/revisions/restored/assets/{$assetId}/{$fileName}";
        }

        $directory = $this->formatter->isPhotoFileType($type)
            ? $this->assetPhotoDirectory($type)
            : $this->assetDocumentDirectory($type);

        return "appraisal-requests/{$requestId}/assets/{$assetId}/{$directory}/{$fileName}";
    }

    private function fileExtension(string ...$candidates): string
    {
        foreach ($candidates as $candidate) {
            $extension = strtolower(pathinfo($candidate, PATHINFO_EXTENSION));

            if ($extension !== '') {
                return $extension;
            }
        }

        return 'bin';
    }

    private function assetPhotoDirectory(string $type): string
    {
        return match ($type) {
            'photo_access_road' => 'photos/access_road',
            'photo_front' => 'photos/front',
            'photo_interior' => 'photos/interior',
            default => 'photos/other',
        };
    }

    private function assetDocumentDirectory(string $type): string
    {
        return match ($type) {
            'doc_pbb' => 'documents/pbb',
            'doc_imb' => 'documents/imb',
            'doc_certs' => 'documents/certificate',
            default => 'documents/other',
        };
    }

    private function isRevisionEntry(string $backupEntry): bool
    {
        return str_starts_with($backupEntry, 'files/revisions/');
    }

    private function existingIdOrNull(string $modelClass, mixed $id): ?int
    {
        if (! filled($id)) {
            return null;
        }

        $normalized = (int) $id;

        return $modelClass::query()->whereKey($normalized)->exists()
            ? $normalized
            : null;
    }

    private function requireOriginalId(array $row, string $dataset): int
    {
        if (! isset($row['original_id'])) {
            throw new RuntimeException("Dataset {$dataset} tidak memiliki original_id.");
        }

        return (int) $row['original_id'];
    }

    private function isRevisionPath(string $path): bool
    {
        $normalized = str_replace('\\', '/', strtolower($path));

        return str_contains($normalized, '/revisions/');
    }

    private function safeName(string $value): string
    {
        return preg_replace('/[^A-Za-z0-9\\-_.]/', '-', $value);
    }
}

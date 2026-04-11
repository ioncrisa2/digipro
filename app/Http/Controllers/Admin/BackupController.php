<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\BackupIndexRequest;
use App\Http\Requests\Admin\RestoreAppraisalBackupRequest;
use App\Models\AppraisalAssetFile;
use App\Models\AppraisalRequest;
use App\Models\AppraisalRequestFile;
use App\Models\AppraisalRequestRevisionItem;
use App\Services\Backups\AppraisalRequestBackupService;
use App\Services\Customer\Payloads\AppraisalPayloadFormatter;
use Illuminate\Http\RedirectResponse;
use Inertia\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Throwable;

class BackupController extends Controller
{
    public function __construct(
        private readonly AppraisalPayloadFormatter $formatter,
    ) {
    }

    public function index(BackupIndexRequest $request): Response
    {
        $filters = $request->filters();
        $customerRequestFileTypes = $this->formatter->customerRequestFileTypes();

        $records = AppraisalRequest::query()
            ->with('user:id,name')
            ->withCount('assets')
            ->withCount([
                'files as request_files_count' => fn ($query) => $query->whereIn('type', $customerRequestFileTypes),
                'assetFiles as asset_files_count',
                'revisionBatches as revision_batches_count',
            ])
            ->when($filters['q'] !== '', function ($query) use ($filters): void {
                $query->where(function ($innerQuery) use ($filters): void {
                    $innerQuery
                        ->where('request_number', 'like', '%' . $filters['q'] . '%')
                        ->orWhere('client_name', 'like', '%' . $filters['q'] . '%')
                        ->orWhereHas('user', fn ($userQuery) => $userQuery->where('name', 'like', '%' . $filters['q'] . '%'));
                });
            })
            ->latest('updated_at')
            ->paginate($request->perPage())
            ->withQueryString();

        $records->through(fn (AppraisalRequest $record) => [
            'id' => (int) $record->id,
            'request_number' => (string) ($record->request_number ?: ('REQ-' . $record->id)),
            'client_name' => (string) ($record->client_name ?: '-'),
            'requester_name' => (string) ($record->user?->name ?: '-'),
            'status_label' => $record->status?->label() ?? '-',
            'status_value' => $record->status?->value ?? null,
            'assets_count' => (int) ($record->assets_count ?? 0),
            'request_files_count' => (int) ($record->request_files_count ?? 0),
            'asset_files_count' => (int) ($record->asset_files_count ?? 0),
            'revision_batches_count' => (int) ($record->revision_batches_count ?? 0),
            'requested_at' => $record->requested_at?->toIso8601String(),
            'updated_at' => $record->updated_at?->toIso8601String(),
            'download_url' => route('admin.backups.download', $record),
            'detail_url' => route('admin.appraisal-requests.show', $record),
        ]);

        return inertia('Admin/Backups/Index', [
            'filters' => $filters,
            'summary' => [
                'total_requests' => AppraisalRequest::query()->count(),
                'client_request_uploads' => AppraisalRequestFile::query()
                    ->whereIn('type', $customerRequestFileTypes)
                    ->count(),
                'asset_files' => AppraisalAssetFile::query()->count(),
                'revision_items' => AppraisalRequestRevisionItem::query()->count(),
            ],
            'records' => $this->paginatedRecordsPayload($records),
            'index_url' => route('admin.backups.index'),
            'restore' => [
                'url' => route('admin.backups.restore'),
                'max_upload_mb' => 100,
                'accept' => '.zip',
            ],
        ]);
    }

    public function download(
        AppraisalRequest $appraisalRequest,
        AppraisalRequestBackupService $backupService
    ): BinaryFileResponse {
        $archive = $backupService->createBackupZip($appraisalRequest);

        return response()
            ->download($archive['path'], $archive['download_name'])
            ->deleteFileAfterSend(true);
    }

    public function restore(
        RestoreAppraisalBackupRequest $request,
        AppraisalRequestBackupService $backupService
    ): RedirectResponse {
        try {
            $summary = $backupService->restoreFromUploadedZip($request->file('backup_zip'));
        } catch (Throwable $e) {
            report($e);

            return redirect()
                ->route('admin.backups.index')
                ->with('error', $e->getMessage() ?: 'Restore backup gagal diproses.');
        }

        return redirect()
            ->route('admin.backups.index')
            ->with('success', 'Backup request berhasil direstore ke data appraisal baru.')
            ->with('backup_restore_summary', $summary);
    }
}

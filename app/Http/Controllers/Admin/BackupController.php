<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\BackupIndexRequest;
use App\Http\Requests\Admin\RestoreAppraisalBackupRequest;
use App\Models\AppraisalRequest;
use App\Services\Admin\AdminBackupWorkspaceService;
use App\Services\Backups\AppraisalRequestBackupService;
use Illuminate\Http\RedirectResponse;
use Inertia\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Throwable;

class BackupController extends Controller
{
    public function __construct(
        private readonly AdminBackupWorkspaceService $workspaceService,
    ) {
    }

    public function index(BackupIndexRequest $request): Response
    {
        return inertia('Admin/Backups/Index', $this->workspaceService
            ->indexPayload($request->filters(), $request->perPage()));
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

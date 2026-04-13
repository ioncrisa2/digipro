<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ReportSignerIndexRequest;
use App\Http\Requests\Admin\StoreReportSignerRequest;
use App\Models\ReportSigner;
use App\Services\Admin\AdminReportSignerWorkspaceService;
use Illuminate\Http\RedirectResponse;
use Inertia\Response;

class ReportSignerController extends Controller
{
    public function __construct(
        private readonly AdminReportSignerWorkspaceService $workspaceService,
    ) {
    }

    public function index(ReportSignerIndexRequest $request): Response
    {
        return inertia('Admin/ReportSigners/Index', $this->workspaceService
            ->indexPayload($request->filters(), $request->perPage()));
    }

    public function create(): Response
    {
        return inertia('Admin/ReportSigners/Form', $this->workspaceService->createPayload());
    }

    public function store(StoreReportSignerRequest $request): RedirectResponse
    {
        $signer = $this->workspaceService->saveReportSigner($request->validated());

        return redirect()
            ->route('admin.master-data.report-signers.edit', $signer)
            ->with('success', 'Profil penandatangan report berhasil ditambahkan.');
    }

    public function edit(ReportSigner $reportSigner): Response
    {
        return inertia('Admin/ReportSigners/Form', $this->workspaceService->editPayload($reportSigner));
    }

    public function update(StoreReportSignerRequest $request, ReportSigner $reportSigner): RedirectResponse
    {
        $this->workspaceService->saveReportSigner($request->validated(), $reportSigner);

        return redirect()
            ->route('admin.master-data.report-signers.edit', $reportSigner)
            ->with('success', 'Profil penandatangan report berhasil diperbarui.');
    }

    public function destroy(ReportSigner $reportSigner): RedirectResponse
    {
        $this->workspaceService->deleteReportSigner($reportSigner);

        return redirect()
            ->route('admin.master-data.report-signers.index')
            ->with('success', 'Profil penandatangan report berhasil dihapus.');
    }
}

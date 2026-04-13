<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreAppraisalAssetFileRequest;
use App\Http\Requests\Admin\UpsertAppraisalAssetRequest;
use App\Models\AppraisalAsset;
use App\Models\AppraisalAssetFile;
use App\Models\AppraisalRequest;
use App\Services\Admin\AdminAppraisalAssetWorkspaceService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Response;

class AppraisalAssetController extends Controller
{
    public function __construct(
        private readonly AdminAppraisalAssetWorkspaceService $workspaceService,
    ) {
    }

    public function appraisalRequestAssetCreate(Request $request, AppraisalRequest $appraisalRequest): Response
    {
        return inertia('Admin/AppraisalRequests/AssetForm', $this->workspaceService
            ->createPagePayload($request, $appraisalRequest));
    }

    public function appraisalRequestAssetEdit(
        Request $request,
        AppraisalRequest $appraisalRequest,
        AppraisalAsset $asset
    ): Response {
        return inertia('Admin/AppraisalRequests/AssetForm', $this->workspaceService
            ->editPagePayload($request, $appraisalRequest, $asset));
    }

    public function storeAppraisalRequestAsset(
        UpsertAppraisalAssetRequest $request,
        AppraisalRequest $appraisalRequest
    ): RedirectResponse {
        $asset = $this->workspaceService->createAsset($appraisalRequest, $request->validated());

        return redirect()
            ->route('admin.appraisal-requests.show', $appraisalRequest)
            ->with('success', "Aset #{$asset->id} berhasil ditambahkan.");
    }

    public function updateAppraisalRequestAsset(
        UpsertAppraisalAssetRequest $request,
        AppraisalRequest $appraisalRequest,
        AppraisalAsset $asset
    ): RedirectResponse {
        $this->workspaceService->updateAsset($appraisalRequest, $asset, $request->validated());

        return redirect()
            ->route('admin.appraisal-requests.show', $appraisalRequest)
            ->with('success', 'Data aset berhasil diperbarui.');
    }

    public function destroyAppraisalRequestAsset(
        AppraisalRequest $appraisalRequest,
        AppraisalAsset $asset
    ): RedirectResponse {
        $this->workspaceService->deleteAsset($appraisalRequest, $asset);

        return back()->with('success', 'Aset berhasil dihapus.');
    }

    public function storeAppraisalAssetFile(
        StoreAppraisalAssetFileRequest $request,
        AppraisalRequest $appraisalRequest,
        AppraisalAsset $asset
    ): RedirectResponse {
        $this->workspaceService->storeAssetFile(
            $appraisalRequest,
            $asset,
            $request->validated(),
            $request->file('file')
        );

        return back()->with('success', 'File aset berhasil diunggah.');
    }

    public function destroyAppraisalAssetFile(
        AppraisalRequest $appraisalRequest,
        AppraisalAsset $asset,
        AppraisalAssetFile $file
    ): RedirectResponse {
        $this->workspaceService->deleteAssetFile($appraisalRequest, $asset, $file);

        return back()->with('success', 'File aset berhasil dihapus.');
    }
}

<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreAppraisalAssetFileRequest;
use App\Http\Requests\Admin\UpsertAppraisalAssetRequest;
use App\Models\AppraisalAsset;
use App\Models\AppraisalAssetFile;
use App\Models\AppraisalRequest;
use App\Support\Admin\AppraisalAssetFormBuilder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Response;

class AppraisalAssetController extends Controller
{
    public function __construct(
        private readonly AppraisalAssetFormBuilder $assetFormBuilder,
    ) {
    }

    public function appraisalRequestAssetCreate(Request $request, AppraisalRequest $appraisalRequest): Response
    {
        return inertia('Admin/AppraisalRequests/AssetForm', $this->assetFormBuilder->buildEditorProps($request, $appraisalRequest));
    }

    public function appraisalRequestAssetEdit(
        Request $request,
        AppraisalRequest $appraisalRequest,
        AppraisalAsset $asset
    ): Response {
        $this->assetFormBuilder->ensureBelongsToRequest($appraisalRequest, $asset);

        return inertia('Admin/AppraisalRequests/AssetForm', $this->assetFormBuilder->buildEditorProps($request, $appraisalRequest, $asset));
    }

    public function storeAppraisalRequestAsset(
        UpsertAppraisalAssetRequest $request,
        AppraisalRequest $appraisalRequest
    ): RedirectResponse {
        $asset = $appraisalRequest->assets()->create($this->assetFormBuilder->assetPayload($request->validated()));

        return redirect()
            ->route('admin.appraisal-requests.show', $appraisalRequest)
            ->with('success', "Aset #{$asset->id} berhasil ditambahkan.");
    }

    public function updateAppraisalRequestAsset(
        UpsertAppraisalAssetRequest $request,
        AppraisalRequest $appraisalRequest,
        AppraisalAsset $asset
    ): RedirectResponse {
        $this->assetFormBuilder->ensureBelongsToRequest($appraisalRequest, $asset);

        $asset->update($this->assetFormBuilder->assetPayload($request->validated()));

        return redirect()
            ->route('admin.appraisal-requests.show', $appraisalRequest)
            ->with('success', 'Data aset berhasil diperbarui.');
    }

    public function destroyAppraisalRequestAsset(
        AppraisalRequest $appraisalRequest,
        AppraisalAsset $asset
    ): RedirectResponse {
        $this->assetFormBuilder->ensureBelongsToRequest($appraisalRequest, $asset);

        foreach ($asset->files as $file) {
            Storage::disk('public')->delete($file->path);
        }

        $asset->delete();

        return back()->with('success', 'Aset berhasil dihapus.');
    }

    public function storeAppraisalAssetFile(
        StoreAppraisalAssetFileRequest $request,
        AppraisalRequest $appraisalRequest,
        AppraisalAsset $asset
    ): RedirectResponse {
        $this->assetFormBuilder->ensureBelongsToRequest($appraisalRequest, $asset);

        $validated = $request->validated();
        $file = $request->file('file');
        $directory = $this->assetFormBuilder->assetFileDirectory($validated['type']);
        $storedPath = $file->storeAs(
            "appraisal-requests/{$appraisalRequest->id}/assets/{$asset->id}/{$directory}",
            now()->format('YmdHis') . '-' . uniqid() . '.' . $file->getClientOriginalExtension(),
            'public'
        );

        $asset->files()->create([
            'type' => $validated['type'],
            'path' => $storedPath,
            'original_name' => $file->getClientOriginalName(),
            'mime' => $file->getMimeType(),
            'size' => $file->getSize(),
        ]);

        return back()->with('success', 'File aset berhasil diunggah.');
    }

    public function destroyAppraisalAssetFile(
        AppraisalRequest $appraisalRequest,
        AppraisalAsset $asset,
        AppraisalAssetFile $file
    ): RedirectResponse {
        $this->assetFormBuilder->ensureBelongsToRequest($appraisalRequest, $asset);
        abort_unless((int) $file->appraisal_asset_id === (int) $asset->id, 404);

        Storage::disk('public')->delete($file->path);
        $file->delete();

        return back()->with('success', 'File aset berhasil dihapus.');
    }
}

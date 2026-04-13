<?php

namespace App\Services\Admin;

use App\Models\AppraisalAsset;
use App\Models\AppraisalAssetFile;
use App\Models\AppraisalRequest;
use App\Support\Admin\AppraisalAssetFormBuilder;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class AdminAppraisalAssetWorkspaceService
{
    public function __construct(
        private readonly AppraisalAssetFormBuilder $assetFormBuilder,
    ) {
    }

    public function createPagePayload(Request $request, AppraisalRequest $appraisalRequest): array
    {
        return $this->assetFormBuilder->buildEditorProps($request, $appraisalRequest);
    }

    public function editPagePayload(Request $request, AppraisalRequest $appraisalRequest, AppraisalAsset $asset): array
    {
        $this->assetFormBuilder->ensureBelongsToRequest($appraisalRequest, $asset);

        return $this->assetFormBuilder->buildEditorProps($request, $appraisalRequest, $asset);
    }

    public function createAsset(AppraisalRequest $appraisalRequest, array $validated): AppraisalAsset
    {
        return $appraisalRequest->assets()->create($this->assetFormBuilder->assetPayload($validated));
    }

    public function updateAsset(AppraisalRequest $appraisalRequest, AppraisalAsset $asset, array $validated): void
    {
        $this->assetFormBuilder->ensureBelongsToRequest($appraisalRequest, $asset);

        $asset->update($this->assetFormBuilder->assetPayload($validated));
    }

    public function deleteAsset(AppraisalRequest $appraisalRequest, AppraisalAsset $asset): void
    {
        $this->assetFormBuilder->ensureBelongsToRequest($appraisalRequest, $asset);

        foreach ($asset->files as $file) {
            Storage::disk('public')->delete($file->path);
        }

        $asset->delete();
    }

    public function storeAssetFile(
        AppraisalRequest $appraisalRequest,
        AppraisalAsset $asset,
        array $validated,
        UploadedFile $file,
    ): void {
        $this->assetFormBuilder->ensureBelongsToRequest($appraisalRequest, $asset);

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
    }

    public function deleteAssetFile(
        AppraisalRequest $appraisalRequest,
        AppraisalAsset $asset,
        AppraisalAssetFile $file,
    ): void {
        $this->assetFormBuilder->ensureBelongsToRequest($appraisalRequest, $asset);
        abort_unless((int) $file->appraisal_asset_id === (int) $asset->id, 404);

        Storage::disk('public')->delete($file->path);
        $file->delete();
    }
}

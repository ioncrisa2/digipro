<?php

namespace App\Services\Customer;

use App\Models\AppraisalAsset;
use App\Models\AppraisalAssetFile;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AppraisalAssetFileStorage
{
    public function storeForAsset(
        Request $request,
        int $index,
        AppraisalAsset $asset,
        bool $hasBuilding,
        string $assetDir
    ): void {
        $certificateFiles = $this->certificateFiles($request, $index);

        if ($certificateFiles === []) {
            throw ValidationException::withMessages([
                "assets.$index.certificate" => 'Sertifikat wajib diunggah.',
            ]);
        }

        foreach ($certificateFiles as $fileIndex => $file) {
            $path = $this->storeLocalFile($file, "$assetDir/documents/certificate", 'Sertifikat-' . ($fileIndex + 1));
            $this->createAssetFile((int) $asset->id, 'doc_certs', $file, $path);
        }

        $pbbFile = $this->firstFile($request, [
            "assets.$index.documents.pbb",
            "assets.$index.doc_pbb",
        ]);

        if (! $pbbFile) {
            throw ValidationException::withMessages([
                "assets.$index.pbb" => 'PBB terbaru wajib diunggah.',
            ]);
        }

        $pbbPath = $this->storeLocalFile($pbbFile, "$assetDir/documents/pbb", 'PBB');
        $this->createAssetFile((int) $asset->id, 'doc_pbb', $pbbFile, $pbbPath);

        $imbFile = $this->firstFile($request, [
            "assets.$index.documents.imb",
            "assets.$index.doc_imb",
        ]);

        if ($hasBuilding && $imbFile) {
            $imbPath = $this->storeLocalFile($imbFile, "$assetDir/documents/imb", 'IMB');
            $this->createAssetFile((int) $asset->id, 'doc_imb', $imbFile, $imbPath);
        }

        $photoGroups = [
            ['photo_access_road', "assets.$index.photos.akses_jalan", "assets.$index.photos_access_road", 'photos/access_road', 'AksesJalan'],
            ['photo_front', "assets.$index.photos.depan", "assets.$index.photos_front", 'photos/front', 'DepanAset'],
            ['photo_interior', "assets.$index.photos.dalam", "assets.$index.photos_interior", 'photos/interior', 'DalamAset'],
        ];

        foreach ($photoGroups as [$type, $newKey, $oldKey, $dir, $prefix]) {
            $files = $this->filesArray($request, [$newKey, $oldKey]);

            foreach ($files as $fileIndex => $file) {
                $path = $this->storeLocalFile($file, "$assetDir/$dir", $prefix . '-' . ($fileIndex + 1));
                $this->createAssetFile((int) $asset->id, $type, $file, $path);
            }
        }
    }

    /**
     * @return array<int, UploadedFile>
     */
    private function certificateFiles(Request $request, int $index): array
    {
        $single = $request->file("assets.$index.documents.certificate");

        if ($single instanceof UploadedFile) {
            return [$single];
        }

        $files = (array) $request->file("assets.$index.doc_certs", []);

        return array_values(array_filter($files, fn ($file) => $file instanceof UploadedFile));
    }

    private function firstFile(Request $request, array $paths): ?UploadedFile
    {
        foreach ($paths as $path) {
            $file = $request->file($path);

            if ($file instanceof UploadedFile) {
                return $file;
            }
        }

        return null;
    }

    /**
     * @return array<int, UploadedFile>
     */
    private function filesArray(Request $request, array $paths): array
    {
        foreach ($paths as $path) {
            $files = $request->file($path, null);

            if (is_array($files) && count($files) > 0) {
                return array_values(array_filter($files, fn ($file) => $file instanceof UploadedFile));
            }
        }

        return [];
    }

    private function createAssetFile(int $assetId, string $type, UploadedFile $file, string $path): void
    {
        AppraisalAssetFile::create([
            'appraisal_asset_id' => $assetId,
            'type' => $type,
            'path' => $path,
            'original_name' => $file->getClientOriginalName(),
            'mime' => $file->getMimeType(),
            'size' => $file->getSize(),
        ]);
    }

    private function storeLocalFile(UploadedFile $file, string $dir, string $prefix): string
    {
        $extension = $file->getClientOriginalExtension() ?: 'bin';
        $name = $prefix . '-' . Str::uuid() . '.' . $extension;

        return $file->storeAs($dir, $name, 'public');
    }
}

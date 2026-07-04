<?php

namespace App\Services\Signatures;

use App\Models\ReportSigner;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class DemoSignatureSpecimenService
{
    public function store(ReportSigner $signer, UploadedFile $image, User $admin): void
    {
        $binary = file_get_contents($image->getRealPath());
        if ($binary === false) {
            throw new RuntimeException('Gambar tanda tangan tidak dapat dibaca.');
        }

        $mime = (string) $image->getMimeType();
        $extension = $mime === 'image/png' ? 'png' : 'jpg';
        $hash = 'sha256:'.hash('sha256', $binary);
        $disk = $this->disk();
        $path = sprintf(
            'demo-signatures/report-signers/%d/%s.%s',
            $signer->id,
            Str::uuid(),
            $extension,
        );

        if (! Storage::disk($disk)->put($path, $binary)) {
            throw new RuntimeException('Gambar tanda tangan gagal disimpan.');
        }

        $oldPath = $signer->demo_signature_path;

        try {
            $signer->update([
                'demo_signature_path' => $path,
                'demo_signature_mime' => $mime,
                'demo_signature_hash' => $hash,
                'demo_signature_updated_at' => now(),
                'demo_signature_updated_by' => $admin->id,
            ]);
        } catch (Throwable $exception) {
            Storage::disk($disk)->delete($path);
            throw $exception;
        }

        if (is_string($oldPath) && $oldPath !== '' && $oldPath !== $path) {
            Storage::disk($disk)->delete($oldPath);
        }
    }

    public function disk(): string
    {
        return (string) config('signatures.canvas_demo.signature_disk', 'local');
    }
}

<?php

namespace App\Services\Peruri;

use App\Contracts\DigitalSignatureProvider;
use App\Models\ReportSigner;
use Illuminate\Http\UploadedFile;
use RuntimeException;

class PeruriSignerOnboardingService
{
    public function __construct(
        private readonly DigitalSignatureProvider $provider,
    ) {
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function registerUser(ReportSigner $signer, array $payload): array
    {
        return $this->provider->registerUser(array_merge($this->defaultIdentityPayload($signer), $payload));
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function submitKycVideo(ReportSigner $signer, UploadedFile $video, array $payload = []): array
    {
        $email = $this->email($signer);
        $binary = file_get_contents($video->getRealPath());

        if ($binary === false) {
            throw new RuntimeException('File video KYC tidak dapat dibaca.');
        }

        return $this->provider->submitKycVideo(
            email: $email,
            fileName: $video->getClientOriginalName() ?: ('kyc-' . $signer->id . '.mp4'),
            videoBinary: $binary,
            payload: $payload,
        );
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function setSignatureSpecimen(ReportSigner $signer, UploadedFile $image, array $payload = []): array
    {
        $email = $this->email($signer);
        $binary = file_get_contents($image->getRealPath());

        if ($binary === false) {
            throw new RuntimeException('File specimen tanda tangan tidak dapat dibaca.');
        }

        return $this->provider->setSignatureSpecimen(
            email: $email,
            fileName: $image->getClientOriginalName() ?: ('signature-' . $signer->id . '.png'),
            imageBinary: $binary,
            payload: $payload,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function registerKeyla(ReportSigner $signer): array
    {
        return $this->provider->registerKeyla($this->email($signer));
    }

    /**
     * @return array<string, mixed>
     */
    public function defaultIdentityPayload(ReportSigner $signer): array
    {
        return array_filter([
            'name' => $signer->name,
            'email' => $signer->email,
            'phone' => $signer->phone_number,
        ], fn ($value) => filled($value));
    }

    private function email(ReportSigner $signer): string
    {
        $email = trim((string) $signer->email);

        if ($email === '') {
            throw new RuntimeException('Email Peruri signer belum diisi.');
        }

        return $email;
    }
}

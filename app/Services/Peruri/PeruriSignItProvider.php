<?php

namespace App\Services\Peruri;

use App\Contracts\DigitalSignatureProvider;
use RuntimeException;

class PeruriSignItProvider implements DigitalSignatureProvider
{
    public function __construct(
        private readonly PeruriClient $client,
    ) {}

    public function startTierEnvelope(
        string $uploaderEmail,
        string $fileName,
        string $pdfBinary,
        array $signerEmails,
        array $payload = [],
    ): array {
        $version = (string) config('peruri.api_version', 'v1');
        $corporateId = (string) config('peruri.corporate_id');

        if ($corporateId === '') {
            throw new RuntimeException('Konfigurasi Peruri corporate id belum diisi.');
        }

        $body = [
            'uploader' => $uploaderEmail,
            'payload' => (object) $payload,
            'fileName' => $fileName,
            'base64Document' => base64_encode($pdfBinary),
            'signer' => array_values(array_map(
                fn (string $email) => ['email' => $email],
                $signerEmails
            )),
        ];

        $resp = $this->client->post("/sign/{$version}/{$corporateId}/model/tier/send", $body);

        $orderIdTier = (string) data_get($resp, 'data.orderIdTier', '');
        $firstOrderId = (string) data_get($resp, 'data.orderId', '');

        if ($orderIdTier === '' || $firstOrderId === '') {
            throw new RuntimeException('Response Peruri tidak lengkap (orderIdTier/orderId).');
        }

        return [
            'order_id_tier' => $orderIdTier,
            'first_order_id' => $firstOrderId,
        ];
    }

    public function registerUser(array $payload): array
    {
        $version = (string) config('peruri.api_version', 'v1');
        $corporateId = (string) config('peruri.corporate_id');

        return $this->client->post("/registration/{$version}/{$corporateId}/user", $payload);
    }

    public function submitKycVideo(string $email, string $fileName, string $videoBinary, array $payload = []): array
    {
        $version = (string) config('peruri.api_version', 'v1');
        $corporateId = (string) config('peruri.corporate_id');

        return $this->client->post("/registration/{$version}/{$corporateId}/kyc", array_merge($payload, [
            'email' => $email,
            'videoStream' => base64_encode($videoBinary),
        ]));
    }

    public function setSignatureSpecimen(string $email, string $fileName, string $imageBinary, array $payload = []): array
    {
        $version = (string) config('peruri.api_version', 'v1');
        $corporateId = (string) config('peruri.corporate_id');

        return $this->client->put("/specimen/{$version}/{$corporateId}/set/signature", array_merge($payload, [
            'email' => $email,
            'specimen' => base64_encode($imageBinary),
        ]));
    }

    public function setSignatureCoordinate(
        string $orderId,
        string $signerEmail,
        array $coords,
        bool $visible = true,
        string $certificateLevel = 'NOT_CERTIFIED',
        ?string $varLocation = null,
        ?string $varReason = null,
    ): void {
        $version = (string) config('peruri.api_version', 'v1');
        $corporateId = (string) config('peruri.corporate_id');

        $body = array_filter([
            'orderId' => $orderId,
            'signer' => ['email' => $signerEmail],
            'isVisualSign' => $visible ? 'YES' : 'NO',
            'lowerLeftX' => (int) $coords['lower_left_x'],
            'lowerLeftY' => (int) $coords['lower_left_y'],
            'upperRightX' => (int) $coords['upper_right_x'],
            'upperRightY' => (int) $coords['upper_right_y'],
            'page' => (int) $coords['page'],
            'certificateLevel' => $certificateLevel,
            'varLocation' => $varLocation,
            'varReason' => $varReason,
        ], fn ($value) => $value !== null && $value !== '');

        $this->client->put("/specimen/{$version}/{$corporateId}/coordinate/signature", $body);
    }

    public function signTierWithKeylaToken(string $orderId, string $keylaToken): void
    {
        $version = (string) config('peruri.api_version', 'v1');
        $corporateId = (string) config('peruri.corporate_id');

        $body = [
            'orderId' => $orderId,
            'otpCode' => '',
            'token' => $keylaToken,
        ];

        $this->client->put("/sign/{$version}/{$corporateId}/model/tier/signing", $body);
    }

    public function startSingleSigning(
        string $uploaderEmail,
        string $fileName,
        string $pdfBinary,
        string $signerEmail,
        array $payload = [],
    ): array {
        $version = (string) config('peruri.api_version', 'v1');
        $corporateId = (string) config('peruri.corporate_id');

        $response = $this->client->post("/sign/{$version}/{$corporateId}/model/single/send", [
            'uploader' => $uploaderEmail,
            'payload' => (object) $payload,
            'fileName' => $fileName,
            'base64Document' => base64_encode($pdfBinary),
            'signer' => [
                'email' => $signerEmail,
            ],
        ]);

        $orderId = (string) data_get($response, 'data.orderId', '');
        if ($orderId === '') {
            throw new RuntimeException('Response Peruri tidak lengkap (orderId).');
        }

        return [
            'order_id' => $orderId,
        ];
    }

    public function signSingleWithKeylaToken(string $orderId, string $keylaToken): void
    {
        $version = (string) config('peruri.api_version', 'v1');
        $corporateId = (string) config('peruri.corporate_id');

        $this->client->put("/sign/{$version}/{$corporateId}/model/single/signing", [
            'orderId' => $orderId,
            'otpCode' => '',
            'token' => $keylaToken,
        ]);
    }

    public function checkStatusByOrderType(string $orderId, string $orderType, string $uploaderEmail): array
    {
        $version = (string) config('peruri.api_version', 'v1');
        $corporateId = (string) config('peruri.corporate_id');

        return $this->client->get("/document/{$version}/{$corporateId}/status/by/type", [
            'orderId' => $orderId,
            'orderType' => $orderType,
            'uploader' => $uploaderEmail,
        ]);
    }

    public function downloadTierDocument(string $orderIdTier, string $uploaderEmail): string
    {
        $version = (string) config('peruri.api_version', 'v1');
        $corporateId = (string) config('peruri.corporate_id');

        $resp = $this->client->get("/sign/{$version}/{$corporateId}/model/tier/download", [
            'orderIdTier' => $orderIdTier,
            'uploader' => $uploaderEmail,
        ]);

        $base64 = (string) data_get($resp, 'data.base64Document', '');
        if ($base64 === '') {
            throw new RuntimeException('Peruri tidak mengembalikan dokumen hasil download.');
        }

        return $base64;
    }

    public function previewDocument(string $orderId): array
    {
        $version = (string) config('peruri.api_version', 'v1');
        $corporateId = (string) config('peruri.corporate_id');

        return $this->client->get("/document/{$version}/{$corporateId}/view", [
            'orderId' => $orderId,
        ]);
    }

    public function downloadSingleDocument(string $orderId): string
    {
        $version = (string) config('peruri.api_version', 'v1');
        $corporateId = (string) config('peruri.corporate_id');

        $response = $this->client->get("/sign/{$version}/{$corporateId}/model/single/download", [
            'orderId' => $orderId,
        ]);

        $base64 = (string) data_get($response, 'data.base64Document', '');
        if ($base64 === '') {
            throw new RuntimeException('Peruri tidak mengembalikan dokumen hasil download single signing.');
        }

        return $base64;
    }

    public function checkCertificate(string $email): array
    {
        $version = (string) config('peruri.api_version', 'v1');
        $corporateId = (string) config('peruri.corporate_id');

        return $this->client->get("/certificate/{$version}/{$corporateId}/check", [
            'email' => $email,
        ]);
    }

    public function checkKeylaRegistration(string $email): array
    {
        $version = (string) config('peruri.api_version', 'v1');
        $corporateId = (string) config('peruri.corporate_id');

        return $this->client->get("/Keyla/{$version}/{$corporateId}/sign/check", [
            'email' => $email,
        ]);
    }

    public function verifyKeylaToken(string $email, string $keylaToken): array
    {
        $version = (string) config('peruri.api_version', 'v1');
        $corporateId = (string) config('peruri.corporate_id');

        return $this->client->post("/keyla/{$version}/{$corporateId}/sign/verify", [
            'email' => $email,
            'token' => $keylaToken,
        ]);
    }

    public function registerKeyla(string $email): array
    {
        $version = (string) config('peruri.api_version', 'v1');
        $corporateId = (string) config('peruri.corporate_id');

        return $this->client->post("/keyla/{$version}/{$corporateId}/sign/register", [
            'email' => $email,
        ]);
    }

    public function referenceProvinces(): array
    {
        $version = (string) config('peruri.api_version', 'v1');
        $corporateId = (string) config('peruri.corporate_id');

        return $this->client->get("/registration/{$version}/{$corporateId}/reference/province");
    }

    public function referenceCities(int|string $provinceId): array
    {
        $version = (string) config('peruri.api_version', 'v1');
        $corporateId = (string) config('peruri.corporate_id');

        return $this->client->get("/registration/{$version}/{$corporateId}/reference/city", [
            'idProvince' => $provinceId,
        ]);
    }
}

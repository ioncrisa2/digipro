<?php

namespace App\Contracts;

interface DigitalSignatureProvider
{
    /**
     * @param  array<int, string>  $signerEmails
     * @param  array<string, mixed>  $payload
     * @return array{order_id_tier: string, first_order_id: string}
     */
    public function startTierEnvelope(
        string $uploaderEmail,
        string $fileName,
        string $pdfBinary,
        array $signerEmails,
        array $payload = [],
    ): array;

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function registerUser(array $payload): array;

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function submitKycVideo(string $email, string $fileName, string $videoBinary, array $payload = []): array;

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function setSignatureSpecimen(string $email, string $fileName, string $imageBinary, array $payload = []): array;

    /**
     * @param  array{page:int,lower_left_x:int,lower_left_y:int,upper_right_x:int,upper_right_y:int}  $coords
     */
    public function setSignatureCoordinate(
        string $orderId,
        string $signerEmail,
        array $coords,
        bool $visible = true,
        string $certificateLevel = 'NOT_CERTIFIED',
        ?string $varLocation = null,
        ?string $varReason = null,
    ): void;

    public function signTierWithKeylaToken(string $orderId, string $keylaToken): void;

    /**
     * @param  array<string, mixed>  $payload
     * @return array{order_id: string}
     */
    public function startSingleSigning(
        string $uploaderEmail,
        string $fileName,
        string $pdfBinary,
        string $signerEmail,
        array $payload = [],
    ): array;

    public function signSingleWithKeylaToken(string $orderId, string $keylaToken): void;

    /**
     * @return array<string, mixed>
     */
    public function checkStatusByOrderType(string $orderId, string $orderType, string $uploaderEmail): array;

    public function downloadTierDocument(string $orderIdTier, string $uploaderEmail): string;

    /**
     * @return array<string, mixed>
     */
    public function previewDocument(string $orderId): array;

    public function downloadSingleDocument(string $orderId): string;

    /**
     * @return array<string, mixed>
     */
    public function checkCertificate(string $email): array;

    /**
     * @return array<string, mixed>
     */
    public function checkKeylaRegistration(string $email): array;

    /**
     * @return array<string, mixed>
     */
    public function verifyKeylaToken(string $email, string $keylaToken): array;

    /**
     * @return array<string, mixed>
     */
    public function registerKeyla(string $email): array;

    /**
     * @return array<string, mixed>
     */
    public function referenceProvinces(): array;

    /**
     * @return array<string, mixed>
     */
    public function referenceCities(int|string $provinceId): array;
}

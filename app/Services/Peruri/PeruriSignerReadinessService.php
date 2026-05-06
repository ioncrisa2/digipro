<?php

namespace App\Services\Peruri;

use App\Contracts\DigitalSignatureProvider;
use App\Models\AppraisalRequest;
use App\Models\ReportSigner;
use App\Models\User;
use App\Models\UserSignatureProfile;
use RuntimeException;

class PeruriSignerReadinessService
{
    public function __construct(
        private readonly DigitalSignatureProvider $provider,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function forEmail(?string $email): array
    {
        $normalizedEmail = trim((string) $email);

        if ($normalizedEmail === '') {
            return [
                'email' => null,
                'certificate' => $this->statusState('missing_email', 'Email Peruri belum tersedia.', false, 'warning'),
                'keyla' => $this->statusState('missing_email', 'Email KEYLA belum tersedia.', false, 'warning'),
                'overall' => $this->statusState('blocked', 'Lengkapi email Peruri terlebih dahulu.', false, 'warning'),
                'checked_at' => now()->toDateTimeString(),
            ];
        }

        $certificate = $this->inspectCertificate($normalizedEmail);
        $keyla = $this->inspectKeyla($normalizedEmail);
        $isReady = $certificate['is_ready'] && $keyla['is_ready'];

        return [
            'email' => $normalizedEmail,
            'certificate' => $certificate,
            'keyla' => $keyla,
            'overall' => $isReady
                ? $this->statusState('ready', 'Siap untuk proses tanda tangan digital.', true, 'success')
                : $this->statusState('blocked', $certificate['is_ready'] ? $keyla['message'] : $certificate['message'], false, 'warning'),
            'checked_at' => now()->toDateTimeString(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function forSigner(?ReportSigner $signer, bool $sync = false): array
    {
        if (! $signer) {
            return [
                'signer_id' => null,
                'name' => null,
                'email' => null,
                'stored' => false,
                'readiness' => $this->forEmail(null),
            ];
        }

        $readiness = $sync ? $this->syncSigner($signer) : $this->fromSignerSnapshot($signer);

        return [
            'signer_id' => $signer->id,
            'name' => $signer->name,
            'email' => $signer->email,
            'stored' => ! $sync,
            'readiness' => $readiness,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function forCustomer(?User $user, bool $sync = false): array
    {
        if (! $user) {
            return $this->forEmail(null);
        }

        $profile = $user->signatureProfile;
        if (! $profile) {
            return [
                'email' => $user->email,
                'profile_id' => null,
                'registration' => $this->statusState('inactive', 'Registrasi user PDS belum dilakukan.', false, 'warning'),
                'kyc' => $this->statusState('inactive', 'Video E-KYC belum dikirim.', false, 'warning'),
                'specimen' => $this->statusState('inactive', 'Specimen tanda tangan belum dikirim.', false, 'warning'),
                'certificate' => $this->statusState('missing_email', 'Profil tanda tangan digital customer belum dibuat.', false, 'warning'),
                'keyla' => $this->statusState('missing_email', 'Profil KEYLA customer belum dibuat.', false, 'warning'),
                'overall' => $this->statusState('blocked', 'Customer perlu menyelesaikan onboarding PDS/KEYLA terlebih dahulu.', false, 'warning'),
                'checked_at' => null,
                'last_error' => null,
                'keyla_qr_image' => null,
            ];
        }

        return $sync ? $this->syncCustomerProfile($profile) : $this->forCustomerProfile($profile, probeRemote: false);
    }

    /**
     * @return array<string, mixed>
     */
    public function forContract(AppraisalRequest $record, ?string $customerEmail = null, bool $syncPublicSigner = false, ?User $customer = null): array
    {
        $record->loadMissing(['contractPublicAppraiserSigner']);

        $customerReadiness = $customer
            ? $this->forCustomer($customer, sync: true)
            : $this->forEmail($customerEmail);
        $publicAppraiser = $this->forSigner($record->contractPublicAppraiserSigner, $syncPublicSigner);

        return [
            'customer' => $customerReadiness,
            'public_appraiser' => $publicAppraiser,
            'can_customer_sign' => (bool) $customerReadiness['overall']['is_ready'],
        ];
    }

    public function assertReadyForSigning(string $email): void
    {
        $readiness = $this->forEmail($email);

        if (! $readiness['certificate']['is_ready']) {
            throw new RuntimeException((string) $readiness['certificate']['message']);
        }

        if (! $readiness['keyla']['is_ready']) {
            throw new RuntimeException((string) $readiness['keyla']['message']);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function syncSigner(ReportSigner $signer): array
    {
        $readiness = $this->forEmail($signer->email);

        $signer->forceFill([
            'peruri_certificate_status' => $readiness['certificate']['code'],
            'peruri_keyla_status' => $readiness['keyla']['code'],
            'peruri_last_checked_at' => now(),
        ])->save();

        return $readiness;
    }

    /**
     * @return array<string, mixed>
     */
    public function syncCustomerProfile(UserSignatureProfile $profile): array
    {
        $readiness = $this->forCustomerProfile($profile, probeRemote: true);
        $checkedAt = now();

        $profile->forceFill([
            'certificate_status' => $readiness['certificate']['code'],
            'keyla_status' => $readiness['keyla']['code'],
            'last_checked_at' => $checkedAt,
            'last_error' => $readiness['last_error'] ?? null,
        ])->save();

        $readiness['checked_at'] = $checkedAt->toDateTimeString();
        $readiness['last_error'] = $profile->last_error;

        return $readiness;
    }

    /**
     * @return array<string, mixed>
     */
    public function forCustomerProfile(UserSignatureProfile $profile, bool $probeRemote = false): array
    {
        $email = trim((string) $profile->peruri_email);
        $registration = $this->customerStepState(
            (string) ($profile->registration_status ?? ''),
            'Registrasi user PDS belum dilakukan.',
            'Registrasi user PDS sudah dikirim.',
        );
        $kyc = $this->customerStepState(
            (string) ($profile->kyc_status ?? ''),
            'Video E-KYC belum dikirim.',
            'Video E-KYC sudah dikirim.',
        );
        $specimen = $this->customerStepState(
            (string) ($profile->specimen_status ?? ''),
            'Specimen tanda tangan belum dikirim.',
            'Specimen tanda tangan sudah dikirim.',
        );

        if ($email === '') {
            return [
                'email' => null,
                'profile_id' => $profile->id,
                'registration' => $registration,
                'kyc' => $kyc,
                'specimen' => $specimen,
                'certificate' => $this->statusState('missing_email', 'Email Peruri belum tersedia.', false, 'warning'),
                'keyla' => $this->statusState('missing_email', 'Email KEYLA belum tersedia.', false, 'warning'),
                'overall' => $this->statusState('blocked', 'Lengkapi identitas onboarding customer terlebih dahulu.', false, 'warning'),
                'checked_at' => optional($profile->last_checked_at)->toDateTimeString(),
                'last_error' => $profile->last_error,
                'keyla_qr_image' => $profile->keyla_qr_image,
            ];
        }

        $certificate = $probeRemote
            ? $this->inspectCertificate($email)
            : $this->storedOrPendingCertificateState($profile, $registration, $kyc, $specimen);
        $keyla = $probeRemote
            ? $this->inspectKeyla($email)
            : $this->storedOrPendingKeylaState($profile, $registration, $kyc, $specimen);
        $isReady = $registration['is_ready']
            && $kyc['is_ready']
            && $specimen['is_ready']
            && $certificate['is_ready']
            && $keyla['is_ready'];

        return [
            'email' => $email,
            'profile_id' => $profile->id,
            'registration' => $registration,
            'kyc' => $kyc,
            'specimen' => $specimen,
            'certificate' => $certificate,
            'keyla' => $keyla,
            'overall' => $isReady
                ? $this->statusState('ready', 'Customer siap untuk tanda tangan digital.', true, 'success')
                : $this->statusState('blocked', $this->customerBlockedMessage($registration, $kyc, $specimen, $certificate, $keyla), false, 'warning'),
            'checked_at' => optional($profile->last_checked_at)->toDateTimeString(),
            'last_error' => $this->customerLastError($profile, $certificate, $keyla),
            'keyla_qr_image' => $profile->keyla_qr_image,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function fromSignerSnapshot(ReportSigner $signer): array
    {
        return [
            'email' => $signer->email,
            'certificate' => $this->storedState(
                (string) ($signer->peruri_certificate_status ?? ''),
                'Status sertifikat belum pernah diperiksa.'
            ),
            'keyla' => $this->storedState(
                (string) ($signer->peruri_keyla_status ?? ''),
                'Status KEYLA belum pernah diperiksa.'
            ),
            'overall' => $this->storedOverallState($signer),
            'checked_at' => optional($signer->peruri_last_checked_at)->toDateTimeString(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function inspectCertificate(string $email): array
    {
        try {
            $response = $this->provider->checkCertificate($email);
            $data = is_array(data_get($response, 'data')) ? data_get($response, 'data') : [];

            $expired = $this->isTruthy($data['isExpired'] ?? null);
            if ($expired) {
                return $this->statusState('expired', 'Sertifikat elektronik Peruri sudah expired.', false, 'danger');
            }

            if ($this->hasNegativeFlag($data, ['isActive', 'active', 'isRegistered', 'registered', 'isCertificateExists', 'certificateExists', 'isCertificateActive', 'certificateActive'])) {
                return $this->statusState('inactive', 'Sertifikat elektronik Peruri belum aktif atau belum terdaftar.', false, 'warning');
            }

            if ($this->hasNegativeText($data, ['certificateStatus', 'statusCertificate', 'certStatus'], ['inactive', 'not_found', 'not found', 'expired', 'revoked'])) {
                return $this->statusState('inactive', 'Sertifikat elektronik Peruri belum aktif atau belum terdaftar.', false, 'warning');
            }

            if (
                $this->hasPositiveFlag($data, ['isActive', 'active', 'isCertificateActive', 'certificateActive'])
                || $this->hasPositiveText($data, ['certificateStatus', 'statusCertificate', 'certStatus'], ['active', 'issued', 'valid'])
                || array_key_exists('isExpired', $data)
            ) {
                return $this->statusState('ready', 'Sertifikat elektronik Peruri aktif.', true, 'success');
            }

            return $this->statusState('unknown', 'Status sertifikat belum dapat dipastikan dari response PDS.', false, 'warning');
        } catch (RuntimeException $exception) {
            return $this->statusState('error', $exception->getMessage(), false, 'danger');
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function inspectKeyla(string $email): array
    {
        try {
            $response = $this->provider->checkKeylaRegistration($email);
            $data = is_array(data_get($response, 'data')) ? data_get($response, 'data') : [];

            if ($this->hasNegativeFlag($data, ['isRegistered', 'registered', 'isActive', 'active', 'isKeylaRegistered', 'keylaRegistered', 'isKeylaActive', 'keylaActive'])) {
                return $this->statusState('inactive', 'Akun KEYLA belum terhubung atau belum aktif.', false, 'warning');
            }

            if ($this->hasNegativeText($data, ['status', 'keylaStatus', 'registrationStatus'], ['inactive', 'unregistered', 'not_found', 'not found'])) {
                return $this->statusState('inactive', 'Akun KEYLA belum terhubung atau belum aktif.', false, 'warning');
            }

            if (
                $this->hasPositiveFlag($data, ['isRegistered', 'registered', 'isActive', 'active', 'isKeylaRegistered', 'keylaRegistered', 'isKeylaActive', 'keylaActive'])
                || $this->hasPositiveText($data, ['status', 'keylaStatus', 'registrationStatus'], ['active', 'registered', 'ready'])
            ) {
                return $this->statusState('ready', 'Akun KEYLA sudah aktif dan siap diverifikasi.', true, 'success');
            }

            return $this->statusState('unknown', 'Status KEYLA belum dapat dipastikan dari response PDS.', false, 'warning');
        } catch (RuntimeException $exception) {
            return $this->statusState('error', $exception->getMessage(), false, 'danger');
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function storedState(string $code, string $fallbackMessage): array
    {
        return match ($code) {
            'ready' => $this->statusState('ready', 'Siap.', true, 'success'),
            'expired' => $this->statusState('expired', 'Sertifikat expired.', false, 'danger'),
            'inactive' => $this->statusState('inactive', 'Belum aktif atau belum terdaftar.', false, 'warning'),
            'missing_email' => $this->statusState('missing_email', 'Email belum tersedia.', false, 'warning'),
            'error' => $this->statusState('error', 'Pemeriksaan terakhir gagal.', false, 'danger'),
            'unknown' => $this->statusState('unknown', 'Status belum dapat dipastikan.', false, 'warning'),
            default => $this->statusState('unknown', $fallbackMessage, false, 'muted'),
        };
    }

    /**
     * @return array<string, mixed>
     */
    private function storedOverallState(ReportSigner $signer): array
    {
        $certificate = $this->storedState((string) ($signer->peruri_certificate_status ?? ''), 'Status sertifikat belum pernah diperiksa.');
        $keyla = $this->storedState((string) ($signer->peruri_keyla_status ?? ''), 'Status KEYLA belum pernah diperiksa.');

        if ($certificate['is_ready'] && $keyla['is_ready']) {
            return $this->statusState('ready', 'Siap untuk proses tanda tangan digital.', true, 'success');
        }

        $message = $certificate['is_ready'] ? $keyla['message'] : $certificate['message'];

        return $this->statusState('blocked', $message, false, 'warning');
    }

    /**
     * @return array<string, mixed>
     */
    private function customerStepState(string $status, string $defaultMessage, string $readyMessage): array
    {
        return match ($status) {
            'submitted', 'ready' => $this->statusState('ready', $readyMessage, true, 'success'),
            'error' => $this->statusState('error', 'Langkah onboarding terakhir gagal diproses.', false, 'danger'),
            default => $this->statusState('inactive', $defaultMessage, false, 'warning'),
        };
    }

    /**
     * @param  array<string, mixed>  $registration
     * @param  array<string, mixed>  $kyc
     * @param  array<string, mixed>  $specimen
     * @param  array<string, mixed>  $certificate
     * @param  array<string, mixed>  $keyla
     */
    private function customerBlockedMessage(array $registration, array $kyc, array $specimen, array $certificate, array $keyla): string
    {
        foreach ([$registration, $kyc, $specimen, $certificate, $keyla] as $state) {
            if (! ($state['is_ready'] ?? false)) {
                return (string) ($state['message'] ?? 'Customer belum siap onboarding.');
            }
        }

        return 'Customer belum siap onboarding.';
    }

    /**
     * @param  array<string, mixed>  $certificate
     * @param  array<string, mixed>  $keyla
     */
    private function customerLastError(UserSignatureProfile $profile, array $certificate, array $keyla): ?string
    {
        if (($certificate['code'] ?? null) === 'error') {
            return (string) ($certificate['message'] ?? $profile->last_error);
        }

        if (($keyla['code'] ?? null) === 'error') {
            return (string) ($keyla['message'] ?? $profile->last_error);
        }

        return $profile->last_error;
    }

    /**
     * @param  array<string, mixed>  $registration
     * @param  array<string, mixed>  $kyc
     * @param  array<string, mixed>  $specimen
     * @return array<string, mixed>
     */
    private function storedOrPendingCertificateState(UserSignatureProfile $profile, array $registration, array $kyc, array $specimen): array
    {
        $storedStatus = (string) ($profile->certificate_status ?? '');
        if ($storedStatus !== '') {
            return $this->storedState($storedStatus, 'Status sertifikat belum pernah diperiksa.');
        }

        if (! ($registration['is_ready'] ?? false)) {
            return $this->statusState('inactive', 'Registrasi user PDS perlu dikirim terlebih dahulu.', false, 'warning');
        }

        if (! ($kyc['is_ready'] ?? false)) {
            return $this->statusState('inactive', 'Video E-KYC perlu dikirim sebelum sertifikat dapat dicek.', false, 'warning');
        }

        if (! ($specimen['is_ready'] ?? false)) {
            return $this->statusState('inactive', 'Specimen tanda tangan perlu dikirim sebelum sertifikat dapat dicek.', false, 'warning');
        }

        return $this->statusState('unknown', 'Status sertifikat belum diperiksa. Gunakan tombol cek status untuk sinkronisasi terbaru.', false, 'muted');
    }

    /**
     * @param  array<string, mixed>  $registration
     * @param  array<string, mixed>  $kyc
     * @param  array<string, mixed>  $specimen
     * @return array<string, mixed>
     */
    private function storedOrPendingKeylaState(UserSignatureProfile $profile, array $registration, array $kyc, array $specimen): array
    {
        $storedStatus = (string) ($profile->keyla_status ?? '');
        if ($storedStatus !== '') {
            return $this->storedState($storedStatus, 'Status KEYLA belum pernah diperiksa.');
        }

        if (! ($registration['is_ready'] ?? false)) {
            return $this->statusState('inactive', 'Registrasi user PDS perlu dikirim terlebih dahulu.', false, 'warning');
        }

        if (! ($kyc['is_ready'] ?? false)) {
            return $this->statusState('inactive', 'Video E-KYC perlu dikirim sebelum KEYLA dapat dicek.', false, 'warning');
        }

        if (! ($specimen['is_ready'] ?? false)) {
            return $this->statusState('inactive', 'Specimen tanda tangan perlu dikirim sebelum KEYLA dapat dicek.', false, 'warning');
        }

        return $this->statusState('unknown', 'Status KEYLA belum diperiksa. Gunakan tombol cek status untuk sinkronisasi terbaru.', false, 'muted');
    }

    /**
     * @return array{code:string,label:string,message:string,is_ready:bool,tone:string}
     */
    private function statusState(string $code, string $message, bool $isReady, string $tone): array
    {
        return [
            'code' => $code,
            'label' => match ($code) {
                'ready' => 'Siap',
                'expired' => 'Expired',
                'inactive' => 'Belum Aktif',
                'missing_email' => 'Email Belum Ada',
                'error' => 'Gagal Diperiksa',
                'unknown' => 'Belum Diketahui',
                'blocked' => 'Belum Siap',
                default => 'Status',
            },
            'message' => $message,
            'is_ready' => $isReady,
            'tone' => $tone,
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array<int, string>  $keys
     */
    private function hasNegativeFlag(array $data, array $keys): bool
    {
        foreach ($keys as $key) {
            if (! array_key_exists($key, $data)) {
                continue;
            }

            if ($this->isFalsy($data[$key])) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array<int, string>  $keys
     */
    private function hasPositiveFlag(array $data, array $keys): bool
    {
        foreach ($keys as $key) {
            if (! array_key_exists($key, $data)) {
                continue;
            }

            if ($this->isTruthy($data[$key])) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array<int, string>  $keys
     * @param  array<int, string>  $negativeValues
     */
    private function hasNegativeText(array $data, array $keys, array $negativeValues): bool
    {
        return $this->hasTextValue($data, $keys, $negativeValues);
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array<int, string>  $keys
     * @param  array<int, string>  $positiveValues
     */
    private function hasPositiveText(array $data, array $keys, array $positiveValues): bool
    {
        return $this->hasTextValue($data, $keys, $positiveValues);
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array<int, string>  $keys
     * @param  array<int, string>  $values
     */
    private function hasTextValue(array $data, array $keys, array $values): bool
    {
        $normalizedValues = array_map(
            static fn (string $value): string => mb_strtolower(trim($value)),
            $values,
        );

        foreach ($keys as $key) {
            $value = $data[$key] ?? null;
            if (! is_string($value)) {
                continue;
            }

            if (in_array(mb_strtolower(trim($value)), $normalizedValues, true)) {
                return true;
            }
        }

        return false;
    }

    private function isTruthy(mixed $value): bool
    {
        return $value === true || $value === 1 || $value === '1' || $value === 'true';
    }

    private function isFalsy(mixed $value): bool
    {
        return $value === false || $value === 0 || $value === '0' || $value === 'false';
    }
}

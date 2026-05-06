<?php

namespace App\Services\Peruri;

use App\Contracts\DigitalSignatureProvider;
use App\Models\AppraisalRequest;
use App\Models\Province;
use App\Models\Regency;
use App\Models\User;
use App\Models\UserSignatureProfile;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class CustomerSignatureOnboardingService
{
    public function __construct(
        private readonly DigitalSignatureProvider $provider,
        private readonly PeruriSignerReadinessService $readinessService,
    ) {}

    public function profileFor(User $user): UserSignatureProfile
    {
        return $user->signatureProfile()->firstOrCreate([], [
            'provider' => 'peruri_signit',
            'peruri_email' => $user->email,
            'peruri_phone' => $user->phone_number,
            'nik' => $user->billing_nik,
            'is_wna' => false,
            'identity_payload' => [],
            'meta' => [],
        ]);
    }

    public function saveIdentity(User $user, array $validated): UserSignatureProfile
    {
        $profile = $this->profileFor($user);

        $currentIdentity = [
            'peruri_email' => $profile->peruri_email,
            'peruri_phone' => $profile->peruri_phone,
            'nik' => $profile->nik,
            'is_wna' => (bool) $profile->is_wna,
            'reference_province_id' => $profile->reference_province_id,
            'reference_city_id' => $profile->reference_city_id,
            'gender' => $profile->gender,
            'place_of_birth' => $profile->place_of_birth,
            'date_of_birth' => optional($profile->date_of_birth)->toDateString(),
            'address' => data_get($profile->identity_payload, 'address'),
            'ktp_photo_path' => $profile->ktp_photo_path,
        ];

        $ktpPhotoPath = $profile->ktp_photo_path;
        if (($validated['ktp_photo'] ?? null) instanceof UploadedFile) {
            if ($ktpPhotoPath && Storage::disk('local')->exists($ktpPhotoPath)) {
                Storage::disk('local')->delete($ktpPhotoPath);
            }

            $ktpPhotoPath = $validated['ktp_photo']->store("signature-profiles/{$user->id}/identity", 'local');
            if ($ktpPhotoPath === false) {
                throw new RuntimeException('Foto identitas tidak dapat disimpan.');
            }
        }

        $nextIdentity = [
            'is_wna' => filter_var($validated['is_wna'], FILTER_VALIDATE_BOOLEAN),
            'peruri_email' => (string) $validated['peruri_email'],
            'peruri_phone' => (string) $validated['peruri_phone'],
            'nik' => (string) $validated['nik'],
            'reference_province_id' => (int) $validated['reference_province_id'],
            'reference_city_id' => (int) $validated['reference_city_id'],
            'gender' => (string) $validated['gender'],
            'place_of_birth' => (string) $validated['place_of_birth'],
            'date_of_birth' => (string) $validated['date_of_birth'],
            'address' => (string) $validated['address'],
            'ktp_photo_path' => $ktpPhotoPath,
        ];

        $identityChanged = $currentIdentity !== $nextIdentity;

        $profile->forceFill([
            'provider' => 'peruri_signit',
            'peruri_email' => $nextIdentity['peruri_email'],
            'peruri_phone' => $nextIdentity['peruri_phone'],
            'nik' => $nextIdentity['nik'],
            'is_wna' => $nextIdentity['is_wna'],
            'reference_province_id' => $nextIdentity['reference_province_id'],
            'reference_city_id' => $nextIdentity['reference_city_id'],
            'gender' => $nextIdentity['gender'],
            'place_of_birth' => $nextIdentity['place_of_birth'],
            'date_of_birth' => $nextIdentity['date_of_birth'],
            'ktp_photo_path' => $nextIdentity['ktp_photo_path'],
            'identity_payload' => array_merge((array) ($profile->identity_payload ?? []), [
                'name' => $user->name,
                'email' => $nextIdentity['peruri_email'],
                'phone' => $nextIdentity['peruri_phone'],
                'nik' => $nextIdentity['nik'],
                'is_wna' => $nextIdentity['is_wna'],
                'gender' => $nextIdentity['gender'],
                'place_of_birth' => $nextIdentity['place_of_birth'],
                'date_of_birth' => $nextIdentity['date_of_birth'],
                'address' => $nextIdentity['address'],
                'province_id' => $nextIdentity['reference_province_id'],
                'city_id' => $nextIdentity['reference_city_id'],
            ]),
        ]);

        if ($identityChanged) {
            $this->resetReadiness($profile, 'Data identitas diperbarui. Silakan lanjutkan onboarding PDS.');
        }

        $profile->save();

        return $profile->fresh();
    }

    public function registerUser(User $user): array
    {
        $profile = $this->ensureIdentityComplete($this->profileFor($user));

        $response = $this->provider->registerUser($this->registrationPayload($user, $profile));

        $profile->forceFill([
            'registration_status' => 'submitted',
            'last_error' => null,
        ])->save();

        return $response;
    }

    public function submitKyc(User $user, UploadedFile $video): array
    {
        $profile = $this->ensureIdentityComplete($this->profileFor($user));
        $binary = file_get_contents($video->getRealPath());

        if ($binary === false) {
            throw new RuntimeException('File video KYC tidak dapat dibaca.');
        }

        $path = $video->store("signature-profiles/{$user->id}/kyc", 'public');

        $response = $this->provider->submitKycVideo(
            email: (string) $profile->peruri_email,
            fileName: $video->getClientOriginalName() ?: ('kyc-'.$user->id.'.mp4'),
            videoBinary: $binary,
            payload: [],
        );

        $profile->forceFill([
            'kyc_status' => 'submitted',
            'kyc_video_path' => $path,
            'last_error' => null,
        ])->save();

        return $response;
    }

    public function setSpecimen(User $user, UploadedFile $image): array
    {
        $profile = $this->ensureIdentityComplete($this->profileFor($user));
        $binary = file_get_contents($image->getRealPath());

        if ($binary === false) {
            throw new RuntimeException('File specimen tanda tangan tidak dapat dibaca.');
        }

        $path = $image->store("signature-profiles/{$user->id}/specimen", 'public');

        $response = $this->provider->setSignatureSpecimen(
            email: (string) $profile->peruri_email,
            fileName: $image->getClientOriginalName() ?: ('specimen-'.$user->id.'.png'),
            imageBinary: $binary,
            payload: [],
        );

        $profile->forceFill([
            'specimen_status' => 'submitted',
            'specimen_image_path' => $path,
            'last_error' => null,
        ])->save();

        return $response;
    }

    public function registerKeyla(User $user): array
    {
        $profile = $this->ensureIdentityComplete($this->profileFor($user));

        $response = $this->provider->registerKeyla((string) $profile->peruri_email);

        $profile->forceFill([
            'keyla_status' => 'registered',
            'keyla_qr_image' => data_get($response, 'data.qrImage'),
            'last_error' => null,
        ])->save();

        return $response;
    }

    public function syncReadiness(User $user): array
    {
        $profile = $this->profileFor($user);

        return $this->readinessService->syncCustomerProfile($profile);
    }

    public function onboardingPayload(AppraisalRequest $record, User $user, ?int $selectedProvinceId = null): array
    {
        $profile = $this->profileFor($user)->fresh();
        $readiness = $this->readinessService->forCustomerProfile($profile);
        $references = $this->referencePayload($selectedProvinceId ?: $profile->reference_province_id);

        return [
            'request' => [
                'id' => $record->id,
                'request_number' => $record->request_number ?? ('REQ-'.$record->id),
                'contract_number' => $record->contract_number,
                'contract_date' => optional($record->contract_date)->toDateString(),
            ],
            'profile' => [
                'id' => $profile->id,
                'provider' => $profile->provider,
                'peruri_email' => $profile->peruri_email,
                'peruri_phone' => $profile->peruri_phone,
                'nik' => $profile->nik,
                'is_wna' => (bool) $profile->is_wna,
                'reference_province_id' => $profile->reference_province_id ? (string) $profile->reference_province_id : '',
                'reference_city_id' => $profile->reference_city_id ? (string) $profile->reference_city_id : '',
                'gender' => $profile->gender,
                'place_of_birth' => $profile->place_of_birth,
                'date_of_birth' => optional($profile->date_of_birth)->toDateString(),
                'address' => (string) data_get($profile->identity_payload, 'address', $user->address ?? ''),
                'has_ktp_photo' => filled($profile->ktp_photo_path),
                'registration_status' => $profile->registration_status,
                'kyc_status' => $profile->kyc_status,
                'specimen_status' => $profile->specimen_status,
                'certificate_status' => $profile->certificate_status,
                'keyla_status' => $profile->keyla_status,
                'keyla_qr_image' => $profile->keyla_qr_image,
                'last_error' => $profile->last_error,
                'last_checked_at' => optional($profile->last_checked_at)->toDateTimeString(),
            ],
            'references' => $references,
            'readiness' => $readiness,
            'actions' => [
                'save_identity_url' => route('appraisal.contract.onboarding.identity', ['id' => $record->id]),
                'register_user_url' => route('appraisal.contract.onboarding.register-user', ['id' => $record->id]),
                'submit_kyc_url' => route('appraisal.contract.onboarding.submit-kyc', ['id' => $record->id]),
                'set_specimen_url' => route('appraisal.contract.onboarding.set-specimen', ['id' => $record->id]),
                'register_keyla_url' => route('appraisal.contract.onboarding.register-keyla', ['id' => $record->id]),
                'refresh_url' => route('appraisal.contract.onboarding.refresh', ['id' => $record->id]),
                'contract_url' => route('appraisal.contract.page', ['id' => $record->id]),
            ],
        ];
    }

    public function snapshotForRequest(AppraisalRequest $record, User $user): void
    {
        $profile = $user->signatureProfile()->first();
        $readiness = $profile
            ? $this->snapshotReadinessFromStoredProfile($profile)
            : $this->readinessService->forCustomer($user, sync: false);
        $snapshot = (array) ($record->contract_signer_snapshot ?? []);

        $snapshot['customer'] = [
            'profile_id' => $profile?->id,
            'email' => $profile?->peruri_email,
            'phone' => $profile?->peruri_phone,
            'nik' => $profile?->nik ? str_repeat('*', max(strlen($profile->nik) - 4, 0)).substr((string) $profile->nik, -4) : null,
            'overall' => data_get($readiness, 'overall'),
            'certificate' => data_get($readiness, 'certificate'),
            'keyla' => data_get($readiness, 'keyla'),
            'last_checked_at' => data_get($readiness, 'checked_at'),
            'snapshot_at' => now()->toDateTimeString(),
        ];

        $record->forceFill([
            'contract_signer_snapshot' => $snapshot,
        ])->save();
    }

    /**
     * @return array<string, mixed>
     */
    private function snapshotReadinessFromStoredProfile(UserSignatureProfile $profile): array
    {
        $registrationReady = in_array((string) $profile->registration_status, ['submitted', 'ready'], true);
        $kycReady = in_array((string) $profile->kyc_status, ['submitted', 'ready'], true);
        $specimenReady = in_array((string) $profile->specimen_status, ['submitted', 'ready'], true);
        $certificateReady = (string) $profile->certificate_status === 'ready';
        $keylaReady = (string) $profile->keyla_status === 'ready';
        $overallReady = $registrationReady && $kycReady && $specimenReady && $certificateReady && $keylaReady;

        return [
            'overall' => [
                'code' => $overallReady ? 'ready' : 'blocked',
                'label' => $overallReady ? 'Siap' : 'Belum Siap',
                'message' => $overallReady
                    ? 'Customer siap untuk tanda tangan digital.'
                    : ((string) ($profile->last_error ?: 'Customer masih menyelesaikan onboarding PDS/KEYLA.')),
                'is_ready' => $overallReady,
                'tone' => $overallReady ? 'success' : 'warning',
            ],
            'certificate' => [
                'code' => (string) ($profile->certificate_status ?: 'unknown'),
            ],
            'keyla' => [
                'code' => (string) ($profile->keyla_status ?: 'unknown'),
            ],
            'checked_at' => optional($profile->last_checked_at)->toDateTimeString(),
        ];
    }

    private function referencePayload(?int $selectedProvinceId): array
    {
        try {
            $provinceData = $this->normalizeReferenceRows($this->provider->referenceProvinces());
            $cityData = $selectedProvinceId
                ? $this->normalizeReferenceRows($this->provider->referenceCities($selectedProvinceId))
                : [];

            return [
                'provinces' => $provinceData !== [] ? $provinceData : $this->fallbackProvinceRows(),
                'cities' => $cityData !== [] ? $cityData : $this->fallbackCityRows($selectedProvinceId),
                'error' => $provinceData !== [] ? null : 'Referensi wilayah PDS belum tersedia. Daftar wilayah sementara memakai master data internal.',
            ];
        } catch (RuntimeException $exception) {
            return [
                'provinces' => $this->fallbackProvinceRows(),
                'cities' => $this->fallbackCityRows($selectedProvinceId),
                'error' => $this->fallbackReferenceError($exception->getMessage()),
            ];
        }
    }

    private function normalizeReferenceRows(mixed $rows): array
    {
        $candidates = $this->extractReferenceCollections($rows);

        if ($candidates === []) {
            return [];
        }

        return collect($candidates)
            ->filter(fn ($row) => is_array($row))
            ->map(function (array $row): array {
                return [
                    'value' => (string) ($row['id'] ?? $row['idProvince'] ?? $row['idCity'] ?? $row['provinceId'] ?? $row['cityId'] ?? ''),
                    'label' => (string) ($row['name'] ?? $row['provinceName'] ?? $row['cityName'] ?? $row['province'] ?? $row['city'] ?? ''),
                ];
            })
            ->filter(fn (array $row) => $row['value'] !== '' && $row['label'] !== '')
            ->unique('value')
            ->values()
            ->all();
    }

    private function extractReferenceCollections(mixed $payload): array
    {
        if (! is_array($payload)) {
            return [];
        }

        if ($this->looksLikeReferenceRows($payload)) {
            return $payload;
        }

        foreach ($payload as $value) {
            if (! is_array($value)) {
                continue;
            }

            $nested = $this->extractReferenceCollections($value);
            if ($nested !== []) {
                return $nested;
            }
        }

        return [];
    }

    private function looksLikeReferenceRows(array $rows): bool
    {
        if ($rows === []) {
            return false;
        }

        foreach ($rows as $row) {
            if (! is_array($row)) {
                return false;
            }

            $hasId = isset($row['id']) || isset($row['idProvince']) || isset($row['idCity']) || isset($row['provinceId']) || isset($row['cityId']);
            $hasLabel = isset($row['name']) || isset($row['provinceName']) || isset($row['cityName']) || isset($row['province']) || isset($row['city']);

            if ($hasId && $hasLabel) {
                return true;
            }
        }

        return false;
    }

    private function fallbackProvinceRows(): array
    {
        return Province::query()
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (Province $province): array => [
                'value' => (string) $province->id,
                'label' => (string) $province->name,
            ])
            ->values()
            ->all();
    }

    private function fallbackCityRows(?int $selectedProvinceId): array
    {
        if (! $selectedProvinceId) {
            return [];
        }

        return Regency::query()
            ->where('province_id', (string) $selectedProvinceId)
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (Regency $regency): array => [
                'value' => (string) $regency->id,
                'label' => (string) $regency->name,
            ])
            ->values()
            ->all();
    }

    private function fallbackReferenceError(string $message): string
    {
        $fallbackAvailable = $this->fallbackProvinceRows() !== [];

        return $fallbackAvailable
            ? 'Referensi wilayah PDS sedang bermasalah. Daftar wilayah sementara memakai master data internal. '.$message
            : $message;
    }

    private function registrationPayload(User $user, UserSignatureProfile $profile): array
    {
        $ktpPhotoPath = (string) $profile->ktp_photo_path;
        $ktpPhotoBinary = Storage::disk('local')->get($ktpPhotoPath);
        if ($ktpPhotoBinary === null) {
            throw new RuntimeException('Foto identitas tidak dapat dibaca.');
        }

        return array_filter([
            'isWNA' => (bool) $profile->is_wna,
            'name' => $user->name,
            'email' => $profile->peruri_email,
            'phone' => $profile->peruri_phone,
            'type' => 'INDIVIDUAL',
            'ktp' => $profile->nik,
            'ktpPhoto' => base64_encode($ktpPhotoBinary),
            'province' => (string) $profile->reference_province_id,
            'city' => (string) $profile->reference_city_id,
            'address' => data_get($profile->identity_payload, 'address'),
            'gender' => $profile->gender,
            'placeOfBirth' => $profile->place_of_birth,
            'dateOfBirth' => optional($profile->date_of_birth)->format('d/m/Y'),
        ], fn ($value) => $value !== null && $value !== '');
    }

    private function ensureIdentityComplete(UserSignatureProfile $profile): UserSignatureProfile
    {
        if (! filled($profile->peruri_email) || ! filled($profile->peruri_phone) || ! filled($profile->nik)) {
            throw new RuntimeException('Lengkapi identitas onboarding terlebih dahulu.');
        }

        if (! $profile->reference_province_id || ! $profile->reference_city_id) {
            throw new RuntimeException('Pilih provinsi dan kota sesuai referensi PDS.');
        }

        if (! filled($profile->gender) || ! filled($profile->place_of_birth) || ! $profile->date_of_birth) {
            throw new RuntimeException('Lengkapi jenis kelamin, tempat lahir, dan tanggal lahir untuk onboarding PDS.');
        }

        if (! filled(data_get($profile->identity_payload, 'address'))) {
            throw new RuntimeException('Alamat customer wajib diisi untuk onboarding PDS.');
        }

        if (! filled($profile->ktp_photo_path) || ! Storage::disk('local')->exists((string) $profile->ktp_photo_path)) {
            throw new RuntimeException('Foto identitas wajib diunggah untuk onboarding PDS.');
        }

        return $profile;
    }

    private function resetReadiness(UserSignatureProfile $profile, string $message): void
    {
        $profile->forceFill([
            'registration_status' => null,
            'kyc_status' => null,
            'specimen_status' => null,
            'certificate_status' => null,
            'keyla_status' => null,
            'keyla_qr_image' => null,
            'last_checked_at' => null,
            'last_error' => $message,
        ]);
    }
}

<?php

namespace App\Support\Peruri;

class CustomerSignatureOnboardingPresenter
{
    /**
     * @param  array<string, mixed>  $profile
     * @param  array<string, mixed>  $readiness
     * @return array<string, mixed>
     */
    public function present(array $profile, array $readiness): array
    {
        $registrationDone = (bool) data_get($readiness, 'registration.is_ready', false);
        $kycDone = (bool) data_get($readiness, 'kyc.is_ready', false);
        $specimenDone = (bool) data_get($readiness, 'specimen.is_ready', false);
        $certificateReady = (bool) data_get($readiness, 'certificate.is_ready', false);
        $keylaDone = (bool) data_get($readiness, 'keyla.is_ready', false);
        $overallReady = (bool) data_get($readiness, 'overall.is_ready', false);
        $hasSavedIdentity = filled($profile['reference_city_id'] ?? null)
            && filled($profile['gender'] ?? null)
            && filled($profile['date_of_birth'] ?? null)
            && ($profile['has_ktp_photo'] ?? false);
        $keylaQrAvailable = filled($profile['keyla_qr_image'] ?? null);

        return [
            'step_labels' => ['Data Diri', 'Rekam Wajah', 'Tanda Tangan', 'Aplikasi HP'],
            'active_step' => $this->activeStep($registrationDone, $kycDone, $specimenDone),
            'status' => [
                'label' => (string) data_get($readiness, 'overall.label', 'Belum Siap'),
                'tone' => (string) data_get($readiness, 'overall.tone', 'warning'),
                'message' => $overallReady
                    ? 'Aktivasi selesai. Anda sudah bisa kembali ke kontrak untuk tanda tangan.'
                    : 'Selesaikan langkah aktif di bawah agar kontrak bisa ditandatangani secara digital.',
                'show_last_error' => filled(data_get($readiness, 'last_error')),
            ],
            'steps' => $this->steps($registrationDone, $kycDone, $specimenDone, $keylaDone),
            'actions' => [
                'identity_submit_label' => $this->identitySubmitLabel($registrationDone, $hasSavedIdentity),
                'keyla_refresh_label' => $keylaQrAvailable ? 'Saya Sudah Scan, Cek Status' : 'Cek Status Aktivasi',
                'can_register_keyla' => $registrationDone && $kycDone && $specimenDone && $certificateReady && ! $keylaDone,
            ],
            'account_verification_message' => $certificateReady
                ? 'Akun tanda tangan Anda sudah selesai diverifikasi.'
                : 'Akun Anda masih diverifikasi oleh Peruri. Tekan cek status secara berkala sebelum menghubungkan aplikasi HP.',
            'auto_refresh' => [
                'enabled' => $keylaQrAvailable && ! $keylaDone,
                'interval_ms' => 15000,
                'max_attempts' => 8,
                'message' => 'Setelah QR discan, DigiPro akan mengecek status aplikasi HP secara otomatis beberapa kali.',
            ],
            'keyla_help' => [
                ['title' => 'Buka aplikasi KEYLA di HP', 'description' => 'Gunakan aplikasi KEYLA dari Peruri pada HP yang Anda pakai untuk tanda tangan.'],
                ['title' => 'Buat QR Aktivasi di DigiPro', 'description' => 'Tekan tombol buat QR, lalu arahkan kamera aplikasi KEYLA ke QR yang muncul.'],
                ['title' => 'Cek status setelah scan', 'description' => 'Jika aplikasi sudah terhubung, status akan berubah menjadi siap dan Anda bisa kembali ke kontrak.'],
            ],
            'technical_details' => $this->technicalDetails($profile, $readiness),
        ];
    }

    private function activeStep(bool $registrationDone, bool $kycDone, bool $specimenDone): int
    {
        if (! $registrationDone) {
            return 1;
        }

        if (! $kycDone) {
            return 2;
        }

        if (! $specimenDone) {
            return 3;
        }

        return 4;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function steps(bool $registrationDone, bool $kycDone, bool $specimenDone, bool $keylaDone): array
    {
        return [
            $this->step('identity', 'Data diri', $registrationDone ? 'Sudah lengkap' : 'Lengkapi data diri', $registrationDone, 'Data dipakai untuk membuat akun tanda tangan.'),
            $this->step('face_video', 'Video wajah', $kycDone ? 'Sudah dikirim' : 'Belum direkam', $kycDone, 'Rekam video singkat untuk verifikasi.'),
            $this->step('signature', 'Tanda tangan', $specimenDone ? 'Sudah tersimpan' : 'Belum dibuat', $specimenDone, 'Buat tanda tangan yang akan dipakai di dokumen.'),
            $this->step('mobile_app', 'Aplikasi HP', $keylaDone ? 'Sudah aktif' : 'Belum terhubung', $keylaDone, 'Hubungkan aplikasi KEYLA agar tanda tangan bisa diproses.'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function step(string $key, string $label, string $value, bool $isComplete, string $message): array
    {
        return [
            'key' => $key,
            'label' => $label,
            'value' => $value,
            'message' => $message,
            'is_complete' => $isComplete,
            'tone' => $isComplete ? 'success' : 'warning',
        ];
    }

    private function identitySubmitLabel(bool $registrationDone, bool $hasSavedIdentity): string
    {
        if ($registrationDone) {
            return 'Simpan Perubahan Data';
        }

        if ($hasSavedIdentity) {
            return 'Coba Buat Akun Lagi';
        }

        return 'Simpan dan Buat Akun Tanda Tangan';
    }

    /**
     * @param  array<string, mixed>  $profile
     * @param  array<string, mixed>  $readiness
     * @return array<int, array<string, string>>
     */
    private function technicalDetails(array $profile, array $readiness): array
    {
        return [
            $this->technicalRow('Akun tanda tangan', $readiness, 'registration', (string) ($profile['registration_status'] ?? '')),
            $this->technicalRow('Video wajah', $readiness, 'kyc', (string) ($profile['kyc_status'] ?? '')),
            $this->technicalRow('Tanda tangan', $readiness, 'specimen', (string) ($profile['specimen_status'] ?? '')),
            $this->technicalRow('Verifikasi akun', $readiness, 'certificate', (string) ($profile['certificate_status'] ?? '')),
            $this->technicalRow('Aplikasi KEYLA', $readiness, 'keyla', (string) ($profile['keyla_status'] ?? '')),
            [
                'label' => 'Terakhir dicek',
                'value' => (string) (($profile['last_checked_at'] ?? null) ?: data_get($readiness, 'checked_at', '-')),
                'code' => '',
                'tone' => 'muted',
            ],
            [
                'label' => 'Catatan terakhir',
                'value' => (string) (($profile['last_error'] ?? null) ?: data_get($readiness, 'last_error', '-')),
                'code' => '',
                'tone' => filled(($profile['last_error'] ?? null) ?: data_get($readiness, 'last_error')) ? 'danger' : 'muted',
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $readiness
     * @return array<string, string>
     */
    private function technicalRow(string $label, array $readiness, string $key, string $storedCode): array
    {
        return [
            'label' => $label,
            'value' => (string) data_get($readiness, "{$key}.label", 'Belum Diketahui'),
            'code' => $storedCode !== '' ? $storedCode : (string) data_get($readiness, "{$key}.code", ''),
            'tone' => (string) data_get($readiness, "{$key}.tone", 'muted'),
        ];
    }
}

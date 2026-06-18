<?php

use App\Support\Peruri\CustomerSignatureOnboardingPresenter;

it('presents retry copy when identity is saved but the signature account is not registered', function (): void {
    $presenter = new CustomerSignatureOnboardingPresenter;

    $payload = $presenter->present([
        'reference_city_id' => 'R-31.74',
        'gender' => 'M',
        'date_of_birth' => '1990-01-15',
        'has_ktp_photo' => true,
        'keyla_qr_image' => 'data:image/png;base64,AAA',
        'registration_status' => null,
        'last_error' => 'Vendor unavailable',
    ], [
        'registration' => ['is_ready' => false, 'label' => 'Belum Aktif', 'code' => 'inactive', 'tone' => 'warning'],
        'kyc' => ['is_ready' => false, 'label' => 'Belum Aktif', 'code' => 'inactive', 'tone' => 'warning'],
        'specimen' => ['is_ready' => false, 'label' => 'Belum Aktif', 'code' => 'inactive', 'tone' => 'warning'],
        'certificate' => ['is_ready' => false, 'label' => 'Belum Diketahui', 'code' => 'unknown', 'tone' => 'muted'],
        'keyla' => ['is_ready' => false, 'label' => 'Belum Aktif', 'code' => 'registered', 'tone' => 'warning'],
        'overall' => ['is_ready' => false, 'label' => 'Belum Siap', 'tone' => 'warning'],
        'last_error' => 'Vendor unavailable',
    ]);

    expect($payload['active_step'])->toBe(1);
    expect($payload['actions']['identity_submit_label'])->toBe('Coba Buat Akun Lagi');
    expect($payload['auto_refresh']['enabled'])->toBeTrue();
    expect(collect($payload['technical_details'])->contains(fn (array $row): bool => $row['label'] === 'Catatan terakhir' && $row['value'] === 'Vendor unavailable'))->toBeTrue();
});

it('presents completed activation copy when all customer steps are ready', function (): void {
    $presenter = new CustomerSignatureOnboardingPresenter;

    $payload = $presenter->present([
        'reference_city_id' => 'R-31.74',
        'gender' => 'M',
        'date_of_birth' => '1990-01-15',
        'has_ktp_photo' => true,
        'keyla_qr_image' => 'data:image/png;base64,AAA',
        'registration_status' => 'submitted',
        'kyc_status' => 'submitted',
        'specimen_status' => 'submitted',
        'certificate_status' => 'ready',
        'keyla_status' => 'ready',
    ], [
        'registration' => ['is_ready' => true, 'label' => 'Siap', 'code' => 'ready', 'tone' => 'success'],
        'kyc' => ['is_ready' => true, 'label' => 'Siap', 'code' => 'ready', 'tone' => 'success'],
        'specimen' => ['is_ready' => true, 'label' => 'Siap', 'code' => 'ready', 'tone' => 'success'],
        'certificate' => ['is_ready' => true, 'label' => 'Siap', 'code' => 'ready', 'tone' => 'success'],
        'keyla' => ['is_ready' => true, 'label' => 'Siap', 'code' => 'ready', 'tone' => 'success'],
        'overall' => ['is_ready' => true, 'label' => 'Siap', 'tone' => 'success'],
    ]);

    expect($payload['active_step'])->toBe(4);
    expect($payload['status']['message'])->toBe('Aktivasi selesai. Anda sudah bisa kembali ke kontrak untuk tanda tangan.');
    expect($payload['actions']['identity_submit_label'])->toBe('Simpan Perubahan Data');
    expect($payload['auto_refresh']['enabled'])->toBeFalse();
});

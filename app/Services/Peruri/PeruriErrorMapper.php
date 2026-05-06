<?php

namespace App\Services\Peruri;

class PeruriErrorMapper
{
    public function messageForStatus(string $status, ?string $fallbackMessage = null): string
    {
        return match ($status) {
            '00' => 'Sukses.',
            '84' => 'Client Peruri tidak aktif.',
            '01' => 'Akun Peruri belum diverifikasi (belum mengirimkan specimen).',
            '05' => 'User belum melakukan e-KYC.',
            '15' => 'Masa berlaku sertifikat sudah habis.',
            '16' => 'Sertifikat tidak ditemukan.',
            '38' => 'Data order tidak ditemukan.',
            '42' => 'OTP/Token tidak valid.',
            '45' => 'Dokumen expired (lebih dari 3 hari belum ditandatangani).',
            '46' => 'Specimen tidak ditemukan.',
            '47' => 'Koordinat tanda tangan tidak ditemukan.',
            '55' => 'Token KEYLA tidak valid.',
            '61' => 'Token KEYLA expired.',
            '67' => 'Dokumen melebihi ukuran maksimal (5MB).',
            '71' => 'Kuota tidak mencukupi.',
            '87' => 'Versi API sudah deprecated.',
            '90' => 'Token akses tidak valid.',
            '94' => 'Unauthorized request.',
            '95' => 'Gagal generate access token.',
            '96' => 'Service sedang maintenance.',
            '97' => 'Too many requests.',
            '98' => 'Session expired.',
            '99' => 'General error.',
            default => $fallbackMessage ?: "Peruri error (status: {$status}).",
        };
    }
}

<?php

namespace App\Support\Admin\MasterData;

class LocationResourceRegistry
{
    public function definition(string $key): array
    {
        return match ($key) {
            'provinces' => [
                'key' => 'provinces',
                'title' => 'Provinsi',
                'singular' => 'Provinsi',
                'description' => 'Kelola daftar nama provinsi untuk dipakai lintas flow penilaian.',
                'create_label' => 'Tambah Provinsi',
                'code_label' => 'Kode Provinsi',
            ],
            'regencies' => [
                'key' => 'regencies',
                'title' => 'Kabupaten/Kota',
                'singular' => 'Kabupaten/Kota',
                'description' => 'Kelola daftar kabupaten dan kota per provinsi.',
                'create_label' => 'Tambah Kabupaten/Kota',
                'code_label' => 'Kode Kabupaten/Kota',
            ],
            'districts' => [
                'key' => 'districts',
                'title' => 'Kecamatan',
                'singular' => 'Kecamatan',
                'description' => 'Kelola daftar kecamatan per kabupaten/kota.',
                'create_label' => 'Tambah Kecamatan',
                'code_label' => 'Kode Kecamatan',
            ],
            default => [
                'key' => 'villages',
                'title' => 'Kelurahan/Desa',
                'singular' => 'Kelurahan/Desa',
                'description' => 'Kelola daftar kelurahan dan desa per kecamatan.',
                'create_label' => 'Tambah Kelurahan/Desa',
                'code_label' => 'Kode Kelurahan/Desa',
            ],
        };
    }

    public function generatorProps(string $type, string $previewUrl, ?string $parentField = null): array
    {
        return [
            'type' => $type,
            'parent_field' => $parentField,
            'preview_url' => $previewUrl,
        ];
    }
}

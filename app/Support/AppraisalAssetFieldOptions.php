<?php

namespace App\Support;

class AppraisalAssetFieldOptions
{
    public static function usageOptions(): array
    {
        return [
            ['value' => 'tanah_kosong', 'label' => 'Tanah Kosong'],
            ['value' => 'rumah_tinggal', 'label' => 'Rumah Tinggal'],
            ['value' => 'ruko', 'label' => 'Ruko / Rukan'],
            ['value' => 'kantor', 'label' => 'Kantor'],
            ['value' => 'gudang', 'label' => 'Gudang'],
            ['value' => 'pabrik', 'label' => 'Pabrik'],
            ['value' => 'apartement', 'label' => 'Apartemen'],
            ['value' => 'kios', 'label' => 'Kios'],
            ['value' => 'tanah_kebun', 'label' => 'Tanah Kebun'],
            ['value' => 'sawah', 'label' => 'Sawah'],
        ];
    }

    public static function titleDocumentOptions(): array
    {
        return [
            ['value' => 'shm', 'label' => 'SHM'],
            ['value' => 'hgb', 'label' => 'HGB'],
            ['value' => 'girik', 'label' => 'Girik'],
            ['value' => 'akta_jual_beli', 'label' => 'Akta Jual Beli'],
            ['value' => 'lainnya', 'label' => 'Lainnya'],
        ];
    }

    public static function landShapeOptions(): array
    {
        return [
            ['value' => 'persegi', 'label' => 'Persegi'],
            ['value' => 'persegi_panjang', 'label' => 'Persegi Panjang'],
            ['value' => 'segitiga', 'label' => 'Segitiga'],
            ['value' => 'tidak_beraturan', 'label' => 'Tidak Beraturan'],
        ];
    }

    public static function landPositionOptions(): array
    {
        return [
            ['value' => 'hook', 'label' => 'Hook / Corner Lot'],
            ['value' => 'tengah', 'label' => 'Tengah / Interior Lot'],
            ['value' => 'cul_de_sac', 'label' => 'Cul-de-sac'],
            ['value' => 'tusuk_sate', 'label' => 'Tusuk Sate'],
        ];
    }

    public static function landConditionOptions(): array
    {
        return [
            ['value' => 'matang', 'label' => 'Matang'],
            ['value' => 'belum_matang', 'label' => 'Belum Matang'],
            ['value' => 'perlu_urug', 'label' => 'Perlu Urug'],
        ];
    }

    public static function topographyOptions(): array
    {
        return [
            ['value' => 'datar_sama_dengan_jalan', 'label' => 'Datar, sama dengan jalan'],
            ['value' => 'datar_lebih_rendah_dari_jalan', 'label' => 'Datar, lebih rendah dari jalan'],
            ['value' => 'datar_lebih_tinggi_dari_jalan', 'label' => 'Datar, lebih tinggi dari jalan'],
            ['value' => 'bergelombang', 'label' => 'Bergelombang'],
            ['value' => 'berlereng', 'label' => 'Berlereng'],
        ];
    }

    public static function toSelectMap(array $options): array
    {
        $mapped = [];

        foreach ($options as $option) {
            $value = (string) ($option['value'] ?? '');
            $label = (string) ($option['label'] ?? $value);

            if ($value === '') {
                continue;
            }

            $mapped[$value] = $label;
        }

        return $mapped;
    }
}

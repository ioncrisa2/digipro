<?php

return [
    /**
     * Tier dokumen kepemilikan dan penyesuaiannya (dalam persen, negatif artinya penurunan).
     */
    'document_tiers' => [
        'sertifikat_hak_milik' => 0.0,
        'sertifikat_hak_guna_bangunan' => -2.5,
        'sertifikat_hak_guna_usaha' => -4.0,
        'akta_jual_beli' => -6.0,
        'girik' => -8.0,
        'petok_desa' => -8.0,
        'surat_camat' => -10.0,
        'lainnya' => -10.0,
    ],

    /**
     * Penyesuaian rasio luas tanah (perbandingan luas pembanding terhadap luas subjek).
     * Kunci adalah batas rasio (<=), nilai adalah penyesuaian persen.
     */
    'land_area_ratio_grid' => [
        '0.50' => 8.0,
        '0.70' => 4.0,
        '0.90' => 2.0,
        '1.10' => 0.0,
        '1.30' => -2.0,
        '1.50' => -4.0,
        '2.00' => -6.0,
        '99.0' => -8.0, // fallback untuk rasio sangat besar
    ],

    /**
     * Penyesuaian lebar muka (frontage) relatif terhadap kebutuhan ideal.
     * Kunci adalah rasio frontage pembanding terhadap ideal; nilai persen.
     */
    'frontage_ratio_grid' => [
        '0.50' => 6.0,
        '0.75' => 3.0,
        '1.00' => 0.0,
        '1.25' => -2.0,
        '1.50' => -3.0,
        '2.00' => -4.0,
        '99.0' => -5.0,
    ],
];

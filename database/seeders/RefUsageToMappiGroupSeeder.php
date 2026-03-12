<?php

namespace Database\Seeders;

use App\Models\RefUsageToMappiGroup;
use Illuminate\Database\Seeder;

class RefUsageToMappiGroupSeeder extends Seeder
{
    public function run(): void
    {
        $rows = [
            [
                'peruntukan_enum' => 'tanah_kosong',
                'mappi_building_type' => '',
                'mappi_building_class' => null,
                'default_storey_group' => null,
                'notes' => 'Tanah kosong, tidak menggunakan RCN bangunan.',
            ],
            [
                'peruntukan_enum' => 'rumah_tinggal',
                'mappi_building_type' => 'BANGUNAN_RUMAH_TINGGAL',
                'mappi_building_class' => 'SEDERHANA',
                'default_storey_group' => '1 Lantai',
                'notes' => 'Default awal untuk rumah tinggal. Kelas dapat diubah bila ada aturan lebih spesifik.',
            ],
            [
                'peruntukan_enum' => 'ruko',
                'mappi_building_type' => 'RUKO',
                'mappi_building_class' => null,
                'default_storey_group' => null,
                'notes' => 'Ruko/rukan mengikuti referensi MAPPI tipe RUKO.',
            ],
            [
                'peruntukan_enum' => 'kantor',
                'mappi_building_type' => 'BANGUNAN_GEDUNG_BERTINGKAT',
                'mappi_building_class' => 'LOW_RISE',
                'default_storey_group' => '3 Lantai (< 5 Lantai)',
                'notes' => 'Default konservatif untuk kantor umum.',
            ],
            [
                'peruntukan_enum' => 'gudang',
                'mappi_building_type' => 'BANGUNAN_GUDANG',
                'mappi_building_class' => null,
                'default_storey_group' => '1 Lantai',
                'notes' => 'Gudang standar.',
            ],
            [
                'peruntukan_enum' => 'pabrik',
                'mappi_building_type' => 'BANGUNAN_GUDANG',
                'mappi_building_class' => null,
                'default_storey_group' => '1 Lantai',
                'notes' => 'Fallback awal; sesuaikan jika nanti ada referensi pabrik yang lebih spesifik.',
            ],
            [
                'peruntukan_enum' => 'apartement',
                'mappi_building_type' => 'MODEL_APARTEMEN',
                'mappi_building_class' => 'GRADE_B',
                'default_storey_group' => null,
                'notes' => 'Default awal untuk apartemen.',
            ],
            [
                'peruntukan_enum' => 'kios',
                'mappi_building_type' => 'RUKO',
                'mappi_building_class' => null,
                'default_storey_group' => null,
                'notes' => 'Kios diarahkan ke kategori komersial ringan.',
            ],
            [
                'peruntukan_enum' => 'tanah_kebun',
                'mappi_building_type' => '',
                'mappi_building_class' => null,
                'default_storey_group' => null,
                'notes' => 'Tanah kebun, default tanpa bangunan.',
            ],
            [
                'peruntukan_enum' => 'sawah',
                'mappi_building_type' => '',
                'mappi_building_class' => null,
                'default_storey_group' => null,
                'notes' => 'Sawah, default tanpa bangunan.',
            ],
        ];

        foreach ($rows as $row) {
            RefUsageToMappiGroup::query()->updateOrCreate(
                ['peruntukan_enum' => $row['peruntukan_enum']],
                $row,
            );
        }
    }
}

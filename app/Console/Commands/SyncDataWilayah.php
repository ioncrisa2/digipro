<?php

namespace App\Console\Commands;

use App\Models\Regency;
use App\Models\Village;
use App\Models\District;
use App\Models\Province;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;

/**
 * Console command to sync Indonesian region data from external API into local tables.
 */
class SyncDataWilayah extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:sync-data-wilayah';


    /**
     * The console command description.
    *
    * @var string
    */
    protected $description = 'Sinkronisasi data wilayah Indonesia dari API ke database lokal';

    protected $baseUrl = 'https://emsifa.github.io/api-wilayah-indonesia/api';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Mulai sinkronisasi data wilayah...');

        try {
            DB::transaction(function () {
                $this->syncProvinces();
                $this->syncRegencies();
                $this->syncDistricts();
                $this->syncVillages();
            });

            $this->info('Sinkronisasi data wilayah selesai.');
            return 0;
        } catch (\Throwable $e) {
            $this->error('Sinkronisasi Gagal: ' . $e->getMessage());
            Log::error('Sync Wilayah Gagal: ' . $e->getMessage(), ['exception' => $e]);
            return 1;
        }
    }

    private function syncProvinces()
    {
        $this->line('Menyinkronkan Provinsi...');
        $response = Http::retry(3, 100)->get("{$this->baseUrl}/provinces.json");

        if (!$response->ok()) {
            throw new \Exception('Gagal mengambil data provinsi.');
        }

        $provinces = $response->json();

        $dataToUpsert = collect($provinces)->map(fn ($data) => [
            'id' => $data['id'],
            'name' => $data['name']
        ])->all();

        Province::upsert($dataToUpsert, ['id'], ['name']);

        $this->info(count($dataToUpsert) . ' Provinsi disinkronkan.');
    }

    private function syncRegencies()
    {
        $this->line('Menyinkronkan Kabupaten/Kota...');

        $provinces = Province::lazy();
        $bar = $this->output->createProgressBar($provinces->count());
        $bar->start();

        foreach ($provinces as $province) {
            $response = Http::retry(3, 100)->get("{$this->baseUrl}/regencies/{$province->id}.json");

            if (!$response->ok()) {
                Log::warning("Gagal mengambil data kabupaten untuk provinsi: {$province->name}");
                continue;
            }

            $regencies = $response->json();
            if (empty($regencies)) {
                $bar->advance();
                continue;
            }

            $dataToUpsert = collect($regencies)->map(fn ($data) => [
                'id' => $data['id'],
                'name' => $data['name'],
                'province_id' => $province->id
            ])->all();

            Regency::upsert($dataToUpsert, ['id'], ['name', 'province_id']);

            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);
    }

    private function syncDistricts()
    {
        $this->line('Menyinkronkan Kecamatan...');

        $regencies = Regency::lazy();
        $bar = $this->output->createProgressBar($regencies->count());
        $bar->start();

        foreach ($regencies as $regency) {
            $response = Http::retry(3, 100)->get("{$this->baseUrl}/districts/{$regency->id}.json");

            if (!$response->ok()) {
                Log::warning("Gagal mengambil data kecamatan untuk kabupaten: {$regency->name}");
                continue;
            }

            $districts = $response->json();
            if (empty($districts)) {
                $bar->advance();
                continue;
            }

            $dataToUpsert = collect($districts)->map(fn ($data) => [
                'id' => $data['id'],
                'name' => $data['name'],
                'regency_id' => $regency->id
            ])->all();

            District::upsert($dataToUpsert, ['id'], ['name', 'regency_id']);

            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);
    }

    private function syncVillages()
    {
        $this->line('Menyinkronkan Desa/Kelurahan...');

        $districts = District::lazy();
        $bar = $this->output->createProgressBar($districts->count());
        $bar->start();

        foreach ($districts as $district) {
            $response = Http::retry(3, 100)->get("{$this->baseUrl}/villages/{$district->id}.json");

            if (!$response->ok()) {
                Log::warning("Gagal mengambil data desa untuk kecamatan: {$district->name}");
                continue;
            }

            $villages = $response->json();
            if (empty($villages)) {
                $bar->advance();
                continue;
            }

            $dataToUpsert = collect($villages)->map(fn ($data) => [
                'id' => $data['id'],
                'name' => $data['name'],
                'district_id' => $district->id
            ])->all();

            // 1 Query per kecamatan
            Village::upsert($dataToUpsert, ['id'], ['name', 'district_id']);

            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);
    }
}

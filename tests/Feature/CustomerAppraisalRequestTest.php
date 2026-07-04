<?php

use App\Models\AppraisalAsset;
use App\Models\AppraisalAssetFile;
use App\Models\GuidelineSet;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

it('stores a building asset when the optional renovation year is blank', function () {
    Storage::fake('public');

    GuidelineSet::query()->create([
        'name' => 'Pedoman DigiPro 2026',
        'year' => 2026,
        'description' => 'Guideline aktif untuk test customer appraisal.',
        'is_active' => true,
    ]);

    $user = User::factory()->create([
        'email_verified_at' => now(),
    ]);

    $assetData = [
        'type' => 'tanah_bangunan',
        'land_area' => '120',
        'building_area' => '80',
        'floors' => '2',
        'build_year' => '2018',
        'renovation_year' => '',
        'peruntukan' => 'rumah_tinggal',
        'title_document' => 'SHM',
        'address' => 'Jl. Permohonan Test No. 1',
        'coordinates_lat' => '-6.200000',
        'coordinates_lng' => '106.816666',
        'maps_link' => '',
    ];

    $response = $this->actingAs($user)->post(route('appraisal.store'), [
        'sertifikat_on_hand_confirmed' => '1',
        'certificate_not_encumbered_confirmed' => '1',
        'assets' => [
            [
                'data' => json_encode($assetData, JSON_THROW_ON_ERROR),
                'doc_pbb' => UploadedFile::fake()->create('pbb.pdf', 200, 'application/pdf'),
                'doc_imb' => UploadedFile::fake()->create('imb.pdf', 200, 'application/pdf'),
                'doc_certs' => [
                    UploadedFile::fake()->create('sertifikat.pdf', 240, 'application/pdf'),
                ],
                'photos_access_road' => [
                    UploadedFile::fake()->image('akses-jalan.jpg'),
                ],
                'photos_front' => [
                    UploadedFile::fake()->image('depan.jpg'),
                ],
                'photos_interior' => [
                    UploadedFile::fake()->image('dalam.jpg'),
                ],
            ],
        ],
    ]);

    $response->assertRedirect(route('appraisal.list'));

    $asset = AppraisalAsset::query()->sole();

    expect($asset->renovation_year)->toBeNull();

    $frontPhoto = AppraisalAssetFile::query()
        ->where('appraisal_asset_id', $asset->id)
        ->where('type', 'photo_front')
        ->sole();

    $this->actingAs($user)
        ->get(route('appraisal.list'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Penilaian/Index')
            ->where('appraisals.data.0.front_photo_url', Storage::disk('public')->url($frontPhoto->path))
        );
});

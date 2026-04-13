<?php

namespace App\Services\Admin;

use App\Models\District;
use App\Models\Province;
use App\Models\Regency;
use App\Models\Village;
use App\Services\Location\LocationIdGenerator;
use App\Support\Admin\MasterData\LocationOptionsProvider;
use App\Support\Admin\MasterData\LocationResourceRegistry;
use App\Support\Admin\MasterData\LocationRowPresenter;
use Illuminate\Support\Facades\DB;

class AdminMasterDataLocationWorkspaceService
{
    public function __construct(
        private readonly LocationOptionsProvider $locationOptions,
        private readonly LocationResourceRegistry $locationResources,
        private readonly LocationRowPresenter $locationRows,
    ) {
    }

    public function locationOptionsPayload(array $validated): array
    {
        return match ($validated['type']) {
            'provinces' => $this->locationOptions->provinceSelectOptions(),
            'regencies' => $this->locationOptions->regencySelectOptionsByProvince($validated['province_id'] ?? null),
            'districts' => $this->locationOptions->districtSelectOptionsByRegency($validated['regency_id'] ?? null),
        };
    }

    public function previewLocationId(array $validated, LocationIdGenerator $generator): ?string
    {
        return DB::transaction(function () use ($validated, $generator) {
            return match ($validated['type']) {
                'province' => $generator->nextProvinceId(),
                'regency' => $generator->nextRegencyId((string) ($validated['province_id'] ?? '')),
                'district' => $generator->nextDistrictId((string) ($validated['regency_id'] ?? '')),
                'village' => $generator->nextVillageId((string) ($validated['district_id'] ?? '')),
            };
        });
    }

    public function provincesIndexPayload(array $filters, int $perPage, string $workspacePrefix): array
    {
        $records = Province::query()
            ->withCount('regencies')
            ->when($filters['q'] !== '', function ($query) use ($filters): void {
                $query->where(function ($innerQuery) use ($filters): void {
                    $innerQuery
                        ->where('id', 'like', '%' . $filters['q'] . '%')
                        ->orWhere('name', 'like', '%' . $filters['q'] . '%');
                });
            })
            ->orderBy('name')
            ->paginate($perPage)
            ->withQueryString();

        $records->through(fn (Province $province) => $this->locationRows->province(
            $province,
            $this->workspaceRoute($workspacePrefix, 'master-data.provinces.edit', $province),
            $this->workspaceRoute($workspacePrefix, 'master-data.provinces.destroy', $province),
        ));

        return [
            'resource' => $this->locationResources->definition('provinces'),
            'filters' => $filters,
            'filterOptions' => [],
            'summaryCards' => [
                ['label' => 'Total Provinsi', 'value' => Province::query()->count()],
                ['label' => 'Total Kabupaten/Kota', 'value' => Regency::query()->count()],
                ['label' => 'Provinsi Dengan Kabupaten/Kota', 'value' => Province::query()->has('regencies')->count()],
            ],
            'records' => $this->paginatedRecordsPayload($records),
            'indexUrl' => $this->workspaceRoute($workspacePrefix, 'master-data.provinces.index'),
            'createUrl' => $this->workspaceRoute($workspacePrefix, 'master-data.provinces.create'),
        ];
    }

    public function provincesCreatePayload(LocationIdGenerator $generator, string $workspacePrefix): array
    {
        return [
            'resource' => $this->locationResources->definition('provinces'),
            'mode' => 'create',
            'record' => [
                'id' => $this->locationGeneratedIdPreview('province', [], $generator),
                'name' => '',
            ],
            'selectFields' => [],
            'generator' => $this->locationResources->generatorProps(
                'province',
                $this->workspaceRoute($workspacePrefix, 'master-data.locations.id-preview')
            ),
            'optionsUrl' => $this->workspaceRoute($workspacePrefix, 'master-data.locations.options'),
            'indexUrl' => $this->workspaceRoute($workspacePrefix, 'master-data.provinces.index'),
            'submitUrl' => $this->workspaceRoute($workspacePrefix, 'master-data.provinces.store'),
        ];
    }

    public function saveProvince(array $validated, LocationIdGenerator $generator): void
    {
        DB::transaction(function () use ($validated, $generator): void {
            Province::query()->create([
                'id' => $generator->nextProvinceId(),
                'name' => $validated['name'],
            ]);
        });
    }

    public function provincesEditPayload(Province $province, string $workspacePrefix): array
    {
        return [
            'resource' => $this->locationResources->definition('provinces'),
            'mode' => 'edit',
            'record' => [
                'id' => $province->id,
                'name' => $province->name,
            ],
            'selectFields' => [],
            'generator' => null,
            'optionsUrl' => $this->workspaceRoute($workspacePrefix, 'master-data.locations.options'),
            'indexUrl' => $this->workspaceRoute($workspacePrefix, 'master-data.provinces.index'),
            'submitUrl' => $this->workspaceRoute($workspacePrefix, 'master-data.provinces.update', $province),
        ];
    }

    public function updateProvince(Province $province, array $validated): void
    {
        $province->forceFill([
            'name' => $validated['name'],
        ])->save();
    }

    public function regenciesIndexPayload(array $filters, int $perPage, string $workspacePrefix): array
    {
        $records = Regency::query()
            ->with(['province:id,name'])
            ->withCount('districts')
            ->when($filters['q'] !== '', function ($query) use ($filters): void {
                $query->where(function ($innerQuery) use ($filters): void {
                    $innerQuery
                        ->where('id', 'like', '%' . $filters['q'] . '%')
                        ->orWhere('name', 'like', '%' . $filters['q'] . '%');
                });
            })
            ->when($filters['province_id'] !== 'all', fn ($query) => $query->where('province_id', $filters['province_id']))
            ->orderBy('name')
            ->paginate($perPage)
            ->withQueryString();

        $records->through(fn (Regency $regency) => $this->locationRows->regency(
            $regency,
            $this->workspaceRoute($workspacePrefix, 'master-data.regencies.edit', $regency),
            $this->workspaceRoute($workspacePrefix, 'master-data.regencies.destroy', $regency),
        ));

        return [
            'resource' => $this->locationResources->definition('regencies'),
            'filters' => $filters,
            'filterOptions' => [[
                'key' => 'province_id',
                'label' => 'Provinsi',
                'defaultValue' => 'all',
                'options' => $this->locationOptions->provinceFilterOptions(),
            ]],
            'summaryCards' => [
                ['label' => 'Total Kabupaten/Kota', 'value' => Regency::query()->count()],
                ['label' => 'Provinsi Tercakup', 'value' => Regency::query()->distinct('province_id')->count('province_id')],
                ['label' => 'Total Kecamatan', 'value' => District::query()->count()],
            ],
            'records' => $this->paginatedRecordsPayload($records),
            'indexUrl' => $this->workspaceRoute($workspacePrefix, 'master-data.regencies.index'),
            'createUrl' => $this->workspaceRoute($workspacePrefix, 'master-data.regencies.create'),
        ];
    }

    public function regenciesCreatePayload(
        string $selectedProvinceId,
        LocationIdGenerator $generator,
        string $workspacePrefix,
    ): array {
        return [
            'resource' => $this->locationResources->definition('regencies'),
            'mode' => 'create',
            'record' => [
                'id' => $this->locationGeneratedIdPreview('regency', ['province_id' => $selectedProvinceId], $generator),
                'name' => '',
                'province_id' => $selectedProvinceId,
            ],
            'selectFields' => [[
                'key' => 'province_id',
                'label' => 'Provinsi',
                'placeholder' => 'Pilih provinsi',
                'options' => $this->locationOptions->provinceSelectOptions(),
            ]],
            'generator' => $this->locationResources->generatorProps(
                'regency',
                $this->workspaceRoute($workspacePrefix, 'master-data.locations.id-preview'),
                'province_id',
            ),
            'showIdField' => false,
            'optionsUrl' => $this->workspaceRoute($workspacePrefix, 'master-data.locations.options'),
            'indexUrl' => $this->workspaceRoute($workspacePrefix, 'master-data.regencies.index'),
            'submitUrl' => $this->workspaceRoute($workspacePrefix, 'master-data.regencies.store'),
        ];
    }

    public function saveRegency(array $validated, LocationIdGenerator $generator): void
    {
        DB::transaction(function () use ($validated, $generator): void {
            Regency::query()->create([
                'id' => $generator->nextRegencyId($validated['province_id']),
                'province_id' => $validated['province_id'],
                'name' => $validated['name'],
            ]);
        });
    }

    public function regenciesEditPayload(Regency $regency, string $workspacePrefix): array
    {
        $regency->loadMissing('province:id,name');

        return [
            'resource' => $this->locationResources->definition('regencies'),
            'mode' => 'edit',
            'record' => [
                'id' => $regency->id,
                'name' => $regency->name,
                'province_id' => $regency->province_id,
            ],
            'selectFields' => [[
                'key' => 'province_id',
                'label' => 'Provinsi',
                'placeholder' => 'Pilih provinsi',
                'options' => $this->locationOptions->provinceSelectOptions(),
            ]],
            'generator' => null,
            'optionsUrl' => $this->workspaceRoute($workspacePrefix, 'master-data.locations.options'),
            'indexUrl' => $this->workspaceRoute($workspacePrefix, 'master-data.regencies.index'),
            'submitUrl' => $this->workspaceRoute($workspacePrefix, 'master-data.regencies.update', $regency),
        ];
    }

    public function updateRegency(Regency $regency, array $validated): void
    {
        $regency->forceFill([
            'province_id' => $validated['province_id'],
            'name' => $validated['name'],
        ])->save();
    }

    public function districtsIndexPayload(array $filters, int $perPage, string $workspacePrefix): array
    {
        $records = District::query()
            ->with(['regency:id,name,province_id', 'regency.province:id,name'])
            ->withCount('villages')
            ->when($filters['q'] !== '', function ($query) use ($filters): void {
                $query->where(function ($innerQuery) use ($filters): void {
                    $innerQuery
                        ->where('id', 'like', '%' . $filters['q'] . '%')
                        ->orWhere('name', 'like', '%' . $filters['q'] . '%');
                });
            })
            ->when($filters['regency_id'] !== 'all', fn ($query) => $query->where('regency_id', $filters['regency_id']))
            ->when($filters['province_id'] !== 'all', function ($query) use ($filters): void {
                $query->whereHas('regency', fn ($regencyQuery) => $regencyQuery->where('province_id', $filters['province_id']));
            })
            ->orderBy('name')
            ->paginate($perPage)
            ->withQueryString();

        $records->through(fn (District $district) => $this->locationRows->district(
            $district,
            $this->workspaceRoute($workspacePrefix, 'master-data.districts.edit', $district),
            $this->workspaceRoute($workspacePrefix, 'master-data.districts.destroy', $district),
        ));

        return [
            'resource' => $this->locationResources->definition('districts'),
            'filters' => $filters,
            'filterOptions' => [
                [
                    'key' => 'province_id',
                    'label' => 'Provinsi',
                    'defaultValue' => 'all',
                    'options' => $this->locationOptions->provinceFilterOptions(),
                ],
                [
                    'key' => 'regency_id',
                    'label' => 'Kabupaten/Kota',
                    'defaultValue' => 'all',
                    'options' => $this->locationOptions->regencyFilterOptions(),
                ],
            ],
            'summaryCards' => [
                ['label' => 'Total Kecamatan', 'value' => District::query()->count()],
                ['label' => 'Kabupaten/Kota Tercakup', 'value' => District::query()->distinct('regency_id')->count('regency_id')],
                ['label' => 'Total Kelurahan/Desa', 'value' => Village::query()->count()],
            ],
            'records' => $this->paginatedRecordsPayload($records),
            'indexUrl' => $this->workspaceRoute($workspacePrefix, 'master-data.districts.index'),
            'createUrl' => $this->workspaceRoute($workspacePrefix, 'master-data.districts.create'),
        ];
    }

    public function districtsCreatePayload(
        string $selectedRegencyId,
        LocationIdGenerator $generator,
        string $workspacePrefix,
    ): array {
        $selectedProvinceId = '';

        if ($selectedRegencyId !== '') {
            $selectedProvinceId = (string) Regency::query()
                ->whereKey($selectedRegencyId)
                ->value('province_id');
        }

        return [
            'resource' => $this->locationResources->definition('districts'),
            'mode' => 'create',
            'record' => [
                'id' => $this->locationGeneratedIdPreview('district', ['regency_id' => $selectedRegencyId], $generator),
                'name' => '',
                'province_id' => $selectedProvinceId,
                'regency_id' => $selectedRegencyId,
            ],
            'selectFields' => [
                [
                    'key' => 'province_id',
                    'label' => 'Provinsi',
                    'placeholder' => 'Pilih provinsi',
                    'options' => $this->locationOptions->provinceSelectOptions(),
                ],
                [
                    'key' => 'regency_id',
                    'label' => 'Kabupaten/Kota',
                    'placeholder' => 'Pilih kabupaten/kota',
                    'options' => $this->locationOptions->regencySelectOptionsByProvince($selectedProvinceId),
                    'depends_on' => 'province_id',
                    'endpoint_type' => 'regencies',
                    'parent_param' => 'province_id',
                ],
            ],
            'generator' => $this->locationResources->generatorProps(
                'district',
                $this->workspaceRoute($workspacePrefix, 'master-data.locations.id-preview'),
                'regency_id',
            ),
            'showIdField' => false,
            'optionsUrl' => $this->workspaceRoute($workspacePrefix, 'master-data.locations.options'),
            'indexUrl' => $this->workspaceRoute($workspacePrefix, 'master-data.districts.index'),
            'submitUrl' => $this->workspaceRoute($workspacePrefix, 'master-data.districts.store'),
        ];
    }

    public function saveDistrict(array $validated, LocationIdGenerator $generator): void
    {
        DB::transaction(function () use ($validated, $generator): void {
            District::query()->create([
                'id' => $generator->nextDistrictId($validated['regency_id']),
                'regency_id' => $validated['regency_id'],
                'name' => $validated['name'],
            ]);
        });
    }

    public function districtsEditPayload(District $district, string $workspacePrefix): array
    {
        $district->loadMissing(['regency:id,name,province_id', 'regency.province:id,name']);
        $selectedProvinceId = (string) ($district->regency?->province_id ?? '');

        return [
            'resource' => $this->locationResources->definition('districts'),
            'mode' => 'edit',
            'record' => [
                'id' => $district->id,
                'name' => $district->name,
                'province_id' => $selectedProvinceId,
                'regency_id' => $district->regency_id,
            ],
            'selectFields' => [
                [
                    'key' => 'province_id',
                    'label' => 'Provinsi',
                    'placeholder' => 'Pilih provinsi',
                    'options' => $this->locationOptions->provinceSelectOptions(),
                ],
                [
                    'key' => 'regency_id',
                    'label' => 'Kabupaten/Kota',
                    'placeholder' => 'Pilih kabupaten/kota',
                    'options' => $this->locationOptions->regencySelectOptionsByProvince($selectedProvinceId),
                    'depends_on' => 'province_id',
                    'endpoint_type' => 'regencies',
                    'parent_param' => 'province_id',
                ],
            ],
            'generator' => null,
            'optionsUrl' => $this->workspaceRoute($workspacePrefix, 'master-data.locations.options'),
            'indexUrl' => $this->workspaceRoute($workspacePrefix, 'master-data.districts.index'),
            'submitUrl' => $this->workspaceRoute($workspacePrefix, 'master-data.districts.update', $district),
        ];
    }

    public function updateDistrict(District $district, array $validated): void
    {
        $district->forceFill([
            'regency_id' => $validated['regency_id'],
            'name' => $validated['name'],
        ])->save();
    }

    public function villagesIndexPayload(array $filters, int $perPage, string $workspacePrefix): array
    {
        $records = Village::query()
            ->with(['district:id,name,regency_id', 'district.regency:id,name,province_id', 'district.regency.province:id,name'])
            ->when($filters['q'] !== '', function ($query) use ($filters): void {
                $query->where(function ($innerQuery) use ($filters): void {
                    $innerQuery
                        ->where('id', 'like', '%' . $filters['q'] . '%')
                        ->orWhere('name', 'like', '%' . $filters['q'] . '%');
                });
            })
            ->when($filters['district_id'] !== 'all', fn ($query) => $query->where('district_id', $filters['district_id']))
            ->when($filters['regency_id'] !== 'all', function ($query) use ($filters): void {
                $query->whereHas('district', fn ($districtQuery) => $districtQuery->where('regency_id', $filters['regency_id']));
            })
            ->when($filters['province_id'] !== 'all', function ($query) use ($filters): void {
                $query->whereHas('district.regency', fn ($regencyQuery) => $regencyQuery->where('province_id', $filters['province_id']));
            })
            ->orderBy('name')
            ->paginate($perPage)
            ->withQueryString();

        $records->through(fn (Village $village) => $this->locationRows->village(
            $village,
            $this->workspaceRoute($workspacePrefix, 'master-data.villages.edit', $village),
            $this->workspaceRoute($workspacePrefix, 'master-data.villages.destroy', $village),
        ));

        return [
            'resource' => $this->locationResources->definition('villages'),
            'filters' => $filters,
            'filterOptions' => [
                [
                    'key' => 'province_id',
                    'label' => 'Provinsi',
                    'defaultValue' => 'all',
                    'options' => $this->locationOptions->provinceFilterOptions(),
                ],
                [
                    'key' => 'regency_id',
                    'label' => 'Kabupaten/Kota',
                    'defaultValue' => 'all',
                    'options' => $this->locationOptions->regencyFilterOptions(),
                ],
                [
                    'key' => 'district_id',
                    'label' => 'Kecamatan',
                    'defaultValue' => 'all',
                    'options' => $this->locationOptions->districtFilterOptions(),
                ],
            ],
            'summaryCards' => [
                ['label' => 'Total Kelurahan/Desa', 'value' => Village::query()->count()],
                ['label' => 'Kecamatan Tercakup', 'value' => Village::query()->distinct('district_id')->count('district_id')],
                ['label' => 'Kabupaten/Kota Tercakup', 'value' => District::query()->has('villages')->distinct('regency_id')->count('regency_id')],
            ],
            'records' => $this->paginatedRecordsPayload($records),
            'indexUrl' => $this->workspaceRoute($workspacePrefix, 'master-data.villages.index'),
            'createUrl' => $this->workspaceRoute($workspacePrefix, 'master-data.villages.create'),
        ];
    }

    public function villagesCreatePayload(
        string $selectedDistrictId,
        LocationIdGenerator $generator,
        string $workspacePrefix,
    ): array {
        $selectedRegencyId = '';
        $selectedProvinceId = '';

        if ($selectedDistrictId !== '') {
            $district = District::query()
                ->with('regency:id,province_id')
                ->find($selectedDistrictId);

            $selectedRegencyId = (string) ($district?->regency_id ?? '');
            $selectedProvinceId = (string) ($district?->regency?->province_id ?? '');
        }

        return [
            'resource' => $this->locationResources->definition('villages'),
            'mode' => 'create',
            'record' => [
                'id' => $this->locationGeneratedIdPreview('village', ['district_id' => $selectedDistrictId], $generator),
                'name' => '',
                'province_id' => $selectedProvinceId,
                'regency_id' => $selectedRegencyId,
                'district_id' => $selectedDistrictId,
            ],
            'selectFields' => [
                [
                    'key' => 'province_id',
                    'label' => 'Provinsi',
                    'placeholder' => 'Pilih provinsi',
                    'options' => $this->locationOptions->provinceSelectOptions(),
                ],
                [
                    'key' => 'regency_id',
                    'label' => 'Kabupaten/Kota',
                    'placeholder' => 'Pilih kabupaten/kota',
                    'options' => $this->locationOptions->regencySelectOptionsByProvince($selectedProvinceId),
                    'depends_on' => 'province_id',
                    'endpoint_type' => 'regencies',
                    'parent_param' => 'province_id',
                ],
                [
                    'key' => 'district_id',
                    'label' => 'Kecamatan',
                    'placeholder' => 'Pilih kecamatan',
                    'options' => $this->locationOptions->districtSelectOptionsByRegency($selectedRegencyId),
                    'depends_on' => 'regency_id',
                    'endpoint_type' => 'districts',
                    'parent_param' => 'regency_id',
                ],
            ],
            'generator' => $this->locationResources->generatorProps(
                'village',
                $this->workspaceRoute($workspacePrefix, 'master-data.locations.id-preview'),
                'district_id',
            ),
            'showIdField' => false,
            'optionsUrl' => $this->workspaceRoute($workspacePrefix, 'master-data.locations.options'),
            'indexUrl' => $this->workspaceRoute($workspacePrefix, 'master-data.villages.index'),
            'submitUrl' => $this->workspaceRoute($workspacePrefix, 'master-data.villages.store'),
        ];
    }

    public function saveVillage(array $validated, LocationIdGenerator $generator): void
    {
        DB::transaction(function () use ($validated, $generator): void {
            Village::query()->create([
                'id' => $generator->nextVillageId($validated['district_id']),
                'district_id' => $validated['district_id'],
                'name' => $validated['name'],
            ]);
        });
    }

    public function villagesEditPayload(Village $village, string $workspacePrefix): array
    {
        $village->loadMissing(['district:id,name,regency_id', 'district.regency:id,name,province_id', 'district.regency.province:id,name']);
        $selectedRegencyId = (string) ($village->district?->regency_id ?? '');
        $selectedProvinceId = (string) ($village->district?->regency?->province_id ?? '');

        return [
            'resource' => $this->locationResources->definition('villages'),
            'mode' => 'edit',
            'record' => [
                'id' => $village->id,
                'name' => $village->name,
                'province_id' => $selectedProvinceId,
                'regency_id' => $selectedRegencyId,
                'district_id' => $village->district_id,
            ],
            'selectFields' => [
                [
                    'key' => 'province_id',
                    'label' => 'Provinsi',
                    'placeholder' => 'Pilih provinsi',
                    'options' => $this->locationOptions->provinceSelectOptions(),
                ],
                [
                    'key' => 'regency_id',
                    'label' => 'Kabupaten/Kota',
                    'placeholder' => 'Pilih kabupaten/kota',
                    'options' => $this->locationOptions->regencySelectOptionsByProvince($selectedProvinceId),
                    'depends_on' => 'province_id',
                    'endpoint_type' => 'regencies',
                    'parent_param' => 'province_id',
                ],
                [
                    'key' => 'district_id',
                    'label' => 'Kecamatan',
                    'placeholder' => 'Pilih kecamatan',
                    'options' => $this->locationOptions->districtSelectOptionsByRegency($selectedRegencyId),
                    'depends_on' => 'regency_id',
                    'endpoint_type' => 'districts',
                    'parent_param' => 'regency_id',
                ],
            ],
            'generator' => null,
            'optionsUrl' => $this->workspaceRoute($workspacePrefix, 'master-data.locations.options'),
            'indexUrl' => $this->workspaceRoute($workspacePrefix, 'master-data.villages.index'),
            'submitUrl' => $this->workspaceRoute($workspacePrefix, 'master-data.villages.update', $village),
        ];
    }

    public function updateVillage(Village $village, array $validated): void
    {
        $village->forceFill([
            'district_id' => $validated['district_id'],
            'name' => $validated['name'],
        ])->save();
    }

    private function locationGeneratedIdPreview(string $type, array $context, LocationIdGenerator $generator): ?string
    {
        try {
            return DB::transaction(function () use ($type, $context, $generator) {
                return match ($type) {
                    'province' => $generator->nextProvinceId(),
                    'regency' => filled($context['province_id'] ?? null)
                        ? $generator->nextRegencyId((string) $context['province_id'])
                        : null,
                    'district' => filled($context['regency_id'] ?? null)
                        ? $generator->nextDistrictId((string) $context['regency_id'])
                        : null,
                    'village' => filled($context['district_id'] ?? null)
                        ? $generator->nextVillageId((string) $context['district_id'])
                        : null,
                    default => null,
                };
            });
        } catch (\Throwable) {
            return null;
        }
    }

    private function workspaceRoute(string $workspacePrefix, string $suffix, mixed $parameters = []): string
    {
        return route($workspacePrefix . '.' . ltrim($suffix, '.'), $parameters);
    }

    private function paginatedRecordsPayload(object $records): array
    {
        return [
            'data' => $records->items(),
            'meta' => [
                'from' => $records->firstItem(),
                'to' => $records->lastItem(),
                'total' => $records->total(),
                'current_page' => $records->currentPage(),
                'last_page' => $records->lastPage(),
                'per_page' => $records->perPage(),
                'links' => $records->linkCollection()->toArray(),
            ],
        ];
    }
}

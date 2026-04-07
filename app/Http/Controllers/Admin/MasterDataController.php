<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;

use App\Http\Requests\Admin\LocationIdPreviewRequest;
use App\Http\Requests\Admin\LocationOptionsRequest;
use App\Http\Requests\Admin\MasterDataLocationIndexRequest;
use App\Http\Requests\Admin\StoreDistrictRequest;
use App\Http\Requests\Admin\StoreProvinceRequest;
use App\Http\Requests\Admin\StoreRegencyRequest;
use App\Http\Requests\Admin\StoreUserRequest;
use App\Http\Requests\Admin\StoreVillageRequest;
use App\Http\Requests\Admin\UsersIndexRequest;

use App\Models\District;
use App\Models\Province;
use App\Models\Regency;
use App\Models\User;
use App\Models\Village;

use App\Services\Location\LocationIdGenerator;

use App\Support\Admin\MasterData\LocationDestroyer;
use App\Support\Admin\MasterData\LocationOptionsProvider;
use App\Support\Admin\MasterData\LocationResourceRegistry;
use App\Support\Admin\MasterData\LocationRowPresenter;
use App\Support\Admin\MasterData\UserManagementService;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Inertia\Response;

class MasterDataController extends Controller
{
    public function __construct(
        private readonly UserManagementService $userManagement,
        private readonly LocationOptionsProvider $locationOptions,
        private readonly LocationResourceRegistry $locationResources,
        private readonly LocationRowPresenter $locationRows,
        private readonly LocationDestroyer $locationDestroyer,
    ) {
    }

    public function usersIndex(UsersIndexRequest $request): Response
    {
        $filters = $request->filters();

        $baseQuery = $this->userManagement->manageableUsersQuery();

        $records = $this->userManagement->manageableUsersQuery()
            ->with('roles:id,name')
            ->when($filters['q'] !== '', function ($query) use ($filters): void {
                $query->where(function ($innerQuery) use ($filters): void {
                    $innerQuery
                        ->where('name', 'like', '%' . $filters['q'] . '%')
                        ->orWhere('email', 'like', '%' . $filters['q'] . '%');
                });
            })
            ->when($filters['role'] !== 'all', fn ($query) => $query->role($filters['role']))
            ->when($filters['verified'] === 'verified', fn ($query) => $query->whereNotNull('email_verified_at'))
            ->when($filters['verified'] === 'unverified', fn ($query) => $query->whereNull('email_verified_at'))
            ->latest('created_at')
            ->paginate($request->perPage())
            ->withQueryString();

        $records->through(fn (User $user) => $this->userManagement->transformUserRow($user));

        return inertia('Admin/Users/Index', [
            'filters' => $filters,
            'roleOptions' => $this->userManagement->roleSelectOptions(),
            'verifiedOptions' => [
                ['value' => 'all', 'label' => 'Semua Status'],
                ['value' => 'verified', 'label' => 'Verified'],
                ['value' => 'unverified', 'label' => 'Belum Verified'],
            ],
            'summary' => $this->userManagement->summary($baseQuery),
            'records' => $this->paginatedRecordsPayload($records),
            'canCreate' => $this->userManagement->canManageUsersCreate(),
            'createUrl' => route('admin.master-data.users.create'),
        ]);
    }

    public function usersCreate(): Response
    {
        abort_unless($this->userManagement->canManageUsersCreate(), 403);

        return inertia('Admin/Users/Form', [
            'mode' => 'create',
            'record' => [
                'name' => '',
                'email' => '',
                'email_verified_at' => '',
                'roles' => $this->userManagement->defaultCreateRoles(),
            ],
            'roleOptions' => $this->userManagement->roleSelectOptions(),
            'indexUrl' => route('admin.master-data.users.index'),
            'submitUrl' => route('admin.master-data.users.store'),
        ]);
    }

    public function usersStore(StoreUserRequest $request): RedirectResponse
    {
        abort_unless($this->userManagement->canManageUsersCreate(), 403);

        $validated = $request->validated();

        $user = new User();
        $user->forceFill([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => $validated['password'],
            'email_verified_at' => $validated['email_verified_at'] ?? null,
        ]);
        $user->save();

        $user->syncRoles($this->userManagement->assignableRolesPayload($validated));

        return redirect()
            ->route('admin.master-data.users.show', $user)
            ->with('success', 'User berhasil ditambahkan.');
    }

    public function usersShow(User $user): Response
    {
        abort_unless($this->userManagement->canManageUser($user), 403);

        $user->loadMissing('roles:id,name');

        return inertia('Admin/Users/Show', [
            'record' => $this->userManagement->showPayload($user),
            'indexUrl' => route('admin.master-data.users.index'),
            'editUrl' => route('admin.master-data.users.edit', $user),
            'destroyUrl' => $this->userManagement->canDeleteUser($user) ? route('admin.master-data.users.destroy', $user) : null,
        ]);
    }

    public function usersEdit(User $user): Response
    {
        abort_unless($this->userManagement->canManageUser($user), 403);

        $user->loadMissing('roles:id,name');

        return inertia('Admin/Users/Form', [
            'mode' => 'edit',
            'record' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'email_verified_at' => $user->email_verified_at?->format('Y-m-d\\TH:i'),
                'roles' => $user->roles->pluck('name')->values()->all(),
            ],
            'roleOptions' => $this->userManagement->roleSelectOptions(),
            'indexUrl' => route('admin.master-data.users.index'),
            'submitUrl' => route('admin.master-data.users.update', $user),
        ]);
    }

    public function usersUpdate(StoreUserRequest $request, User $user): RedirectResponse
    {
        abort_unless($this->userManagement->canManageUser($user), 403);

        $validated = $request->validated();

        $payload = [
            'name' => $validated['name'],
            'email' => $validated['email'],
            'email_verified_at' => $validated['email_verified_at'] ?? null,
        ];

        if (filled($validated['password'] ?? null)) {
            $payload['password'] = $validated['password'];
        }

        $user->forceFill($payload)->save();
        $user->syncRoles($this->userManagement->assignableRolesPayload($validated));

        return redirect()
            ->route('admin.master-data.users.show', $user)
            ->with('success', 'User berhasil diperbarui.');
    }

    public function usersDestroy(User $user): RedirectResponse
    {
        abort_unless($this->userManagement->canDeleteUser($user), 403);

        $user->delete();

        return redirect()
            ->route('admin.master-data.users.index')
            ->with('success', 'User berhasil dihapus.');
    }

    public function locationIdPreview(LocationIdPreviewRequest $request, LocationIdGenerator $generator): JsonResponse
    {
        $validated = $request->validated();

        try {
            $id = DB::transaction(function () use ($validated, $generator) {
                return match ($validated['type']) {
                    'province' => $generator->nextProvinceId(),
                    'regency' => $generator->nextRegencyId((string) ($validated['province_id'] ?? '')),
                    'district' => $generator->nextDistrictId((string) ($validated['regency_id'] ?? '')),
                    'village' => $generator->nextVillageId((string) ($validated['district_id'] ?? '')),
                };
            });
        } catch (\InvalidArgumentException|\RuntimeException $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
            ], 422);
        }

        return response()->json([
            'id' => $id,
        ]);
    }

    public function locationOptions(LocationOptionsRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $options = match ($validated['type']) {
            'provinces' => $this->locationOptions->provinceSelectOptions(),
            'regencies' => $this->locationOptions->regencySelectOptionsByProvince($validated['province_id'] ?? null),
            'districts' => $this->locationOptions->districtSelectOptionsByRegency($validated['regency_id'] ?? null),
        };

        return response()->json([
            'options' => $options,
        ]);
    }

    public function provincesIndex(MasterDataLocationIndexRequest $request): Response
    {
        $filters = $request->filters(['q']);

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
            ->paginate($request->perPage())
            ->withQueryString();

        $records->through(fn (Province $province) => $this->locationRows->province(
            $province,
            $this->workspaceRoute('master-data.provinces.edit', $province),
            $this->workspaceRoute('master-data.provinces.destroy', $province),
        ));

        return inertia('Admin/Locations/Index', [
            'resource' => $this->locationResources->definition('provinces'),
            'filters' => $filters,
            'filterOptions' => [],
            'summaryCards' => [
                ['label' => 'Total Provinsi', 'value' => Province::query()->count()],
                ['label' => 'Total Kabupaten/Kota', 'value' => Regency::query()->count()],
                ['label' => 'Provinsi Dengan Kabupaten/Kota', 'value' => Province::query()->has('regencies')->count()],
            ],
            'records' => $this->paginatedRecordsPayload($records),
            'indexUrl' => $this->workspaceRoute('master-data.provinces.index'),
            'createUrl' => $this->workspaceRoute('master-data.provinces.create'),
        ]);
    }

    public function provincesCreate(LocationIdGenerator $generator): Response
    {
        return inertia('Admin/Locations/Form', [
            'resource' => $this->locationResources->definition('provinces'),
            'mode' => 'create',
            'record' => [
                'id' => $this->locationGeneratedIdPreview('province', [], $generator),
                'name' => '',
            ],
            'selectFields' => [],
            'generator' => $this->locationResources->generatorProps('province', $this->workspaceRoute('master-data.locations.id-preview')),
            'optionsUrl' => $this->workspaceRoute('master-data.locations.options'),
            'indexUrl' => $this->workspaceRoute('master-data.provinces.index'),
            'submitUrl' => $this->workspaceRoute('master-data.provinces.store'),
        ]);
    }

    public function provincesStore(StoreProvinceRequest $request, LocationIdGenerator $generator): RedirectResponse
    {
        $validated = $request->validated();

        DB::transaction(function () use ($validated, $generator): void {
            Province::query()->create([
                'id' => $generator->nextProvinceId(),
                'name' => $validated['name'],
            ]);
        });

        return redirect()
            ->route($this->workspaceRouteName('master-data.provinces.index'))
            ->with('success', 'Provinsi berhasil ditambahkan.');
    }

    public function provincesEdit(Province $province): Response
    {
        return inertia('Admin/Locations/Form', [
            'resource' => $this->locationResources->definition('provinces'),
            'mode' => 'edit',
            'record' => [
                'id' => $province->id,
                'name' => $province->name,
            ],
            'selectFields' => [],
            'generator' => null,
            'optionsUrl' => $this->workspaceRoute('master-data.locations.options'),
            'indexUrl' => $this->workspaceRoute('master-data.provinces.index'),
            'submitUrl' => $this->workspaceRoute('master-data.provinces.update', $province),
        ]);
    }

    public function provincesUpdate(StoreProvinceRequest $request, Province $province): RedirectResponse
    {
        $validated = $request->validated();

        $province->forceFill([
            'name' => $validated['name'],
        ])->save();

        return redirect()
            ->route($this->workspaceRouteName('master-data.provinces.index'))
            ->with('success', 'Provinsi berhasil diperbarui.');
    }

    public function provincesDestroy(Province $province): RedirectResponse
    {
        return $this->locationDestroyer->destroy($province, $this->workspaceRouteName('master-data.provinces.index'), 'Provinsi');
    }

    public function regenciesIndex(MasterDataLocationIndexRequest $request): Response
    {
        $filters = $request->filters(['q', 'province_id']);

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
            ->paginate($request->perPage())
            ->withQueryString();

        $records->through(fn (Regency $regency) => $this->locationRows->regency(
            $regency,
            $this->workspaceRoute('master-data.regencies.edit', $regency),
            $this->workspaceRoute('master-data.regencies.destroy', $regency),
        ));

        return inertia('Admin/Locations/Index', [
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
            'indexUrl' => $this->workspaceRoute('master-data.regencies.index'),
            'createUrl' => $this->workspaceRoute('master-data.regencies.create'),
        ]);
    }

    public function regenciesCreate(MasterDataLocationIndexRequest $request, LocationIdGenerator $generator): Response
    {
        $selectedProvinceId = $request->selectedProvinceId();

        return inertia('Admin/Locations/Form', [
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
            'generator' => $this->locationResources->generatorProps('regency', $this->workspaceRoute('master-data.locations.id-preview'), 'province_id'),
            'showIdField' => false,
            'optionsUrl' => $this->workspaceRoute('master-data.locations.options'),
            'indexUrl' => $this->workspaceRoute('master-data.regencies.index'),
            'submitUrl' => $this->workspaceRoute('master-data.regencies.store'),
        ]);
    }

    public function regenciesStore(StoreRegencyRequest $request, LocationIdGenerator $generator): RedirectResponse
    {
        $validated = $request->validated();

        DB::transaction(function () use ($validated, $generator): void {
            Regency::query()->create([
                'id' => $generator->nextRegencyId($validated['province_id']),
                'province_id' => $validated['province_id'],
                'name' => $validated['name'],
            ]);
        });

        return redirect()
            ->route($this->workspaceRouteName('master-data.regencies.index'))
            ->with('success', 'Kabupaten/Kota berhasil ditambahkan.');
    }

    public function regenciesEdit(Regency $regency): Response
    {
        $regency->loadMissing('province:id,name');

        return inertia('Admin/Locations/Form', [
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
            'optionsUrl' => $this->workspaceRoute('master-data.locations.options'),
            'indexUrl' => $this->workspaceRoute('master-data.regencies.index'),
            'submitUrl' => $this->workspaceRoute('master-data.regencies.update', $regency),
        ]);
    }

    public function regenciesUpdate(StoreRegencyRequest $request, Regency $regency): RedirectResponse
    {
        $validated = $request->validated();

        $regency->forceFill([
            'province_id' => $validated['province_id'],
            'name' => $validated['name'],
        ])->save();

        return redirect()
            ->route($this->workspaceRouteName('master-data.regencies.index'))
            ->with('success', 'Kabupaten/Kota berhasil diperbarui.');
    }

    public function regenciesDestroy(Regency $regency): RedirectResponse
    {
        return $this->locationDestroyer->destroy($regency, $this->workspaceRouteName('master-data.regencies.index'), 'Kabupaten/Kota');
    }

    public function districtsIndex(MasterDataLocationIndexRequest $request): Response
    {
        $filters = $request->filters(['q', 'province_id', 'regency_id']);

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
            ->paginate($request->perPage())
            ->withQueryString();

        $records->through(fn (District $district) => $this->locationRows->district(
            $district,
            $this->workspaceRoute('master-data.districts.edit', $district),
            $this->workspaceRoute('master-data.districts.destroy', $district),
        ));

        return inertia('Admin/Locations/Index', [
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
            'indexUrl' => $this->workspaceRoute('master-data.districts.index'),
            'createUrl' => $this->workspaceRoute('master-data.districts.create'),
        ]);
    }

    public function districtsCreate(MasterDataLocationIndexRequest $request, LocationIdGenerator $generator): Response
    {
        $selectedRegencyId = $request->selectedRegencyId();
        $selectedProvinceId = '';

        if ($selectedRegencyId !== '') {
            $selectedProvinceId = (string) Regency::query()
                ->whereKey($selectedRegencyId)
                ->value('province_id');
        }

        return inertia('Admin/Locations/Form', [
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
            'generator' => $this->locationResources->generatorProps('district', $this->workspaceRoute('master-data.locations.id-preview'), 'regency_id'),
            'showIdField' => false,
            'optionsUrl' => $this->workspaceRoute('master-data.locations.options'),
            'indexUrl' => $this->workspaceRoute('master-data.districts.index'),
            'submitUrl' => $this->workspaceRoute('master-data.districts.store'),
        ]);
    }

    public function districtsStore(StoreDistrictRequest $request, LocationIdGenerator $generator): RedirectResponse
    {
        $validated = $request->validated();

        DB::transaction(function () use ($validated, $generator): void {
            District::query()->create([
                'id' => $generator->nextDistrictId($validated['regency_id']),
                'regency_id' => $validated['regency_id'],
                'name' => $validated['name'],
            ]);
        });

        return redirect()
            ->route($this->workspaceRouteName('master-data.districts.index'))
            ->with('success', 'Kecamatan berhasil ditambahkan.');
    }

    public function districtsEdit(District $district): Response
    {
        $district->loadMissing(['regency:id,name,province_id', 'regency.province:id,name']);
        $selectedProvinceId = (string) ($district->regency?->province_id ?? '');

        return inertia('Admin/Locations/Form', [
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
            'optionsUrl' => $this->workspaceRoute('master-data.locations.options'),
            'indexUrl' => $this->workspaceRoute('master-data.districts.index'),
            'submitUrl' => $this->workspaceRoute('master-data.districts.update', $district),
        ]);
    }

    public function districtsUpdate(StoreDistrictRequest $request, District $district): RedirectResponse
    {
        $validated = $request->validated();

        $district->forceFill([
            'regency_id' => $validated['regency_id'],
            'name' => $validated['name'],
        ])->save();

        return redirect()
            ->route($this->workspaceRouteName('master-data.districts.index'))
            ->with('success', 'Kecamatan berhasil diperbarui.');
    }

    public function districtsDestroy(District $district): RedirectResponse
    {
        return $this->locationDestroyer->destroy($district, $this->workspaceRouteName('master-data.districts.index'), 'Kecamatan');
    }

    public function villagesIndex(MasterDataLocationIndexRequest $request): Response
    {
        $filters = $request->filters(['q', 'province_id', 'regency_id', 'district_id']);

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
            ->paginate($request->perPage())
            ->withQueryString();

        $records->through(fn (Village $village) => $this->locationRows->village(
            $village,
            $this->workspaceRoute('master-data.villages.edit', $village),
            $this->workspaceRoute('master-data.villages.destroy', $village),
        ));

        return inertia('Admin/Locations/Index', [
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
            'indexUrl' => $this->workspaceRoute('master-data.villages.index'),
            'createUrl' => $this->workspaceRoute('master-data.villages.create'),
        ]);
    }

    public function villagesCreate(MasterDataLocationIndexRequest $request, LocationIdGenerator $generator): Response
    {
        $selectedDistrictId = $request->selectedDistrictId();
        $selectedRegencyId = '';
        $selectedProvinceId = '';

        if ($selectedDistrictId !== '') {
            $district = District::query()
                ->with('regency:id,province_id')
                ->find($selectedDistrictId);

            $selectedRegencyId = (string) ($district?->regency_id ?? '');
            $selectedProvinceId = (string) ($district?->regency?->province_id ?? '');
        }

        return inertia('Admin/Locations/Form', [
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
            'generator' => $this->locationResources->generatorProps('village', $this->workspaceRoute('master-data.locations.id-preview'), 'district_id'),
            'showIdField' => false,
            'optionsUrl' => $this->workspaceRoute('master-data.locations.options'),
            'indexUrl' => $this->workspaceRoute('master-data.villages.index'),
            'submitUrl' => $this->workspaceRoute('master-data.villages.store'),
        ]);
    }

    public function villagesStore(StoreVillageRequest $request, LocationIdGenerator $generator): RedirectResponse
    {
        $validated = $request->validated();

        DB::transaction(function () use ($validated, $generator): void {
            Village::query()->create([
                'id' => $generator->nextVillageId($validated['district_id']),
                'district_id' => $validated['district_id'],
                'name' => $validated['name'],
            ]);
        });

        return redirect()
            ->route($this->workspaceRouteName('master-data.villages.index'))
            ->with('success', 'Kelurahan/Desa berhasil ditambahkan.');
    }

    public function villagesEdit(Village $village): Response
    {
        $village->loadMissing(['district:id,name,regency_id', 'district.regency:id,name,province_id', 'district.regency.province:id,name']);
        $selectedRegencyId = (string) ($village->district?->regency_id ?? '');
        $selectedProvinceId = (string) ($village->district?->regency?->province_id ?? '');

        return inertia('Admin/Locations/Form', [
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
            'optionsUrl' => $this->workspaceRoute('master-data.locations.options'),
            'indexUrl' => $this->workspaceRoute('master-data.villages.index'),
            'submitUrl' => $this->workspaceRoute('master-data.villages.update', $village),
        ]);
    }

    public function villagesUpdate(StoreVillageRequest $request, Village $village): RedirectResponse
    {
        $validated = $request->validated();

        $village->forceFill([
            'district_id' => $validated['district_id'],
            'name' => $validated['name'],
        ])->save();

        return redirect()
            ->route($this->workspaceRouteName('master-data.villages.index'))
            ->with('success', 'Kelurahan/Desa berhasil diperbarui.');
    }

    public function villagesDestroy(Village $village): RedirectResponse
    {
        return $this->locationDestroyer->destroy($village, $this->workspaceRouteName('master-data.villages.index'), 'Kelurahan/Desa');
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
}

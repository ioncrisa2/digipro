<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreDistrictRequest;
use App\Http\Requests\Admin\StoreProvinceRequest;
use App\Http\Requests\Admin\StoreRegencyRequest;
use App\Http\Requests\Admin\StoreUserRequest;
use App\Http\Requests\Admin\StoreVillageRequest;
use App\Models\District;
use App\Models\Province;
use App\Models\Regency;
use App\Models\User;
use App\Models\Village;
use App\Services\Location\LocationIdGenerator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Response;
use Spatie\Permission\Models\Role;

class MasterDataController extends Controller
{
    public function usersIndex(Request $request): Response
    {
        $filters = [
            'q' => trim((string) $request->query('q', '')),
            'role' => (string) $request->query('role', 'all'),
            'verified' => (string) $request->query('verified', 'all'),
            'per_page' => (string) $this->adminPerPage($request),
        ];

        $records = User::query()
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
            ->paginate($this->adminPerPage($request))
            ->withQueryString();

        $records->through(fn (User $user) => $this->transformUserRow($user));

        return inertia('Admin/Users/Index', [
            'filters' => $filters,
            'roleOptions' => $this->roleSelectOptions(),
            'verifiedOptions' => [
                ['value' => 'all', 'label' => 'Semua Status'],
                ['value' => 'verified', 'label' => 'Verified'],
                ['value' => 'unverified', 'label' => 'Belum Verified'],
            ],
            'summary' => [
                'total' => User::query()->count(),
                'verified' => User::query()->whereNotNull('email_verified_at')->count(),
                'admins' => User::role(['admin', $this->superAdminRoleName()])->count(),
                'reviewers' => User::role('Reviewer')->count(),
            ],
            'records' => $this->paginatedRecordsPayload($records),
            'canCreate' => $this->canManageUsersCreate(),
            'createUrl' => route('admin.master-data.users.create'),
        ]);
    }

    public function usersCreate(): Response
    {
        abort_unless($this->canManageUsersCreate(), 403);

        return inertia('Admin/Users/Form', [
            'mode' => 'create',
            'record' => [
                'name' => '',
                'email' => '',
                'email_verified_at' => '',
                'roles' => [],
            ],
            'roleOptions' => $this->roleSelectOptions(),
            'indexUrl' => route('admin.master-data.users.index'),
            'submitUrl' => route('admin.master-data.users.store'),
        ]);
    }

    public function usersStore(StoreUserRequest $request): RedirectResponse
    {
        abort_unless($this->canManageUsersCreate(), 403);

        $validated = $request->validated();

        $user = new User();
        $user->forceFill([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => $validated['password'],
            'email_verified_at' => $validated['email_verified_at'] ?? null,
        ]);
        $user->save();

        $user->syncRoles($validated['roles'] ?? []);

        return redirect()
            ->route('admin.master-data.users.show', $user)
            ->with('success', 'User berhasil ditambahkan.');
    }

    public function usersShow(User $user): Response
    {
        $user->loadMissing('roles:id,name');

        return inertia('Admin/Users/Show', [
            'record' => $this->userShowPayload($user),
            'indexUrl' => route('admin.master-data.users.index'),
            'editUrl' => route('admin.master-data.users.edit', $user),
        ]);
    }

    public function usersEdit(User $user): Response
    {
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
            'roleOptions' => $this->roleSelectOptions(),
            'indexUrl' => route('admin.master-data.users.index'),
            'submitUrl' => route('admin.master-data.users.update', $user),
        ]);
    }

    public function usersUpdate(StoreUserRequest $request, User $user): RedirectResponse
    {
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
        $user->syncRoles($validated['roles'] ?? []);

        return redirect()
            ->route('admin.master-data.users.show', $user)
            ->with('success', 'User berhasil diperbarui.');
    }

    public function locationIdPreview(Request $request, LocationIdGenerator $generator): JsonResponse
    {
        $validated = $request->validate([
            'type' => ['required', 'in:province,regency,district,village'],
            'province_id' => ['nullable', 'string', 'size:2'],
            'regency_id' => ['nullable', 'string', 'size:4'],
            'district_id' => ['nullable', 'string', 'size:7'],
        ]);

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

    public function locationOptions(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'type' => ['required', 'in:provinces,regencies,districts'],
            'province_id' => ['nullable', 'string', 'size:2'],
            'regency_id' => ['nullable', 'string', 'size:4'],
        ]);

        $options = match ($validated['type']) {
            'provinces' => $this->provinceSelectOptions(),
            'regencies' => $this->regencySelectOptionsByProvince($validated['province_id'] ?? null),
            'districts' => $this->districtSelectOptionsByRegency($validated['regency_id'] ?? null),
        };

        return response()->json([
            'options' => $options,
        ]);
    }

    public function provincesIndex(Request $request): Response
    {
        $filters = [
            'q' => trim((string) $request->query('q', '')),
            'per_page' => (string) $this->adminPerPage($request),
        ];

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
            ->paginate($this->adminPerPage($request))
            ->withQueryString();

        $records->through(fn (Province $province) => $this->transformProvinceRow($province));

        return inertia('Admin/Locations/Index', [
            'resource' => $this->locationResourceDefinition('provinces'),
            'filters' => $filters,
            'filterOptions' => [],
            'summaryCards' => [
                ['label' => 'Total Provinsi', 'value' => Province::query()->count()],
                ['label' => 'Total Kabupaten/Kota', 'value' => Regency::query()->count()],
                ['label' => 'Provinsi Dengan Kabupaten/Kota', 'value' => Province::query()->has('regencies')->count()],
            ],
            'records' => $this->paginatedRecordsPayload($records),
            'indexUrl' => route('admin.master-data.provinces.index'),
            'createUrl' => route('admin.master-data.provinces.create'),
        ]);
    }

    public function provincesCreate(LocationIdGenerator $generator): Response
    {
        return inertia('Admin/Locations/Form', [
            'resource' => $this->locationResourceDefinition('provinces'),
            'mode' => 'create',
            'record' => [
                'id' => $this->locationGeneratedIdPreview('province', [], $generator),
                'name' => '',
            ],
            'selectFields' => [],
            'generator' => $this->locationGeneratorProps('province'),
            'indexUrl' => route('admin.master-data.provinces.index'),
            'submitUrl' => route('admin.master-data.provinces.store'),
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
            ->route('admin.master-data.provinces.index')
            ->with('success', 'Provinsi berhasil ditambahkan.');
    }

    public function provincesEdit(Province $province): Response
    {
        return inertia('Admin/Locations/Form', [
            'resource' => $this->locationResourceDefinition('provinces'),
            'mode' => 'edit',
            'record' => [
                'id' => $province->id,
                'name' => $province->name,
            ],
            'selectFields' => [],
            'generator' => null,
            'indexUrl' => route('admin.master-data.provinces.index'),
            'submitUrl' => route('admin.master-data.provinces.update', $province),
        ]);
    }

    public function provincesUpdate(StoreProvinceRequest $request, Province $province): RedirectResponse
    {
        $validated = $request->validated();

        $province->forceFill([
            'name' => $validated['name'],
        ])->save();

        return redirect()
            ->route('admin.master-data.provinces.index')
            ->with('success', 'Provinsi berhasil diperbarui.');
    }

    public function provincesDestroy(Province $province): RedirectResponse
    {
        return $this->destroyLocationRecord($province, 'admin.master-data.provinces.index', 'Provinsi');
    }

    public function regenciesIndex(Request $request): Response
    {
        $filters = [
            'q' => trim((string) $request->query('q', '')),
            'province_id' => (string) $request->query('province_id', 'all'),
            'per_page' => (string) $this->adminPerPage($request),
        ];

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
            ->paginate($this->adminPerPage($request))
            ->withQueryString();

        $records->through(fn (Regency $regency) => $this->transformRegencyRow($regency));

        return inertia('Admin/Locations/Index', [
            'resource' => $this->locationResourceDefinition('regencies'),
            'filters' => $filters,
            'filterOptions' => [[
                'key' => 'province_id',
                'label' => 'Provinsi',
                'defaultValue' => 'all',
                'options' => $this->provinceFilterOptions(),
            ]],
            'summaryCards' => [
                ['label' => 'Total Kabupaten/Kota', 'value' => Regency::query()->count()],
                ['label' => 'Provinsi Tercakup', 'value' => Regency::query()->distinct('province_id')->count('province_id')],
                ['label' => 'Total Kecamatan', 'value' => District::query()->count()],
            ],
            'records' => $this->paginatedRecordsPayload($records),
            'indexUrl' => route('admin.master-data.regencies.index'),
            'createUrl' => route('admin.master-data.regencies.create'),
        ]);
    }

    public function regenciesCreate(Request $request, LocationIdGenerator $generator): Response
    {
        $selectedProvinceId = (string) $request->query('province_id', '');

        return inertia('Admin/Locations/Form', [
            'resource' => $this->locationResourceDefinition('regencies'),
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
                'options' => $this->provinceSelectOptions(),
            ]],
            'generator' => $this->locationGeneratorProps('regency', 'province_id'),
            'showIdField' => false,
            'indexUrl' => route('admin.master-data.regencies.index'),
            'submitUrl' => route('admin.master-data.regencies.store'),
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
            ->route('admin.master-data.regencies.index')
            ->with('success', 'Kabupaten/Kota berhasil ditambahkan.');
    }

    public function regenciesEdit(Regency $regency): Response
    {
        $regency->loadMissing('province:id,name');

        return inertia('Admin/Locations/Form', [
            'resource' => $this->locationResourceDefinition('regencies'),
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
                'options' => $this->provinceSelectOptions(),
            ]],
            'generator' => null,
            'indexUrl' => route('admin.master-data.regencies.index'),
            'submitUrl' => route('admin.master-data.regencies.update', $regency),
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
            ->route('admin.master-data.regencies.index')
            ->with('success', 'Kabupaten/Kota berhasil diperbarui.');
    }

    public function regenciesDestroy(Regency $regency): RedirectResponse
    {
        return $this->destroyLocationRecord($regency, 'admin.master-data.regencies.index', 'Kabupaten/Kota');
    }

    public function districtsIndex(Request $request): Response
    {
        $filters = [
            'q' => trim((string) $request->query('q', '')),
            'province_id' => (string) $request->query('province_id', 'all'),
            'regency_id' => (string) $request->query('regency_id', 'all'),
            'per_page' => (string) $this->adminPerPage($request),
        ];

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
            ->paginate($this->adminPerPage($request))
            ->withQueryString();

        $records->through(fn (District $district) => $this->transformDistrictRow($district));

        return inertia('Admin/Locations/Index', [
            'resource' => $this->locationResourceDefinition('districts'),
            'filters' => $filters,
            'filterOptions' => [
                [
                    'key' => 'province_id',
                    'label' => 'Provinsi',
                    'defaultValue' => 'all',
                    'options' => $this->provinceFilterOptions(),
                ],
                [
                    'key' => 'regency_id',
                    'label' => 'Kabupaten/Kota',
                    'defaultValue' => 'all',
                    'options' => $this->regencyFilterOptions(),
                ],
            ],
            'summaryCards' => [
                ['label' => 'Total Kecamatan', 'value' => District::query()->count()],
                ['label' => 'Kabupaten/Kota Tercakup', 'value' => District::query()->distinct('regency_id')->count('regency_id')],
                ['label' => 'Total Kelurahan/Desa', 'value' => Village::query()->count()],
            ],
            'records' => $this->paginatedRecordsPayload($records),
            'indexUrl' => route('admin.master-data.districts.index'),
            'createUrl' => route('admin.master-data.districts.create'),
        ]);
    }

    public function districtsCreate(Request $request, LocationIdGenerator $generator): Response
    {
        $selectedRegencyId = (string) $request->query('regency_id', '');
        $selectedProvinceId = '';

        if ($selectedRegencyId !== '') {
            $selectedProvinceId = (string) Regency::query()
                ->whereKey($selectedRegencyId)
                ->value('province_id');
        }

        return inertia('Admin/Locations/Form', [
            'resource' => $this->locationResourceDefinition('districts'),
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
                    'options' => $this->provinceSelectOptions(),
                ],
                [
                    'key' => 'regency_id',
                    'label' => 'Kabupaten/Kota',
                    'placeholder' => 'Pilih kabupaten/kota',
                    'options' => $this->regencySelectOptionsByProvince($selectedProvinceId),
                    'depends_on' => 'province_id',
                    'endpoint_type' => 'regencies',
                    'parent_param' => 'province_id',
                ],
            ],
            'generator' => $this->locationGeneratorProps('district', 'regency_id'),
            'showIdField' => false,
            'indexUrl' => route('admin.master-data.districts.index'),
            'submitUrl' => route('admin.master-data.districts.store'),
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
            ->route('admin.master-data.districts.index')
            ->with('success', 'Kecamatan berhasil ditambahkan.');
    }

    public function districtsEdit(District $district): Response
    {
        $district->loadMissing(['regency:id,name,province_id', 'regency.province:id,name']);
        $selectedProvinceId = (string) ($district->regency?->province_id ?? '');

        return inertia('Admin/Locations/Form', [
            'resource' => $this->locationResourceDefinition('districts'),
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
                    'options' => $this->provinceSelectOptions(),
                ],
                [
                    'key' => 'regency_id',
                    'label' => 'Kabupaten/Kota',
                    'placeholder' => 'Pilih kabupaten/kota',
                    'options' => $this->regencySelectOptionsByProvince($selectedProvinceId),
                    'depends_on' => 'province_id',
                    'endpoint_type' => 'regencies',
                    'parent_param' => 'province_id',
                ],
            ],
            'generator' => null,
            'indexUrl' => route('admin.master-data.districts.index'),
            'submitUrl' => route('admin.master-data.districts.update', $district),
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
            ->route('admin.master-data.districts.index')
            ->with('success', 'Kecamatan berhasil diperbarui.');
    }

    public function districtsDestroy(District $district): RedirectResponse
    {
        return $this->destroyLocationRecord($district, 'admin.master-data.districts.index', 'Kecamatan');
    }

    public function villagesIndex(Request $request): Response
    {
        $filters = [
            'q' => trim((string) $request->query('q', '')),
            'province_id' => (string) $request->query('province_id', 'all'),
            'regency_id' => (string) $request->query('regency_id', 'all'),
            'district_id' => (string) $request->query('district_id', 'all'),
            'per_page' => (string) $this->adminPerPage($request),
        ];

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
            ->paginate($this->adminPerPage($request))
            ->withQueryString();

        $records->through(fn (Village $village) => $this->transformVillageRow($village));

        return inertia('Admin/Locations/Index', [
            'resource' => $this->locationResourceDefinition('villages'),
            'filters' => $filters,
            'filterOptions' => [
                [
                    'key' => 'province_id',
                    'label' => 'Provinsi',
                    'defaultValue' => 'all',
                    'options' => $this->provinceFilterOptions(),
                ],
                [
                    'key' => 'regency_id',
                    'label' => 'Kabupaten/Kota',
                    'defaultValue' => 'all',
                    'options' => $this->regencyFilterOptions(),
                ],
                [
                    'key' => 'district_id',
                    'label' => 'Kecamatan',
                    'defaultValue' => 'all',
                    'options' => $this->districtFilterOptions(),
                ],
            ],
            'summaryCards' => [
                ['label' => 'Total Kelurahan/Desa', 'value' => Village::query()->count()],
                ['label' => 'Kecamatan Tercakup', 'value' => Village::query()->distinct('district_id')->count('district_id')],
                ['label' => 'Kabupaten/Kota Tercakup', 'value' => District::query()->has('villages')->distinct('regency_id')->count('regency_id')],
            ],
            'records' => $this->paginatedRecordsPayload($records),
            'indexUrl' => route('admin.master-data.villages.index'),
            'createUrl' => route('admin.master-data.villages.create'),
        ]);
    }

    public function villagesCreate(Request $request, LocationIdGenerator $generator): Response
    {
        $selectedDistrictId = (string) $request->query('district_id', '');
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
            'resource' => $this->locationResourceDefinition('villages'),
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
                    'options' => $this->provinceSelectOptions(),
                ],
                [
                    'key' => 'regency_id',
                    'label' => 'Kabupaten/Kota',
                    'placeholder' => 'Pilih kabupaten/kota',
                    'options' => $this->regencySelectOptionsByProvince($selectedProvinceId),
                    'depends_on' => 'province_id',
                    'endpoint_type' => 'regencies',
                    'parent_param' => 'province_id',
                ],
                [
                    'key' => 'district_id',
                    'label' => 'Kecamatan',
                    'placeholder' => 'Pilih kecamatan',
                    'options' => $this->districtSelectOptionsByRegency($selectedRegencyId),
                    'depends_on' => 'regency_id',
                    'endpoint_type' => 'districts',
                    'parent_param' => 'regency_id',
                ],
            ],
            'generator' => $this->locationGeneratorProps('village', 'district_id'),
            'showIdField' => false,
            'indexUrl' => route('admin.master-data.villages.index'),
            'submitUrl' => route('admin.master-data.villages.store'),
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
            ->route('admin.master-data.villages.index')
            ->with('success', 'Kelurahan/Desa berhasil ditambahkan.');
    }

    public function villagesEdit(Village $village): Response
    {
        $village->loadMissing(['district:id,name,regency_id', 'district.regency:id,name,province_id', 'district.regency.province:id,name']);
        $selectedRegencyId = (string) ($village->district?->regency_id ?? '');
        $selectedProvinceId = (string) ($village->district?->regency?->province_id ?? '');

        return inertia('Admin/Locations/Form', [
            'resource' => $this->locationResourceDefinition('villages'),
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
                    'options' => $this->provinceSelectOptions(),
                ],
                [
                    'key' => 'regency_id',
                    'label' => 'Kabupaten/Kota',
                    'placeholder' => 'Pilih kabupaten/kota',
                    'options' => $this->regencySelectOptionsByProvince($selectedProvinceId),
                    'depends_on' => 'province_id',
                    'endpoint_type' => 'regencies',
                    'parent_param' => 'province_id',
                ],
                [
                    'key' => 'district_id',
                    'label' => 'Kecamatan',
                    'placeholder' => 'Pilih kecamatan',
                    'options' => $this->districtSelectOptionsByRegency($selectedRegencyId),
                    'depends_on' => 'regency_id',
                    'endpoint_type' => 'districts',
                    'parent_param' => 'regency_id',
                ],
            ],
            'generator' => null,
            'indexUrl' => route('admin.master-data.villages.index'),
            'submitUrl' => route('admin.master-data.villages.update', $village),
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
            ->route('admin.master-data.villages.index')
            ->with('success', 'Kelurahan/Desa berhasil diperbarui.');
    }

    public function villagesDestroy(Village $village): RedirectResponse
    {
        return $this->destroyLocationRecord($village, 'admin.master-data.villages.index', 'Kelurahan/Desa');
    }

    private function canManageUsersCreate(): bool
    {
        $user = auth()->user();

        return $user !== null && $user->hasRole($this->superAdminRoleName());
    }

    private function superAdminRoleName(): string
    {
        return (string) config('access-control.super_admin.name', 'super_admin');
    }

    private function roleSelectOptions(): array
    {
        return Role::query()
            ->where('guard_name', 'web')
            ->orderBy('name')
            ->get(['name'])
            ->map(fn (Role $role) => [
                'value' => $role->name,
                'label' => $role->name,
            ])
            ->values()
            ->all();
    }

    private function transformUserRow(User $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'role_names' => $user->roles->pluck('name')->values()->all(),
            'is_verified' => filled($user->email_verified_at),
            'created_at' => $user->created_at?->toIso8601String(),
            'show_url' => route('admin.master-data.users.show', $user),
            'edit_url' => route('admin.master-data.users.edit', $user),
        ];
    }

    private function userShowPayload(User $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'email_verified_at' => $user->email_verified_at?->toIso8601String(),
            'role_names' => $user->roles->pluck('name')->values()->all(),
            'created_at' => $user->created_at?->toIso8601String(),
            'updated_at' => $user->updated_at?->toIso8601String(),
        ];
    }

    protected function paginatedRecordsPayload(object $records): array
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

    private function locationResourceDefinition(string $key): array
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

    private function locationGeneratorProps(string $type, ?string $parentField = null): array
    {
        return [
            'type' => $type,
            'parent_field' => $parentField,
            'preview_url' => route('admin.master-data.locations.id-preview'),
        ];
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

    private function provinceSelectOptions(): array
    {
        return Province::query()
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (Province $province) => [
                'value' => (string) $province->id,
                'label' => $province->name . ' (' . $province->id . ')',
            ])
            ->values()
            ->all();
    }

    private function provinceFilterOptions(): array
    {
        return [
            ['value' => 'all', 'label' => 'Semua Provinsi'],
            ...$this->provinceSelectOptions(),
        ];
    }

    private function regencySelectOptionsByProvince(?string $provinceId): array
    {
        if (blank($provinceId)) {
            return [];
        }

        return Regency::query()
            ->where('province_id', $provinceId)
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (Regency $regency) => [
                'value' => (string) $regency->id,
                'label' => $regency->name . ' (' . $regency->id . ')',
            ])
            ->values()
            ->all();
    }

    private function districtSelectOptionsByRegency(?string $regencyId): array
    {
        if (blank($regencyId)) {
            return [];
        }

        return District::query()
            ->where('regency_id', $regencyId)
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (District $district) => [
                'value' => (string) $district->id,
                'label' => $district->name . ' (' . $district->id . ')',
            ])
            ->values()
            ->all();
    }

    private function regencyFilterOptions(): array
    {
        return Regency::query()
            ->with('province:id,name')
            ->orderBy('name')
            ->get(['id', 'name', 'province_id'])
            ->map(fn (Regency $regency) => [
                'value' => (string) $regency->id,
                'label' => $regency->name . ' - ' . ($regency->province?->name ?? '-'),
            ])
            ->values()
            ->all();
    }

    private function districtFilterOptions(): array
    {
        return District::query()
            ->with(['regency:id,name,province_id', 'regency.province:id,name'])
            ->orderBy('name')
            ->get(['id', 'name', 'regency_id'])
            ->map(fn (District $district) => [
                'value' => (string) $district->id,
                'label' => $district->name
                    . ' - '
                    . ($district->regency?->name ?? '-')
                    . ' / '
                    . ($district->regency?->province?->name ?? '-'),
            ])
            ->values()
            ->all();
    }

    private function transformProvinceRow(Province $province): array
    {
        return [
            'id' => $province->id,
            'code' => $province->id,
            'name' => $province->name,
            'details' => [],
            'stats' => [
                ['label' => 'Kabupaten/Kota', 'value' => (int) ($province->regencies_count ?? 0)],
            ],
            'edit_url' => route('admin.master-data.provinces.edit', $province),
            'destroy_url' => route('admin.master-data.provinces.destroy', $province),
        ];
    }

    private function transformRegencyRow(Regency $regency): array
    {
        return [
            'id' => $regency->id,
            'code' => $regency->id,
            'name' => $regency->name,
            'details' => [
                'Provinsi: ' . ($regency->province?->name ?? '-'),
            ],
            'stats' => [
                ['label' => 'Kecamatan', 'value' => (int) ($regency->districts_count ?? 0)],
            ],
            'edit_url' => route('admin.master-data.regencies.edit', $regency),
            'destroy_url' => route('admin.master-data.regencies.destroy', $regency),
        ];
    }

    private function transformDistrictRow(District $district): array
    {
        return [
            'id' => $district->id,
            'code' => $district->id,
            'name' => $district->name,
            'details' => [
                'Kabupaten/Kota: ' . ($district->regency?->name ?? '-'),
                'Provinsi: ' . ($district->regency?->province?->name ?? '-'),
            ],
            'stats' => [
                ['label' => 'Kelurahan/Desa', 'value' => (int) ($district->villages_count ?? 0)],
            ],
            'edit_url' => route('admin.master-data.districts.edit', $district),
            'destroy_url' => route('admin.master-data.districts.destroy', $district),
        ];
    }

    private function transformVillageRow(Village $village): array
    {
        return [
            'id' => $village->id,
            'code' => $village->id,
            'name' => $village->name,
            'details' => [
                'Kecamatan: ' . ($village->district?->name ?? '-'),
                'Kabupaten/Kota: ' . ($village->district?->regency?->name ?? '-'),
                'Provinsi: ' . ($village->district?->regency?->province?->name ?? '-'),
            ],
            'stats' => [],
            'edit_url' => route('admin.master-data.villages.edit', $village),
            'destroy_url' => route('admin.master-data.villages.destroy', $village),
        ];
    }

    private function destroyLocationRecord(Model $record, string $routeName, string $label): RedirectResponse
    {
        try {
            $record->delete();
        } catch (QueryException) {
            return redirect()
                ->route($routeName)
                ->with('error', $label . ' tidak bisa dihapus karena masih dipakai data turunan.');
        }

        return redirect()
            ->route($routeName)
            ->with('success', $label . ' berhasil dihapus.');
    }
}

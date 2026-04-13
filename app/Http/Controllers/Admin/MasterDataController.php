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

use App\Services\Admin\AdminMasterDataLocationWorkspaceService;
use App\Services\Admin\AdminMasterDataWorkspaceService;
use App\Services\Location\LocationIdGenerator;

use App\Support\Admin\MasterData\LocationDestroyer;
use App\Support\Admin\MasterData\UserManagementService;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Inertia\Response;

class MasterDataController extends Controller
{
    public function __construct(
        private readonly AdminMasterDataWorkspaceService $workspaceService,
        private readonly AdminMasterDataLocationWorkspaceService $locationWorkspaceService,
        private readonly UserManagementService $userManagement,
        private readonly LocationDestroyer $locationDestroyer,
    ) {
    }

    public function usersIndex(UsersIndexRequest $request): Response
    {
        return inertia('Admin/Users/Index', $this->workspaceService
            ->usersIndexPayload($request->filters(), $request->perPage()));
    }

    public function usersCreate(): Response
    {
        abort_unless($this->userManagement->canManageUsersCreate(), 403);

        return inertia('Admin/Users/Form', $this->workspaceService->usersCreatePayload());
    }

    public function usersStore(StoreUserRequest $request): RedirectResponse
    {
        abort_unless($this->userManagement->canManageUsersCreate(), 403);

        $user = $this->workspaceService->createUser($request->validated());

        return redirect()
            ->route('admin.master-data.users.show', $user)
            ->with('success', 'User berhasil ditambahkan.');
    }

    public function usersShow(User $user): Response
    {
        abort_unless($this->userManagement->canManageUser($user), 403);

        return inertia('Admin/Users/Show', $this->workspaceService->usersShowPayload($user));
    }

    public function usersEdit(User $user): Response
    {
        abort_unless($this->userManagement->canManageUser($user), 403);

        return inertia('Admin/Users/Form', $this->workspaceService->usersEditPayload($user));
    }

    public function usersUpdate(StoreUserRequest $request, User $user): RedirectResponse
    {
        abort_unless($this->userManagement->canManageUser($user), 403);

        $this->workspaceService->updateUser($user, $request->validated());

        return redirect()
            ->route('admin.master-data.users.show', $user)
            ->with('success', 'User berhasil diperbarui.');
    }

    public function usersDestroy(User $user): RedirectResponse
    {
        abort_unless($this->userManagement->canDeleteUser($user), 403);

        $this->workspaceService->deleteUser($user);

        return redirect()
            ->route('admin.master-data.users.index')
            ->with('success', 'User berhasil dihapus.');
    }

    public function locationIdPreview(LocationIdPreviewRequest $request, LocationIdGenerator $generator): JsonResponse
    {
        $validated = $request->validated();

        try {
            $id = $this->locationWorkspaceService->previewLocationId($validated, $generator);
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
        return response()->json([
            'options' => $this->locationWorkspaceService->locationOptionsPayload($request->validated()),
        ]);
    }

    public function provincesIndex(MasterDataLocationIndexRequest $request): Response
    {
        return inertia('Admin/Locations/Index', $this->locationWorkspaceService
            ->provincesIndexPayload($request->filters(['q']), $request->perPage(), $this->workspacePrefix()));
    }

    public function provincesCreate(LocationIdGenerator $generator): Response
    {
        return inertia('Admin/Locations/Form', $this->locationWorkspaceService
            ->provincesCreatePayload($generator, $this->workspacePrefix()));
    }

    public function provincesStore(StoreProvinceRequest $request, LocationIdGenerator $generator): RedirectResponse
    {
        $this->locationWorkspaceService->saveProvince($request->validated(), $generator);

        return redirect()
            ->route($this->workspaceRouteName('master-data.provinces.index'))
            ->with('success', 'Provinsi berhasil ditambahkan.');
    }

    public function provincesEdit(Province $province): Response
    {
        return inertia('Admin/Locations/Form', $this->locationWorkspaceService
            ->provincesEditPayload($province, $this->workspacePrefix()));
    }

    public function provincesUpdate(StoreProvinceRequest $request, Province $province): RedirectResponse
    {
        $this->locationWorkspaceService->updateProvince($province, $request->validated());

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
        return inertia('Admin/Locations/Index', $this->locationWorkspaceService
            ->regenciesIndexPayload(
                $request->filters(['q', 'province_id']),
                $request->perPage(),
                $this->workspacePrefix(),
            ));
    }

    public function regenciesCreate(MasterDataLocationIndexRequest $request, LocationIdGenerator $generator): Response
    {
        return inertia('Admin/Locations/Form', $this->locationWorkspaceService
            ->regenciesCreatePayload($request->selectedProvinceId(), $generator, $this->workspacePrefix()));
    }

    public function regenciesStore(StoreRegencyRequest $request, LocationIdGenerator $generator): RedirectResponse
    {
        $this->locationWorkspaceService->saveRegency($request->validated(), $generator);

        return redirect()
            ->route($this->workspaceRouteName('master-data.regencies.index'))
            ->with('success', 'Kabupaten/Kota berhasil ditambahkan.');
    }

    public function regenciesEdit(Regency $regency): Response
    {
        return inertia('Admin/Locations/Form', $this->locationWorkspaceService
            ->regenciesEditPayload($regency, $this->workspacePrefix()));
    }

    public function regenciesUpdate(StoreRegencyRequest $request, Regency $regency): RedirectResponse
    {
        $this->locationWorkspaceService->updateRegency($regency, $request->validated());

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
        return inertia('Admin/Locations/Index', $this->locationWorkspaceService
            ->districtsIndexPayload(
                $request->filters(['q', 'province_id', 'regency_id']),
                $request->perPage(),
                $this->workspacePrefix(),
            ));
    }

    public function districtsCreate(MasterDataLocationIndexRequest $request, LocationIdGenerator $generator): Response
    {
        return inertia('Admin/Locations/Form', $this->locationWorkspaceService
            ->districtsCreatePayload($request->selectedRegencyId(), $generator, $this->workspacePrefix()));
    }

    public function districtsStore(StoreDistrictRequest $request, LocationIdGenerator $generator): RedirectResponse
    {
        $this->locationWorkspaceService->saveDistrict($request->validated(), $generator);

        return redirect()
            ->route($this->workspaceRouteName('master-data.districts.index'))
            ->with('success', 'Kecamatan berhasil ditambahkan.');
    }

    public function districtsEdit(District $district): Response
    {
        return inertia('Admin/Locations/Form', $this->locationWorkspaceService
            ->districtsEditPayload($district, $this->workspacePrefix()));
    }

    public function districtsUpdate(StoreDistrictRequest $request, District $district): RedirectResponse
    {
        $this->locationWorkspaceService->updateDistrict($district, $request->validated());

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
        return inertia('Admin/Locations/Index', $this->locationWorkspaceService
            ->villagesIndexPayload(
                $request->filters(['q', 'province_id', 'regency_id', 'district_id']),
                $request->perPage(),
                $this->workspacePrefix(),
            ));
    }

    public function villagesCreate(MasterDataLocationIndexRequest $request, LocationIdGenerator $generator): Response
    {
        return inertia('Admin/Locations/Form', $this->locationWorkspaceService
            ->villagesCreatePayload($request->selectedDistrictId(), $generator, $this->workspacePrefix()));
    }

    public function villagesStore(StoreVillageRequest $request, LocationIdGenerator $generator): RedirectResponse
    {
        $this->locationWorkspaceService->saveVillage($request->validated(), $generator);

        return redirect()
            ->route($this->workspaceRouteName('master-data.villages.index'))
            ->with('success', 'Kelurahan/Desa berhasil ditambahkan.');
    }

    public function villagesEdit(Village $village): Response
    {
        return inertia('Admin/Locations/Form', $this->locationWorkspaceService
            ->villagesEditPayload($village, $this->workspacePrefix()));
    }

    public function villagesUpdate(StoreVillageRequest $request, Village $village): RedirectResponse
    {
        $this->locationWorkspaceService->updateVillage($village, $request->validated());

        return redirect()
            ->route($this->workspaceRouteName('master-data.villages.index'))
            ->with('success', 'Kelurahan/Desa berhasil diperbarui.');
    }

    public function villagesDestroy(Village $village): RedirectResponse
    {
        return $this->locationDestroyer->destroy($village, $this->workspaceRouteName('master-data.villages.index'), 'Kelurahan/Desa');
    }
}

<?php

namespace App\Http\Controllers\Api\V1\Customer;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Customer\MobileAvatarUpdateRequest;
use App\Http\Requests\Api\V1\Customer\MobilePasswordUpdateRequest;
use App\Http\Requests\Api\V1\Customer\MobilePasswordVerifyRequest;
use App\Http\Requests\Api\V1\Customer\MobileProfileLocationOptionsRequest;
use App\Http\Requests\Api\V1\Customer\MobileProfileUpdateRequest;
use App\Http\Resources\Api\V1\ProfileResource;
use App\Services\Customer\MobileProfileService;
use App\Support\Admin\MasterData\LocationOptionsProvider;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MobileProfileController extends Controller
{
    public function __construct(
        private readonly MobileProfileService $profileService,
    ) {}

    public function show(Request $request): ProfileResource
    {
        return ProfileResource::make($this->profileService->load($request->user()));
    }

    public function update(MobileProfileUpdateRequest $request): ProfileResource
    {
        $user = $this->profileService->update($request->user(), $request->validated());

        return ProfileResource::make($user)->additional([
            'message' => 'Profil berhasil diperbarui.',
        ]);
    }

    public function locationOptions(
        MobileProfileLocationOptionsRequest $request,
        LocationOptionsProvider $locationOptions,
    ): JsonResponse {
        $validated = $request->validated();
        $options = match ($validated['type']) {
            'provinces' => $locationOptions->provinceSelectOptions(),
            'regencies' => $locationOptions->regencySelectOptionsByProvince($validated['province_id'] ?? null),
            'districts' => $locationOptions->districtSelectOptionsByRegency($validated['regency_id'] ?? null),
            'villages' => $locationOptions->villageSelectOptionsByDistrict($validated['district_id'] ?? null),
        };

        return response()->json(['data' => $options]);
    }

    public function updatePassword(MobilePasswordUpdateRequest $request): JsonResponse
    {
        $this->profileService->updatePassword($request->user(), $request->validated());

        return response()->json(['message' => 'Password berhasil diperbarui.']);
    }

    public function verifyPassword(MobilePasswordVerifyRequest $request): JsonResponse
    {
        $this->profileService->verifyPassword($request->user(), $request->string('current_password')->toString());

        return response()->json(['data' => ['valid' => true]]);
    }

    public function updateAvatar(MobileAvatarUpdateRequest $request): ProfileResource
    {
        $user = $this->profileService->updateAvatar($request->user(), $request->file('avatar'));

        return ProfileResource::make($user)->additional([
            'message' => 'Foto profil berhasil diperbarui.',
        ]);
    }

    public function removeAvatar(Request $request): ProfileResource
    {
        $user = $this->profileService->removeAvatar($request->user());

        return ProfileResource::make($user)->additional([
            'message' => 'Foto profil berhasil dihapus.',
        ]);
    }
}

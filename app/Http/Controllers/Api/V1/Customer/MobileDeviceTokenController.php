<?php

namespace App\Http\Controllers\Api\V1\Customer;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Customer\MobileDeviceTokenDestroyRequest;
use App\Http\Requests\Api\V1\Customer\MobileDeviceTokenStoreRequest;
use App\Http\Resources\Api\V1\MobileDeviceTokenResource;
use App\Services\Customer\MobileDeviceTokenService;
use Illuminate\Http\Response;

class MobileDeviceTokenController extends Controller
{
    public function __construct(
        private readonly MobileDeviceTokenService $deviceTokens,
    ) {}

    public function store(MobileDeviceTokenStoreRequest $request): MobileDeviceTokenResource
    {
        $token = $this->deviceTokens->register($request->user(), $request->validated());

        return MobileDeviceTokenResource::make($token)->additional([
            'message' => 'Device token berhasil disimpan.',
        ]);
    }

    public function destroy(MobileDeviceTokenDestroyRequest $request): Response
    {
        $this->deviceTokens->remove($request->user(), $request->validated('token'));

        return response()->noContent();
    }
}

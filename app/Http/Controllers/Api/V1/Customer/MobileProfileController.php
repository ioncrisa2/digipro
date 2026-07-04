<?php

namespace App\Http\Controllers\Api\V1\Customer;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\ProfileResource;
use Illuminate\Http\Request;

class MobileProfileController extends Controller
{
    public function __invoke(Request $request): ProfileResource
    {
        $user = $request->user();
        $user->load([
            'billingProvince:id,name',
            'billingRegency:id,name',
            'billingDistrict:id,name',
            'billingVillage:id,name',
        ]);

        return ProfileResource::make($user);
    }
}

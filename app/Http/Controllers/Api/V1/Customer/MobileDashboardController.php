<?php

namespace App\Http\Controllers\Api\V1\Customer;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\DashboardResource;
use App\Services\Customer\MobileDashboardService;
use Illuminate\Http\Request;

class MobileDashboardController extends Controller
{
    public function __invoke(Request $request, MobileDashboardService $service): DashboardResource
    {
        return DashboardResource::make($service->build($request->user()));
    }
}

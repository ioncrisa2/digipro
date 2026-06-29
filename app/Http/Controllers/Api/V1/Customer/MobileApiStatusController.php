<?php

namespace App\Http\Controllers\Api\V1\Customer;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\UserResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MobileApiStatusController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        return response()->json([
            'data' => [
                'status' => 'ok',
                'api_version' => 'v1',
                'user' => UserResource::make($request->user()),
            ],
            'message' => 'Mobile API is ready.',
        ]);
    }
}

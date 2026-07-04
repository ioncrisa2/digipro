<?php

namespace App\Http\Controllers\Api\V1\Customer;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Customer\MobileAppraisalIndexRequest;
use App\Http\Resources\Api\V1\AppraisalDetailResource;
use App\Http\Resources\Api\V1\AppraisalSummaryResource;
use App\Http\Resources\Api\V1\AppraisalTrackingResource;
use App\Services\Customer\MobileAppraisalReadService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class MobileAppraisalController extends Controller
{
    public function index(
        MobileAppraisalIndexRequest $request,
        MobileAppraisalReadService $service,
    ): AnonymousResourceCollection {
        $result = $service->paginate($request->user(), $request->filters());

        return AppraisalSummaryResource::collection($result['paginator'])
            ->additional([
                'stats' => $result['stats'],
                'filters' => $result['filters'],
            ]);
    }

    public function options(Request $request, MobileAppraisalReadService $service): JsonResponse
    {
        return response()->json([
            'data' => $service->options($request->user()),
            'message' => 'OK',
        ]);
    }

    public function show(Request $request, int $appraisal, MobileAppraisalReadService $service): AppraisalDetailResource
    {
        return AppraisalDetailResource::make($service->detail($request->user(), $appraisal));
    }

    public function tracking(Request $request, int $appraisal, MobileAppraisalReadService $service): AppraisalTrackingResource
    {
        return AppraisalTrackingResource::make($service->tracking($request->user(), $appraisal));
    }
}

<?php

namespace App\Http\Controllers\Api\V1\Customer;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Customer\MobileAppraisalAssetRequest;
use App\Http\Requests\Api\V1\Customer\MobileAppraisalDraftRequest;
use App\Http\Requests\Api\V1\Customer\MobileAppraisalFileUploadRequest;
use App\Http\Requests\Api\V1\Customer\MobileAppraisalSubmitRequest;
use App\Http\Resources\Api\V1\AppraisalAssetFileResource;
use App\Http\Resources\Api\V1\AppraisalDraftResource;
use App\Services\Customer\MobileAppraisalDraftService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class MobileAppraisalDraftController extends Controller
{
    public function store(
        MobileAppraisalDraftRequest $request,
        MobileAppraisalDraftService $service,
    ): JsonResponse {
        return AppraisalDraftResource::make($service->create($request->user(), $request->validated()))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    public function show(Request $request, int $draft, MobileAppraisalDraftService $service): AppraisalDraftResource
    {
        return AppraisalDraftResource::make($service->find($request->user(), $draft));
    }

    public function update(
        MobileAppraisalDraftRequest $request,
        int $draft,
        MobileAppraisalDraftService $service,
    ): AppraisalDraftResource {
        return AppraisalDraftResource::make($service->update($request->user(), $draft, $request->validated()));
    }

    public function storeAsset(
        MobileAppraisalAssetRequest $request,
        int $draft,
        MobileAppraisalDraftService $service,
    ): JsonResponse {
        return AppraisalDraftResource::make($service->addAsset($request->user(), $draft, $request->validated()))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    public function updateAsset(
        MobileAppraisalAssetRequest $request,
        int $draft,
        int $asset,
        MobileAppraisalDraftService $service,
    ): AppraisalDraftResource {
        return AppraisalDraftResource::make(
            $service->updateAsset($request->user(), $draft, $asset, $request->validated()),
        );
    }

    public function destroyAsset(
        Request $request,
        int $draft,
        int $asset,
        MobileAppraisalDraftService $service,
    ): AppraisalDraftResource {
        return AppraisalDraftResource::make($service->deleteAsset($request->user(), $draft, $asset));
    }

    public function storeFiles(
        MobileAppraisalFileUploadRequest $request,
        int $draft,
        int $asset,
        MobileAppraisalDraftService $service,
    ): JsonResponse {
        return AppraisalAssetFileResource::collection($service->uploadFiles(
            $request->user(),
            $draft,
            $asset,
            $request->string('type')->toString(),
            $request->file('files', []),
        ))->response()->setStatusCode(Response::HTTP_CREATED);
    }

    public function destroyFile(
        Request $request,
        int $draft,
        int $file,
        MobileAppraisalDraftService $service,
    ): Response {
        $service->deleteFile($request->user(), $draft, $file);

        return response()->noContent();
    }

    public function submit(
        MobileAppraisalSubmitRequest $request,
        int $draft,
        MobileAppraisalDraftService $service,
    ): JsonResponse {
        $submitted = $service->submit($request->user(), $request, $draft);

        return response()->json([
            'data' => [
                'id' => $submitted->id,
                'request_number' => $submitted->request_number,
                'status' => $submitted->status->value,
            ],
            'message' => 'Permohonan berhasil dikirim.',
        ]);
    }
}

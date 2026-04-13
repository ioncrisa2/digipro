<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\IkkByProvinceIndexRequest;
use App\Http\Requests\Admin\SaveIkkByProvinceRequest;
use App\Services\Admin\AdminReferenceGuideDataWorkspaceService;
use Illuminate\Http\RedirectResponse;
use Inertia\Response;

class IkkByProvinceController extends Controller
{
    public function __construct(
        private readonly AdminReferenceGuideDataWorkspaceService $workspaceService,
    ) {
    }

    public function index(IkkByProvinceIndexRequest $request): Response
    {
        return inertia('Admin/IkkByProvince/Index', $this->workspaceService
            ->ikkByProvinceIndexPayload(
                $request->filters(),
                $request->guidelineSetId(),
                $request->yearValue(),
                $request->provinceId(),
                $this->workspacePrefix(),
            ));
    }

    public function save(SaveIkkByProvinceRequest $request): RedirectResponse
    {
        $filters = $this->workspaceService->saveIkkByProvince($request->validated());

        return redirect()
            ->route($this->workspaceRouteName('ref-guidelines.ikk-by-province.index'), $filters)
            ->with('success', 'IKK by Province berhasil disimpan.');
    }
}

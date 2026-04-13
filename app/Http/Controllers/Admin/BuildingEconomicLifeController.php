<?php

namespace App\Http\Controllers\Admin;

use App\Exports\BuildingEconomicLifeExport;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ImportBuildingEconomicLifeRequest;
use App\Http\Requests\Admin\ReferenceGuideDataIndexRequest;
use App\Http\Requests\Admin\StoreBuildingEconomicLifeRequest;
use App\Models\BuildingEconomicLife;
use App\Services\Admin\AdminReferenceGuideDataWorkspaceService;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Inertia\Response;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Throwable;

class BuildingEconomicLifeController extends Controller
{
    public function __construct(
        private readonly AdminReferenceGuideDataWorkspaceService $workspaceService,
    ) {
    }

    public function index(ReferenceGuideDataIndexRequest $request): Response
    {
        return inertia('Admin/BuildingEconomicLives/Index', $this->workspaceService
            ->buildingEconomicLivesIndexPayload(
                $request->filters(['q', 'guideline_item_id', 'year', 'category', 'building_class']),
                $request->perPage(),
                $this->workspacePrefix(),
            ));
    }

    public function export(ReferenceGuideDataIndexRequest $request): BinaryFileResponse
    {
        return Excel::download(
            new BuildingEconomicLifeExport($this->workspaceService->buildingEconomicLivesExportQuery(
                $request->filters(['q', 'guideline_item_id', 'year', 'category', 'building_class'], false)
            )),
            'building-economic-lives-' . now()->format('Ymd-His') . '.xlsx'
        );
    }

    public function create(): Response
    {
        return inertia('Admin/BuildingEconomicLives/Form', $this->workspaceService
            ->buildingEconomicLivesCreatePayload($this->workspacePrefix()));
    }

    public function store(StoreBuildingEconomicLifeRequest $request): RedirectResponse
    {
        $this->workspaceService->saveBuildingEconomicLife($request->validated());

        return redirect()
            ->route($this->workspaceRouteName('ref-guidelines.building-economic-lives.index'))
            ->with('success', 'BEL berhasil ditambahkan.');
    }

    public function import(ImportBuildingEconomicLifeRequest $request): RedirectResponse
    {
        try {
            $result = $this->workspaceService->importBuildingEconomicLives($request);
        } catch (Throwable) {
            return redirect()
                ->route($this->workspaceRouteName('ref-guidelines.building-economic-lives.index'))
                ->with('error', 'Import BEL gagal diproses. Pastikan header file sesuai format: category, sub_category, building_type, building_class, storey_min, storey_max, economic_life.');
        }

        return redirect()
            ->route($this->workspaceRouteName('ref-guidelines.building-economic-lives.index'), $result['filters'])
            ->with('success', $result['message']);
    }

    public function edit(BuildingEconomicLife $buildingEconomicLife): Response
    {
        return inertia('Admin/BuildingEconomicLives/Form', $this->workspaceService
            ->buildingEconomicLivesEditPayload($buildingEconomicLife, $this->workspacePrefix()));
    }

    public function update(
        StoreBuildingEconomicLifeRequest $request,
        BuildingEconomicLife $buildingEconomicLife
    ): RedirectResponse {
        $this->workspaceService->saveBuildingEconomicLife($request->validated(), $buildingEconomicLife);

        return redirect()
            ->route($this->workspaceRouteName('ref-guidelines.building-economic-lives.index'))
            ->with('success', 'BEL berhasil diperbarui.');
    }

    public function destroy(BuildingEconomicLife $buildingEconomicLife): RedirectResponse
    {
        try {
            $buildingEconomicLife->delete();
        } catch (QueryException) {
            return redirect()
                ->route($this->workspaceRouteName('ref-guidelines.building-economic-lives.index'))
                ->with('error', 'BEL tidak bisa dihapus karena masih dipakai perhitungan reviewer.');
        }

        return redirect()
            ->route($this->workspaceRouteName('ref-guidelines.building-economic-lives.index'))
            ->with('success', 'BEL berhasil dihapus.');
    }
}

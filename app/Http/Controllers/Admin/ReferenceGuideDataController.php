<?php

namespace App\Http\Controllers\Admin;

use App\Exports\CostElementExport;
use App\Exports\FloorIndexExport;
use App\Exports\IkkExport;
use App\Exports\MappiRcnStandardExport;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ImportConstructionCostIndicesRequest;
use App\Http\Requests\Admin\ImportCostElementRequest;
use App\Http\Requests\Admin\ImportFloorIndexRequest;
use App\Http\Requests\Admin\ImportMappiRcnStandardRequest;
use App\Http\Requests\Admin\ReferenceGuideDataIndexRequest;
use App\Http\Requests\Admin\StoreConstructionCostIndexRequest;
use App\Http\Requests\Admin\StoreCostElementRequest;
use App\Http\Requests\Admin\StoreFloorIndexRequest;
use App\Http\Requests\Admin\StoreMappiRcnStandardRequest;
use App\Models\ConstructionCostIndex;
use App\Models\CostElement;
use App\Models\FloorIndex;
use App\Models\MappiRcnStandard;
use App\Services\Admin\AdminReferenceGuideDataWorkspaceService;
use Illuminate\Http\RedirectResponse;
use Inertia\Response;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Throwable;

class ReferenceGuideDataController extends Controller
{
    public function __construct(
        private readonly AdminReferenceGuideDataWorkspaceService $workspaceService,
    ) {
    }

    public function constructionCostIndicesIndex(ReferenceGuideDataIndexRequest $request): Response
    {
        return inertia('Admin/ConstructionCostIndices/Index', $this->workspaceService
            ->constructionCostIndicesIndexPayload(
                $request->filters(['q', 'guideline_set_id', 'year', 'province_id']),
                $request->perPage(),
                $this->workspacePrefix(),
            ));
    }

    public function constructionCostIndicesExport(ReferenceGuideDataIndexRequest $request): BinaryFileResponse
    {
        return Excel::download(
            new IkkExport($this->workspaceService->constructionCostIndicesExportQuery(
                $request->filters(['q', 'guideline_set_id', 'year', 'province_id'], false)
            )),
            'ikk-' . now()->format('Ymd-His') . '.xlsx'
        );
    }

    public function constructionCostIndicesCreate(): Response
    {
        return inertia('Admin/ConstructionCostIndices/Form', $this->workspaceService
            ->constructionCostIndicesCreatePayload($this->workspacePrefix()));
    }

    public function constructionCostIndicesStore(StoreConstructionCostIndexRequest $request): RedirectResponse
    {
        $this->workspaceService->saveConstructionCostIndex($request->validated());

        return redirect()
            ->route($this->workspaceRouteName('ref-guidelines.construction-cost-indices.index'))
            ->with('success', 'IKK berhasil ditambahkan.');
    }

    public function constructionCostIndicesImport(ImportConstructionCostIndicesRequest $request): RedirectResponse
    {
        try {
            $result = $this->workspaceService->importConstructionCostIndices($request);
        } catch (Throwable) {
            return redirect()
                ->route($this->workspaceRouteName('ref-guidelines.construction-cost-indices.index'))
                ->with('error', 'Import IKK gagal diproses. Pastikan format header Excel sesuai template: kode, nama_provinsi_kota_kabupaten, ikk_mappi.');
        }

        return redirect()
            ->route($this->workspaceRouteName('ref-guidelines.construction-cost-indices.index'), $result['filters'])
            ->with('success', $result['message']);
    }

    public function constructionCostIndicesEdit(ConstructionCostIndex $constructionCostIndex): Response
    {
        return inertia('Admin/ConstructionCostIndices/Form', $this->workspaceService
            ->constructionCostIndicesEditPayload($constructionCostIndex, $this->workspacePrefix()));
    }

    public function constructionCostIndicesUpdate(StoreConstructionCostIndexRequest $request, ConstructionCostIndex $constructionCostIndex): RedirectResponse
    {
        $this->workspaceService->saveConstructionCostIndex($request->validated(), $constructionCostIndex);

        return redirect()
            ->route($this->workspaceRouteName('ref-guidelines.construction-cost-indices.index'))
            ->with('success', 'IKK berhasil diperbarui.');
    }

    public function constructionCostIndicesDestroy(ConstructionCostIndex $constructionCostIndex): RedirectResponse
    {
        try {
            $this->workspaceService->deleteConstructionCostIndex($constructionCostIndex);
        } catch (\RuntimeException $exception) {
            return redirect()
                ->route($this->workspaceRouteName('ref-guidelines.construction-cost-indices.index'))
                ->with('error', $exception->getMessage());
        }

        return redirect()
            ->route($this->workspaceRouteName('ref-guidelines.construction-cost-indices.index'))
            ->with('success', 'IKK berhasil dihapus.');
    }

    public function costElementsIndex(ReferenceGuideDataIndexRequest $request): Response
    {
        return inertia('Admin/CostElements/Index', $this->workspaceService
            ->costElementsIndexPayload(
                $request->filters(['q', 'guideline_set_id', 'year', 'base_region', 'group']),
                $request->perPage(),
                $this->workspacePrefix(),
            ));
    }

    public function costElementsExport(ReferenceGuideDataIndexRequest $request): BinaryFileResponse
    {
        return Excel::download(
            new CostElementExport($this->workspaceService->costElementsExportQuery(
                $request->filters(['q', 'guideline_set_id', 'year', 'base_region', 'group'], false)
            )),
            'cost-elements-' . now()->format('Ymd-His') . '.xlsx'
        );
    }

    public function costElementsCreate(): Response
    {
        return inertia('Admin/CostElements/Form', $this->workspaceService
            ->costElementsCreatePayload($this->workspacePrefix()));
    }

    public function costElementsStore(StoreCostElementRequest $request): RedirectResponse
    {
        $this->workspaceService->saveCostElement($request->validated());

        return redirect()
            ->route($this->workspaceRouteName('ref-guidelines.cost-elements.index'))
            ->with('success', 'Cost element berhasil ditambahkan.');
    }

    public function costElementsImport(ImportCostElementRequest $request): RedirectResponse
    {
        try {
            $result = $this->workspaceService->importCostElements($request);
        } catch (Throwable) {
            return redirect()
                ->route($this->workspaceRouteName('ref-guidelines.cost-elements.index'))
                ->with('error', 'Import biaya unit terpasang gagal diproses. Pastikan header Excel sesuai template: group, element_code, element_name, building_type, building_class, storey_pattern, unit, unit_cost, spec_json.');
        }

        return redirect()
            ->route($this->workspaceRouteName('ref-guidelines.cost-elements.index'), $result['filters'])
            ->with('success', $result['message']);
    }

    public function costElementsEdit(CostElement $costElement): Response
    {
        return inertia('Admin/CostElements/Form', $this->workspaceService
            ->costElementsEditPayload($costElement, $this->workspacePrefix()));
    }

    public function costElementsUpdate(StoreCostElementRequest $request, CostElement $costElement): RedirectResponse
    {
        $this->workspaceService->saveCostElement($request->validated(), $costElement);

        return redirect()
            ->route($this->workspaceRouteName('ref-guidelines.cost-elements.index'))
            ->with('success', 'Cost element berhasil diperbarui.');
    }

    public function costElementsDestroy(CostElement $costElement): RedirectResponse
    {
        try {
            $this->workspaceService->deleteCostElement($costElement);
        } catch (\RuntimeException $exception) {
            return redirect()
                ->route($this->workspaceRouteName('ref-guidelines.cost-elements.index'))
                ->with('error', $exception->getMessage());
        }

        return redirect()
            ->route($this->workspaceRouteName('ref-guidelines.cost-elements.index'))
            ->with('success', 'Cost element berhasil dihapus.');
    }

    public function floorIndicesIndex(ReferenceGuideDataIndexRequest $request): Response
    {
        return inertia('Admin/FloorIndices/Index', $this->workspaceService
            ->floorIndicesIndexPayload(
                $request->filters(['q', 'guideline_set_id', 'year', 'building_class']),
                $request->perPage(),
                $this->workspacePrefix(),
            ));
    }

    public function floorIndicesExport(ReferenceGuideDataIndexRequest $request): BinaryFileResponse
    {
        return Excel::download(
            new FloorIndexExport($this->workspaceService->floorIndicesExportQuery(
                $request->filters(['q', 'guideline_set_id', 'year', 'building_class'], false)
            )),
            'floor-indices-' . now()->format('Ymd-His') . '.xlsx'
        );
    }

    public function floorIndicesCreate(): Response
    {
        return inertia('Admin/FloorIndices/Form', $this->workspaceService
            ->floorIndicesCreatePayload($this->workspacePrefix()));
    }

    public function floorIndicesStore(StoreFloorIndexRequest $request): RedirectResponse
    {
        $this->workspaceService->saveFloorIndex($request->validated());

        return redirect()
            ->route($this->workspaceRouteName('ref-guidelines.floor-indices.index'))
            ->with('success', 'Floor index berhasil ditambahkan.');
    }

    public function floorIndicesImport(ImportFloorIndexRequest $request): RedirectResponse
    {
        try {
            $result = $this->workspaceService->importFloorIndices($request);
        } catch (Throwable) {
            return redirect()
                ->route($this->workspaceRouteName('ref-guidelines.floor-indices.index'))
                ->with('error', 'Import floor index gagal diproses. Pastikan format header Excel sesuai template: building_class, floor_count, il_value.');
        }

        return redirect()
            ->route($this->workspaceRouteName('ref-guidelines.floor-indices.index'), $result['filters'])
            ->with('success', $result['message']);
    }

    public function floorIndicesEdit(FloorIndex $floorIndex): Response
    {
        return inertia('Admin/FloorIndices/Form', $this->workspaceService
            ->floorIndicesEditPayload($floorIndex, $this->workspacePrefix()));
    }

    public function floorIndicesUpdate(StoreFloorIndexRequest $request, FloorIndex $floorIndex): RedirectResponse
    {
        $this->workspaceService->saveFloorIndex($request->validated(), $floorIndex);

        return redirect()
            ->route($this->workspaceRouteName('ref-guidelines.floor-indices.index'))
            ->with('success', 'Floor index berhasil diperbarui.');
    }

    public function floorIndicesDestroy(FloorIndex $floorIndex): RedirectResponse
    {
        try {
            $this->workspaceService->deleteFloorIndex($floorIndex);
        } catch (\RuntimeException $exception) {
            return redirect()
                ->route($this->workspaceRouteName('ref-guidelines.floor-indices.index'))
                ->with('error', $exception->getMessage());
        }

        return redirect()
            ->route($this->workspaceRouteName('ref-guidelines.floor-indices.index'))
            ->with('success', 'Floor index berhasil dihapus.');
    }

    public function mappiRcnStandardsIndex(ReferenceGuideDataIndexRequest $request): Response
    {
        return inertia('Admin/MappiRcnStandards/Index', $this->workspaceService
            ->mappiRcnStandardsIndexPayload(
                $request->filters(['q', 'guideline_set_id', 'year', 'building_type', 'building_class']),
                $request->perPage(),
                $this->workspacePrefix(),
            ));
    }

    public function mappiRcnStandardsExport(ReferenceGuideDataIndexRequest $request): BinaryFileResponse
    {
        return Excel::download(
            new MappiRcnStandardExport($this->workspaceService->mappiRcnStandardsExportQuery(
                $request->filters(['q', 'guideline_set_id', 'year', 'building_type', 'building_class'], false)
            )),
            'mappi-rcn-standards-' . now()->format('Ymd-His') . '.xlsx'
        );
    }

    public function mappiRcnStandardsCreate(): Response
    {
        return inertia('Admin/MappiRcnStandards/Form', $this->workspaceService
            ->mappiRcnStandardsCreatePayload($this->workspacePrefix()));
    }

    public function mappiRcnStandardsStore(StoreMappiRcnStandardRequest $request): RedirectResponse
    {
        $this->workspaceService->saveMappiRcnStandard($request->validated());

        return redirect()
            ->route($this->workspaceRouteName('ref-guidelines.mappi-rcn-standards.index'))
            ->with('success', 'MAPPI RCN berhasil ditambahkan.');
    }

    public function mappiRcnStandardsImport(ImportMappiRcnStandardRequest $request): RedirectResponse
    {
        try {
            $result = $this->workspaceService->importMappiRcnStandards($request);
        } catch (Throwable) {
            return redirect()
                ->route($this->workspaceRouteName('ref-guidelines.mappi-rcn-standards.index'))
                ->with('error', 'Import MAPPI RCN gagal diproses. Pastikan header Excel sesuai template: building_type, building_class, storey_pattern, rcn_value, notes.');
        }

        return redirect()
            ->route($this->workspaceRouteName('ref-guidelines.mappi-rcn-standards.index'), $result['filters'])
            ->with('success', $result['message']);
    }

    public function mappiRcnStandardsEdit(MappiRcnStandard $mappiRcnStandard): Response
    {
        return inertia('Admin/MappiRcnStandards/Form', $this->workspaceService
            ->mappiRcnStandardsEditPayload($mappiRcnStandard, $this->workspacePrefix()));
    }

    public function mappiRcnStandardsUpdate(StoreMappiRcnStandardRequest $request, MappiRcnStandard $mappiRcnStandard): RedirectResponse
    {
        $this->workspaceService->saveMappiRcnStandard($request->validated(), $mappiRcnStandard);

        return redirect()
            ->route($this->workspaceRouteName('ref-guidelines.mappi-rcn-standards.index'))
            ->with('success', 'MAPPI RCN berhasil diperbarui.');
    }

    public function mappiRcnStandardsDestroy(MappiRcnStandard $mappiRcnStandard): RedirectResponse
    {
        try {
            $this->workspaceService->deleteMappiRcnStandard($mappiRcnStandard);
        } catch (\RuntimeException $exception) {
            return redirect()
                ->route($this->workspaceRouteName('ref-guidelines.mappi-rcn-standards.index'))
                ->with('error', $exception->getMessage());
        }

        return redirect()
            ->route($this->workspaceRouteName('ref-guidelines.mappi-rcn-standards.index'))
            ->with('success', 'MAPPI RCN berhasil dihapus.');
    }
}

<?php

namespace App\Http\Controllers\Admin;

use App\Exports\GuidelineSetExport;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\GuidelineSetIndexRequest;
use App\Http\Requests\Admin\StoreGuidelineSetRequest;
use App\Http\Requests\Admin\StoreValuationSettingRequest;
use App\Http\Requests\Admin\ValuationSettingIndexRequest;
use App\Models\GuidelineSet;
use App\Models\ValuationSetting;
use App\Services\Admin\AdminReferenceGuideSettingsWorkspaceService;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Inertia\Response;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ReferenceGuideSettingsController extends Controller
{
    public function __construct(
        private readonly AdminReferenceGuideSettingsWorkspaceService $workspaceService,
    ) {
    }

    public function guidelineSetsIndex(GuidelineSetIndexRequest $request): Response
    {
        return inertia('Admin/GuidelineSets/Index', $this->workspaceService
            ->guidelineSetsIndexPayload(
                $request->filters(),
                $request->perPage(),
                $this->workspacePrefix(),
            ));
    }

    public function guidelineSetsExport(GuidelineSetIndexRequest $request): BinaryFileResponse
    {
        return Excel::download(
            new GuidelineSetExport($this->workspaceService->guidelineSetsExportQuery(
                $request->filters(withPerPage: false)
            )),
            'guideline-sets-' . now()->format('Ymd-His') . '.xlsx'
        );
    }

    public function guidelineSetsCreate(): Response
    {
        return inertia('Admin/GuidelineSets/Form', $this->workspaceService
            ->guidelineSetsCreatePayload($this->workspacePrefix()));
    }

    public function guidelineSetsStore(StoreGuidelineSetRequest $request): RedirectResponse
    {
        $this->workspaceService->saveGuidelineSet($request->validated());

        return redirect()
            ->route($this->workspaceRouteName('ref-guidelines.guideline-sets.index'))
            ->with('success', 'Guideline set berhasil ditambahkan.');
    }

    public function guidelineSetsEdit(GuidelineSet $guidelineSet): Response
    {
        return inertia('Admin/GuidelineSets/Form', $this->workspaceService
            ->guidelineSetsEditPayload($guidelineSet, $this->workspacePrefix()));
    }

    public function guidelineSetsUpdate(StoreGuidelineSetRequest $request, GuidelineSet $guidelineSet): RedirectResponse
    {
        $this->workspaceService->saveGuidelineSet($request->validated(), $guidelineSet);

        return redirect()
            ->route($this->workspaceRouteName('ref-guidelines.guideline-sets.index'))
            ->with('success', 'Guideline set berhasil diperbarui.');
    }

    public function guidelineSetsDestroy(GuidelineSet $guidelineSet): RedirectResponse
    {
        try {
            $guidelineSet->delete();
        } catch (QueryException) {
            return redirect()
                ->route($this->workspaceRouteName('ref-guidelines.guideline-sets.index'))
                ->with('error', 'Guideline set tidak bisa dihapus karena masih dipakai resource referensi lain.');
        }

        return redirect()
            ->route($this->workspaceRouteName('ref-guidelines.guideline-sets.index'))
            ->with('success', 'Guideline set berhasil dihapus.');
    }

    public function valuationSettingsIndex(ValuationSettingIndexRequest $request): Response
    {
        return inertia('Admin/ValuationSettings/Index', $this->workspaceService
            ->valuationSettingsIndexPayload(
                $request->filters(),
                $request->perPage(),
                $this->workspacePrefix(),
            ));
    }

    public function valuationSettingsCreate(): Response
    {
        return inertia('Admin/ValuationSettings/Form', $this->workspaceService
            ->valuationSettingsCreatePayload($this->workspacePrefix()));
    }

    public function valuationSettingsStore(StoreValuationSettingRequest $request): RedirectResponse
    {
        $this->workspaceService->saveValuationSetting($request->validated());

        return redirect()
            ->route($this->workspaceRouteName('ref-guidelines.valuation-settings.index'))
            ->with('success', 'Valuation setting berhasil ditambahkan.');
    }

    public function valuationSettingsEdit(ValuationSetting $valuationSetting): Response
    {
        return inertia('Admin/ValuationSettings/Form', $this->workspaceService
            ->valuationSettingsEditPayload($valuationSetting, $this->workspacePrefix()));
    }

    public function valuationSettingsUpdate(StoreValuationSettingRequest $request, ValuationSetting $valuationSetting): RedirectResponse
    {
        $this->workspaceService->saveValuationSetting($request->validated(), $valuationSetting);

        return redirect()
            ->route($this->workspaceRouteName('ref-guidelines.valuation-settings.index'))
            ->with('success', 'Valuation setting berhasil diperbarui.');
    }

    public function valuationSettingsDestroy(ValuationSetting $valuationSetting): RedirectResponse
    {
        $valuationSetting->delete();

        return redirect()
            ->route($this->workspaceRouteName('ref-guidelines.valuation-settings.index'))
            ->with('success', 'Valuation setting berhasil dihapus.');
    }
}

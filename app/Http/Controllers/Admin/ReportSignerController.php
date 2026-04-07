<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ReportSignerIndexRequest;
use App\Http\Requests\Admin\StoreReportSignerRequest;
use App\Models\ReportSigner;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Response;

class ReportSignerController extends Controller
{
    public function index(ReportSignerIndexRequest $request): Response
    {
        $filters = $request->filters();

        $baseQuery = ReportSigner::query();

        $records = ReportSigner::query()
            ->when($filters['q'] !== '', function ($query) use ($filters): void {
                $query->where(function ($innerQuery) use ($filters): void {
                    $innerQuery
                        ->where('name', 'like', '%' . $filters['q'] . '%')
                        ->orWhere('position_title', 'like', '%' . $filters['q'] . '%')
                        ->orWhere('certification_number', 'like', '%' . $filters['q'] . '%');
                });
            })
            ->when($filters['role'] !== 'all', fn ($query) => $query->where('role', $filters['role']))
            ->when($filters['active'] === 'active', fn ($query) => $query->where('is_active', true))
            ->when($filters['active'] === 'inactive', fn ($query) => $query->where('is_active', false))
            ->orderByDesc('is_active')
            ->orderBy('role')
            ->orderBy('name')
            ->paginate($request->perPage())
            ->withQueryString();

        $records->through(fn (ReportSigner $signer) => $this->transformSignerRow($signer));

        return inertia('Admin/ReportSigners/Index', [
            'filters' => $filters,
            'roleOptions' => $this->roleOptions(),
            'activeOptions' => [
                ['value' => 'all', 'label' => 'Semua Status'],
                ['value' => 'active', 'label' => 'Aktif'],
                ['value' => 'inactive', 'label' => 'Nonaktif'],
            ],
            'summary' => [
                'total' => (clone $baseQuery)->count(),
                'reviewers' => (clone $baseQuery)->where('role', 'reviewer')->count(),
                'public_appraisers' => (clone $baseQuery)->where('role', 'public_appraiser')->count(),
                'active' => (clone $baseQuery)->where('is_active', true)->count(),
            ],
            'records' => $this->paginatedRecordsPayload($records),
            'createUrl' => route('admin.master-data.report-signers.create'),
        ]);
    }

    public function create(): Response
    {
        return inertia('Admin/ReportSigners/Form', [
            'mode' => 'create',
            'record' => [
                'role' => 'reviewer',
                'name' => '',
                'position_title' => '',
                'title_suffix' => '',
                'certification_number' => '',
                'is_active' => true,
            ],
            'roleOptions' => $this->roleOptions(),
            'submitUrl' => route('admin.master-data.report-signers.store'),
            'indexUrl' => route('admin.master-data.report-signers.index'),
        ]);
    }

    public function store(StoreReportSignerRequest $request): RedirectResponse
    {
        $signer = ReportSigner::query()->create($request->validated());

        return redirect()
            ->route('admin.master-data.report-signers.edit', $signer)
            ->with('success', 'Profil penandatangan report berhasil ditambahkan.');
    }

    public function edit(ReportSigner $reportSigner): Response
    {
        return inertia('Admin/ReportSigners/Form', [
            'mode' => 'edit',
            'record' => [
                'id' => $reportSigner->id,
                'role' => $reportSigner->role,
                'name' => $reportSigner->name,
                'position_title' => $reportSigner->position_title,
                'title_suffix' => $reportSigner->title_suffix,
                'certification_number' => $reportSigner->certification_number,
                'is_active' => (bool) $reportSigner->is_active,
            ],
            'roleOptions' => $this->roleOptions(),
            'submitUrl' => route('admin.master-data.report-signers.update', $reportSigner),
            'indexUrl' => route('admin.master-data.report-signers.index'),
        ]);
    }

    public function update(StoreReportSignerRequest $request, ReportSigner $reportSigner): RedirectResponse
    {
        $reportSigner->update($request->validated());

        return redirect()
            ->route('admin.master-data.report-signers.edit', $reportSigner)
            ->with('success', 'Profil penandatangan report berhasil diperbarui.');
    }

    public function destroy(ReportSigner $reportSigner): RedirectResponse
    {
        $reportSigner->delete();

        return redirect()
            ->route('admin.master-data.report-signers.index')
            ->with('success', 'Profil penandatangan report berhasil dihapus.');
    }

    private function roleOptions(): array
    {
        return [
            ['value' => 'reviewer', 'label' => 'Reviewer'],
            ['value' => 'public_appraiser', 'label' => 'Penilai Publik'],
        ];
    }

    private function transformSignerRow(ReportSigner $signer): array
    {
        return [
            'id' => $signer->id,
            'name' => $signer->name,
            'role' => $signer->role,
            'role_label' => $signer->role === 'public_appraiser' ? 'Penilai Publik' : 'Reviewer',
            'position_title' => $signer->position_title,
            'title_suffix' => $signer->title_suffix,
            'certification_number' => $signer->certification_number,
            'is_active' => (bool) $signer->is_active,
            'edit_url' => route('admin.master-data.report-signers.edit', $signer),
            'destroy_url' => route('admin.master-data.report-signers.destroy', $signer),
        ];
    }

}

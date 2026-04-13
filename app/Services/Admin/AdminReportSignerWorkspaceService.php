<?php

namespace App\Services\Admin;

use App\Models\ReportSigner;

class AdminReportSignerWorkspaceService
{
    public function indexPayload(array $filters, int $perPage): array
    {
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
            ->paginate($perPage)
            ->withQueryString();

        $records->through(fn (ReportSigner $signer) => $this->signerRow($signer));

        return [
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
        ];
    }

    public function createPayload(): array
    {
        return [
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
        ];
    }

    public function editPayload(ReportSigner $reportSigner): array
    {
        return [
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
        ];
    }

    public function saveReportSigner(array $validated, ?ReportSigner $reportSigner = null): ReportSigner
    {
        if ($reportSigner === null) {
            return ReportSigner::query()->create($validated);
        }

        $reportSigner->update($validated);

        return $reportSigner;
    }

    public function deleteReportSigner(ReportSigner $reportSigner): void
    {
        $reportSigner->delete();
    }

    private function roleOptions(): array
    {
        return [
            ['value' => 'reviewer', 'label' => 'Reviewer'],
            ['value' => 'public_appraiser', 'label' => 'Penilai Publik'],
        ];
    }

    private function signerRow(ReportSigner $signer): array
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

    private function paginatedRecordsPayload(object $records): array
    {
        return [
            'data' => $records->items(),
            'meta' => [
                'from' => $records->firstItem(),
                'to' => $records->lastItem(),
                'total' => $records->total(),
                'current_page' => $records->currentPage(),
                'last_page' => $records->lastPage(),
                'per_page' => $records->perPage(),
                'links' => $records->linkCollection()->toArray(),
            ],
        ];
    }
}

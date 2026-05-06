<?php

namespace App\Services\Admin;

use App\Models\ReportSigner;
use App\Models\User;
use App\Services\Peruri\PeruriSignerReadinessService;

class AdminReportSignerWorkspaceService
{
    public function __construct(
        private readonly PeruriSignerReadinessService $readinessService,
    ) {
    }

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
        $record = [
            'user_id' => null,
            'role' => 'reviewer',
            'name' => '',
            'email' => '',
            'phone_number' => '',
            'position_title' => '',
            'title_suffix' => '',
            'certification_number' => '',
            'is_active' => true,
            'peruri_certificate_status' => null,
            'peruri_keyla_status' => null,
            'peruri_last_checked_at' => null,
        ];

        return [
            'mode' => 'create',
            'record' => $record,
            'roleOptions' => $this->roleOptions(),
            'userOptions' => $this->internalUserOptions(),
            'submitUrl' => route('admin.master-data.report-signers.store'),
            'indexUrl' => route('admin.master-data.report-signers.index'),
            'refreshReadinessUrl' => null,
            'peruriActions' => $this->peruriActionsPayload(null, $record),
        ];
    }

    public function editPayload(ReportSigner $reportSigner): array
    {
        $record = [
            'id' => $reportSigner->id,
            'user_id' => $reportSigner->user_id,
            'role' => $reportSigner->role,
            'name' => $reportSigner->name,
            'email' => $reportSigner->email,
            'phone_number' => $reportSigner->phone_number,
            'position_title' => $reportSigner->position_title,
            'title_suffix' => $reportSigner->title_suffix,
            'certification_number' => $reportSigner->certification_number,
            'is_active' => (bool) $reportSigner->is_active,
            'peruri_certificate_status' => $reportSigner->peruri_certificate_status,
            'peruri_keyla_status' => $reportSigner->peruri_keyla_status,
            'peruri_last_checked_at' => optional($reportSigner->peruri_last_checked_at)->toDateTimeString(),
        ];

        return [
            'mode' => 'edit',
            'record' => $record,
            'roleOptions' => $this->roleOptions(),
            'userOptions' => $this->internalUserOptions(),
            'submitUrl' => route('admin.master-data.report-signers.update', $reportSigner),
            'indexUrl' => route('admin.master-data.report-signers.index'),
            'refreshReadinessUrl' => route('admin.master-data.report-signers.refresh-readiness', $reportSigner),
            'peruriActions' => $this->peruriActionsPayload($reportSigner, $record),
        ];
    }

    public function saveReportSigner(array $validated, ?ReportSigner $reportSigner = null): ReportSigner
    {
        if ($reportSigner === null) {
            return ReportSigner::query()->create($validated);
        }

        if (($validated['email'] ?? null) !== $reportSigner->email) {
            $validated['peruri_certificate_status'] = null;
            $validated['peruri_keyla_status'] = null;
            $validated['peruri_last_checked_at'] = null;
        }

        $reportSigner->update($validated);

        return $reportSigner;
    }

    public function deleteReportSigner(ReportSigner $reportSigner): void
    {
        $reportSigner->delete();
    }

    public function refreshReadiness(ReportSigner $reportSigner): array
    {
        return $this->readinessService->syncSigner($reportSigner);
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
            'email' => $signer->email,
            'user_id' => $signer->user_id,
            'is_active' => (bool) $signer->is_active,
            'peruri_certificate_status' => $signer->peruri_certificate_status,
            'peruri_keyla_status' => $signer->peruri_keyla_status,
            'peruri_last_checked_at' => optional($signer->peruri_last_checked_at)->toDateTimeString(),
            'edit_url' => route('admin.master-data.report-signers.edit', $signer),
            'destroy_url' => route('admin.master-data.report-signers.destroy', $signer),
        ];
    }

    private function internalUserOptions(): array
    {
        $roles = array_values(array_filter([
            config('access-control.super_admin.enabled', true)
                ? config('access-control.super_admin.name', 'super_admin')
                : null,
            'admin',
            'Reviewer',
        ]));

        return User::query()
            ->when(! empty($roles), fn ($query) => $query->whereHas('roles', fn ($rolesQuery) => $rolesQuery->whereIn('name', $roles)))
            ->orderBy('name')
            ->limit(200)
            ->get(['id', 'name', 'email'])
            ->map(fn (User $user) => [
                'value' => $user->id,
                'label' => $user->name,
                'description' => $user->email,
            ])
            ->values()
            ->all();
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

    /**
     * @param  array<string, mixed>  $record
     * @return array<string, mixed>
     */
    private function peruriActionsPayload(?ReportSigner $reportSigner, array $record): array
    {
        $registerTemplate = json_encode(array_filter([
            'name' => $record['name'] ?? null,
            'email' => $record['email'] ?? null,
            'phone' => $record['phone_number'] ?? null,
        ], fn ($value) => filled($value)), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

        return [
            'register_user_url' => $reportSigner ? route('admin.master-data.report-signers.peruri.register-user', $reportSigner) : null,
            'submit_kyc_url' => $reportSigner ? route('admin.master-data.report-signers.peruri.submit-kyc', $reportSigner) : null,
            'set_specimen_url' => $reportSigner ? route('admin.master-data.report-signers.peruri.set-specimen', $reportSigner) : null,
            'register_keyla_url' => $reportSigner ? route('admin.master-data.report-signers.peruri.register-keyla', $reportSigner) : null,
            'templates' => [
                'register_user_payload' => $registerTemplate ?: '{}',
                'kyc_payload' => '{}',
                'specimen_payload' => '{}',
            ],
        ];
    }
}

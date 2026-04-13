<?php

namespace App\Services\Admin;

use App\Enums\AppraisalStatusEnum;
use App\Models\AppraisalAsset;
use App\Models\AppraisalRequest;
use App\Models\Article;
use App\Models\BuildingEconomicLife;
use App\Models\ConstructionCostIndex;
use App\Models\ContactMessage;
use App\Models\CostElement;
use App\Models\FloorIndex;
use App\Models\GuidelineSet;
use App\Models\MappiRcnStandard;
use App\Models\Payment;
use App\Models\User;
use App\Models\ValuationSetting;
use App\Support\SystemNavigation;
use Illuminate\Support\Carbon;
use Spatie\Permission\Models\Role;

class AdminDashboardViewService
{
    public function build(?User $user): array
    {
        $isSuperAdmin = (bool) $user?->hasRole($this->superAdminRoleName());

        return [
            'stats' => $this->stats(),
            'actionItems' => $this->actionItems(),
            'paymentQueue' => $this->paymentQueue(),
            'isSuperAdmin' => $isSuperAdmin,
            'superAdminWidgets' => $isSuperAdmin ? $this->superAdminWidgets() : null,
        ];
    }

    private function stats(): array
    {
        return [
            $this->stat('submitted', 'Submitted', AppraisalRequest::query()->where('status', AppraisalStatusEnum::Submitted)->count(), 'Menunggu verifikasi', 'info'),
            $this->stat('docs_incomplete', 'Dokumen Kurang', AppraisalRequest::query()->where('status', AppraisalStatusEnum::DocsIncomplete)->count(), 'Perlu tindak lanjut', 'warning'),
            $this->stat('waiting_offer', 'Waiting Offer', AppraisalRequest::query()->where('status', AppraisalStatusEnum::WaitingOffer)->count(), 'Siap diberi penawaran', 'warning'),
            $this->stat('offer_sent', 'Offer Sent', AppraisalRequest::query()->where('status', AppraisalStatusEnum::OfferSent)->count(), 'Menunggu respons klien', 'primary'),
            $this->stat('waiting_signature', 'Waiting Signature', AppraisalRequest::query()->where('status', AppraisalStatusEnum::WaitingSignature)->count(), 'Kontrak belum ditandatangani', 'warning'),
            $this->stat('contract_signed', 'Contract Signed', AppraisalRequest::query()->where('status', AppraisalStatusEnum::ContractSigned)->count(), 'Siap proses valuasi', 'success'),
            $this->stat('requests_today', 'Permohonan Hari Ini', AppraisalRequest::query()->whereDate('requested_at', now()->toDateString())->count(), 'Permohonan baru', 'success'),
            $this->stat('assets_today', 'Aset Hari Ini', AppraisalAsset::query()->whereDate('created_at', now()->toDateString())->count(), 'Aset baru diunggah', 'info'),
        ];
    }

    private function stat(string $key, string $label, int $value, string $description, string $tone): array
    {
        return compact('key', 'label', 'value', 'description', 'tone');
    }

    private function actionItems(): array
    {
        return AppraisalRequest::query()
            ->whereIn('status', [
                AppraisalStatusEnum::Submitted,
                AppraisalStatusEnum::DocsIncomplete,
                AppraisalStatusEnum::Verified,
                AppraisalStatusEnum::WaitingOffer,
            ])
            ->with('user')
            ->withCount('assets')
            ->latest('requested_at')
            ->limit(8)
            ->get()
            ->map(fn (AppraisalRequest $record) => $this->transformRequestListItem($record))
            ->values()
            ->all();
    }

    private function paymentQueue(): array
    {
        return AppraisalRequest::query()
            ->where('status', AppraisalStatusEnum::ContractSigned)
            ->with('user')
            ->latest('updated_at')
            ->limit(8)
            ->get()
            ->map(fn (AppraisalRequest $record) => [
                'id' => $record->id,
                'request_number' => $this->requestNumber($record),
                'requester_name' => $record->user?->name ?? '-',
                'fee_total' => (int) ($record->fee_total ?? 0),
                'offer_validity_days' => $record->offer_validity_days,
                'updated_at' => $record->updated_at?->toIso8601String(),
                'show_url' => route('admin.appraisal-requests.show', $record),
            ])
            ->values()
            ->all();
    }

    private function superAdminWidgets(): array
    {
        $activeGuideline = GuidelineSet::query()
            ->where('is_active', true)
            ->latest('year')
            ->latest('updated_at')
            ->first();

        return [
            'system_health' => $this->superAdminSystemHealthSnapshot(),
            'role_summary' => $this->superAdminRoleAndUserSummary(),
            'reference_completeness' => $this->superAdminReferenceCompleteness($activeGuideline),
            'sensitive_changes' => $this->superAdminSensitiveChanges(),
            'permission_overview' => $this->superAdminPermissionOverview(),
            'exception_queue' => $this->superAdminExceptionQueue(),
        ];
    }

    private function transformRequestListItem(AppraisalRequest $record): array
    {
        return [
            'id' => $record->id,
            'request_number' => $this->requestNumber($record),
            'requester_name' => $record->user?->name ?? '-',
            'client_name' => $record->client_name ?: ($record->user?->name ?? '-'),
            'status_label' => $record->status?->label() ?? '-',
            'status_value' => $record->status?->value ?? null,
            'assets_count' => (int) ($record->assets_count ?? 0),
            'requested_at' => $record->requested_at?->toIso8601String(),
            'show_url' => route('admin.appraisal-requests.show', $record),
        ];
    }

    private function superAdminSystemHealthSnapshot(): array
    {
        return [
            [
                'key' => 'valuation_in_progress',
                'label' => 'Valuasi Berjalan',
                'value' => AppraisalRequest::query()->where('status', AppraisalStatusEnum::ValuationOnProgress)->count(),
                'description' => 'Request yang sedang dikerjakan reviewer.',
                'tone' => 'info',
                'url' => route('admin.appraisal-requests.index', ['status' => AppraisalStatusEnum::ValuationOnProgress->value]),
            ],
            [
                'key' => 'completed_today',
                'label' => 'Review Selesai Hari Ini',
                'value' => AppraisalRequest::query()
                    ->whereIn('status', [
                        AppraisalStatusEnum::ValuationCompleted,
                        AppraisalStatusEnum::ReportReady,
                        AppraisalStatusEnum::Completed,
                    ])
                    ->whereDate('updated_at', now()->toDateString())
                    ->count(),
                'description' => 'Output valuasi yang selesai diperbarui hari ini.',
                'tone' => 'success',
                'url' => route('admin.appraisal-requests.index'),
            ],
            [
                'key' => 'pending_payments',
                'label' => 'Pembayaran Pending',
                'value' => Payment::query()->where('status', 'pending')->count(),
                'description' => 'Transaksi yang masih menunggu penyelesaian.',
                'tone' => 'warning',
                'url' => route('admin.finance.payments.index', ['status' => 'pending']),
            ],
            [
                'key' => 'unread_messages',
                'label' => 'Pesan Belum Dibaca',
                'value' => ContactMessage::query()->whereNull('read_at')->count(),
                'description' => 'Inbox komunikasi yang belum disentuh admin.',
                'tone' => 'warning',
                'url' => route('admin.communications.contact-messages.index', ['unread' => 'yes']),
            ],
            [
                'key' => 'draft_articles',
                'label' => 'Draft Artikel',
                'value' => Article::query()->where('is_published', false)->count(),
                'description' => 'Konten yang belum dipublikasikan.',
                'tone' => 'primary',
                'url' => route('admin.content.articles.index'),
            ],
            [
                'key' => 'active_guidelines',
                'label' => 'Pedoman Aktif',
                'value' => GuidelineSet::query()->where('is_active', true)->count(),
                'description' => 'Pedoman referensi yang aktif di sistem.',
                'tone' => 'success',
                'url' => route('admin.ref-guidelines.guideline-sets.index'),
            ],
        ];
    }

    private function superAdminRoleAndUserSummary(): array
    {
        return [
            $this->stat('users', 'Total User', User::query()->count(), 'Seluruh akun terdaftar di aplikasi.', 'info'),
            $this->stat('super_admins', 'Super Admin', User::role($this->superAdminRoleName())->count(), 'Akun dengan akses penuh sistem.', 'primary'),
            $this->stat('admins', 'Admin', User::role('admin')->count(), 'Operator workspace admin.', 'success'),
            $this->stat('reviewers', 'Reviewer', User::role('Reviewer')->count(), 'Akun reviewer aktif.', 'info'),
            $this->stat('customers', 'Customer', User::role('customer')->count(), 'Akun pemohon atau klien.', 'warning'),
            $this->stat('roles', 'Role', Role::query()->count(), 'Peran yang tersedia di sistem.', 'primary'),
        ];
    }

    private function superAdminReferenceCompleteness(?GuidelineSet $activeGuideline): array
    {
        $guidelineId = $activeGuideline?->id;

        return [
            'active_guideline' => $activeGuideline ? [
                'id' => $activeGuideline->id,
                'name' => $activeGuideline->name,
                'year' => $activeGuideline->year,
                'updated_at' => $activeGuideline->updated_at?->toIso8601String(),
            ] : null,
            'items' => [
                $this->referenceCompletenessItem('construction_cost_indices', 'IKK', $guidelineId ? ConstructionCostIndex::query()->where('guideline_set_id', $guidelineId)->count() : 0, $guidelineId ? ConstructionCostIndex::query()->where('guideline_set_id', $guidelineId)->max('updated_at') : null, route('admin.ref-guidelines.construction-cost-indices.index'), 'Indeks kemahalan konstruksi per kabupaten atau kota.'),
                $this->referenceCompletenessItem('cost_elements', 'Biaya Unit Terpasang', $guidelineId ? CostElement::query()->where('guideline_set_id', $guidelineId)->count() : 0, $guidelineId ? CostElement::query()->where('guideline_set_id', $guidelineId)->max('updated_at') : null, route('admin.ref-guidelines.cost-elements.index'), 'Elemen biaya model BTB workbook.'),
                $this->referenceCompletenessItem('floor_indices', 'Indeks Lantai', $guidelineId ? FloorIndex::query()->where('guideline_set_id', $guidelineId)->count() : 0, $guidelineId ? FloorIndex::query()->where('guideline_set_id', $guidelineId)->max('updated_at') : null, route('admin.ref-guidelines.floor-indices.index'), 'Faktor indeks lantai per kelas bangunan.'),
                $this->referenceCompletenessItem('mappi_rcn', 'MAPPI RCN', $guidelineId ? MappiRcnStandard::query()->where('guideline_set_id', $guidelineId)->count() : 0, $guidelineId ? MappiRcnStandard::query()->where('guideline_set_id', $guidelineId)->max('updated_at') : null, route('admin.ref-guidelines.mappi-rcn-standards.index'), 'Baseline RCN untuk referensi penilaian.'),
                $this->referenceCompletenessItem('building_economic_life', 'Umur Ekonomis Bangunan', $guidelineId ? BuildingEconomicLife::query()->where('guideline_item_id', $guidelineId)->count() : 0, $guidelineId ? BuildingEconomicLife::query()->where('guideline_item_id', $guidelineId)->max('updated_at') : null, route('admin.ref-guidelines.building-economic-lives.index'), 'Parameter depresiasi dan umur manfaat.'),
                $this->referenceCompletenessItem('valuation_settings', 'Pengaturan Valuasi', $guidelineId ? ValuationSetting::query()->where('guideline_set_id', $guidelineId)->count() : 0, $guidelineId ? ValuationSetting::query()->where('guideline_set_id', $guidelineId)->max('updated_at') : null, route('admin.ref-guidelines.valuation-settings.index'), 'Setting global seperti PPN dan konstanta valuasi.'),
            ],
        ];
    }

    private function referenceCompletenessItem(
        string $key,
        string $label,
        int $count,
        mixed $updatedAt,
        string $route,
        string $description,
    ): array {
        return [
            'key' => $key,
            'label' => $label,
            'value' => $count,
            'description' => $description,
            'status_label' => $count > 0 ? 'Siap' : 'Belum terisi',
            'tone' => $count > 0 ? 'success' : 'warning',
            'updated_at' => $updatedAt ? Carbon::parse((string) $updatedAt)->toIso8601String() : null,
            'url' => $route,
        ];
    }

    private function superAdminSensitiveChanges(): array
    {
        return collect()
            ->merge(
                Role::query()
                    ->withCount(['permissions', 'users'])
                    ->latest('updated_at')
                    ->limit(4)
                    ->get()
                    ->map(fn (Role $role) => [
                        'key' => 'role-' . $role->id,
                        'group' => 'Hak Akses',
                        'title' => 'Role ' . $role->name,
                        'description' => $role->permissions_count . ' permission - ' . $role->users_count . ' user',
                        'changed_at' => $role->updated_at?->toIso8601String(),
                        'url' => route('admin.access-control.roles.show', $role),
                    ])
            )
            ->merge(
                User::query()
                    ->with('roles:id,name')
                    ->latest('updated_at')
                    ->limit(4)
                    ->get()
                    ->map(fn (User $user) => [
                        'key' => 'user-' . $user->id,
                        'group' => 'User',
                        'title' => $user->name,
                        'description' => $user->roles->pluck('name')->implode(', ') ?: 'Tanpa role',
                        'changed_at' => $user->updated_at?->toIso8601String(),
                        'url' => route('admin.master-data.users.show', $user),
                    ])
            )
            ->merge(
                GuidelineSet::query()
                    ->latest('updated_at')
                    ->limit(3)
                    ->get()
                    ->map(fn (GuidelineSet $guideline) => [
                        'key' => 'guideline-' . $guideline->id,
                        'group' => 'Pedoman',
                        'title' => $guideline->name,
                        'description' => 'Tahun ' . $guideline->year . ($guideline->is_active ? ' - aktif' : ''),
                        'changed_at' => $guideline->updated_at?->toIso8601String(),
                        'url' => route('admin.ref-guidelines.guideline-sets.index'),
                    ])
            )
            ->merge(
                ValuationSetting::query()
                    ->with('guidelineSet:id,name')
                    ->latest('updated_at')
                    ->limit(3)
                    ->get()
                    ->map(fn (ValuationSetting $setting) => [
                        'key' => 'valuation-setting-' . $setting->id,
                        'group' => 'Valuasi',
                        'title' => $setting->label,
                        'description' => ($setting->guidelineSet?->name ?? 'Pedoman tidak diketahui') . ' - ' . ($setting->value_text ?: (string) $setting->value_number),
                        'changed_at' => $setting->updated_at?->toIso8601String(),
                        'url' => route('admin.ref-guidelines.valuation-settings.index'),
                    ])
            )
            ->filter(fn (array $item) => $item['changed_at'] !== null)
            ->sortByDesc('changed_at')
            ->take(10)
            ->values()
            ->all();
    }

    private function superAdminPermissionOverview(): array
    {
        $sensitivePermissionLabels = [
            SystemNavigation::MANAGE_ADMIN_FINANCE => 'Keuangan',
            SystemNavigation::MANAGE_ADMIN_MASTER_DATA_USERS => 'User Terdaftar',
            SystemNavigation::MANAGE_ADMIN_ACCESS_CONTROL => 'Hak Akses',
            SystemNavigation::MANAGE_ADMIN_CONTENT => 'Konten',
        ];

        $roles = Role::query()
            ->with('permissions:id,name')
            ->withCount('users')
            ->orderByDesc('updated_at')
            ->get();

        $rows = $roles
            ->map(function (Role $role) use ($sensitivePermissionLabels): array {
                $permissionNames = $role->permissions->pluck('name');
                $enabledSections = $permissionNames
                    ->intersect(SystemNavigation::sectionPermissions())
                    ->values();

                $sensitiveAccess = $permissionNames
                    ->filter(fn (string $permission) => array_key_exists($permission, $sensitivePermissionLabels))
                    ->map(fn (string $permission) => $sensitivePermissionLabels[$permission])
                    ->values()
                    ->all();

                return [
                    'id' => $role->id,
                    'role' => $role->name,
                    'users_count' => (int) $role->users_count,
                    'sections_count' => $enabledSections->count(),
                    'sensitive_access' => $sensitiveAccess,
                    'updated_at' => $role->updated_at?->toIso8601String(),
                    'edit_url' => route('admin.access-control.system-menus.edit', $role),
                    'show_url' => route('admin.access-control.roles.show', $role),
                ];
            })
            ->sortByDesc(fn (array $row) => sprintf('%03d-%03d-%s', count($row['sensitive_access']), $row['sections_count'], $row['role']))
            ->take(8)
            ->values()
            ->all();

        return [
            'summary' => [
                'total_roles' => $roles->count(),
                'roles_with_sensitive_access' => collect($rows)
                    ->filter(fn (array $row) => count($row['sensitive_access']) > 0)
                    ->count(),
            ],
            'rows' => $rows,
        ];
    }

    private function superAdminExceptionQueue(): array
    {
        return [
            [
                'key' => 'request_backlog',
                'label' => 'Backlog Permohonan',
                'value' => AppraisalRequest::query()
                    ->whereIn('status', [
                        AppraisalStatusEnum::Submitted,
                        AppraisalStatusEnum::DocsIncomplete,
                        AppraisalStatusEnum::Verified,
                        AppraisalStatusEnum::WaitingOffer,
                        AppraisalStatusEnum::WaitingSignature,
                    ])
                    ->count(),
                'description' => 'Permohonan yang masih menunggu keputusan admin.',
                'tone' => 'warning',
                'url' => route('admin.appraisal-requests.index'),
            ],
            [
                'key' => 'payment_exceptions',
                'label' => 'Pembayaran Bermasalah',
                'value' => Payment::query()->whereIn('status', ['failed', 'expired', 'rejected'])->count(),
                'description' => 'Transaksi gagal, expired, atau ditolak.',
                'tone' => 'warning',
                'url' => route('admin.finance.payments.index'),
            ],
            [
                'key' => 'unread_contact_messages',
                'label' => 'Pesan Belum Ditindak',
                'value' => ContactMessage::query()->whereNull('read_at')->count(),
                'description' => 'Pesan masuk yang belum dibaca atau ditangani.',
                'tone' => 'info',
                'url' => route('admin.communications.contact-messages.index', ['unread' => 'yes']),
            ],
            [
                'key' => 'refunded_payments',
                'label' => 'Pembayaran Refund',
                'value' => Payment::query()->where('status', 'refunded')->count(),
                'description' => 'Transaksi yang sudah masuk status refund.',
                'tone' => 'primary',
                'url' => route('admin.finance.payments.index', ['status' => 'refunded']),
            ],
            [
                'key' => 'unpublished_articles',
                'label' => 'Konten Belum Publish',
                'value' => Article::query()->where('is_published', false)->count(),
                'description' => 'Draft konten yang masih tertahan.',
                'tone' => 'info',
                'url' => route('admin.content.articles.index'),
            ],
        ];
    }

    private function requestNumber(AppraisalRequest $record): string
    {
        return (string) ($record->request_number ?? ('REQ-' . $record->id));
    }

    private function superAdminRoleName(): string
    {
        return (string) config('access-control.super_admin.name', 'super_admin');
    }
}

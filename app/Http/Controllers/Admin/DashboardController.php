<?php

namespace App\Http\Controllers\Admin;

use App\Enums\AppraisalStatusEnum;
use App\Http\Controllers\Controller;
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
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Carbon;
use Inertia\Response;
use Spatie\Permission\Models\Role;

class DashboardController extends Controller
{
    public function entry(): Response|RedirectResponse
    {
        $user = auth()->user();

        if ($user && SystemNavigation::hasSectionAccess($user, SystemNavigation::ACCESS_ADMIN_DASHBOARD)) {
            return $this->dashboard();
        }

        $firstRouteName = SystemNavigation::firstAccessibleRouteName($user, 'admin');

        abort_unless($firstRouteName !== null, 403);

        return redirect()->route($firstRouteName);
    }

    public function dashboard(): Response
    {
        $user = auth()->user();
        $isSuperAdmin = (bool) $user?->hasRole($this->superAdminRoleName());

        $stats = [
            [
                'key' => 'submitted',
                'label' => 'Submitted',
                'value' => AppraisalRequest::query()->where('status', AppraisalStatusEnum::Submitted)->count(),
                'description' => 'Menunggu verifikasi',
                'tone' => 'info',
            ],
            [
                'key' => 'docs_incomplete',
                'label' => 'Dokumen Kurang',
                'value' => AppraisalRequest::query()->where('status', AppraisalStatusEnum::DocsIncomplete)->count(),
                'description' => 'Perlu tindak lanjut',
                'tone' => 'warning',
            ],
            [
                'key' => 'waiting_offer',
                'label' => 'Waiting Offer',
                'value' => AppraisalRequest::query()->where('status', AppraisalStatusEnum::WaitingOffer)->count(),
                'description' => 'Siap diberi penawaran',
                'tone' => 'warning',
            ],
            [
                'key' => 'offer_sent',
                'label' => 'Offer Sent',
                'value' => AppraisalRequest::query()->where('status', AppraisalStatusEnum::OfferSent)->count(),
                'description' => 'Menunggu respons klien',
                'tone' => 'primary',
            ],
            [
                'key' => 'waiting_signature',
                'label' => 'Waiting Signature',
                'value' => AppraisalRequest::query()->where('status', AppraisalStatusEnum::WaitingSignature)->count(),
                'description' => 'Kontrak belum ditandatangani',
                'tone' => 'warning',
            ],
            [
                'key' => 'contract_signed',
                'label' => 'Contract Signed',
                'value' => AppraisalRequest::query()->where('status', AppraisalStatusEnum::ContractSigned)->count(),
                'description' => 'Siap proses valuasi',
                'tone' => 'success',
            ],
            [
                'key' => 'requests_today',
                'label' => 'Permohonan Hari Ini',
                'value' => AppraisalRequest::query()->whereDate('requested_at', now()->toDateString())->count(),
                'description' => 'Permohonan baru',
                'tone' => 'success',
            ],
            [
                'key' => 'assets_today',
                'label' => 'Aset Hari Ini',
                'value' => AppraisalAsset::query()->whereDate('created_at', now()->toDateString())->count(),
                'description' => 'Aset baru diunggah',
                'tone' => 'info',
            ],
        ];

        $actionItems = AppraisalRequest::query()
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
            ->values();

        $paymentQueue = AppraisalRequest::query()
            ->where('status', AppraisalStatusEnum::ContractSigned)
            ->with('user')
            ->latest('updated_at')
            ->limit(8)
            ->get()
            ->map(fn (AppraisalRequest $record) => [
                'id' => $record->id,
                'request_number' => $record->request_number ?? ('REQ-' . $record->id),
                'requester_name' => $record->user?->name ?? '-',
                'fee_total' => (int) ($record->fee_total ?? 0),
                'offer_validity_days' => $record->offer_validity_days,
                'updated_at' => $record->updated_at?->toIso8601String(),
                'show_url' => route('admin.appraisal-requests.show', $record),
            ])
            ->values();

        return inertia('Admin/Dashboard', [
            'stats' => $stats,
            'actionItems' => $actionItems,
            'paymentQueue' => $paymentQueue,
            'isSuperAdmin' => $isSuperAdmin,
            'superAdminWidgets' => $isSuperAdmin ? $this->superAdminWidgets() : null,
        ]);
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
            'request_number' => $record->request_number ?? ('REQ-' . $record->id),
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
            [
                'key' => 'users',
                'label' => 'Total User',
                'value' => User::query()->count(),
                'description' => 'Seluruh akun terdaftar di aplikasi.',
                'tone' => 'info',
            ],
            [
                'key' => 'super_admins',
                'label' => 'Super Admin',
                'value' => User::role($this->superAdminRoleName())->count(),
                'description' => 'Akun dengan akses penuh sistem.',
                'tone' => 'primary',
            ],
            [
                'key' => 'admins',
                'label' => 'Admin',
                'value' => User::role('admin')->count(),
                'description' => 'Operator workspace admin.',
                'tone' => 'success',
            ],
            [
                'key' => 'reviewers',
                'label' => 'Reviewer',
                'value' => User::role('Reviewer')->count(),
                'description' => 'Akun reviewer aktif.',
                'tone' => 'info',
            ],
            [
                'key' => 'customers',
                'label' => 'Customer',
                'value' => User::role('customer')->count(),
                'description' => 'Akun pemohon/klien.',
                'tone' => 'warning',
            ],
            [
                'key' => 'roles',
                'label' => 'Role',
                'value' => Role::query()->count(),
                'description' => 'Peran yang tersedia di sistem.',
                'tone' => 'primary',
            ],
        ];
    }

    private function superAdminReferenceCompleteness(?GuidelineSet $activeGuideline): array
    {
        $guidelineId = $activeGuideline?->id;

        $items = [
            $this->referenceCompletenessItem(
                key: 'construction_cost_indices',
                label: 'IKK',
                count: $guidelineId ? ConstructionCostIndex::query()->where('guideline_set_id', $guidelineId)->count() : 0,
                updatedAt: $guidelineId ? ConstructionCostIndex::query()->where('guideline_set_id', $guidelineId)->max('updated_at') : null,
                route: route('admin.ref-guidelines.construction-cost-indices.index'),
                description: 'Indeks kemahalan konstruksi per kabupaten/kota.'
            ),
            $this->referenceCompletenessItem(
                key: 'cost_elements',
                label: 'Biaya Unit Terpasang',
                count: $guidelineId ? CostElement::query()->where('guideline_set_id', $guidelineId)->count() : 0,
                updatedAt: $guidelineId ? CostElement::query()->where('guideline_set_id', $guidelineId)->max('updated_at') : null,
                route: route('admin.ref-guidelines.cost-elements.index'),
                description: 'Elemen biaya model BTB workbook.'
            ),
            $this->referenceCompletenessItem(
                key: 'floor_indices',
                label: 'Indeks Lantai',
                count: $guidelineId ? FloorIndex::query()->where('guideline_set_id', $guidelineId)->count() : 0,
                updatedAt: $guidelineId ? FloorIndex::query()->where('guideline_set_id', $guidelineId)->max('updated_at') : null,
                route: route('admin.ref-guidelines.floor-indices.index'),
                description: 'Faktor indeks lantai per kelas bangunan.'
            ),
            $this->referenceCompletenessItem(
                key: 'mappi_rcn',
                label: 'MAPPI RCN',
                count: $guidelineId ? MappiRcnStandard::query()->where('guideline_set_id', $guidelineId)->count() : 0,
                updatedAt: $guidelineId ? MappiRcnStandard::query()->where('guideline_set_id', $guidelineId)->max('updated_at') : null,
                route: route('admin.ref-guidelines.mappi-rcn-standards.index'),
                description: 'Baseline RCN untuk referensi penilaian.'
            ),
            $this->referenceCompletenessItem(
                key: 'building_economic_life',
                label: 'Umur Ekonomis Bangunan',
                count: $guidelineId ? BuildingEconomicLife::query()->where('guideline_item_id', $guidelineId)->count() : 0,
                updatedAt: $guidelineId ? BuildingEconomicLife::query()->where('guideline_item_id', $guidelineId)->max('updated_at') : null,
                route: route('admin.ref-guidelines.building-economic-lives.index'),
                description: 'Parameter depresiasi dan umur manfaat.'
            ),
            $this->referenceCompletenessItem(
                key: 'valuation_settings',
                label: 'Pengaturan Valuasi',
                count: $guidelineId ? ValuationSetting::query()->where('guideline_set_id', $guidelineId)->count() : 0,
                updatedAt: $guidelineId ? ValuationSetting::query()->where('guideline_set_id', $guidelineId)->max('updated_at') : null,
                route: route('admin.ref-guidelines.valuation-settings.index'),
                description: 'Setting global seperti PPN dan konstanta valuasi.'
            ),
        ];

        return [
            'active_guideline' => $activeGuideline ? [
                'id' => $activeGuideline->id,
                'name' => $activeGuideline->name,
                'year' => $activeGuideline->year,
                'updated_at' => $activeGuideline->updated_at?->toIso8601String(),
            ] : null,
            'items' => $items,
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
                        'description' => $role->permissions_count . ' permission · ' . $role->users_count . ' user',
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
                        'description' => 'Tahun ' . $guideline->year . ($guideline->is_active ? ' · aktif' : ''),
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
                        'description' => ($setting->guidelineSet?->name ?? 'Pedoman tidak diketahui') . ' · ' . ($setting->value_text ?: (string) $setting->value_number),
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

    private function superAdminRoleName(): string
    {
        return (string) config('access-control.super_admin.name', 'super_admin');
    }
}


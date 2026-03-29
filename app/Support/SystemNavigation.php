<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Arr;

class SystemNavigation
{
    public const ACCESS_REVIEWER_DASHBOARD = 'access_reviewer_dashboard';
    public const MANAGE_REVIEWER_REVIEWS = 'manage_reviewer_reviews';
    public const MANAGE_REVIEWER_COMPARABLES = 'manage_reviewer_comparables';

    public const ACCESS_ADMIN_DASHBOARD = 'access_admin_dashboard';
    public const MANAGE_ADMIN_APPRAISAL_REQUESTS = 'manage_admin_appraisal_requests';
    public const MANAGE_ADMIN_FINANCE = 'manage_admin_finance';
    public const MANAGE_ADMIN_MASTER_DATA = 'manage_admin_master_data';
    public const MANAGE_ADMIN_MASTER_DATA_USERS = 'manage_admin_master_data_users';
    public const MANAGE_ADMIN_REF_GUIDELINES = 'manage_admin_ref_guidelines';
    public const MANAGE_ADMIN_ACCESS_CONTROL = 'manage_admin_access_control';
    public const MANAGE_ADMIN_CONTENT = 'manage_admin_content';
    public const MANAGE_ADMIN_COMMUNICATIONS = 'manage_admin_communications';

    public static function reviewerSectionPermissions(): array
    {
        return [
            self::ACCESS_REVIEWER_DASHBOARD,
            self::MANAGE_REVIEWER_REVIEWS,
            self::MANAGE_REVIEWER_COMPARABLES,
        ];
    }

    public static function adminSectionPermissions(): array
    {
        return [
            self::ACCESS_ADMIN_DASHBOARD,
            self::MANAGE_ADMIN_APPRAISAL_REQUESTS,
            self::MANAGE_ADMIN_FINANCE,
            self::MANAGE_ADMIN_MASTER_DATA,
            self::MANAGE_ADMIN_MASTER_DATA_USERS,
            self::MANAGE_ADMIN_REF_GUIDELINES,
            self::MANAGE_ADMIN_ACCESS_CONTROL,
            self::MANAGE_ADMIN_CONTENT,
            self::MANAGE_ADMIN_COMMUNICATIONS,
        ];
    }

    public static function sectionPermissions(): array
    {
        return [
            ...self::reviewerSectionPermissions(),
            ...self::adminSectionPermissions(),
        ];
    }

    public static function sectionNav(): array
    {
        return [
            [
                'key' => 'reviewer.dashboard',
                'label' => 'Dashboard',
                'icon' => 'LayoutDashboard',
                'surface' => 'reviewer',
                'routeName' => 'reviewer.dashboard',
                'activePatterns' => ['reviewer.dashboard'],
                'pathPrefixes' => ['/reviewer'],
                'requiredPermission' => self::ACCESS_REVIEWER_DASHBOARD,
            ],
            [
                'key' => 'reviewer.reviews',
                'label' => 'Review',
                'icon' => 'ClipboardList',
                'surface' => 'reviewer',
                'routeName' => 'reviewer.reviews.index',
                'activePatterns' => ['reviewer.reviews.*', 'reviewer.assets.*'],
                'pathPrefixes' => ['/reviewer/reviews', '/reviewer/assets'],
                'requiredPermission' => self::MANAGE_REVIEWER_REVIEWS,
            ],
            [
                'key' => 'reviewer.comparables',
                'label' => 'Comparables',
                'icon' => 'Scale',
                'surface' => 'reviewer',
                'routeName' => 'reviewer.comparables.index',
                'activePatterns' => ['reviewer.comparables.*'],
                'pathPrefixes' => ['/reviewer/comparables'],
                'requiredPermission' => self::MANAGE_REVIEWER_COMPARABLES,
            ],
            [
                'key' => 'admin.dashboard',
                'label' => 'Dashboard',
                'icon' => 'LayoutDashboard',
                'surface' => 'admin',
                'routeName' => 'admin.dashboard',
                'activePatterns' => ['admin.dashboard'],
                'exactPaths' => ['/admin'],
                'requiredPermission' => self::ACCESS_ADMIN_DASHBOARD,
            ],
            [
                'key' => 'admin.requests',
                'label' => 'Permohonan',
                'icon' => 'ClipboardList',
                'surface' => 'admin',
                'routeName' => 'admin.appraisal-requests.index',
                'activePatterns' => ['admin.appraisal-requests.*'],
                'pathPrefixes' => ['/admin/permohonan-penilaian'],
                'requiredPermission' => self::MANAGE_ADMIN_APPRAISAL_REQUESTS,
            ],
            [
                'key' => 'admin.finance',
                'label' => 'Keuangan',
                'icon' => 'CreditCard',
                'surface' => 'admin',
                'routeName' => 'admin.finance.payments.index',
                'activePatterns' => ['admin.finance.*'],
                'pathPrefixes' => ['/admin/keuangan'],
                'requiredPermission' => self::MANAGE_ADMIN_FINANCE,
            ],
            [
                'key' => 'reviewer.master-data',
                'label' => 'Master Data',
                'icon' => 'MapPinned',
                'surface' => 'reviewer',
                'routeName' => 'reviewer.master-data.provinces.index',
                'activePatterns' => ['reviewer.master-data.*'],
                'pathPrefixes' => ['/reviewer/master-data'],
                'requiredPermission' => self::MANAGE_ADMIN_MASTER_DATA,
                'subItems' => [
                    [
                        'key' => 'reviewer.master-data.provinces',
                        'label' => 'Provinsi',
                        'icon' => 'Map',
                        'routeName' => 'reviewer.master-data.provinces.index',
                        'activePatterns' => ['reviewer.master-data.provinces.*'],
                        'pathPrefixes' => ['/reviewer/master-data/provinsi'],
                    ],
                    [
                        'key' => 'reviewer.master-data.regencies',
                        'label' => 'Kabupaten/Kota',
                        'icon' => 'Building2',
                        'routeName' => 'reviewer.master-data.regencies.index',
                        'activePatterns' => ['reviewer.master-data.regencies.*'],
                        'pathPrefixes' => ['/reviewer/master-data/kabupaten-kota'],
                    ],
                    [
                        'key' => 'reviewer.master-data.districts',
                        'label' => 'Kecamatan',
                        'icon' => 'MapPinned',
                        'routeName' => 'reviewer.master-data.districts.index',
                        'activePatterns' => ['reviewer.master-data.districts.*'],
                        'pathPrefixes' => ['/reviewer/master-data/kecamatan'],
                    ],
                    [
                        'key' => 'reviewer.master-data.villages',
                        'label' => 'Kelurahan/Desa',
                        'icon' => 'House',
                        'routeName' => 'reviewer.master-data.villages.index',
                        'activePatterns' => ['reviewer.master-data.villages.*'],
                        'pathPrefixes' => ['/reviewer/master-data/kelurahan-desa'],
                    ],
                ],
            ],
            [
                'key' => 'reviewer.ref-guidelines',
                'label' => 'Pedoman Referensi',
                'icon' => 'BookOpen',
                'surface' => 'reviewer',
                'routeName' => 'reviewer.ref-guidelines.guideline-sets.index',
                'activePatterns' => ['reviewer.ref-guidelines.*'],
                'pathPrefixes' => ['/reviewer/ref-guidelines'],
                'requiredPermission' => self::MANAGE_ADMIN_REF_GUIDELINES,
                'subItems' => [
                    [
                        'key' => 'reviewer.ref-guidelines.guideline-sets',
                        'label' => 'Set Pedoman',
                        'icon' => 'BookText',
                        'routeName' => 'reviewer.ref-guidelines.guideline-sets.index',
                        'activePatterns' => ['reviewer.ref-guidelines.guideline-sets.*'],
                        'pathPrefixes' => ['/reviewer/ref-guidelines/guideline-sets'],
                    ],
                    [
                        'key' => 'reviewer.ref-guidelines.construction-cost-indices',
                        'label' => 'Indeks Kemahalan Konstruksi',
                        'icon' => 'Factory',
                        'routeName' => 'reviewer.ref-guidelines.construction-cost-indices.index',
                        'activePatterns' => ['reviewer.ref-guidelines.construction-cost-indices.*'],
                        'pathPrefixes' => ['/reviewer/ref-guidelines/ikk'],
                    ],
                    [
                        'key' => 'reviewer.ref-guidelines.cost-elements',
                        'label' => 'Biaya Unit Terpasang',
                        'icon' => 'Layers3',
                        'routeName' => 'reviewer.ref-guidelines.cost-elements.index',
                        'activePatterns' => ['reviewer.ref-guidelines.cost-elements.*'],
                        'pathPrefixes' => ['/reviewer/ref-guidelines/cost-elements'],
                    ],
                    [
                        'key' => 'reviewer.ref-guidelines.floor-indices',
                        'label' => 'Indeks Lantai',
                        'icon' => 'Building2',
                        'routeName' => 'reviewer.ref-guidelines.floor-indices.index',
                        'activePatterns' => ['reviewer.ref-guidelines.floor-indices.*'],
                        'pathPrefixes' => ['/reviewer/ref-guidelines/floor-indices'],
                    ],
                    [
                        'key' => 'reviewer.ref-guidelines.mappi-rcn-standards',
                        'label' => 'MAPPI RCN',
                        'icon' => 'Ruler',
                        'routeName' => 'reviewer.ref-guidelines.mappi-rcn-standards.index',
                        'activePatterns' => ['reviewer.ref-guidelines.mappi-rcn-standards.*'],
                        'pathPrefixes' => ['/reviewer/ref-guidelines/mappi-rcn-standards'],
                    ],
                    [
                        'key' => 'reviewer.ref-guidelines.building-economic-lives',
                        'label' => 'Umur Ekonomis Bangunan',
                        'icon' => 'BookMarked',
                        'routeName' => 'reviewer.ref-guidelines.building-economic-lives.index',
                        'activePatterns' => ['reviewer.ref-guidelines.building-economic-lives.*'],
                        'pathPrefixes' => ['/reviewer/ref-guidelines/building-economic-lives'],
                    ],
                    [
                        'key' => 'reviewer.ref-guidelines.valuation-settings',
                        'label' => 'Pengaturan Valuasi',
                        'icon' => 'ClipboardList',
                        'routeName' => 'reviewer.ref-guidelines.valuation-settings.index',
                        'activePatterns' => ['reviewer.ref-guidelines.valuation-settings.*'],
                        'pathPrefixes' => ['/reviewer/ref-guidelines/valuation-settings'],
                    ],
                ],
            ],
            [
                'key' => 'admin.master-data',
                'label' => 'Master Data',
                'icon' => 'MapPinned',
                'surface' => 'admin',
                'routeName' => 'admin.master-data.provinces.index',
                'activePatterns' => ['admin.master-data.*'],
                'pathPrefixes' => ['/admin/master-data'],
                'requiredPermission' => self::MANAGE_ADMIN_MASTER_DATA,
                'subItems' => [
                    [
                        'key' => 'admin.master-data.users',
                        'label' => 'User Terdaftar',
                        'icon' => 'Users',
                        'routeName' => 'admin.master-data.users.index',
                        'activePatterns' => ['admin.master-data.users.*'],
                        'pathPrefixes' => ['/admin/master-data/users'],
                        'requiredPermission' => self::MANAGE_ADMIN_MASTER_DATA_USERS,
                    ],
                    [
                        'key' => 'admin.master-data.provinces',
                        'label' => 'Provinsi',
                        'icon' => 'Map',
                        'routeName' => 'admin.master-data.provinces.index',
                        'activePatterns' => ['admin.master-data.provinces.*'],
                        'pathPrefixes' => ['/admin/master-data/provinsi'],
                    ],
                    [
                        'key' => 'admin.master-data.regencies',
                        'label' => 'Kabupaten/Kota',
                        'icon' => 'Building2',
                        'routeName' => 'admin.master-data.regencies.index',
                        'activePatterns' => ['admin.master-data.regencies.*'],
                        'pathPrefixes' => ['/admin/master-data/kabupaten-kota'],
                    ],
                    [
                        'key' => 'admin.master-data.districts',
                        'label' => 'Kecamatan',
                        'icon' => 'MapPinned',
                        'routeName' => 'admin.master-data.districts.index',
                        'activePatterns' => ['admin.master-data.districts.*'],
                        'pathPrefixes' => ['/admin/master-data/kecamatan'],
                    ],
                    [
                        'key' => 'admin.master-data.villages',
                        'label' => 'Kelurahan/Desa',
                        'icon' => 'House',
                        'routeName' => 'admin.master-data.villages.index',
                        'activePatterns' => ['admin.master-data.villages.*'],
                        'pathPrefixes' => ['/admin/master-data/kelurahan-desa'],
                    ],
                ],
            ],
            [
                'key' => 'admin.ref-guidelines',
                'label' => 'Pedoman Referensi',
                'icon' => 'BookOpen',
                'surface' => 'admin',
                'routeName' => 'admin.ref-guidelines.guideline-sets.index',
                'activePatterns' => ['admin.ref-guidelines.*'],
                'pathPrefixes' => ['/admin/ref-guidelines'],
                'requiredPermission' => self::MANAGE_ADMIN_REF_GUIDELINES,
                'subItems' => [
                    [
                        'key' => 'admin.ref-guidelines.guideline-sets',
                        'label' => 'Set Pedoman',
                        'icon' => 'BookText',
                        'routeName' => 'admin.ref-guidelines.guideline-sets.index',
                        'activePatterns' => ['admin.ref-guidelines.guideline-sets.*'],
                        'pathPrefixes' => ['/admin/ref-guidelines/guideline-sets'],
                    ],
                    [
                        'key' => 'admin.ref-guidelines.construction-cost-indices',
                        'label' => 'Indeks Kemahalan Konstruksi',
                        'icon' => 'Factory',
                        'routeName' => 'admin.ref-guidelines.construction-cost-indices.index',
                        'activePatterns' => ['admin.ref-guidelines.construction-cost-indices.*'],
                        'pathPrefixes' => ['/admin/ref-guidelines/ikk'],
                    ],
                    [
                        'key' => 'admin.ref-guidelines.cost-elements',
                        'label' => 'Biaya Unit Terpasang',
                        'icon' => 'Layers3',
                        'routeName' => 'admin.ref-guidelines.cost-elements.index',
                        'activePatterns' => ['admin.ref-guidelines.cost-elements.*'],
                        'pathPrefixes' => ['/admin/ref-guidelines/cost-elements'],
                    ],
                    [
                        'key' => 'admin.ref-guidelines.floor-indices',
                        'label' => 'Indeks Lantai',
                        'icon' => 'Building2',
                        'routeName' => 'admin.ref-guidelines.floor-indices.index',
                        'activePatterns' => ['admin.ref-guidelines.floor-indices.*'],
                        'pathPrefixes' => ['/admin/ref-guidelines/floor-indices'],
                    ],
                    [
                        'key' => 'admin.ref-guidelines.mappi-rcn-standards',
                        'label' => 'MAPPI RCN',
                        'icon' => 'Ruler',
                        'routeName' => 'admin.ref-guidelines.mappi-rcn-standards.index',
                        'activePatterns' => ['admin.ref-guidelines.mappi-rcn-standards.*'],
                        'pathPrefixes' => ['/admin/ref-guidelines/mappi-rcn-standards'],
                    ],
                    [
                        'key' => 'admin.ref-guidelines.building-economic-lives',
                        'label' => 'Umur Ekonomis Bangunan',
                        'icon' => 'BookMarked',
                        'routeName' => 'admin.ref-guidelines.building-economic-lives.index',
                        'activePatterns' => ['admin.ref-guidelines.building-economic-lives.*'],
                        'pathPrefixes' => ['/admin/ref-guidelines/building-economic-lives'],
                    ],
                    [
                        'key' => 'admin.ref-guidelines.valuation-settings',
                        'label' => 'Pengaturan Valuasi',
                        'icon' => 'ClipboardList',
                        'routeName' => 'admin.ref-guidelines.valuation-settings.index',
                        'activePatterns' => ['admin.ref-guidelines.valuation-settings.*'],
                        'pathPrefixes' => ['/admin/ref-guidelines/valuation-settings'],
                    ],
                ],
            ],
            [
                'key' => 'admin.access-control',
                'label' => 'Hak Akses',
                'icon' => 'LockKeyhole',
                'surface' => 'admin',
                'routeName' => 'admin.access-control.roles.index',
                'activePatterns' => ['admin.access-control.*'],
                'pathPrefixes' => ['/admin/hak-akses'],
                'requiredPermission' => self::MANAGE_ADMIN_ACCESS_CONTROL,
            ],
            [
                'key' => 'admin.content',
                'label' => 'Konten',
                'icon' => 'BookOpen',
                'surface' => 'admin',
                'routeName' => 'admin.content.articles.index',
                'activePatterns' => ['admin.content.*', 'admin.content.legal.*'],
                'pathPrefixes' => ['/admin/konten'],
                'requiredPermission' => self::MANAGE_ADMIN_CONTENT,
                'subItems' => [
                    [
                        'key' => 'admin.content.articles',
                        'label' => 'Artikel',
                        'icon' => 'BookText',
                        'routeName' => 'admin.content.articles.index',
                        'activePatterns' => ['admin.content.articles.*'],
                        'pathPrefixes' => ['/admin/konten/artikel'],
                    ],
                    [
                        'key' => 'admin.content.categories',
                        'label' => 'Kategori Artikel',
                        'icon' => 'FolderTree',
                        'routeName' => 'admin.content.categories.index',
                        'activePatterns' => ['admin.content.categories.*'],
                        'pathPrefixes' => ['/admin/konten/kategori-artikel'],
                    ],
                    [
                        'key' => 'admin.content.tags',
                        'label' => 'Tag',
                        'icon' => 'Tags',
                        'routeName' => 'admin.content.tags.index',
                        'activePatterns' => ['admin.content.tags.*'],
                        'pathPrefixes' => ['/admin/konten/tag'],
                    ],
                    [
                        'key' => 'admin.legal.faqs',
                        'label' => 'FAQ',
                        'icon' => 'CircleHelp',
                        'routeName' => 'admin.content.legal.faqs.index',
                        'activePatterns' => ['admin.content.legal.faqs.*'],
                        'pathPrefixes' => ['/admin/konten/legal/faq'],
                    ],
                    [
                        'key' => 'admin.legal.features',
                        'label' => 'Fitur',
                        'icon' => 'Sparkles',
                        'routeName' => 'admin.content.legal.features.index',
                        'activePatterns' => ['admin.content.legal.features.*'],
                        'pathPrefixes' => ['/admin/konten/legal/fitur'],
                    ],
                    [
                        'key' => 'admin.legal.testimonials',
                        'label' => 'Testimoni',
                        'icon' => 'MessageSquareQuote',
                        'routeName' => 'admin.content.legal.testimonials.index',
                        'activePatterns' => ['admin.content.legal.testimonials.*'],
                        'pathPrefixes' => ['/admin/konten/legal/testimoni'],
                    ],
                    [
                        'key' => 'admin.legal.terms',
                        'label' => 'Terms',
                        'icon' => 'ScrollText',
                        'routeName' => 'admin.content.legal.terms.index',
                        'activePatterns' => ['admin.content.legal.terms.*'],
                        'pathPrefixes' => ['/admin/konten/legal/terms'],
                    ],
                    [
                        'key' => 'admin.legal.privacy',
                        'label' => 'Privacy',
                        'icon' => 'ShieldCheck',
                        'routeName' => 'admin.content.legal.privacy.index',
                        'activePatterns' => ['admin.content.legal.privacy.*'],
                        'pathPrefixes' => ['/admin/konten/legal/privacy'],
                    ],
                    [
                        'key' => 'admin.legal.consent',
                        'label' => 'Consent',
                        'icon' => 'FileText',
                        'routeName' => 'admin.content.legal.consent.index',
                        'activePatterns' => ['admin.content.legal.consent.*'],
                        'pathPrefixes' => ['/admin/konten/legal/consent'],
                    ],
                    [
                        'key' => 'admin.legal.user-consents',
                        'label' => 'Audit Consent',
                        'icon' => 'ClipboardCheck',
                        'routeName' => 'admin.content.legal.user-consents.index',
                        'activePatterns' => ['admin.content.legal.user-consents.*'],
                        'pathPrefixes' => ['/admin/konten/legal/persetujuan-pengguna'],
                    ],
                ],
            ],
            [
                'key' => 'admin.communications',
                'label' => 'Komunikasi',
                'icon' => 'Mail',
                'surface' => 'admin',
                'routeName' => 'admin.communications.contact-messages.index',
                'activePatterns' => ['admin.communications.*'],
                'pathPrefixes' => ['/admin/komunikasi'],
                'requiredPermission' => self::MANAGE_ADMIN_COMMUNICATIONS,
            ],
        ];
    }

    public static function permissionRegistry(): array
    {
        return [
            self::ACCESS_REVIEWER_DASHBOARD => [
                'title' => 'Reviewer Workspace',
                'label' => 'Access Reviewer Dashboard',
            ],
            self::MANAGE_REVIEWER_REVIEWS => [
                'title' => 'Reviewer Workspace',
                'label' => 'Manage Reviewer Reviews',
            ],
            self::MANAGE_REVIEWER_COMPARABLES => [
                'title' => 'Reviewer Workspace',
                'label' => 'Manage Reviewer Comparables',
            ],
            self::ACCESS_ADMIN_DASHBOARD => [
                'title' => 'Admin Workspace',
                'label' => 'Access Admin Dashboard',
            ],
            self::MANAGE_ADMIN_APPRAISAL_REQUESTS => [
                'title' => 'Admin Workspace',
                'label' => 'Manage Admin Appraisal Requests',
            ],
            self::MANAGE_ADMIN_FINANCE => [
                'title' => 'Admin Workspace',
                'label' => 'Manage Admin Finance',
            ],
            self::MANAGE_ADMIN_MASTER_DATA => [
                'title' => 'System Menu',
                'label' => 'Manage Master Data',
            ],
            self::MANAGE_ADMIN_MASTER_DATA_USERS => [
                'title' => 'System Menu',
                'label' => 'Manage Registered Users',
            ],
            self::MANAGE_ADMIN_REF_GUIDELINES => [
                'title' => 'System Menu',
                'label' => 'Manage Reference Guidelines',
            ],
            self::MANAGE_ADMIN_ACCESS_CONTROL => [
                'title' => 'Admin Workspace',
                'label' => 'Manage Admin Access Control',
            ],
            self::MANAGE_ADMIN_CONTENT => [
                'title' => 'Admin Workspace',
                'label' => 'Manage Admin Content',
            ],
            self::MANAGE_ADMIN_COMMUNICATIONS => [
                'title' => 'Admin Workspace',
                'label' => 'Manage Admin Communications',
            ],
        ];
    }

    public static function menuManagementSections(): array
    {
        return [
            [
                'key' => 'reviewer-dashboard',
                'surface' => 'reviewer',
                'label' => 'Dashboard Reviewer',
                'description' => 'Akses halaman ringkasan reviewer.',
                'permission' => self::ACCESS_REVIEWER_DASHBOARD,
                'items' => ['Dashboard'],
            ],
            [
                'key' => 'reviewer-reviews',
                'surface' => 'reviewer',
                'label' => 'Review Reviewer',
                'description' => 'Queue review, detail review, aset, adjustment, dan BTB.',
                'permission' => self::MANAGE_REVIEWER_REVIEWS,
                'items' => ['Review', 'Aset', 'Adjustment Tanah', 'BTB'],
            ],
            [
                'key' => 'reviewer-comparables',
                'surface' => 'reviewer',
                'label' => 'Comparables Reviewer',
                'description' => 'Daftar dan detail pembanding reviewer.',
                'permission' => self::MANAGE_REVIEWER_COMPARABLES,
                'items' => ['Comparables'],
            ],
            [
                'key' => 'admin-dashboard',
                'surface' => 'admin',
                'label' => 'Dashboard Admin',
                'description' => 'Akses halaman ringkasan admin.',
                'permission' => self::ACCESS_ADMIN_DASHBOARD,
                'items' => ['Dashboard'],
            ],
            [
                'key' => 'requests',
                'surface' => 'admin',
                'label' => 'Permohonan',
                'description' => 'Workspace pengelolaan permohonan penilaian.',
                'permission' => self::MANAGE_ADMIN_APPRAISAL_REQUESTS,
                'items' => ['Permohonan Penilaian'],
            ],
            [
                'key' => 'finance',
                'surface' => 'admin',
                'label' => 'Keuangan',
                'description' => 'Pembayaran dan rekening kantor.',
                'permission' => self::MANAGE_ADMIN_FINANCE,
                'items' => ['Pembayaran', 'Rekening Kantor'],
            ],
            [
                'key' => 'master-data',
                'surface' => 'shared',
                'label' => 'Master Data',
                'description' => 'Data lokasi untuk provinsi sampai kelurahan/desa.',
                'permission' => self::MANAGE_ADMIN_MASTER_DATA,
                'items' => ['Provinsi', 'Kabupaten/Kota', 'Kecamatan', 'Kelurahan/Desa'],
            ],
            [
                'key' => 'master-data-users',
                'surface' => 'admin',
                'label' => 'Master Data: User Terdaftar',
                'description' => 'Manajemen user terdaftar di area master data.',
                'permission' => self::MANAGE_ADMIN_MASTER_DATA_USERS,
                'items' => ['User Terdaftar'],
            ],
            [
                'key' => 'ref-guidelines',
                'surface' => 'shared',
                'label' => 'Pedoman Referensi',
                'description' => 'Seluruh menu referensi penilaian dan guideline.',
                'permission' => self::MANAGE_ADMIN_REF_GUIDELINES,
                'items' => [
                    'Set Pedoman',
                    'Indeks Kemahalan Konstruksi',
                    'Biaya Unit Terpasang',
                    'Indeks Lantai',
                    'MAPPI RCN',
                    'Umur Ekonomis Bangunan',
                    'Pengaturan Valuasi',
                ],
            ],
            [
                'key' => 'access-control',
                'surface' => 'admin',
                'label' => 'Hak Akses',
                'description' => 'Role, permission, dan konfigurasi akses sistem.',
                'permission' => self::MANAGE_ADMIN_ACCESS_CONTROL,
                'items' => ['Roles', 'Menu Sistem'],
            ],
            [
                'key' => 'content',
                'surface' => 'admin',
                'label' => 'Konten',
                'description' => 'Artikel, legal content, dan audit consent.',
                'permission' => self::MANAGE_ADMIN_CONTENT,
                'items' => ['Artikel', 'Kategori Artikel', 'Tag', 'FAQ', 'Fitur', 'Testimoni', 'Terms', 'Privacy', 'Consent', 'Audit Consent'],
            ],
            [
                'key' => 'communications',
                'surface' => 'admin',
                'label' => 'Komunikasi',
                'description' => 'Contact messages dan workflow komunikasi.',
                'permission' => self::MANAGE_ADMIN_COMMUNICATIONS,
                'items' => ['Contact Messages'],
            ],
        ];
    }

    public static function permissionsForUser(?User $user): array
    {
        if (! $user) {
            return [];
        }

        return array_values(array_filter(
            self::sectionPermissions(),
            fn (string $permission): bool => $user->can($permission)
        ));
    }

    public static function hasSectionAccess(?User $user, string $permission): bool
    {
        return in_array($permission, self::permissionsForUser($user), true);
    }

    public static function hasContextAccess(?User $user, string $context): bool
    {
        return self::navForUser($user, $context) !== [];
    }

    public static function navForUser(?User $user, string $context): array
    {
        return self::filterNav(self::sectionNav(), self::permissionsForUser($user), $context);
    }

    public static function firstAccessibleRouteName(?User $user, string $context): ?string
    {
        $nav = self::navForUser($user, $context);
        foreach ($nav as $item) {
            if (! empty($item['subItems'])) {
                $firstChild = Arr::first($item['subItems']);
                if ($firstChild && ! empty($firstChild['routeName'])) {
                    return $firstChild['routeName'];
                }
            }

            if (! empty($item['routeName'])) {
                return $item['routeName'];
            }
        }

        return null;
    }

    private static function filterNav(array $items, array $permissions, string $context): array
    {
        $filtered = [];

        foreach ($items as $item) {
            $surface = $item['surface'] ?? 'shared';
            if (! in_array($surface, [$context, 'shared'], true)) {
                continue;
            }

            $requiredPermission = $item['requiredPermission'] ?? null;
            if ($requiredPermission && ! in_array($requiredPermission, $permissions, true)) {
                continue;
            }

            if (! empty($item['subItems'])) {
                $item['subItems'] = self::filterNav($item['subItems'], $permissions, $context);

                if ($item['subItems'] === []) {
                    continue;
                }
            }

            $filtered[] = $item;
        }

        return array_values($filtered);
    }
}

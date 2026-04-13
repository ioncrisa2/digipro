<?php

namespace App\Support;

class SystemMenuManagementRegistry
{
    public static function sections(): array
    {
        return [
            [
                'key' => 'reviewer-dashboard',
                'surface' => 'reviewer',
                'label' => 'Dashboard Reviewer',
                'description' => 'Akses halaman ringkasan reviewer.',
                'permission' => SystemNavigation::ACCESS_REVIEWER_DASHBOARD,
                'items' => ['Dashboard'],
            ],
            [
                'key' => 'reviewer-reviews',
                'surface' => 'reviewer',
                'label' => 'Review Reviewer',
                'description' => 'Queue review, detail review, aset, adjustment, dan BTB.',
                'permission' => SystemNavigation::MANAGE_REVIEWER_REVIEWS,
                'items' => ['Review', 'Aset', 'Adjustment Tanah', 'BTB'],
            ],
            [
                'key' => 'reviewer-comparables',
                'surface' => 'reviewer',
                'label' => 'Comparables Reviewer',
                'description' => 'Daftar dan detail pembanding reviewer.',
                'permission' => SystemNavigation::MANAGE_REVIEWER_COMPARABLES,
                'items' => ['Comparables'],
            ],
            [
                'key' => 'admin-dashboard',
                'surface' => 'admin',
                'label' => 'Dashboard Admin',
                'description' => 'Akses halaman ringkasan admin.',
                'permission' => SystemNavigation::ACCESS_ADMIN_DASHBOARD,
                'items' => ['Dashboard'],
            ],
            [
                'key' => 'requests',
                'surface' => 'admin',
                'label' => 'Permohonan',
                'description' => 'Workspace pengelolaan permohonan penilaian.',
                'permission' => SystemNavigation::MANAGE_ADMIN_APPRAISAL_REQUESTS,
                'items' => ['Permohonan Penilaian'],
            ],
            [
                'key' => 'finance',
                'surface' => 'admin',
                'label' => 'Keuangan',
                'description' => 'Monitoring dan audit pembayaran gateway.',
                'permission' => SystemNavigation::MANAGE_ADMIN_FINANCE,
                'items' => ['Pembayaran'],
            ],
            [
                'key' => 'master-data',
                'surface' => 'shared',
                'label' => 'Master Data',
                'description' => 'Data lokasi dan master profile penandatangan report.',
                'permission' => SystemNavigation::MANAGE_ADMIN_MASTER_DATA,
                'items' => ['Penandatangan Report', 'Provinsi', 'Kabupaten/Kota', 'Kecamatan', 'Kelurahan/Desa'],
            ],
            [
                'key' => 'master-data-users',
                'surface' => 'admin',
                'label' => 'Master Data: User Terdaftar',
                'description' => 'Manajemen user terdaftar di area master data.',
                'permission' => SystemNavigation::MANAGE_ADMIN_MASTER_DATA_USERS,
                'items' => ['User Terdaftar'],
            ],
            [
                'key' => 'ref-guidelines',
                'surface' => 'shared',
                'label' => 'Pedoman Referensi',
                'description' => 'Seluruh menu referensi penilaian dan guideline.',
                'permission' => SystemNavigation::MANAGE_ADMIN_REF_GUIDELINES,
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
                'permission' => SystemNavigation::MANAGE_ADMIN_ACCESS_CONTROL,
                'items' => ['Roles', 'Menu Sistem'],
            ],
            [
                'key' => 'content',
                'surface' => 'admin',
                'label' => 'Konten',
                'description' => 'Artikel, legal content, dan audit consent.',
                'permission' => SystemNavigation::MANAGE_ADMIN_CONTENT,
                'items' => ['Artikel', 'Kategori Artikel', 'Tag', 'FAQ', 'Fitur', 'Testimoni', 'Terms', 'Privacy', 'Consent', 'Audit Consent'],
            ],
            [
                'key' => 'communications',
                'surface' => 'admin',
                'label' => 'Komunikasi',
                'description' => 'Contact messages dan workflow komunikasi.',
                'permission' => SystemNavigation::MANAGE_ADMIN_COMMUNICATIONS,
                'items' => ['Contact Messages'],
            ],
            [
                'key' => 'backups',
                'surface' => 'admin',
                'label' => 'Backup',
                'description' => 'Backup dan restore appraisal per request untuk pemulihan data terkontrol.',
                'permission' => SystemNavigation::MANAGE_ADMIN_BACKUPS,
                'items' => ['Backup Request'],
            ],
            [
                'key' => 'activity-logs',
                'surface' => 'admin',
                'label' => 'Activity Log',
                'description' => 'Monitoring aktivitas user untuk kebutuhan audit dan pengawasan super admin.',
                'permission' => SystemNavigation::MANAGE_ADMIN_ACTIVITY_LOGS,
                'items' => ['Activity Log'],
            ],
        ];
    }
}

<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Inertia\Response;

class ModuleController extends Controller
{
    public function moduleShow(string $module): Response
    {
        $definition = $this->moduleDefinitions()[$module] ?? null;

        abort_if($definition === null, 404);

        return inertia('Admin/Modules/Show', [
            'module' => array_merge($definition, [
                'slug' => $module,
                'status_label' => $this->moduleStatusLabel($definition['status']),
            ]),
        ]);
    }

    private function moduleStatusLabel(string $status): string
    {
        return match ($status) {
            'done' => 'Selesai di Vue',
            'in_progress' => 'Sedang dimigrasikan',
            'planned' => 'Belum dimigrasikan',
            'bridge' => 'Butuh jembatan backend',
            default => 'Legacy',
        };
    }

    private function moduleDefinitions(): array
    {
        return [
            'payments' => [
                'title' => 'Keuangan',
                'description' => 'Operasional pembayaran dan rekening kantor sudah sepenuhnya berjalan di admin Vue.',
                'status' => 'done',
                'legacy_resources' => [
                    'PaymentResource',
                    'OfficeBankAccountResource',
                ],
                'dependencies' => [
                    'List/detail pembayaran, edit pembayaran, dan CRUD rekening kantor sudah tersedia di admin Vue.',
                    'Notifikasi pembayaran admin kini memakai database notification Laravel biasa.',
                ],
            ],
            'content' => [
                'title' => 'Konten',
                'description' => 'Migrasi artikel, kategori artikel, dan tag ke halaman Vue dengan editor yang bisa diganti.',
                'status' => 'done',
                'legacy_resources' => [
                    'ArticleResource',
                    'ArticleCategoryResource',
                    'TagResource',
                ],
                'dependencies' => [
                    'CMS artikel, kategori artikel, dan tag sudah tersedia di admin Vue.',
                    'Editor artikel saat ini memakai HTML textarea yang ringan dan mudah diganti.',
                ],
            ],
            'legal-content' => [
                'title' => 'Konten & Legal',
                'description' => 'Dokumen legal, FAQ, feature highlight, testimonial, dan log persetujuan pengguna.',
                'status' => 'done',
                'legacy_resources' => [
                    'ConsentDocumentResource',
                    'TermsDocumentResource',
                    'PrivacyPolicyResource',
                    'FaqResource',
                    'FeatureResource',
                    'TestimonialResource',
                    'AppraisalUserConsentResource',
                ],
                'dependencies' => [
                    'FAQ, feature, testimonial, terms, privacy, consent document, dan audit persetujuan pengguna sudah tersedia di admin Vue.',
                    'Editor legal sekarang memakai HTML textarea/JSON textarea yang ringan dan konsisten.',
                ],
            ],
            'communications' => [
                'title' => 'Komunikasi',
                'description' => 'Inbox pesan kontak dari landing page dan audit tindak lanjutnya.',
                'status' => 'done',
                'legacy_resources' => [
                    'ContactMessageResource',
                ],
                'dependencies' => [
                    'LandingController sudah menyimpan pesan, inbox contact message list/detail/action sudah tersedia di admin Vue.',
                ],
            ],
            'master-data' => [
                'title' => 'Master Data',
                'description' => 'User terdaftar dan daftar nama lokasi yang dipakai lintas flow penilaian.',
                'status' => 'done',
                'legacy_resources' => [
                    'UserResource',
                    'ProvinceResource',
                    'RegencyResource',
                    'DistrictResource',
                    'VillageResource',
                ],
                'dependencies' => [
                    'Daftar nama lokasi untuk provinsi, kabupaten/kota, kecamatan, dan kelurahan/desa sudah tersedia di admin Vue.',
                    'User management untuk list, detail, edit, dan create terbatas super_admin sudah tersedia di admin Vue.',
                    'Delete user tetap tidak diaktifkan agar parity dengan resource legacy.',
                ],
            ],
            'ref-guidelines' => [
                'title' => 'Ref Guidelines',
                'description' => 'Seluruh referensi appraisal, termasuk guideline set, cost element, index, dan page IKK per provinsi.',
                'status' => 'done',
                'legacy_resources' => [
                    'RefGuidelineSetResource',
                    'BuildingEconomicLifeResource',
                    'ConstructionCostIndexResource',
                    'CostElementResource',
                    'FloorIndexResource',
                    'MappiRcnStandardResource',
                    'ValuationSettingResource',
                    'IkkByProvince Page',
                ],
                'dependencies' => [
                    'Seluruh menu pedoman referensi utama sudah tersedia di admin Vue, termasuk bulk editor IKK per provinsi.',
                ],
            ],
            'access-control' => [
                'title' => 'Hak Akses',
                'description' => 'Role management admin kini berjalan penuh dengan backend spatie/permission.',
                'status' => 'done',
                'legacy_resources' => [
                    'RoleResource',
                ],
                'dependencies' => [
                    'User model memakai spatie/permission dan konfigurasi internal untuk super_admin.',
                    'Role management list/detail/create/edit/delete sudah tersedia di admin Vue.',
                    'Policy role tetap memakai permission prefix yang sudah ada seperti view_any_role dan update_role.',
                ],
            ],
        ];
    }
}

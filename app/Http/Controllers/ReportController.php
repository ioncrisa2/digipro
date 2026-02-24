<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

/**
 * Renders report list and detail pages using mock data.
 */
class ReportController extends Controller
{
    private function mockReports(): array
    {
        return [
            [
                'id' => 1,
                'request_number' => 'REQ-2026-0001',
                'client' => 'PT Sinar Maju',
                'report_type' => 'Terinci',
                'status' => 'Laporan Siap',
                'property' => 'Ruko / Rukan',
                'address' => 'Jl. Sudirman No. 12, Jakarta',
                'updated_at' => '2026-02-05',
                'documents' => [
                    [
                        'label' => 'Penawaran',
                        'name' => 'Penawaran-REQ-2026-0001.pdf',
                        'type' => 'penawaran',
                        'size' => '512 KB',
                    ],
                    [
                        'label' => 'Laporan Penilaian',
                        'name' => 'Laporan-REQ-2026-0001.pdf',
                        'type' => 'laporan',
                        'size' => '2.4 MB',
                    ],
                    [
                        'label' => 'Invoice Pembayaran',
                        'name' => 'Invoice-REQ-2026-0001.pdf',
                        'type' => 'invoice',
                        'size' => '178 KB',
                    ],
                ],
            ],
            [
                'id' => 2,
                'request_number' => 'REQ-2026-0002',
                'client' => 'Dwiksi',
                'report_type' => 'Ringkas',
                'status' => 'Menunggu Pembayaran',
                'property' => 'Rumah Tinggal',
                'address' => 'Jl. Kol. H. Burlian KM7 No 258, Palembang',
                'updated_at' => '2026-02-04',
                'documents' => [
                    [
                        'label' => 'Penawaran',
                        'name' => 'Penawaran-REQ-2026-0002.pdf',
                        'type' => 'penawaran',
                        'size' => '430 KB',
                    ],
                    [
                        'label' => 'Laporan Penilaian',
                        'name' => 'Laporan-REQ-2026-0002.pdf',
                        'type' => 'laporan',
                        'size' => '1.9 MB',
                    ],
                    [
                        'label' => 'Invoice Pembayaran',
                        'name' => 'Invoice-REQ-2026-0002.pdf',
                        'type' => 'invoice',
                        'size' => '156 KB',
                    ],
                ],
            ],
            [
                'id' => 3,
                'request_number' => 'REQ-2026-0003',
                'client' => 'CV Arta Jaya',
                'report_type' => 'Terinci',
                'status' => 'Laporan Siap',
                'property' => 'Gudang / Pabrik',
                'address' => 'Jl. Industri Raya No. 88, Bandung',
                'updated_at' => '2026-02-03',
                'documents' => [
                    [
                        'label' => 'Penawaran',
                        'name' => 'Penawaran-REQ-2026-0003.pdf',
                        'type' => 'penawaran',
                        'size' => '488 KB',
                    ],
                    [
                        'label' => 'Laporan Penilaian',
                        'name' => 'Laporan-REQ-2026-0003.pdf',
                        'type' => 'laporan',
                        'size' => '3.1 MB',
                    ],
                    [
                        'label' => 'Invoice Pembayaran',
                        'name' => 'Invoice-REQ-2026-0003.pdf',
                        'type' => 'invoice',
                        'size' => '201 KB',
                    ],
                ],
            ],
        ];
    }

    public function index(Request $request)
    {
        $items = $this->mockReports();

        return inertia('Reports/Index', [
            'reports' => $items,
        ]);
    }

    public function show(Request $request, int $id)
    {
        $items = $this->mockReports();
        $report = collect($items)->firstWhere('id', $id);

        if (! $report) {
            abort(404);
        }

        return inertia('Reports/Show', [
            'report' => $report,
        ]);
    }
}

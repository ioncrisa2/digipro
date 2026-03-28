<?php

namespace Database\Seeders;

use App\Models\GuidelineSet;
use App\Services\Reviewer\BtbWorkbookImportService;
use Illuminate\Database\Seeder;

class BtbWorkbook2025ReferenceSeeder extends Seeder
{
    public function __construct(
        private readonly BtbWorkbookImportService $importService,
    ) {
    }

    public function run(): void
    {
        $path = (string) env('BTB_WORKBOOK_2025_PATH', '');
        $guidelineSetId = env('BTB_GUIDELINE_SET_ID');
        $year = env('BTB_GUIDELINE_YEAR', 2025);
        $baseRegion = (string) env('BTB_BASE_REGION', 'DKI Jakarta');

        if ($path === '' || ! is_numeric($guidelineSetId)) {
            $this->command?->warn('Seeder BTB workbook 2025 dilewati. Set BTB_WORKBOOK_2025_PATH dan BTB_GUIDELINE_SET_ID untuk mengaktifkan.');

            return;
        }

        $guidelineSet = GuidelineSet::query()->find((int) $guidelineSetId);

        if (! $guidelineSet) {
            $this->command?->warn("Seeder BTB workbook 2025 dilewati. Guideline set {$guidelineSetId} tidak ditemukan.");

            return;
        }

        $result = $this->importService->import(
            path: $path,
            guidelineSet: $guidelineSet,
            year: (int) $year,
            baseRegion: $baseRegion,
        );

        $this->command?->info(sprintf(
            'Seeder BTB workbook 2025 selesai: created=%d updated=%d deleted=%d unchanged=%d',
            $result['totals']['created'],
            $result['totals']['updated'],
            $result['totals']['deleted'],
            $result['totals']['unchanged'],
        ));
    }
}

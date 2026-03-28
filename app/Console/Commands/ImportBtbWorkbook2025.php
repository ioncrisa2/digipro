<?php

namespace App\Console\Commands;

use App\Models\GuidelineSet;
use App\Services\Reviewer\BtbWorkbookImportService;
use Illuminate\Console\Command;

class ImportBtbWorkbook2025 extends Command
{
    protected $signature = 'reviewer:import-btb-workbook-2025
        {path : Path workbook BTB 2025}
        {guideline_set_id : ID guideline set target}
        {--year= : Override valuation year}
        {--base-region=DKI Jakarta : Base region cost elements}
        {--dry-run : Parse workbook tanpa menulis ke database}';

    protected $description = 'Import cost element BTB 2025 dari workbook non-interaktif ke referensi reviewer';

    public function __construct(
        private readonly BtbWorkbookImportService $importService,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $guidelineSet = GuidelineSet::query()->find($this->argument('guideline_set_id'));

        if (! $guidelineSet) {
            $this->error('Guideline set tidak ditemukan.');

            return self::FAILURE;
        }

        $result = $this->importService->import(
            path: (string) $this->argument('path'),
            guidelineSet: $guidelineSet,
            year: $this->option('year') !== null ? (int) $this->option('year') : null,
            baseRegion: (string) $this->option('base-region'),
            dryRun: (bool) $this->option('dry-run'),
        );

        $this->info(($result['dry_run'] ? '[DRY RUN] ' : '') . 'Import workbook BTB selesai.');
        $this->line("Guideline: {$guidelineSet->name} ({$result['year']})");
        $this->line("Base region: {$result['base_region']}");

        $this->table(
            ['Template', 'Sheet', 'Rows', 'Sheet1', 'Created', 'Updated', 'Deleted', 'Unchanged'],
            collect($result['templates'])->map(fn (array $row): array => [
                $row['template_key'],
                $row['sheet_name'],
                $row['imported_rows'],
                $row['sheet_summary_rows'],
                $row['created'],
                $row['updated'],
                $row['deleted'],
                $row['unchanged'],
            ])->all()
        );

        $this->line(sprintf(
            'Totals: created=%d updated=%d deleted=%d unchanged=%d',
            $result['totals']['created'],
            $result['totals']['updated'],
            $result['totals']['deleted'],
            $result['totals']['unchanged'],
        ));

        return self::SUCCESS;
    }
}

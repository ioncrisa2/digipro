<?php

use App\Models\CostElement;
use App\Models\GuidelineSet;
use App\Services\Reviewer\BtbWorkbookImportService;
use App\Services\Reviewer\BtbWorkbookTemplateParser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

uses(RefreshDatabase::class);

function makeSyntheticBtbWorkbook(string $path): void
{
    $spreadsheet = new Spreadsheet();

    $sheet1 = $spreadsheet->getActiveSheet();
    $sheet1->setTitle('Sheet1');
    $sheet1->setCellValue('A2', 'Spesifikasi Rumah Menengah');
    $sheet1->setCellValue('A3', 'Pondasi');
    $sheet1->setCellValue('B3', 'Foot Plate');
    $sheet1->setCellValue('A4', 'Struktur');
    $sheet1->setCellValue('B4', 'Beton Bertulang');

    $butPrint = $spreadsheet->createSheet();
    $butPrint->setTitle('BUT_Print');
    $butPrint->setCellValue('C2', 123456);
    $butPrint->setCellValue('D2', 654321);

    $sheetNames = [
        'R. Mewah',
        'R. Menengah',
        'R.Sederhana',
        'Semi Permanen',
        'Gudang',
        'Low Rise Building',
    ];

    foreach ($sheetNames as $sheetName) {
        $sheet = $spreadsheet->createSheet();
        $sheet->setTitle($sheetName);
        $sheet->setCellValue('A15', 'A. BIAYA LANGSUNG');
        $sheet->setCellValue('A16', 'B. BIAYA TAK LANGSUNG');
    }

    $templateSheet = $spreadsheet->getSheetByName('R. Menengah');
    $templateSheet->setCellValue('A15', 'A. BIAYA LANGSUNG');
    $templateSheet->setCellValue('A16', 'Pondasi');
    $templateSheet->setCellValue('B16', 'Foot Plate');
    $templateSheet->setCellValue('C16', "='BUT_Print'!C2");
    $templateSheet->setCellValue('D16', 0.2);
    $templateSheet->setCellValue('A17', 'Struktur');
    $templateSheet->setCellValue('B17', 'Beton Bertulang');
    $templateSheet->setCellValue('C17', "='BUT_Print'!D2");
    $templateSheet->setCellValue('D17', 0.4);
    $templateSheet->setCellValue('A18', 'B. BIAYA TAK LANGSUNG');

    (new Xlsx($spreadsheet))->save($path);
}

it('parses workbook rows with source-sheet traceability and workbook summary references', function () {
    $path = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'btb-synthetic-parser.xlsx';
    makeSyntheticBtbWorkbook($path);

    $parsed = app(BtbWorkbookTemplateParser::class)->parse($path);

    expect(data_get($parsed, 'sheet_summary.rumah_menengah.title'))->toBe('Spesifikasi Rumah Menengah');
    expect(data_get($parsed, 'sheet_summary.rumah_menengah.rows.0.element'))->toBe('Pondasi');
    expect(data_get($parsed, 'templates.rumah_menengah.rows'))->toHaveCount(2);
    expect(data_get($parsed, 'templates.rumah_menengah.rows.0.group'))->toBe('PONDASI');
    expect(data_get($parsed, 'templates.rumah_menengah.rows.0.unit_cost'))->toBe(123456);
    expect(data_get($parsed, 'templates.rumah_menengah.rows.0.spec_json.default_volume_percent'))->toBe(0.2);
    expect(data_get($parsed, 'templates.rumah_menengah.rows.0.spec_json.source_sheet'))->toBe('BUT_Print');
    expect(data_get($parsed, 'templates.rumah_menengah.rows.0.spec_json.source_cell'))->toBe('C2');
    expect(data_get($parsed, 'templates.rumah_menengah.rows.1.spec_json.source_cell'))->toBe('D2');

    @unlink($path);
});

it('imports parsed workbook rows into cost elements and deletes stale template rows', function () {
    $path = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'btb-synthetic-import.xlsx';
    makeSyntheticBtbWorkbook($path);

    $guideline = GuidelineSet::create([
        'name' => 'Synthetic Workbook',
        'year' => 2025,
        'is_active' => true,
    ]);

    CostElement::create([
        'guideline_set_id' => $guideline->id,
        'year' => 2025,
        'base_region' => 'DKI Jakarta',
        'group' => 'PONDASI',
        'element_code' => 'BTB-RUMAH_MENENGAH-099',
        'element_name' => 'Stale Row',
        'building_type' => 'BANGUNAN_RUMAH_TINGGAL',
        'building_class' => 'MENENGAH',
        'storey_pattern' => '2 Lantai',
        'unit' => 'm2',
        'unit_cost' => 1,
        'spec_json' => ['material_spec' => 'Stale'],
    ]);

    $result = app(BtbWorkbookImportService::class)->import($path, $guideline);

    expect($result['totals']['created'])->toBe(2);
    expect($result['totals']['deleted'])->toBe(1);
    expect(CostElement::query()
        ->where('guideline_set_id', $guideline->id)
        ->where('year', 2025)
        ->count())->toBe(2);

    $row = CostElement::query()
        ->where('guideline_set_id', $guideline->id)
        ->where('element_name', 'Pondasi')
        ->first();

    expect($row)->not->toBeNull();
    expect($row->unit_cost)->toBe(123456);
    expect(data_get($row->spec_json, 'default_volume_percent'))->toBe(0.2);
    expect(data_get($row->spec_json, 'source_sheet'))->toBe('BUT_Print');
    expect(data_get($row->spec_json, 'source_cell'))->toBe('C2');

    @unlink($path);
});

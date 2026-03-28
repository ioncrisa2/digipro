<?php

namespace App\Services\Reviewer;

use App\Support\ReviewerBtbCatalog;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\Cell\Cell;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use RuntimeException;
use Throwable;

class BtbWorkbookTemplateParser
{
    public function parse(string $path): array
    {
        if (! is_file($path)) {
            throw new RuntimeException("Workbook BTB tidak ditemukan: {$path}");
        }

        $spreadsheet = IOFactory::load($path);
        $sheetSummary = $this->parseSheetSummary($spreadsheet->getSheetByName('Sheet1'));
        $templates = [];

        foreach (ReviewerBtbCatalog::templates() as $templateKey => $template) {
            $sheetName = $template['sheet_name'] ?? null;

            if (! is_string($sheetName) || $sheetName === '') {
                continue;
            }

            $worksheet = $spreadsheet->getSheetByName($sheetName);

            if (! $worksheet instanceof Worksheet) {
                throw new RuntimeException("Sheet template BTB tidak ditemukan: {$sheetName}");
            }

            $templates[$templateKey] = [
                'template_key' => $templateKey,
                'sheet_name' => $sheetName,
                'building_type' => $template['mappi_building_type'] ?? null,
                'building_class' => $template['mappi_building_class'] ?? null,
                'storey_pattern' => $template['storey_pattern'] ?? null,
                'rows' => $this->parseTemplateRows($templateKey, $worksheet),
                'sheet_summary_rows' => $sheetSummary[$templateKey]['rows'] ?? [],
            ];
        }

        return [
            'sheet_summary' => $sheetSummary,
            'templates' => $templates,
        ];
    }

    private function parseSheetSummary(?Worksheet $worksheet): array
    {
        if (! $worksheet instanceof Worksheet) {
            return [];
        }

        $summary = [];
        $startColumns = [1, 4, 7, 10, 13, 16];
        $highestRow = $worksheet->getHighestDataRow();

        foreach ($startColumns as $column) {
            $title = $this->stringValue($worksheet, $column, 2);
            $templateKey = $this->sheetSummaryTitleToTemplateKey($title);

            if ($templateKey === null) {
                continue;
            }

            $rows = [];

            for ($row = 3; $row <= $highestRow; $row++) {
                $element = $this->stringValue($worksheet, $column, $row);
                $material = $this->stringValue($worksheet, $column + 1, $row);

                if ($element === null && $material === null) {
                    continue;
                }

                $rows[] = [
                    'element' => $element,
                    'material_spec' => $material,
                ];
            }

            $summary[$templateKey] = [
                'title' => $title,
                'rows' => $rows,
            ];
        }

        return $summary;
    }

    private function parseTemplateRows(string $templateKey, Worksheet $worksheet): array
    {
        $rows = [];
        $currentGroup = null;
        $highestRow = $worksheet->getHighestDataRow();

        for ($row = 15; $row <= $highestRow; $row++) {
            $groupLabel = $this->normalizeGroupLabel($this->stringValue($worksheet, 1, $row));

            if ($groupLabel !== null) {
                $groupKey = Str::upper($groupLabel);

                if (Str::startsWith($groupKey, ['TOTAL BIAYA LANGSUNG', 'B. BIAYA TAK LANGSUNG', 'DEPRESIASI'])) {
                    break;
                }

                if (! Str::startsWith($groupKey, 'A. BIAYA LANGSUNG')) {
                    $currentGroup = $groupLabel;
                }
            }

            $materialSpec = $this->stringValue($worksheet, 2, $row);

            if ($materialSpec === null || $currentGroup === null) {
                continue;
            }

            $unitCost = $this->numericValue($worksheet->getCell($this->columnIndexToName(3) . $row));
            $defaultVolumePercent = $this->numericValue($worksheet->getCell($this->columnIndexToName(4) . $row));

            if ($this->shouldSkipTemplateRow($materialSpec, $unitCost, $defaultVolumePercent)) {
                continue;
            }

            [$sourceSheet, $sourceCell] = $this->extractSourceReference($worksheet->getCell($this->columnIndexToName(3) . $row), $worksheet->getTitle());

            $rows[] = [
                'group' => Str::upper($currentGroup),
                'element_code' => $this->elementCode($templateKey, $row),
                'element_name' => $currentGroup,
                'unit' => 'm2',
                'unit_cost' => (int) round($unitCost),
                'spec_json' => [
                    'template_key' => $templateKey,
                    'material_spec' => $materialSpec,
                    'line_order' => $row,
                    'default_volume_percent' => $defaultVolumePercent,
                    'source_sheet' => $sourceSheet,
                    'source_cell' => $sourceCell,
                ],
            ];
        }

        return $rows;
    }

    private function shouldSkipTemplateRow(string $materialSpec, float $unitCost, float $defaultVolumePercent): bool
    {
        $normalizedMaterial = Str::upper(trim($materialSpec));

        if ($normalizedMaterial === '' || $normalizedMaterial === '-') {
            return $unitCost <= 0 && $defaultVolumePercent <= 0;
        }

        return false;
    }

    private function extractSourceReference(Cell $cell, string $fallbackSheet): array
    {
        $value = $cell->getValue();

        if (is_string($value) && str_starts_with($value, '=')) {
            if (preg_match("/^='?([^']+)'?!([A-Z]{1,3}\\d{1,4})$/", $value, $matches)) {
                return [$matches[1], $matches[2]];
            }

            if (preg_match('/^=([A-Za-z_][A-Za-z0-9_ ]*)!([A-Z]{1,3}\d{1,4})$/', $value, $matches)) {
                return [trim($matches[1]), $matches[2]];
            }

            if (preg_match('/^=([A-Z]{1,3}\d{1,4})$/', $value, $matches)) {
                return [$fallbackSheet, $matches[1]];
            }
        }

        return [$fallbackSheet, $cell->getCoordinate()];
    }

    private function numericValue(Cell $cell): float
    {
        $value = $cell->getValue();

        if (is_numeric($value)) {
            return (float) $value;
        }

        if (! is_string($value) || ! str_starts_with($value, '=')) {
            return 0.0;
        }

        try {
            $calculated = $cell->getCalculatedValue();

            return is_numeric($calculated) ? (float) $calculated : 0.0;
        } catch (Throwable) {
            $cached = $cell->getOldCalculatedValue();

            if (is_numeric($cached)) {
                return (float) $cached;
            }

            if (preg_match("/^='?([^']+)'?!([A-Z]{1,3}\\d{1,4})$/", $value, $matches)) {
                return $this->numericValue($cell->getWorksheet()->getParent()->getSheetByName($matches[1])->getCell($matches[2]));
            }

            if (preg_match('/^=([A-Za-z_][A-Za-z0-9_ ]*)!([A-Z]{1,3}\d{1,4})$/', $value, $matches)) {
                return $this->numericValue($cell->getWorksheet()->getParent()->getSheetByName(trim($matches[1]))->getCell($matches[2]));
            }

            if (preg_match('/^=([A-Z]{1,3}\d{1,4})$/', $value, $matches)) {
                return $this->numericValue($cell->getWorksheet()->getCell($matches[1]));
            }
        }

        return 0.0;
    }

    private function normalizeGroupLabel(?string $label): ?string
    {
        if ($label === null) {
            return null;
        }

        $normalized = preg_replace('/\s+/', ' ', trim($label)) ?? trim($label);

        return $normalized === '' ? null : $normalized;
    }

    private function stringValue(Worksheet $worksheet, int $column, int $row): ?string
    {
        $value = $worksheet->getCell($this->columnIndexToName($column) . $row)->getValue();

        if ($value === null) {
            return null;
        }

        $text = trim((string) $value);

        return $text === '' ? null : $text;
    }

    private function sheetSummaryTitleToTemplateKey(?string $title): ?string
    {
        $normalized = Str::lower(trim((string) $title));

        return match (true) {
            Str::contains($normalized, 'rumah mewah') => 'rumah_mewah',
            Str::contains($normalized, 'rumah menengah') => 'rumah_menengah',
            Str::contains($normalized, 'rumah sederhana') => 'rumah_sederhana',
            Str::contains($normalized, 'semi permanen') => 'semi_permanen',
            Str::contains($normalized, 'gudang') => 'gudang',
            Str::contains($normalized, 'ruko') => 'low_rise_building',
            default => null,
        };
    }

    private function elementCode(string $templateKey, int $row): string
    {
        return sprintf('BTB-%s-%03d', Str::upper(str_replace('-', '_', Str::snake($templateKey))), $row);
    }

    private function columnIndexToName(int $index): string
    {
        $name = '';

        while ($index > 0) {
            $index--;
            $name = chr(65 + ($index % 26)) . $name;
            $index = intdiv($index, 26);
        }

        return $name;
    }
}

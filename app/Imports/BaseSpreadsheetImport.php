<?php

namespace App\Imports;

abstract class BaseSpreadsheetImport
{
    public int $inserted = 0;
    public int $updated = 0;
    public int $skipped = 0;
    public int $processed = 0;

    protected function normalizeNullableString(mixed $value): ?string
    {
        $text = trim((string) $value);

        return $text === '' ? null : $text;
    }

    protected function parseDecimal(mixed $value): float
    {
        $text = trim((string) $value);
        $text = preg_replace('/[^\d\.,-]/', '', $text) ?? '';

        if (str_contains($text, ',') && ! str_contains($text, '.')) {
            $text = str_replace(',', '.', $text);
        } else {
            $text = str_replace(',', '', $text);
        }

        return (float) $text;
    }

    protected function parseIntegerCurrency(mixed $value): int
    {
        return (int) round($this->parseDecimal($value));
    }
}

<?php

namespace App\Support;

final class ReviewerValueCaster
{
    public static function nonEmptyString(mixed $value): ?string
    {
        $text = trim((string) $value);

        return $text === '' ? null : $text;
    }

    public static function nullableFloat(mixed $value): ?float
    {
        return is_numeric($value) ? (float) $value : null;
    }

    public static function nullableInt(mixed $value): ?int
    {
        return is_numeric($value) ? (int) $value : null;
    }

    public static function percentInputFromState(mixed $value): float
    {
        return is_numeric($value) ? round((float) $value * 100, 4) : 0.0;
    }
}

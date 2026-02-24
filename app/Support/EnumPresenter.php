<?php

namespace App\Support;

use Illuminate\Support\Str;

trait EnumPresenter
{
    protected function asEnum(string $enumClass, mixed $value): mixed
    {
        if ($value === null) return null;

        if ($value instanceof $enumClass) {
            return $value;
        }

        if ($value instanceof \BackedEnum) {
            return $enumClass::tryFrom($value->value);
        }

        if (is_string($value)) {
            return $enumClass::tryFrom($value);
        }

        return null;
    }

    protected function enumValue(mixed $value): ?string
    {
        if ($value instanceof \BackedEnum) return $value->value;
        if (is_string($value)) return $value;
        return null;
    }

    protected function headlineOrDash(?string $value): string
    {
        if (! $value) return '-';
        return Str::headline(str_replace(['-', '.'], ' ', $value));
    }
}

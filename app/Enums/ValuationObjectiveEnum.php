<?php

namespace App\Enums;

use App\Traits\EnumTraits;

enum ValuationObjectiveEnum: string
{
    use EnumTraits;

    case KajianNilaiPasarRange = 'kajian_nilai_pasar_dalam_bentuk_range';

    public function label(): string
    {
        return match ($this) {
            self::KajianNilaiPasarRange => 'Kajian Nilai Pasar dalam Bentuk Range',
        };
    }
}

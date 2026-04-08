<?php

namespace App\Enums;

enum WithholdingTaxTypeEnum: string
{
    case PPh23 = 'pph23';

    public function label(): string
    {
        return match ($this) {
            self::PPh23 => 'PPh 23',
        };
    }
}

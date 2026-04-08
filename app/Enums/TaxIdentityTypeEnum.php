<?php

namespace App\Enums;

enum TaxIdentityTypeEnum: string
{
    case NPWP = 'npwp';
    case NIK = 'nik';

    public function label(): string
    {
        return match ($this) {
            self::NPWP => 'NPWP',
            self::NIK => 'NIK',
        };
    }
}

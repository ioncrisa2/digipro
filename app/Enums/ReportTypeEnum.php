<?php

namespace App\Enums;

use App\Traits\EnumTraits;

enum ReportTypeEnum: string
{
    use EnumTraits;

    case Terinci  = 'terinci';
    case Ringkas  = 'singkat';

    public function label(): string
    {
        return match($this){
            self::Terinci => 'Terinci',
            self::Ringkas => 'Ringkas',
        };
    }

}

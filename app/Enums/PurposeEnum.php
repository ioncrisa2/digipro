<?php

namespace App\Enums;

use App\Traits\EnumTraits;

enum PurposeEnum: string
{
    use EnumTraits;

    case JualBeli           = 'jual_beli';
    case PenjaminanUtang    = 'penjaminan_utang';
    case Lelang             = 'lelang';

    public function label(): string
    {
        return match($this){
            self::JualBeli => 'Jual Beli',
            self::PenjaminanUtang => 'Penjaminan Utang',
            self::Lelang => 'Lelang'
        };
    }

}

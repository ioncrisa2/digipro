<?php

namespace App\Enums;

use App\Traits\EnumTraits;

enum AssetTypeEnum: string
{
    use EnumTraits;

    case TANAH              = 'tanah';
    case TANAH_BANGUNAN    = 'tanah_bangunan';
    case RUMAH_TINGGAL      = 'rumah_tinggal';
    case RUKO               = 'ruko';
    case APARTEMENT         = 'apartement';
    case KIOS               = 'kios';
    case GUDANG             = 'gudang';
    case KANTOR             = 'kantor';
    case PABRIK             = 'pabrik';
    case TANAH_KEBUN        = 'tanah_kebun';
    case TANAH_DAN_BANGUNAN = 'tanah_dan_bangunan';
    case SAWAH              = 'sawah';

    public function label(): ?string
    {
        return match ($this){
            self::TANAH                 => 'Tanah',
self::TANAH_BANGUNAN        => 'Tanah dan Bangunan',
            self::RUMAH_TINGGAL         => 'Rumah Tinggal',
            self::RUKO                  => 'Ruko',
            self::APARTEMENT            => 'Apartement',
            self::KIOS                  => 'Kios',
            self::GUDANG                => 'Gudang',
            self::KANTOR                => 'Kantor',
            self::PABRIK                => 'Pabrik',
            self::TANAH_KEBUN           => 'Tanah Kebun',
            self::TANAH_DAN_BANGUNAN    => 'Tanah dan Bangunan',
            self::SAWAH                 => 'Sawah',
        };
    }

}

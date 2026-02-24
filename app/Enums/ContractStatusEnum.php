<?php

namespace App\Enums;

use App\Traits\EnumTraits;

enum ContractStatusEnum: string
{
    use EnumTraits;

    case None                   = 'none';
    case Draft                  = 'draft';
    case SentToClient           = 'sent_to_client';
    case WaitingSignature       = 'waiting_signature';
    case ContractSigned         = 'signed';
    case Negotiation            = 'negotiation';
    case Cancelled              = 'cancelled';

    public function label(): string
    {
        return match($this){
            self::None                  => 'Belum Ada',
            self::Draft                 => 'Draft',
            self::SentToClient          => 'Dikirim ke Klien',
            self::WaitingSignature      => 'Menunggu Tanda Tangan',
            self::ContractSigned        => 'Kontrak Ditandatangani',
            self::Negotiation           => 'Negosiasi',
            self::Cancelled             => 'Dibatalkan',
        };
    }
}

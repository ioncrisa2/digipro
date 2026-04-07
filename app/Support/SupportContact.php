<?php

namespace App\Support;

class SupportContact
{
    public static function payload(): array
    {
        return [
            'name' => (string) config('support.name', 'Tim Support DigiPro'),
            'phone' => (string) config('support.phone', ''),
            'whatsapp' => (string) config('support.whatsapp', ''),
            'email' => (string) config('support.email', ''),
            'availability_label' => (string) config('support.availability_label', 'Senin-Jumat 08:00-17:00 WIB'),
        ];
    }
}

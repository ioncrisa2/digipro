<?php

namespace App\Support;

use App\Models\SupportContactSetting;
use Illuminate\Support\Facades\Schema;

class SupportContact
{
    public static function defaults(): array
    {
        return [
            'name' => (string) config('support.name', 'Tim Support DigiPro by KJPP HJAR'),
            'phone' => (string) config('support.phone', ''),
            'whatsapp' => (string) config('support.whatsapp', ''),
            'email' => (string) config('support.email', ''),
            'availability_label' => (string) config('support.availability_label', 'Senin-Jumat 08:00-17:00 WIB'),
        ];
    }

    public static function payload(): array
    {
        $defaults = self::defaults();

        if (! Schema::hasTable('support_contact_settings')) {
            return $defaults;
        }

        $setting = SupportContactSetting::query()->first();

        if (! $setting) {
            return $defaults;
        }

        return [
            'name' => $setting->name ?: $defaults['name'],
            'phone' => $setting->phone ?: $defaults['phone'],
            'whatsapp' => $setting->whatsapp ?: $defaults['whatsapp'],
            'email' => $setting->email ?: $defaults['email'],
            'availability_label' => $setting->availability_label ?: $defaults['availability_label'],
        ];
    }
}

<?php

namespace App\Services\Customer;

use App\Models\District;
use App\Models\Province;
use App\Models\Regency;
use App\Models\User;
use App\Models\Village;

class ReportDeliverySnapshotResolver
{
    public function resolve(?User $user, bool $requiresPhysicalReport): array
    {
        if (! $requiresPhysicalReport || ! $user) {
            return [
                'address' => null,
                'recipient_name' => null,
                'recipient_phone' => null,
            ];
        }

        $addressParts = array_values(array_filter([
            $this->stringOrNull($user->billing_address_detail),
            $this->locationName(Province::class, $user->billing_province_id),
            $this->locationName(Regency::class, $user->billing_regency_id),
            $this->locationName(District::class, $user->billing_district_id),
            $this->locationName(Village::class, $user->billing_village_id),
            $this->stringOrNull($user->billing_postal_code),
        ]));

        $address = ! empty($addressParts)
            ? implode(', ', $addressParts)
            : $this->stringOrNull($user->address);

        return [
            'address' => $address,
            'recipient_name' => $this->stringOrNull($user->billing_recipient_name) ?? $this->stringOrNull($user->name),
            'recipient_phone' => $this->stringOrNull($user->phone_number) ?? $this->stringOrNull($user->whatsapp_number),
        ];
    }

    private function locationName(string $modelClass, ?string $id): ?string
    {
        if (! filled($id)) {
            return null;
        }

        return $modelClass::query()->whereKey($id)->value('name');
    }

    private function stringOrNull(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $trimmed = trim($value);

        return $trimmed !== '' ? $trimmed : null;
    }
}

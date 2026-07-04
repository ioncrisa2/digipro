<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class ProfileResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'email_verified' => $this->hasVerifiedEmail(),
            'phone_number' => $this->phone_number,
            'whatsapp_number' => $this->whatsapp_number,
            'address' => $this->address,
            'company_name' => $this->company_name,
            'avatar_url' => $this->avatar_url
                ? Storage::disk('public')->url($this->avatar_url)
                : null,
            'billing' => [
                'recipient_name' => $this->billing_recipient_name,
                'email' => $this->billing_email,
                'address' => $this->billing_address,
                'address_detail' => $this->billing_address_detail,
                'postal_code' => $this->billing_postal_code,
                'npwp' => $this->billing_npwp,
                'nik' => $this->billing_nik,
                'province' => ['id' => $this->billing_province_id, 'name' => $this->billingProvince?->name],
                'regency' => ['id' => $this->billing_regency_id, 'name' => $this->billingRegency?->name],
                'district' => ['id' => $this->billing_district_id, 'name' => $this->billingDistrict?->name],
                'village' => ['id' => $this->billing_village_id, 'name' => $this->billingVillage?->name],
            ],
            'profile_complete' => filled($this->phone_number)
                && filled($this->billing_recipient_name)
                && filled($this->billing_address_detail),
            'two_factor_enabled' => $this->hasEnabledTwoFactorAuthentication(),
        ];
    }
}

<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MobileDeviceTokenResource extends JsonResource
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
            'platform' => $this->platform,
            'provider' => $this->provider,
            'device_name' => $this->device_name,
            'app_version' => $this->app_version,
            'os_version' => $this->os_version,
            'locale' => $this->locale,
            'last_seen_at' => $this->last_seen_at?->toIso8601String(),
        ];
    }
}

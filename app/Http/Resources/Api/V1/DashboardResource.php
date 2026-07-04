<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DashboardResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'stats' => $this->resource['stats'],
            'featured_request' => $this->resource['featured_request']
                ? AppraisalSummaryResource::make($this->resource['featured_request'])->resolve($request)
                : null,
            'recent_requests' => AppraisalSummaryResource::collection($this->resource['recent_requests'])->resolve($request),
            'actions' => $this->resource['actions'],
            'profile_completion_alert' => $this->resource['profile_completion_alert'],
            'support_contact' => $this->resource['support_contact'],
        ];
    }
}

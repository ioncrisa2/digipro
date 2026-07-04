<?php

namespace App\Http\Resources\Api\V1;

use App\Support\Mobile\AppraisalStatusPresentation;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AppraisalSummaryResource extends JsonResource
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
            'request_number' => $this->request_number ?? "REQ-{$this->id}",
            'status' => AppraisalStatusPresentation::make($this->status),
            'purpose' => $this->purpose?->value,
            'purpose_label' => $this->purpose?->label(),
            'report_type' => $this->report_type?->value,
            'report_type_label' => $this->report_type?->label(),
            'location' => $this->first_asset_address ?: null,
            'assets_count' => (int) ($this->assets_count ?? 0),
            'requested_at' => $this->requested_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}

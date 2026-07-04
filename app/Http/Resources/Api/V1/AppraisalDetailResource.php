<?php

namespace App\Http\Resources\Api\V1;

use App\Enums\AssetTypeEnum;
use App\Support\Mobile\AppraisalStatusPresentation;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AppraisalDetailResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $latestPayment = $this->payments->first();
        $cancellation = $this->latestCancellationRequest;

        return [
            'id' => $this->id,
            'request_number' => $this->request_number ?? "REQ-{$this->id}",
            'status' => AppraisalStatusPresentation::make($this->status),
            'purpose' => [
                'value' => $this->purpose?->value,
                'label' => $this->purpose?->label(),
            ],
            'valuation_objective' => [
                'value' => $this->valuation_objective?->value,
                'label' => $this->valuation_objective?->label(),
            ],
            'report' => [
                'type' => $this->report_type?->value,
                'type_label' => $this->report_type?->label(),
                'format' => $this->report_format,
                'physical_copies_count' => (int) ($this->physical_copies_count ?? 0),
                'generated_at' => $this->report_generated_at?->toIso8601String(),
            ],
            'client' => [
                'name' => $this->client_name,
                'address' => $this->client_address,
                'spk_number' => $this->client_spk_number,
            ],
            'contract' => [
                'number' => $this->contract_number,
                'date' => $this->contract_date?->toDateString(),
                'status' => $this->contract_status?->value,
                'status_label' => $this->contract_status?->label(),
            ],
            'fee_total' => $this->fee_total,
            'notes' => $this->notes,
            'user_request_note' => $this->user_request_note,
            'requested_at' => $this->requested_at?->toIso8601String(),
            'verified_at' => $this->verified_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
            'assets_count' => (int) ($this->assets_count ?? 0),
            'assets' => $this->assets->map(static fn ($asset): array => [
                'id' => $asset->id,
                'asset_code' => $asset->asset_code,
                'type' => $asset->asset_type,
                'type_label' => AssetTypeEnum::tryFrom((string) $asset->asset_type)?->label(),
                'address' => $asset->address,
                'location' => [
                    'province' => ['id' => $asset->province_id, 'name' => $asset->province?->name],
                    'regency' => ['id' => $asset->regency_id, 'name' => $asset->regency?->name],
                    'district' => ['id' => $asset->district_id, 'name' => $asset->district?->name],
                    'village' => ['id' => $asset->village_id, 'name' => $asset->village?->name],
                ],
                'coordinates' => [
                    'latitude' => $asset->coordinates_lat,
                    'longitude' => $asset->coordinates_lng,
                ],
                'land_area' => $asset->land_area,
                'building_area' => $asset->building_area,
                'building_floors' => $asset->building_floors,
                'build_year' => $asset->build_year,
                'renovation_year' => $asset->renovation_year,
                'usage' => $asset->peruntukan,
                'title_document' => $asset->title_document,
            ])->values(),
            'payment' => $latestPayment ? [
                'id' => $latestPayment->id,
                'status' => $latestPayment->status,
                'amount' => $latestPayment->amount,
                'paid_at' => $latestPayment->paid_at?->toIso8601String(),
            ] : null,
            'cancellation_request' => $cancellation ? [
                'status' => $cancellation->review_status,
                'reason' => $cancellation->reason,
                'review_note' => $cancellation->review_note,
                'requested_at' => $cancellation->created_at?->toIso8601String(),
                'reviewed_at' => $cancellation->reviewed_at?->toIso8601String(),
            ] : null,
        ];
    }
}

<?php

namespace App\Http\Resources\Api\V1;

use App\Support\Mobile\AppraisalStatusPresentation;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AppraisalTrackingResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $record = $this->resource['request'];
        $payment = $record->payments->sortByDesc('id')->first();
        $cancellation = $record->latestCancellationRequest;

        return [
            'request' => [
                'id' => $record->id,
                'request_number' => $record->request_number ?? "REQ-{$record->id}",
                'status' => AppraisalStatusPresentation::make($record->status),
                'assets_count' => (int) ($record->assets_count ?? 0),
                'requested_at' => $record->requested_at?->toIso8601String(),
                'verified_at' => $record->verified_at?->toIso8601String(),
                'cancelled_at' => $record->cancelled_at?->toIso8601String(),
            ],
            'timeline' => $this->resource['timeline'],
            'payment' => $payment ? [
                'status' => $payment->status,
                'amount' => $payment->amount,
                'paid_at' => $payment->paid_at?->toIso8601String(),
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

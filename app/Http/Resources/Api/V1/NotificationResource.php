<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class NotificationResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $data = $this->data;

        return [
            'id' => $this->id,
            'type' => class_basename($this->type),
            'title' => data_get($data, 'title', 'Notifikasi'),
            'message' => data_get($data, 'message', ''),
            'read' => $this->read_at !== null,
            'read_at' => $this->read_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            'action' => data_get($data, 'appraisal_id') ? [
                'key' => $this->actionKey($data),
                'resource_type' => 'appraisal',
                'resource_id' => (int) data_get($data, 'appraisal_id'),
            ] : null,
            'context' => collect($data)->only([
                'appraisal_id',
                'revision_batch_id',
                'mode',
                'payment_status',
                'old_status',
                'new_status',
            ])->all(),
        ];
    }

    private function actionKey(array $data): string
    {
        if (isset($data['revision_batch_id'])) {
            return 'submit_revision';
        }

        if (isset($data['mode'])) {
            return $data['mode'] === 'finalized' ? 'sign_contract' : 'review_offer';
        }

        if (isset($data['payment_status'])) {
            return 'view_payment';
        }

        return 'view_appraisal';
    }
}

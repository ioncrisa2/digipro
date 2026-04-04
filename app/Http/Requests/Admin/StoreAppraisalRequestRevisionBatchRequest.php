<?php

namespace App\Http\Requests\Admin;

use App\Models\AppraisalRequest;
use App\Services\Admin\AppraisalRequestRevisionService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreAppraisalRequestRevisionBatchRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'admin_note' => ['nullable', 'string', 'max:2000'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.target_key' => ['required', 'string', 'max:190'],
            'items.*.issue_note' => ['required', 'string', 'max:1000'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $appraisalRequest = $this->route('appraisalRequest');

            if (! $appraisalRequest instanceof AppraisalRequest) {
                return;
            }

            $targetMap = app(AppraisalRequestRevisionService::class)->targetOptionMap($appraisalRequest);
            $seen = [];

            foreach ((array) $this->input('items', []) as $index => $item) {
                $targetKey = (string) data_get($item, 'target_key', '');

                if ($targetKey === '' || ! array_key_exists($targetKey, $targetMap)) {
                    $validator->errors()->add("items.{$index}.target_key", 'Target revisi tidak valid.');
                    continue;
                }

                if (array_key_exists($targetKey, $seen)) {
                    $validator->errors()->add("items.{$index}.target_key", 'Target revisi yang sama tidak boleh dipilih lebih dari satu kali.');
                    continue;
                }

                $seen[$targetKey] = true;
            }
        });
    }

    public function resolvedItems(): array
    {
        $appraisalRequest = $this->route('appraisalRequest');
        $targetMap = $appraisalRequest instanceof AppraisalRequest
            ? app(AppraisalRequestRevisionService::class)->targetOptionMap($appraisalRequest)
            : [];

        return collect((array) $this->input('items', []))
            ->map(function (array $item) use ($targetMap): ?array {
                $targetKey = (string) ($item['target_key'] ?? '');
                $target = $targetMap[$targetKey] ?? null;

                if ($target === null) {
                    return null;
                }

                return [
                    'appraisal_asset_id' => $target['appraisal_asset_id'],
                    'item_type' => $target['item_type'],
                    'requested_file_type' => $target['requested_file_type'],
                    'requested_field_key' => $target['requested_field_key'] ?? null,
                    'original_value' => $target['field']['value'] ?? null,
                    'original_request_file_id' => $target['original_request_file_id'],
                    'original_asset_file_id' => $target['original_asset_file_id'],
                    'issue_note' => trim((string) ($item['issue_note'] ?? '')),
                ];
            })
            ->filter()
            ->values()
            ->all();
    }
}

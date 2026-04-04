<?php

namespace App\Http\Requests\Admin;

use App\Models\AppraisalRequest;
use App\Services\Revisions\AppraisalRevisionFieldRegistry;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreAppraisalFieldCorrectionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'target_key' => ['required', 'string', 'max:190'],
            'value' => ['nullable'],
            'reason' => ['required', 'string', 'max:1000'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $appraisalRequest = $this->route('appraisalRequest');
            if (! $appraisalRequest instanceof AppraisalRequest) {
                return;
            }

            $target = app(AppraisalRevisionFieldRegistry::class)->targetFromKey(
                $appraisalRequest,
                (string) $this->input('target_key')
            );

            if ($target === null) {
                $validator->errors()->add('target_key', 'Field koreksi tidak valid.');
                return;
            }

            try {
                app(AppraisalRevisionFieldRegistry::class)->validateAndNormalize(
                    (string) $target['requested_field_key'],
                    $this->input('value')
                );
            } catch (\Illuminate\Validation\ValidationException $exception) {
                $validator->errors()->add('value', $exception->errors()['value'][0] ?? 'Nilai koreksi tidak valid.');
            }
        });
    }

    public function normalizedValue(): mixed
    {
        /** @var AppraisalRequest|null $appraisalRequest */
        $appraisalRequest = $this->route('appraisalRequest');
        $target = $appraisalRequest instanceof AppraisalRequest
            ? app(AppraisalRevisionFieldRegistry::class)->targetFromKey($appraisalRequest, (string) $this->input('target_key'))
            : null;

        if ($target === null) {
            return $this->input('value');
        }

        return app(AppraisalRevisionFieldRegistry::class)->validateAndNormalize(
            (string) $target['requested_field_key'],
            $this->input('value')
        );
    }
}

<?php

namespace App\Http\Requests\Customer;

use App\Models\AppraisalRequest;
use App\Services\Revisions\AppraisalRequestRevisionSubmissionService;
use App\Services\Revisions\AppraisalRevisionFieldRegistry;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\Validator;

class SubmitAppraisalRevisionBatchRequest extends CustomerFormRequest
{
    public function rules(): array
    {
        return [
            'replacements' => ['nullable', 'array'],
            'replacements.*' => ['nullable', 'file', 'max:15360', 'mimes:pdf,jpg,jpeg,png,webp'],
            'field_values' => ['nullable', 'array'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $appraisalRequest = $this->resolvedAppraisalRequest();
            if (! $appraisalRequest) {
                return;
            }

            $batch = app(AppraisalRequestRevisionSubmissionService::class)->resolveOpenBatch($appraisalRequest);
            if (! $batch) {
                $validator->errors()->add('replacements', 'Tidak ada batch revisi data atau dokumen yang aktif.');
                return;
            }

            $replacements = (array) $this->file('replacements', []);
            $fieldValues = (array) $this->input('field_values', []);
            $fieldRegistry = app(AppraisalRevisionFieldRegistry::class);

            $items = $batch->items
                ->filter(fn ($item) => in_array((string) $item->status, ['pending', 'rejected'], true))
                ->values();

            foreach ($items as $item) {
                if (in_array((string) $item->item_type, ['asset_field', 'request_field'], true)) {
                    $rawValue = $fieldValues[$item->id] ?? null;

                    try {
                        $fieldRegistry->validateAndNormalize((string) ($item->requested_field_key ?: $item->requested_file_type), $rawValue);
                    } catch (\Illuminate\Validation\ValidationException $exception) {
                        $validator->errors()->add(
                            "field_values.{$item->id}",
                            $exception->errors()['value'][0] ?? 'Nilai pengganti wajib diisi untuk item revisi ini.'
                        );
                    }

                    continue;
                }

                $file = $replacements[$item->id] ?? null;
                if (! $file instanceof UploadedFile) {
                    $validator->errors()->add("replacements.{$item->id}", 'File pengganti wajib diunggah untuk item revisi ini.');
                    continue;
                }

                $extension = strtolower((string) $file->getClientOriginalExtension());

                if ($item->item_type === 'asset_photo' && ! in_array($extension, ['jpg', 'jpeg', 'png', 'webp'], true)) {
                    $validator->errors()->add("replacements.{$item->id}", 'Foto aset revisi harus berformat JPG, JPEG, PNG, atau WEBP.');
                }

                if ($item->item_type !== 'asset_photo' && ! in_array($extension, ['pdf', 'jpg', 'jpeg', 'png'], true)) {
                    $validator->errors()->add("replacements.{$item->id}", 'Dokumen revisi harus berformat PDF, JPG, JPEG, atau PNG.');
                }
            }
        });
    }

    public function replacementFiles(): array
    {
        return collect((array) $this->file('replacements', []))
            ->filter(fn ($file) => $file instanceof UploadedFile)
            ->mapWithKeys(fn (UploadedFile $file, $key) => [(int) $key => $file])
            ->all();
    }

    public function fieldValues(): array
    {
        return collect((array) $this->input('field_values', []))
            ->mapWithKeys(fn ($value, $key) => [(int) $key => $value])
            ->all();
    }

    private function resolvedAppraisalRequest(): ?AppraisalRequest
    {
        $routeValue = $this->route('id');

        if ($routeValue instanceof AppraisalRequest) {
            return $routeValue;
        }

        if (! is_numeric($routeValue)) {
            return null;
        }

        return AppraisalRequest::query()->find((int) $routeValue);
    }
}

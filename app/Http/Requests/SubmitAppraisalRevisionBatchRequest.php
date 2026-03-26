<?php

namespace App\Http\Requests;

use App\Models\AppraisalRequest;
use App\Services\AppraisalRequestRevisionSubmissionService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\Validator;

class SubmitAppraisalRevisionBatchRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'replacements' => ['required', 'array', 'min:1'],
            'replacements.*' => ['nullable', 'file', 'max:15360', 'mimes:pdf,jpg,jpeg,png,webp'],
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
                $validator->errors()->add('replacements', 'Tidak ada batch revisi dokumen yang aktif.');
                return;
            }

            $replacements = (array) $this->file('replacements', []);

            $items = $batch->items
                ->filter(fn ($item) => in_array((string) $item->status, ['pending', 'rejected'], true))
                ->values();

            foreach ($items as $item) {
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

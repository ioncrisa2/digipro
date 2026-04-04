<?php

namespace App\Services\Revisions;

use App\Enums\AssetTypeEnum;
use App\Models\AppraisalAsset;
use App\Models\AppraisalRequest;
use App\Support\AppraisalAssetFieldOptions;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Validator;

class AppraisalRevisionFieldRegistry
{
    public function buildTargetOptions(AppraisalRequest $record): array
    {
        $record->loadMissing('assets');

        return $record->assets
            ->sortBy('id')
            ->values()
            ->flatMap(function (AppraisalAsset $asset, int $index): array {
                $assetLabelPrefix = sprintf('[Aset #%d] ', $index + 1);

                return collect($this->definitions())
                    ->map(function (array $definition, string $fieldKey) use ($asset, $assetLabelPrefix): array {
                        $currentValue = $asset->{$fieldKey};

                        return [
                            'key' => "asset_field:{$asset->id}:{$fieldKey}",
                            'item_type' => 'asset_field',
                            'requested_file_type' => $fieldKey,
                            'requested_field_key' => $fieldKey,
                            'appraisal_asset_id' => (int) $asset->id,
                            'original_request_file_id' => null,
                            'original_asset_file_id' => null,
                            'label' => $assetLabelPrefix . $definition['label'],
                            'description' => trim(implode(' • ', array_filter([
                                $this->formatFieldSnapshotDisplay($fieldKey, $currentValue),
                                $asset->address ?: null,
                            ]))),
                            'kind' => 'field',
                            'field' => $this->fieldPayload($fieldKey, $currentValue),
                        ];
                    })
                    ->values()
                    ->all();
            })
            ->values()
            ->all();
    }

    public function targetFromKey(AppraisalRequest $record, string $targetKey): ?array
    {
        if (! preg_match('/^asset_field:(\d+):([A-Za-z0-9_]+)$/', $targetKey, $matches)) {
            return null;
        }

        $assetId = (int) $matches[1];
        $fieldKey = (string) $matches[2];
        $definition = $this->definitions()[$fieldKey] ?? null;

        if ($definition === null) {
            return null;
        }

        $record->loadMissing('assets');
        $asset = $record->assets->firstWhere('id', $assetId);
        if (! $asset instanceof AppraisalAsset) {
            return null;
        }

        return [
            'target_key' => $targetKey,
            'item_type' => 'asset_field',
            'requested_field_key' => $fieldKey,
            'requested_file_type' => $fieldKey,
            'appraisal_asset_id' => $assetId,
            'original_request_file_id' => null,
            'original_asset_file_id' => null,
            'label' => $definition['label'],
            'field' => $this->fieldPayload($fieldKey, $asset->{$fieldKey}),
            'asset' => $asset,
        ];
    }

    public function fieldPayload(string $fieldKey, mixed $value): array
    {
        $definition = $this->definition($fieldKey);

        return [
            'key' => $fieldKey,
            'label' => $definition['label'],
            'input_type' => $definition['input_type'],
            'placeholder' => $definition['placeholder'] ?? null,
            'accept' => null,
            'options' => $definition['options'] ?? [],
            'value' => $value,
            'display' => $this->formatFieldSnapshotDisplay($fieldKey, $value),
        ];
    }

    public function snapshot(string $fieldKey, mixed $value): array
    {
        return [
            'value' => $value,
            'display' => $this->formatFieldSnapshotDisplay($fieldKey, $value),
        ];
    }

    public function formatFieldSnapshotDisplay(string $fieldKey, mixed $value): string
    {
        if ($value === null || $value === '') {
            return 'Belum diisi';
        }

        $definition = $this->definition($fieldKey);

        if (($definition['input_type'] ?? null) === 'select') {
            $map = collect($definition['options'] ?? [])
                ->mapWithKeys(fn (array $option): array => [(string) $option['value'] => (string) $option['label']])
                ->all();

            return $map[(string) $value] ?? Str::headline((string) $value);
        }

        if (in_array($definition['input_type'] ?? null, ['number', 'integer'], true) && is_numeric($value)) {
            return rtrim(rtrim(number_format((float) $value, 2, '.', ','), '0'), '.');
        }

        return (string) $value;
    }

    public function validateAndNormalize(string $fieldKey, mixed $value): mixed
    {
        $definition = $this->definition($fieldKey);
        $normalized = is_string($value) ? trim($value) : $value;
        $normalized = $normalized === '' ? null : $normalized;

        $validator = Validator::make(
            ['value' => $normalized],
            ['value' => $definition['rules']],
            $this->messagesFor($definition['label'])
        );

        if ($validator->fails()) {
            throw ValidationException::withMessages([
                'value' => $validator->errors()->first('value'),
            ]);
        }

        return $normalized;
    }

    public function apply(AppraisalAsset $asset, string $fieldKey, mixed $value): void
    {
        $asset->forceFill([$fieldKey => $value])->save();
    }

    public function definition(string $fieldKey): array
    {
        return $this->definitions()[$fieldKey]
            ?? throw ValidationException::withMessages([
                'field_key' => 'Field revisi tidak didukung.',
            ]);
    }

    private function definitions(): array
    {
        return [
            'title_document' => [
                'label' => 'Jenis Dokumen Tanah',
                'input_type' => 'select',
                'options' => AppraisalAssetFieldOptions::titleDocumentOptions(),
                'rules' => ['required', 'string', Rule::in(array_column(AppraisalAssetFieldOptions::titleDocumentOptions(), 'value'))],
            ],
            'address' => [
                'label' => 'Alamat Lengkap',
                'input_type' => 'textarea',
                'placeholder' => 'Alamat aset',
                'rules' => ['required', 'string', 'max:2000'],
            ],
            'maps_link' => [
                'label' => 'Link Google Maps',
                'input_type' => 'text',
                'placeholder' => 'https://maps.google.com/?q=...',
                'rules' => ['nullable', 'string', 'max:2048'],
            ],
            'coordinates_lat' => [
                'label' => 'Latitude',
                'input_type' => 'number',
                'placeholder' => '-6.200000',
                'rules' => ['nullable', 'numeric', 'between:-90,90'],
            ],
            'coordinates_lng' => [
                'label' => 'Longitude',
                'input_type' => 'number',
                'placeholder' => '106.816666',
                'rules' => ['nullable', 'numeric', 'between:-180,180'],
            ],
            'land_area' => [
                'label' => 'Luas Tanah (m2)',
                'input_type' => 'number',
                'placeholder' => '0',
                'rules' => ['required', 'numeric', 'min:0'],
            ],
            'building_area' => [
                'label' => 'Luas Bangunan (m2)',
                'input_type' => 'number',
                'placeholder' => '0',
                'rules' => ['nullable', 'numeric', 'min:0'],
            ],
            'building_floors' => [
                'label' => 'Jumlah Lantai',
                'input_type' => 'integer',
                'placeholder' => '1',
                'rules' => ['nullable', 'integer', 'min:0', 'max:200'],
            ],
            'build_year' => [
                'label' => 'Tahun Bangun',
                'input_type' => 'integer',
                'placeholder' => now()->format('Y'),
                'rules' => ['nullable', 'integer', 'min:1900', 'max:' . ((int) now()->format('Y') + 1)],
            ],
            'renovation_year' => [
                'label' => 'Tahun Renovasi',
                'input_type' => 'integer',
                'placeholder' => now()->format('Y'),
                'rules' => ['nullable', 'integer', 'min:1900', 'max:' . ((int) now()->format('Y') + 1)],
            ],
        ];
    }

    private function messagesFor(string $label): array
    {
        return [
            'value.required' => "{$label} wajib diisi.",
            'value.string' => "{$label} harus berupa teks.",
            'value.max' => "{$label} terlalu panjang.",
            'value.numeric' => "{$label} harus berupa angka.",
            'value.integer' => "{$label} harus berupa angka bulat.",
            'value.min' => "{$label} tidak valid.",
            'value.between' => "{$label} tidak valid.",
            'value.in' => "{$label} tidak valid.",
        ];
    }
}

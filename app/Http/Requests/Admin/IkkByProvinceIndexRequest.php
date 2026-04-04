<?php

namespace App\Http\Requests\Admin;

use App\Models\GuidelineSet;

class IkkByProvinceIndexRequest extends AdminFormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return (bool) ($user?->hasAdminAccess() || $user?->isReviewer());
    }

    public function rules(): array
    {
        return [
            'guideline_set_id' => ['nullable', 'string', 'max:20'],
            'year' => ['nullable', 'string', 'max:10'],
            'province_id' => ['nullable', 'string', 'max:10'],
        ];
    }

    public function filters(): array
    {
        $activeGuideline = GuidelineSet::query()->where('is_active', true)->first();

        return [
            'guideline_set_id' => (string) ($this->query('guideline_set_id', $activeGuideline?->id ?? '')),
            'year' => (string) ($this->query('year', $activeGuideline?->year ?? now()->format('Y'))),
            'province_id' => (string) $this->query('province_id', ''),
        ];
    }

    public function guidelineSetId(): ?int
    {
        $value = $this->filters()['guideline_set_id'];

        return is_numeric($value) ? (int) $value : null;
    }

    public function yearValue(): ?int
    {
        $value = $this->filters()['year'];

        return is_numeric($value) ? (int) $value : null;
    }

    public function provinceId(): ?string
    {
        $value = trim($this->filters()['province_id']);

        return $value === '' ? null : $value;
    }
}

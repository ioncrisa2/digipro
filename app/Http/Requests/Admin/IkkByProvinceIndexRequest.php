<?php

namespace App\Http\Requests\Admin;

use App\Models\GuidelineSet;

class IkkByProvinceIndexRequest extends AdminOrReviewerFormRequest
{
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
            'guideline_set_id' => $this->queryStringFilter('guideline_set_id', (string) ($activeGuideline?->id ?? '')),
            'year' => $this->queryStringFilter('year', (string) ($activeGuideline?->year ?? now()->format('Y'))),
            'province_id' => $this->queryStringFilter('province_id'),
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

<?php

namespace App\Http\Requests\Reviewer;

class SignBulkPublicAppraiserContractsRequest extends ReviewerFormRequest
{
    public function rules(): array
    {
        return [
            'keyla_token' => ['required', 'string', 'min:6', 'max:64', 'regex:/^[A-Za-z0-9]+$/'],
            'appraisal_request_ids' => ['required', 'array', 'min:1', 'max:20'],
            'appraisal_request_ids.*' => ['integer', 'distinct'],
        ];
    }

    /**
     * @return array<int, int>
     */
    public function appraisalRequestIds(): array
    {
        return collect($this->validated('appraisal_request_ids', []))
            ->filter(fn ($id) => is_numeric($id))
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();
    }
}

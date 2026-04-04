<?php

namespace App\Http\Requests\Reviewer;

class ReviewIndexRequest extends ReviewerFormRequest
{
    public function rules(): array
    {
        return [
            'q' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', 'string', 'max:50'],
        ];
    }

    public function filters(): array
    {
        return [
            'q' => trim((string) $this->string('q')->toString()),
            'status' => (string) $this->string('status', 'all'),
        ];
    }
}

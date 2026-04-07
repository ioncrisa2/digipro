<?php

namespace App\Http\Requests\Admin;

class ApproveAppraisalRequestCancellationRequest extends AdminFormRequest
{
    public function rules(): array
    {
        return [
            'review_note' => ['nullable', 'string', 'max:2000'],
        ];
    }
}

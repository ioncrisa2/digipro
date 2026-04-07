<?php

namespace App\Http\Requests\Admin;

class RejectAppraisalRequestCancellationRequest extends AdminFormRequest
{
    public function rules(): array
    {
        return [
            'review_note' => ['required', 'string', 'max:2000'],
        ];
    }
}

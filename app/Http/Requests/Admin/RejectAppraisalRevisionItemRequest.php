<?php

namespace App\Http\Requests\Admin;

class RejectAppraisalRevisionItemRequest extends AdminFormRequest
{
    public function rules(): array
    {
        return [
            'review_note' => ['required', 'string', 'max:1000'],
        ];
    }
}

<?php

namespace App\Http\Requests\Admin;

class ReorderFaqRequest extends AdminFormRequest
{
    public function rules(): array
    {
        return [
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['required', 'integer', 'distinct', 'exists:faqs,id'],
        ];
    }
}

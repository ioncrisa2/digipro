<?php

namespace App\Http\Requests\Admin;

class ReorderArticleCategoryRequest extends AdminFormRequest
{
    public function rules(): array
    {
        return [
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['required', 'integer', 'distinct', 'exists:article_categories,id'],
        ];
    }
}

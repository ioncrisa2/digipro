<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreArticleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasAdminAccess() ?? false;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'slug' => (string) $this->input('slug'),
            'is_published' => $this->boolean('is_published'),
            'published_at' => blank($this->input('published_at')) ? null : $this->input('published_at'),
            'category_id' => blank($this->input('category_id')) || $this->input('category_id') === '__none'
                ? null
                : $this->input('category_id'),
            'tag_ids' => array_values(array_filter((array) $this->input('tag_ids', []))),
        ]);
    }

    public function rules(): array
    {
        $recordId = $this->route('article')?->id;

        return [
            'title' => ['required', 'string', 'max:255'],
            'slug' => [
                'required',
                'string',
                'max:200',
                Rule::unique('articles', 'slug')->ignore($recordId),
            ],
            'cover_image' => ['nullable', 'image', 'max:4096'],
            'excerpt' => ['nullable', 'string'],
            'content_html' => ['required', 'string'],
            'meta_title' => ['nullable', 'string', 'max:150'],
            'meta_description' => ['nullable', 'string', 'max:255'],
            'category_id' => ['nullable', 'exists:article_categories,id'],
            'tag_ids' => ['nullable', 'array'],
            'tag_ids.*' => ['integer', 'exists:tags,id'],
            'is_published' => ['required', 'boolean'],
            'published_at' => ['nullable', 'date'],
        ];
    }
}

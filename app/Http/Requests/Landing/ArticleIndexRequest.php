<?php

namespace App\Http\Requests\Landing;

use Illuminate\Foundation\Http\FormRequest;

class ArticleIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'category' => ['nullable', 'string', 'max:100'],
            'q' => ['nullable', 'string', 'max:100'],
            'scope' => ['nullable', 'string', 'max:20'],
        ];
    }

    public function filters(): array
    {
        $scope = $this->string('scope')->trim()->lower()->toString() ?: 'article';
        $allowedScopes = ['article', 'category', 'tag'];

        if (! in_array($scope, $allowedScopes, true)) {
            $scope = 'article';
        }

        return [
            'category' => $this->string('category')->trim()->toString(),
            'q' => $this->string('q')->trim()->toString(),
            'scope' => $scope,
        ];
    }

    public function appendableFilters(): array
    {
        return array_filter($this->filters(), static fn ($value) => $value !== '');
    }
}

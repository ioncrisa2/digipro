<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

abstract class AdminFormRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->hasAdminAccess();
    }

    protected function resolvePerPage(int $default = 10, int $max = 100): int
    {
        $value = (int) $this->query('per_page', $default);

        if ($value <= 0) {
            return $default;
        }

        return min($value, $max);
    }

    public function perPage(): int
    {
        return $this->resolvePerPage();
    }

    protected function queryStringFilter(string $key, string $default = ''): string
    {
        return trim((string) $this->query($key, $default));
    }

    protected function filtersFromQuery(array $defaults, ?array $keys = null, bool $withPerPage = true): array
    {
        $source = [];

        foreach ($defaults as $key => $default) {
            $source[$key] = $this->queryStringFilter($key, (string) $default);
        }

        $filters = [];

        foreach ($keys ?? array_keys($defaults) as $key) {
            $filters[$key] = $source[$key] ?? '';
        }

        if ($withPerPage) {
            $filters['per_page'] = (string) $this->perPage();
        }

        return $filters;
    }
}

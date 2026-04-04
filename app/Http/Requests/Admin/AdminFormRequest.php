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
}

<?php

namespace App\Http\Requests\Reviewer;

use Illuminate\Foundation\Http\FormRequest;

abstract class ReviewerFormRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->isReviewer();
    }

    public function perPage(int $default = 10, int $max = 100): int
    {
        $value = (int) $this->query('per_page', $default);

        if ($value <= 0) {
            return $default;
        }

        return min($value, $max);
    }
}

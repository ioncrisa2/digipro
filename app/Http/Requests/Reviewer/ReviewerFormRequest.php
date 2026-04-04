<?php

namespace App\Http\Requests\Reviewer;

use Illuminate\Foundation\Http\FormRequest;

abstract class ReviewerFormRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->isReviewer();
    }
}

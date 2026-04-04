<?php

namespace App\Http\Requests\Admin;

abstract class AdminOrReviewerFormRequest extends AdminFormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return (bool) ($user?->hasAdminAccess() || $user?->isReviewer());
    }
}

<?php

namespace App\Http\Requests\Customer;

use Illuminate\Foundation\Http\FormRequest;

abstract class CustomerFormRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return (bool) ($user && ! $user->isReviewer() && ! $user->hasAdminNavigationAccess());
    }
}

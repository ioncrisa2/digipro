<?php

namespace App\Services\Customer;

use App\Models\User;
use Illuminate\Http\Request;
use Spatie\Permission\Models\Role;

class AppraisalRequestSubmitterResolver
{
    public function resolve(Request $request): ?User
    {
        $guardName = config('auth.defaults.guard', 'web');
        $requestUser = $request->user();
        $customerRoleName = 'Customer';
        $customerRoleExists = Role::query()
            ->where('name', $customerRoleName)
            ->where('guard_name', $guardName)
            ->exists();

        if (! $requestUser || ! $customerRoleExists) {
            return $requestUser;
        }

        return User::query()
            ->whereKey($requestUser->getKey())
            ->role($customerRoleName, $guardName)
            ->first() ?? $requestUser;
    }
}

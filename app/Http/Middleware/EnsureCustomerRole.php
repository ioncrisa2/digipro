<?php

namespace App\Http\Middleware;

use App\Support\SystemNavigation;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureCustomerRole
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user) {
            return redirect()->route('login');
        }

        if ($user->isReviewer()) {
            return redirect()->route(SystemNavigation::firstAccessibleRouteName($user, 'reviewer') ?? 'reviewer.dashboard');
        }

        if ($user->hasAdminNavigationAccess()) {
            return redirect()->route('admin.dashboard');
        }

        return $next($request);
    }
}

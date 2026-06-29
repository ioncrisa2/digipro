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
            if ($request->is('api/*') || $request->expectsJson()) {
                abort(401);
            }

            return redirect()->route('login');
        }

        if (($request->is('api/*') || $request->expectsJson()) && ! $user->hasRole('customer')) {
            abort(403);
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

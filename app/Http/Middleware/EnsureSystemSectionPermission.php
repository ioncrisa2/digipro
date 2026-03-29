<?php

namespace App\Http\Middleware;

use App\Support\SystemNavigation;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureSystemSectionPermission
{
    public function handle(Request $request, Closure $next, string $permission): Response
    {
        $user = $request->user();

        if (! $user) {
            return redirect()->route('login');
        }

        if (! SystemNavigation::hasSectionAccess($user, $permission)) {
            abort(403);
        }

        return $next($request);
    }
}

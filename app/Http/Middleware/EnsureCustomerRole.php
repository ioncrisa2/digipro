<?php

namespace App\Http\Middleware;

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

        if ($user->hasAdminAccess()) {
            return redirect()->route('admin.dashboard');
        }

        if ($user->isReviewer()) {
            return redirect()->route('reviewer.dashboard');
        }

        return $next($request);
    }
}

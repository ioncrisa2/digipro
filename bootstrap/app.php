<?php

use Illuminate\Foundation\Application;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\EnsureAdminRole;
use App\Http\Middleware\EnsureCustomerRole;
use App\Http\Middleware\EnsureNotReviewerRole;
use App\Http\Middleware\EnsureReviewerRole;
use App\Http\Middleware\EnsureSystemSectionPermission;
use Illuminate\Http\Request;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'admin.role' => EnsureAdminRole::class,
            'customer.role' => EnsureCustomerRole::class,
            'not.reviewer' => EnsureNotReviewerRole::class,
            'reviewer.role' => EnsureReviewerRole::class,
            'system.section' => EnsureSystemSectionPermission::class,
        ]);

        $middleware->validateCsrfTokens(except: [
            'payments/midtrans/notification',
        ]);

        $middleware->web(append: [
            HandleInertiaRequests::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->respond(function (Response $response, \Throwable $exception, Request $request) {
            if ($request->expectsJson()) {
                return $response;
            }

            $status = $response->getStatusCode();
            $handledStatuses = [403, 404, 419, 429, 500, 503, 505];

            if (! in_array($status, $handledStatuses, true)) {
                return $response;
            }

            if (app()->environment('local') && in_array($status, [500, 503, 505], true)) {
                return $response;
            }

            return Inertia::render('Errors/Status', [
                'status' => $status,
            ])->toResponse($request)->setStatusCode($status);
        });
    })->create();

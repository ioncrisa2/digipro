<?php

use Illuminate\Foundation\Application;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\EnsureAdminRole;
use App\Http\Middleware\EnsureCustomerRole;
use App\Http\Middleware\EnsureNotReviewerRole;
use App\Http\Middleware\EnsureReviewerRole;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

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
        ]);

        $middleware->validateCsrfTokens(except: [
            'payments/midtrans/notification',
        ]);

        $middleware->web(append: [
            HandleInertiaRequests::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();

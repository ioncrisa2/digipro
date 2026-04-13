<?php

namespace App\Http\Middleware;

use App\Models\User;
use App\Services\ActivityLogs\ActivityLogRecorder;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RecordUserActivity
{
    public function __construct(
        private readonly ActivityLogRecorder $recorder,
    ) {
    }

    public function handle(Request $request, Closure $next): Response
    {
        $actor = $request->user();
        $response = $next($request);

        $this->recorder->record(
            $request,
            $response,
            $actor instanceof User ? $actor : ($request->user() instanceof User ? $request->user() : null),
        );

        return $response;
    }
}

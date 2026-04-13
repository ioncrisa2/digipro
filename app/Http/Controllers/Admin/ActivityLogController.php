<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ActivityLogIndexRequest;
use App\Models\ActivityLog;
use App\Services\Admin\AdminActivityLogWorkspaceService;
use Inertia\Response;

class ActivityLogController extends Controller
{
    public function __construct(
        private readonly AdminActivityLogWorkspaceService $workspaceService,
    ) {
    }

    public function index(ActivityLogIndexRequest $request): Response
    {
        return inertia('Admin/ActivityLogs/Index', $this->workspaceService
            ->indexPayload($request->filters(), $request->perPage()));
    }

    public function show(ActivityLog $activityLog): Response
    {
        return inertia('Admin/ActivityLogs/Show', $this->workspaceService->showPayload($activityLog));
    }
}

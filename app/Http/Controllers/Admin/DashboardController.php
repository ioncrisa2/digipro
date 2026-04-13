<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Admin\AdminDashboardViewService;
use App\Support\SystemNavigation;
use Illuminate\Http\RedirectResponse;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __construct(
        private readonly AdminDashboardViewService $dashboardViewService,
    ) {
    }

    public function entry(): Response|RedirectResponse
    {
        $user = auth()->user();

        if ($user && SystemNavigation::hasSectionAccess($user, SystemNavigation::ACCESS_ADMIN_DASHBOARD)) {
            return $this->dashboard();
        }

        $firstRouteName = SystemNavigation::firstAccessibleRouteName($user, 'admin');

        abort_unless($firstRouteName !== null, 403);

        return redirect()->route($firstRouteName);
    }

    public function dashboard(): Response
    {
        return inertia('Admin/Dashboard', $this->dashboardViewService->build(auth()->user()));
    }
}

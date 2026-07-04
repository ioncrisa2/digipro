<?php

namespace App\Http\Controllers\Api\V1\Customer;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Customer\MobileNotificationIndexRequest;
use App\Http\Resources\Api\V1\NotificationResource;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class MobileNotificationController extends Controller
{
    public function __invoke(MobileNotificationIndexRequest $request): AnonymousResourceCollection
    {
        $notifications = $request->user()
            ->notifications()
            ->latest()
            ->paginate((int) $request->validated('per_page', 20));

        return NotificationResource::collection($notifications)
            ->additional([
                'unread_count' => $request->user()->unreadNotifications()->count(),
            ]);
    }
}

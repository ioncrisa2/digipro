<?php

namespace App\Http\Controllers\Api\V1\Customer;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Customer\MobileNotificationIndexRequest;
use App\Http\Resources\Api\V1\NotificationResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class MobileNotificationController extends Controller
{
    public function index(MobileNotificationIndexRequest $request): AnonymousResourceCollection
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

    public function read(Request $request, string $notification): NotificationResource
    {
        $record = $request->user()->notifications()->findOrFail($notification);
        $record->markAsRead();

        return NotificationResource::make($record->refresh())->additional([
            'unread_count' => $request->user()->unreadNotifications()->count(),
        ]);
    }

    public function readAll(Request $request): JsonResponse
    {
        $updated = $request->user()->unreadNotifications()->update(['read_at' => now()]);

        return response()->json([
            'data' => [
                'updated_count' => $updated,
                'unread_count' => 0,
            ],
        ]);
    }
}

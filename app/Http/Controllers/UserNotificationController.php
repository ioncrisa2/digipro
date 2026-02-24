<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

/**
 * Marks user notifications as read.
 */
class UserNotificationController extends Controller
{
    public function read(Request $request, string $id)
    {
        $notif = $request->user()->notifications()->findOrFail($id);
        $notif->markAsRead();

        return back();
    }

    public function readAll(Request $request)
    {
        $request->user()->unreadNotifications()->update(['read_at' => now()]);

        return back();
    }
}

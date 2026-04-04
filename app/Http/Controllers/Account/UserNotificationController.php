<?php

namespace App\Http\Controllers\Account;

use App\Http\Controllers\Controller;
use App\Http\Requests\Account\AccountAccessRequest;

/**
 * Marks user notifications as read.
 */
class UserNotificationController extends Controller
{
    public function read(AccountAccessRequest $request, string $id)
    {
        $notif = $request->user()->notifications()->findOrFail($id);
        $notif->markAsRead();

        return back();
    }

    public function readAll(AccountAccessRequest $request)
    {
        $request->user()->unreadNotifications()->update(['read_at' => now()]);

        return back();
    }
}

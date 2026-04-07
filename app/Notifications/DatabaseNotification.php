<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

abstract class DatabaseNotification extends Notification
{
    use Queueable;

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toDatabase(object $notifiable): array
    {
        return $this->databasePayload($notifiable);
    }

    abstract protected function databasePayload(object $notifiable): array;
}

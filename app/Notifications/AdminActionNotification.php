<?php

namespace App\Notifications;

class AdminActionNotification extends DatabaseNotification
{
    public function __construct(
        public string $title,
        public string $message,
        public ?string $url = null,
        public string $actionLabel = 'Lihat',
        public ?string $icon = null,
    ) {
    }

    protected function databasePayload(object $notifiable): array
    {
        return [
            'title' => $this->title,
            'message' => $this->message,
            'url' => $this->url,
            'action_label' => $this->actionLabel,
            'icon' => $this->icon,
        ];
    }
}

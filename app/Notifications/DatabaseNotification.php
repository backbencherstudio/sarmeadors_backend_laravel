<?php

namespace App\Notifications;

use Illuminate\Notifications\Notification;

class DatabaseNotification extends Notification
{
    /**
     * @param  array<string, mixed>  $meta
     */
    public function __construct(
        public string $type,
        public string $title,
        public string $body,
        public ?string $actionUrl = null,
        public array $meta = [],
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function databaseType(object $notifiable): string
    {
        return $this->type;
    }

    /**
     * @return array<string, mixed>
     */
    public function toDatabase(object $notifiable): array
    {
        return [
            'type' => $this->type,
            'title' => $this->title,
            'body' => $this->body,
            'action_url' => $this->actionUrl,
            'meta' => $this->meta,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return $this->toDatabase($notifiable);
    }
}

<?php

namespace App\Notifications;

use Illuminate\Notifications\Notification;

/** A plain in-app message with an optional relative link (stored in the database only). */
class SimpleNotification extends Notification
{
    public function __construct(public string $message, public ?string $url = null) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return ['message' => $this->message, 'url' => $this->url];
    }
}

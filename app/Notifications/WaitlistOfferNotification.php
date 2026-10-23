<?php

namespace App\Notifications;

use App\Models\WaitlistEntry;
use Illuminate\Notifications\Notification;

class WaitlistOfferNotification extends Notification
{
    public function __construct(public WaitlistEntry $entry, public string $listingTitle) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'message' => "A place opened up at {$this->listingTitle} ({$this->entry->timeSlot->starts_at->format('D j M, H:i')}). It’s held for you for 30 minutes.",
            'url' => route('waitlist.claim', $this->entry, false),
        ];
    }
}

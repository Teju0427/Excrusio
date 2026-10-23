<?php

namespace App\Notifications;

use App\Models\Booking;
use Illuminate\Notifications\Notification;

/** Stored in the database and shown in each person's dashboard (no email/SMS cost in the demo). */
class BookingNotification extends Notification
{
    public function __construct(public Booking $booking, public string $message) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'message' => $this->message,
            'reference' => $this->booking->reference,
            'url' => route('bookings.show', $this->booking->reference, false),
        ];
    }
}

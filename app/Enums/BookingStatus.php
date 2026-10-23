<?php

namespace App\Enums;

/**
 * Booking lifecycle. The allowed transitions are the single source of truth:
 * BookingService refuses any change that is not listed here.
 */
enum BookingStatus: string
{
    case AwaitingPayment = 'awaiting_payment';
    case Pending = 'pending';        // waiting for the hotel/provider to approve
    case Confirmed = 'confirmed';
    case Rejected = 'rejected';
    case Cancelled = 'cancelled';
    case Expired = 'expired';        // hold or approval window ran out
    case Completed = 'completed';

    /** @return list<self> */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::AwaitingPayment => [self::Confirmed, self::Pending, self::Expired, self::Cancelled],
            self::Pending => [self::Confirmed, self::Rejected, self::Cancelled, self::Expired],
            self::Confirmed => [self::Cancelled, self::Completed],
            self::Rejected, self::Cancelled, self::Expired, self::Completed => [],
        };
    }

    public function canTransitionTo(self $next): bool
    {
        return in_array($next, $this->allowedTransitions(), true);
    }

    /** Statuses that occupy rooms / seats. AwaitingPayment counts only while its hold is valid. */
    public static function occupying(): array
    {
        return [self::Pending->value, self::Confirmed->value, self::Completed->value];
    }

    public function label(): string
    {
        return match ($this) {
            self::AwaitingPayment => 'Awaiting payment',
            self::Pending => 'Awaiting host approval',
            self::Confirmed => 'Confirmed',
            self::Rejected => 'Declined',
            self::Cancelled => 'Cancelled',
            self::Expired => 'Expired',
            self::Completed => 'Completed',
        };
    }

    /** CSS modifier for the status badge component. */
    public function tone(): string
    {
        return match ($this) {
            self::Confirmed, self::Completed => 'success',
            self::Pending, self::AwaitingPayment => 'warning',
            self::Rejected, self::Cancelled, self::Expired => 'muted',
        };
    }
}

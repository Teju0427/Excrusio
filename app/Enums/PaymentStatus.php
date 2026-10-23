<?php

namespace App\Enums;

/**
 * Demo payment states. No real money is ever taken.
 */
enum PaymentStatus: string
{
    case NotRequired = 'not_required';     // e.g. restaurant, pay at venue
    case Unpaid = 'unpaid';
    case DemoPaid = 'demo_paid';
    case DemoRefunded = 'demo_refunded';
    case DemoPartiallyRefunded = 'demo_partial_refund';

    public function label(): string
    {
        return match ($this) {
            self::NotRequired => 'Pay at venue',
            self::Unpaid => 'Awaiting payment',
            self::DemoPaid => 'Paid (demo)',
            self::DemoRefunded => 'Refunded (demo)',
            self::DemoPartiallyRefunded => 'Partly refunded (demo)',
        };
    }
}

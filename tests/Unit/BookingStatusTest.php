<?php

namespace Tests\Unit;

use App\Enums\BookingStatus;
use PHPUnit\Framework\TestCase;

class BookingStatusTest extends TestCase
{
    public function test_allowed_transitions(): void
    {
        $this->assertTrue(BookingStatus::AwaitingPayment->canTransitionTo(BookingStatus::Pending));
        $this->assertTrue(BookingStatus::Pending->canTransitionTo(BookingStatus::Confirmed));
        $this->assertTrue(BookingStatus::Pending->canTransitionTo(BookingStatus::Rejected));
        $this->assertTrue(BookingStatus::Confirmed->canTransitionTo(BookingStatus::Completed));
    }

    public function test_final_states_cannot_change(): void
    {
        foreach ([BookingStatus::Cancelled, BookingStatus::Rejected, BookingStatus::Expired, BookingStatus::Completed] as $final) {
            foreach (BookingStatus::cases() as $next) {
                $this->assertFalse($final->canTransitionTo($next), "{$final->value} → {$next->value} should be blocked");
            }
        }
    }

    public function test_confirmed_cannot_go_back_to_pending(): void
    {
        $this->assertFalse(BookingStatus::Confirmed->canTransitionTo(BookingStatus::Pending));
        $this->assertFalse(BookingStatus::Rejected->canTransitionTo(BookingStatus::Confirmed));
    }
}

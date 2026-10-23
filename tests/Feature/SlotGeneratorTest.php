<?php

namespace Tests\Feature;

use App\Models\Listing;
use App\Models\TimeSlot;
use App\Services\SlotGenerator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SlotGeneratorTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeding_created_slots_and_rerunning_creates_no_duplicates(): void
    {
        $listing = Listing::where('slug', 'kamo-omakase-counter')->first();
        $before = $listing->timeSlots()->count();

        $this->assertGreaterThan(0, $before);
        $this->assertSame(0, app(SlotGenerator::class)->generate($listing));
        $this->assertSame($before, $listing->timeSlots()->count());
    }

    public function test_slots_follow_the_weekly_schedule(): void
    {
        // Omakase counter is closed on Sunday (0) and Monday (1).
        $listing = Listing::where('slug', 'kamo-omakase-counter')->first();
        $weekdays = $listing->timeSlots()->get()->map(fn (TimeSlot $s) => $s->starts_at->dayOfWeek)->unique();

        $this->assertNotContains(0, $weekdays);
        $this->assertNotContains(1, $weekdays);
        $this->assertSame(10, $listing->timeSlots()->first()->capacity);
    }

    public function test_overnight_sessions_end_the_next_morning(): void
    {
        $slot = Listing::where('slug', 'riverside-camping-night')->first()->timeSlots()->first();

        $this->assertSame('16:00', $slot->starts_at->format('H:i'));
        $this->assertSame('08:00', $slot->ends_at->format('H:i'));
        $this->assertTrue($slot->ends_at->isAfter($slot->starts_at));
    }
}

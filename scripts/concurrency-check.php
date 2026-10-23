<?php

/**
 * Concurrency check: N separate processes try to book the SAME last room at the same instant,
 * each with its own MySQL connection — like N visitors pressing "Reserve" together.
 * Expected: exactly one succeeds, the rest are told it's sold out, and there is one booking.
 *
 * Run (Linux/Docker, needs the pcntl extension):
 *   docker compose exec app php scripts/concurrency-check.php
 * Test data it creates is removed at the end.
 */
use App\Exceptions\BookingException;
use App\Models\Booking;
use App\Models\StayUnit;
use App\Models\User;
use App\Services\BookingService;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

if (! function_exists('pcntl_fork')) {
    exit("This check needs the pcntl extension (available in the Docker container).\n");
}

$n = (int) ($argv[1] ?? 20);
$unit = StayUnit::where('name', 'Family Loft')->firstOrFail();   // quantity = 1
$in = now()->addDays(200)->toDateString();
$out = now()->addDays(203)->toDateString();
$users = collect(range(1, $n))->map(fn () => User::factory()->customer()->create(['email' => 'race-'.uniqid().'@example.test']));
DB::disconnect();

$start = microtime(true) + 1.0;
$pids = [];
foreach ($users as $user) {
    $pid = pcntl_fork();
    if ($pid === 0) {
        DB::reconnect();
        while (microtime(true) < $start) {
            usleep(100);
        }
        try {
            app(BookingService::class)->holdStay($user, $unit, $in, $out, 1, 2);
            exit(0);
        } catch (BookingException) {
            exit(1);
        } catch (Throwable $e) {
            fwrite(STDERR, get_class($e).': '.$e->getMessage()."\n");
            exit(2);
        }
    }
    $pids[] = $pid;
}

$codes = [0 => 0, 1 => 0, 2 => 0];
foreach ($pids as $pid) {
    pcntl_waitpid($pid, $status);
    $codes[pcntl_wexitstatus($status)]++;
}
DB::reconnect();
$rows = Booking::where('stay_unit_id', $unit->id)->where('check_in', $in)->count();

echo "Processes: {$n} | got the room: {$codes[0]} | told sold out: {$codes[1]} | errors: {$codes[2]} | bookings saved: {$rows}\n";
echo ($codes[0] === 1 && $rows === 1 && $codes[2] === 0) ? "PASS — no double booking.\n" : "FAIL\n";

// Clean up
Booking::whereIn('customer_id', $users->pluck('id'))->delete();
DB::table('audit_logs')->whereIn('user_id', $users->pluck('id'))->delete();
User::whereIn('id', $users->pluck('id'))->delete();

<?php

use App\Services\BookingMaintenance;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('bookings:maintain', function (BookingMaintenance $maintenance) {
    $result = $maintenance->run();
    $this->info("Expired holds: {$result['holds']} · expired requests: {$result['requests']} · completed: {$result['completed']}");
})->purpose('Expire unpaid holds and unanswered requests, complete finished bookings');

/*
 * Put the public demo back to its original state (visitors share the demo accounts, so they can
 * rename listings, cancel bookings, etc.). Wipes the database and reseeds it.
 * Refuses to run in production unless EXCURSIO_DEMO_RESET=true is set on purpose.
 */
Artisan::command('excursio:demo-reset {--force : Skip the confirmation question}', function () {
    if (app()->isProduction() && ! config('excursio.demo_reset_allowed')) {
        $this->error('Refusing to wipe a production database. Set EXCURSIO_DEMO_RESET=true if this deployment is a public demo.');

        return 1;
    }
    if (! $this->option('force') && ! $this->confirm('This deletes ALL data and reseeds the demo. Continue?')) {
        return 1;
    }
    $started = microtime(true);
    $this->call('migrate:fresh', ['--seed' => true, '--force' => true]);
    \Illuminate\Support\Facades\Cache::flush();
    $this->info(sprintf('Demo reset in %.1f s.', microtime(true) - $started));

    return 0;
})->purpose('Wipe and reseed the public demo data');

/*
 * Used at container start-up: seed the demo data only when the database is still empty,
 * so restarts (Render's free tier sleeps and wakes often) never duplicate it.
 */
Artisan::command('excursio:seed-if-empty', function () {
    if (\App\Models\User::query()->exists()) {
        $this->info('Database already has data — not seeding.');

        return 0;
    }
    $started = microtime(true);
    $this->call('db:seed', ['--force' => true]);
    $this->info(sprintf('Demo data seeded in %.1f s.', microtime(true) - $started));

    return 0;
})->purpose('Seed demo data once, on an empty database');

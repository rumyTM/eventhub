<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Hold-expiry safety net (CLAUDE.md §G). Availability already ignores expired holds at read time, so
// correctness does not depend on this cadence — it only tidies stale rows + reflects order status.
Schedule::command('holds:release-expired')
    ->everyFiveMinutes()
    ->withoutOverlapping();

// Waitlist: offer freed inventory to the next person in line. Runs right after hold expiry so
// tickets freed by the safety net are picked up in the same scheduler window.
Schedule::command('waitlist:process')
    ->everyFiveMinutes()
    ->withoutOverlapping();

// Event reminders: 24h and 1h windows (CLAUDE.md §G). Deduped via event_reminders table.
Schedule::command('reminders:send-event')
    ->hourly()
    ->withoutOverlapping();

// Daily payout batch: build pending payout records for all eligible vendors (CLAUDE.md §G).
// batchId = today's date makes this idempotent — re-running on the same day is a no-op.
Schedule::command('payouts:process-batch')
    ->dailyAt('02:00')
    ->withoutOverlapping();

// Daily sales report: aggregate yesterday's ledger entries (CLAUDE.md §G). Idempotent upsert.
Schedule::command('reports:generate-sales')
    ->dailyAt('03:00')
    ->withoutOverlapping();

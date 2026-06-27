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

<?php

/**
 * LearnSync -- Route definitions
 *
 * Shared: project-wide infrastructure
 *
 * @author Serena Lim Sze Kee, Foo Chong Xian, Ong Shun Yan, Wong Siew Lam, Ong Kwong Wei
 */

use App\Console\Commands\SendScheduledReminders;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
 * MODULE 3 -- calendar reminders.
 *
 * Every fifteen minutes is fine-grained enough that "starts in an hour" is
 * roughly true when it arrives, and the command is idempotent, so running it
 * often costs nothing: each reminder carries a reference and is written once.
 *
 * This only fires while a scheduler process is running --
 * `php artisan schedule:work` in a spare terminal. Without it the calendar
 * still works and reminders simply never fire; run `php artisan reminders:send`
 * by hand to produce them on demand.
 */
Schedule::command(SendScheduledReminders::class)->everyFifteenMinutes();

<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Refresh market quotes every minute as a baseline. For near real-time pricing
// run `php artisan quotes:poll --loop` as a long-running worker instead.
Schedule::command('quotes:poll')->everyMinute()->withoutOverlapping();

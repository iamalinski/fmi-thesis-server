<?php

use Illuminate\Foundation\Console\ClosureCommand;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    /** @var ClosureCommand $this */
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Emit copies of every recurring invoice whose weekly/monthly run date has
// arrived. Runs once a day; a missed day is caught up on the next run since
// due templates are selected with "next run date <= today".
Schedule::command('invoices:generate-recurring')
    ->dailyAt('00:10')
    ->withoutOverlapping();

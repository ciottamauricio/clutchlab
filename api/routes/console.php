<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Safety net for the split's at-most-once status handoff: rescue matches stranded in
// "parsing" by a lost parse-outcome event (docs/plans/split-the-database.md). Runs every
// minute; only touches rows stuck past the command's grace window. Needs `artisan
// schedule:work` (or a cron) running — see docs/ENGINEERING.md.
Schedule::command('matches:reconcile')->everyMinute();

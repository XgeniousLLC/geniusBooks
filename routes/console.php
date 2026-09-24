<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Housekeeping schedule. Domain jobs (invoice reminders, overdue status,
// recurring expenses) register here as their modules land.
Schedule::command('queue:prune-failed --hours=168')->daily();
Schedule::command('queue:prune-batches --hours=48')->daily();
Schedule::command('invoices:send-reminders')->dailyAt('08:00');
Schedule::command('expenses:generate-recurring')->dailyAt('08:15');
Schedule::command('invoices:generate-recurring')->dailyAt('08:30');

<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// FEAT-20 dev-only scheduler fixture (single guarded registration line,
// integrated under explicit parent coordination consent — core writer does
// not touch this file): proves the scheduler runs real work via a
// short-lived Redis marker. Registered ONLY in local/testing; production
// registers no probe here. Feature schedules (#14/#15, retention #19)
// register their own entries.
if (app()->environment(['local', 'testing'])) {
    Schedule::command('infra:probe')->everyMinute();
}

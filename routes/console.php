<?php

use App\Services\SlaMonitor;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('helpdesk:sla-monitor', function (SlaMonitor $monitor) {
    $this->info('SLA alerts delivered: '.$monitor->run());
})->purpose('Send idempotent SLA warning and escalation notifications');

Schedule::command('helpdesk:sla-monitor')->everyFiveMinutes()->withoutOverlapping();

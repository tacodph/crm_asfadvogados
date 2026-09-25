<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('meta:sincronizar-estatisticas')->dailyAt('05:30')->withoutOverlapping()->onOneServer();
Schedule::command('meta:capi-expurgar-payloads')->weeklyOn(1, '04:00')->withoutOverlapping()->onOneServer();
Schedule::command('meta:ads-sincronizar')->dailyAt('06:00')->withoutOverlapping()->onOneServer();
Schedule::command('meta:ads-expurgar-brutos')->weeklyOn(1, '04:30')->withoutOverlapping()->onOneServer();
Schedule::command('leads:email-capturar')->everyFiveMinutes()->withoutOverlapping()->onOneServer();

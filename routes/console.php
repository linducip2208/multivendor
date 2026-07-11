<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('seo:indexnow --type=all --limit=50')->dailyAt('02:45');
Schedule::command('seo:indexnow --type=products --limit=100')->everySixHours();
Schedule::command('db:backup')->dailyAt('03:00');

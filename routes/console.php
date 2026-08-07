<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Replaces Temporal's autopost + missing-post-recovery workflows - see
// App\Console\Commands\DispatchDuePosts for why one poller covers both.
Schedule::command('posts:dispatch-due')->everyMinute();

// Engagement doesn't need real-time freshness - every 30 minutes keeps API
// call volume modest. See App\Console\Commands\RefreshPostEngagement.
Schedule::command('posts:refresh-engagement')->everyThirtyMinutes();

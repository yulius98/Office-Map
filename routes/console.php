<?php

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

app()->booted(function () {
    $schedule = app(Schedule::class);

    $schedule->command('posts:publish')
        ->everyMinute()
        ->withoutOverlapping()
        ->runInBackground()
        ->appendOutputTo(storage_path('logs/scheduler.log'))
        ->onSuccess(function () {
            info('Posts published successfully');
        })
        ->onFailure(function () {
            info('Posts publish failed');
        });
});

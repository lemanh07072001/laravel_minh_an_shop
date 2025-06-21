<?php

use App\Console\Commands\UnbanUsers;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');


Schedule::command(UnbanUsers::class)
    ->runInBackground()
    ->withoutOverlapping()
    ->appendOutputTo(storage_path('logs/unban_users.log'))
    ->everyMinute();
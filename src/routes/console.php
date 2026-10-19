<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('research-calls:process-opening-notifications')
    ->everyMinute()
    ->withoutOverlapping(5);

Schedule::command('projects:notify-overdue-objectives')
    ->hourly()
    ->withoutOverlapping(10);

Schedule::command('proposal-pdfs:recover')
    ->everyMinute()
    ->withoutOverlapping(5);

Schedule::command('proposal-drafts:purge')
    ->daily()
    ->withoutOverlapping();

Schedule::command('image-previews:prune')
    ->daily()
    ->withoutOverlapping();

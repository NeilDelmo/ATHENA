<?php

use Database\Seeders\MonitoringDemoSeeder;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('monitoring:seed-walkthrough {source : ID of an issued demo proposal}', function (MonitoringDemoSeeder $seeder): int {
    $source = filter_var($this->argument('source'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    if ($source === false) {
        $this->error('Enter a positive proposal ID.');

        return 1;
    }

    $projects = $seeder->seedWalkthrough($source);
    $this->info("Awaiting review: #{$projects['review']->id} {$projects['review']->title}");
    $this->info("Completed: #{$projects['completed']->id} {$projects['completed']->title}");

    return 0;
})->purpose('Prepare monitoring review, closeout, and college reporting examples');

Artisan::command('monitoring:seed-portfolio {source : Source ID of an existing monitoring walkthrough} {--monitoring : Add the supplemental monitoring projects}', function (MonitoringDemoSeeder $seeder): int {
    $source = filter_var($this->argument('source'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    if ($source === false) {
        $this->error('Enter a positive proposal ID.');

        return 1;
    }

    foreach ($seeder->seedPortfolio($source, (bool) $this->option('monitoring')) as $state => $project) {
        $this->info(str_replace('_', ' ', ucfirst($state)).": #{$project->id} {$project->title}");
    }

    return 0;
})->purpose('Add proposal and implementation examples for an existing monitoring walkthrough');

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

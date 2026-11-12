<?php

// This file is loaded only by the isolated Playwright PHP processes.
if (getenv('APP_ENV') !== 'testing' || getenv('DB_DATABASE') !== 'athena_playwright') {
    throw new RuntimeException('The browser-test clock requires the isolated testing environment.');
}

require_once dirname(__DIR__, 2).'/vendor/autoload.php';

$playwrightClock = static function (Carbon\CarbonImmutable $realNow): Carbon\CarbonImmutable {
    $clockPath = getenv('LARAVEL_STORAGE_PATH').'/clock.txt';
    $date = trim(file_get_contents($clockPath));
    clearstatcache(true, $clockPath);

    return $realNow->setTimezone('Asia/Manila')
        ->setDate((int) substr($date, 0, 4), (int) substr($date, 5, 2), (int) substr($date, 8, 2))
        ->setTime((int) substr($date, 11, 2), (int) substr($date, 14, 2), (int) substr($date, 17, 2))
        ->addSeconds(max(0, time() - filemtime($clockPath)));
};

Carbon\Carbon::setTestNow($playwrightClock);
Carbon\CarbonImmutable::setTestNow($playwrightClock);

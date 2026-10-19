<?php

namespace App\Jobs;

use App\Services\ResearchManualChecks;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Throwable;

class CheckResearchManualGuidance implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $maxExceptions = 1;

    public int $timeout = 60;

    public function __construct(public int $manualId)
    {
        $this->onConnection('database')->onQueue('research-manual-checks');
    }

    /** @return list<WithoutOverlapping> */
    public function middleware(): array
    {
        return [(new WithoutOverlapping('manual-guidance:'.$this->manualId))->releaseAfter(5)->expireAfter(75)];
    }

    public function handle(ResearchManualChecks $checks): void
    {
        $checks->run($this->manualId);
    }

    public function failed(?Throwable $exception): void
    {
        app(ResearchManualChecks::class)->fail($this->manualId);
    }
}

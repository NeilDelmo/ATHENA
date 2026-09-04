<?php

namespace App\Jobs;

use App\Models\HarvestedLiteratureSource;
use App\Services\LiteratureWebHarvester;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

class HarvestLiteraturePage implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 80;

    public int $uniqueFor = 3600;

    /** @var list<int> */
    public array $backoff = [60, 300, 900];

    public function __construct(public int $recordId)
    {
        $this->onQueue((string) config('literature.web_harvest.queue'));
    }

    public function uniqueId(): string
    {
        return (string) $this->recordId;
    }

    public function handle(LiteratureWebHarvester $harvester): void
    {
        $record = HarvestedLiteratureSource::find($this->recordId);

        if ($record !== null) {
            $harvester->harvest($record);
        }
    }

    public function failed(?Throwable $exception): void
    {
        HarvestedLiteratureSource::whereKey($this->recordId)->update([
            'status' => 'failed',
            'queued_at' => null,
            'failure_reason' => Str::limit($exception?->getMessage() ?? 'The harvest job failed.', 500, ''),
            'next_harvest_at' => now()->addDay(),
        ]);
        Log::warning('Literature web harvest job failed.', [
            'harvested_source_id' => $this->recordId,
            'exception' => $exception ? $exception::class : null,
        ]);
    }
}

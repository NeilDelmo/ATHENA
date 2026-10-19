<?php

namespace App\Jobs;

use App\Exceptions\ProposalPdfPreparationBusy;
use App\Services\ProposalPdfPreparation;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Throwable;

class PrepareProposalDraftPdfs implements ShouldQueue
{
    use Queueable;

    public int $tries = 0;

    public int $maxExceptions = 3;

    /** @var list<int> */
    public array $backoff = [30, 120];

    public int $timeout = 540;

    public function __construct(public int $draftId, public ?string $runId = null)
    {
        $this->onConnection('proposal-pdfs')->onQueue('proposal-pdfs');
    }

    /** @return list<WithoutOverlapping> */
    public function middleware(): array
    {
        return [(new WithoutOverlapping('proposal-pdfs:'.$this->draftId))->releaseAfter(5)->expireAfter(600)];
    }

    public function handle(ProposalPdfPreparation $preparation): void
    {
        if ($this->job && $preparation->quietSeconds($this->draftId) < 5) {
            $this->release(5);

            return;
        }
        try {
            $preparation->run($this->draftId, $this->runId ?? null);
        } catch (ProposalPdfPreparationBusy $exception) {
            if (! $this->job) {
                throw $exception;
            }
            $this->release(15);
        }
    }

    public function failed(?Throwable $exception): void
    {
        if ($this->runId ?? null) {
            app(ProposalPdfPreparation::class)->fail($this->draftId, $this->runId);
        }
    }
}

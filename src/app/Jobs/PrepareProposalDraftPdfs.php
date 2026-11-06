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
        if ($this->job && $preparation->quietSeconds($this->draftId) < ProposalPdfPreparation::QUIET_SECONDS) {
            $this->release(ProposalPdfPreparation::QUIET_SECONDS);

            return;
        }
        $conversionTimeout = (int) config('document_pdf.timeout_seconds');
        $delegateToPhpCli = config('document_pdf.delegate_to_php_cli');
        // Both conversion phases may wait for the export gate within this job's 540-second budget.
        config(['document_pdf.timeout_seconds' => min($conversionTimeout, 120), 'document_pdf.delegate_to_php_cli' => false]);
        try {
            $preparation->run($this->draftId, $this->runId ?? null);
        } catch (ProposalPdfPreparationBusy $exception) {
            if (! $this->job) {
                throw $exception;
            }
            $this->release(15);
        } finally {
            config(['document_pdf.timeout_seconds' => $conversionTimeout, 'document_pdf.delegate_to_php_cli' => $delegateToPhpCli]);
        }
    }

    public function failed(?Throwable $exception): void
    {
        if ($this->runId ?? null) {
            app(ProposalPdfPreparation::class)->fail($this->draftId, $this->runId);
        }
    }
}

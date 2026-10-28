<?php

namespace App\Console\Commands;

use App\Services\LibreOfficePdfWorker;
use App\Services\ProposalPdfPreparation;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Throwable;

#[Signature('proposal-pdfs:recover {--watch : Check for interrupted preparation every minute}')]
#[Description('Recover interrupted automatic proposal PDF preparation')]
class RecoverProposalPdfs extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(ProposalPdfPreparation $preparation, LibreOfficePdfWorker $office): int
    {
        try {
            do {
                if ($this->option('watch')) {
                    try {
                        $office->start();
                    } catch (Throwable $exception) {
                        report($exception);
                        $this->warn('Fast PDF conversion is unavailable. Standard conversion remains available.');
                    }
                }
                try {
                    $checked = $preparation->reconcile();
                    $this->info('Checked '.$checked.' proposal drafts for interrupted PDF preparation.');
                } catch (Throwable $exception) {
                    report($exception);
                    $this->error('PDF recovery is temporarily unavailable. Saved proposal content is unchanged.');
                    if (! $this->option('watch')) {
                        return self::FAILURE;
                    }
                }
                if ($this->option('watch')) {
                    sleep(60);
                }
            } while ($this->option('watch'));
        } finally {
            $office->stop();
        }

        return self::SUCCESS;
    }
}

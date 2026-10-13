<?php

namespace App\Console\Commands;

use App\Services\ResearchManualIndexer;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use RuntimeException;

#[Signature('research-manual:index {path : Local PDF file to import} {--title=Approved Research Manual (2023) : Source title shown in citations} {--force : Rebuild an unchanged document} {--pages-json= : Page-preserving extraction or OCR JSON in PDF page order}')]
#[Description('Import and locally index a research manual without AI calls or database changes')]
class IndexResearchManual extends Command
{
    public function handle(ResearchManualIndexer $indexer): int
    {
        try {
            $index = $indexer->index(
                (string) $this->argument('path'),
                (string) $this->option('title'),
                (bool) $this->option('force'),
                $this->option('pages-json') !== null ? (string) $this->option('pages-json') : null,
            );
        } catch (RuntimeException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->info(($index['reused'] ? 'Already indexed: ' : 'Indexed: ').$index['title']);
        $this->line($index['page_count'].' PDF pages; '.count($index['chunks']).' searchable passages.');
        $this->line('Version: '.$index['version']);

        if ($index['unindexed_pages'] !== []) {
            $this->warn('Pages without indexable text: '.implode(', ', $index['unindexed_pages']).'. Use --pages-json to include page-preserving OCR.');
        }

        return self::SUCCESS;
    }
}

<?php

namespace App\Console\Commands;

use App\Exceptions\LiteratureHarvestException;
use App\Jobs\HarvestLiteraturePage;
use App\Services\LiteratureWebHarvester;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

#[Signature('literature:harvest-web {--source= : Configured repository key} {--url= : One allowlisted item URL; requires --source} {--limit=10 : Maximum pages per repository (1-50)} {--inline : Process this bounded harvest without a queue worker}')]
#[Description('Harvest public scholarly HTML metadata from allowlisted repository sitemaps')]
class HarvestLiteratureWeb extends Command
{
    public function handle(LiteratureWebHarvester $harvester): int
    {
        $limit = (string) $this->option('limit');
        $repositories = (array) config('literature.web_harvest.repositories');
        $source = $this->option('source');
        $url = $this->option('url');

        if (! config('literature.web_harvest.enabled')) {
            $this->error('Web harvesting is disabled.');

            return self::FAILURE;
        }

        if (! ctype_digit($limit) || (int) $limit < 1 || (int) $limit > 50
            || ($source !== null && ! array_key_exists($source, $repositories))
            || ($url !== null && $source === null)) {
            $this->error('Use a configured source key and a limit between 1 and 50.');

            return self::INVALID;
        }

        $failed = false;

        foreach ($source !== null ? [$source => $repositories[$source]] : $repositories as $key => $repository) {
            try {
                $processed = Cache::lock('literature:discovery:'.$key, 3600)->get(function () use ($harvester, $key, $limit, $url): int {
                    $records = $url !== null ? [$harvester->page($key, $url)] : $harvester->discover($key, (int) $limit);

                    foreach ($records as $record) {
                        $record->update(['queued_at' => now()]);

                        if ($this->option('inline')) {
                            $harvester->harvest($record);
                            $this->line($record->fresh()->status.': '.$record->url);
                        } else {
                            HarvestLiteraturePage::dispatch($record->getKey());
                        }
                    }

                    return count($records);
                });

                $this->info($repository['name'].': '.($processed === false ? 'already running' : $processed.($this->option('inline') ? ' page(s) processed' : ' page(s) queued')));
            } catch (LiteratureHarvestException $exception) {
                $failed = true;
                $this->error($repository['name'].': '.$exception->getMessage());

                if ($this->output->isVerbose() && $exception->getPrevious() !== null) {
                    $this->line($exception->getPrevious()->getMessage());
                }
            }
        }

        return $failed ? self::FAILURE : self::SUCCESS;
    }
}

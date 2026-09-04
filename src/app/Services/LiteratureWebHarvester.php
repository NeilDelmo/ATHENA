<?php

namespace App\Services;

use App\Exceptions\LiteratureHarvestException;
use App\Models\HarvestedLiteratureSource;
use App\Models\LiteratureSource;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class LiteratureWebHarvester
{
    public function __construct(
        private LiteratureWebFetcher $fetcher,
        private LiteratureWebMetadataParser $parser,
    ) {}

    public function page(string $key, string $url): HarvestedLiteratureSource
    {
        if (! $this->fetcher->allowsUrl($key, $url)) {
            throw new LiteratureHarvestException('The URL is outside the repository allowlist.');
        }

        return HarvestedLiteratureSource::firstOrCreate(
            ['url_hash' => hash('sha256', $url)],
            ['repository_key' => $key, 'url' => $url],
        );
    }

    /** @return list<HarvestedLiteratureSource> */
    public function discover(string $key, int $limit): array
    {
        $repository = $this->fetcher->repository($key);
        $urls = Cache::remember('literature:sitemap:'.hash('sha256', json_encode($repository)), 3600, fn (): array => $this->sitemapUrls($key));
        $candidates = [];

        foreach ($urls as $url) {
            if ($this->fetcher->allowsUrl($key, $url)) {
                $candidates[hash('sha256', $url)] = $url;
            }
        }

        foreach (array_chunk(array_keys($candidates), 500) as $hashes) {
            $unavailable = HarvestedLiteratureSource::query()
                ->whereIn('url_hash', $hashes)
                ->where(function (Builder $query): void {
                    $query->where('next_harvest_at', '>', now())
                        ->orWhere('queued_at', '>', now()->subHour());
                })
                ->pluck('url_hash');

            foreach ($unavailable as $hash) {
                unset($candidates[$hash]);
            }
        }

        $records = [];

        foreach (array_slice($candidates, 0, max(1, min(50, $limit)), true) as $hash => $url) {
            $records[] = $this->page($key, $url);
        }

        return $records;
    }

    public function harvest(HarvestedLiteratureSource $record): void
    {
        $record->update(['last_attempted_at' => now()]);

        try {
            $response = $this->fetcher->get($record->repository_key, $record->url);

            if (! in_array($response['content_type'], ['text/html', 'application/xhtml+xml'], true)) {
                throw new LiteratureHarvestException('Only public HTML citation pages are harvested; documents are not downloaded.');
            }

            $metadata = $this->parser->parse($response['body']);

            if ($metadata === null) {
                throw new LiteratureHarvestException('No supported scholarly citation metadata was found.');
            }

            $record->update([
                ...$metadata,
                'fingerprint' => LiteratureSource::fingerprintFor($metadata['doi'], $metadata['title'], $metadata['publication_year']),
                'status' => 'ready',
                'failure_reason' => null,
                'queued_at' => null,
                'harvested_at' => now(),
                'next_harvest_at' => now()->addDays((int) config('literature.web_harvest.refresh_days')),
            ]);
        } catch (LiteratureHarvestException $exception) {
            $record->update([
                'status' => $exception->retryable ? 'failed' : 'skipped',
                'failure_reason' => Str::limit($exception->getMessage(), 500, ''),
                'queued_at' => null,
                'next_harvest_at' => $exception->retryable ? now()->addDay() : now()->addDays(30),
                ...($exception->retryable ? [] : [
                    'title' => null, 'authors' => null, 'abstract' => null,
                    'doi' => null, 'fingerprint' => null, 'metadata' => null,
                ]),
            ]);

            if ($exception->retryable) {
                throw $exception;
            }
        }
    }

    /** @return list<string> */
    private function sitemapUrls(string $key): array
    {
        $queue = [$this->fetcher->repository($key)['sitemap']];
        $visited = [];
        $urls = [];
        $maximumUrls = (int) config('literature.web_harvest.maximum_urls');

        while ($queue !== [] && count($visited) < (int) config('literature.web_harvest.maximum_sitemaps')) {
            $url = array_shift($queue);

            if (isset($visited[$url]) || ! $this->fetcher->allowsUrl($key, $url, 'sitemap')) {
                continue;
            }

            $visited[$url] = true;
            $response = $this->fetcher->get($key, $url, 'sitemap');
            $body = $response['body'];

            if (preg_match('/<!DOCTYPE|<!ENTITY/i', $body) === 1) {
                throw new LiteratureHarvestException('Sitemaps containing document types or entities are not accepted.');
            }

            $previous = libxml_use_internal_errors(true);

            try {
                $xml = simplexml_load_string($body, options: LIBXML_NONET);
            } finally {
                libxml_clear_errors();
                libxml_use_internal_errors($previous);
            }

            if ($xml === false || ! in_array($xml->getName(), ['urlset', 'sitemapindex'], true)) {
                throw new LiteratureHarvestException('The repository did not return a valid sitemap.');
            }

            $isIndex = $xml->getName() === 'sitemapindex';
            $locations = $xml->xpath($isIndex
                ? '/*[local-name()="sitemapindex"]/*[local-name()="sitemap"]/*[local-name()="loc"]'
                : '/*[local-name()="urlset"]/*[local-name()="url"]/*[local-name()="loc"]') ?: [];

            foreach ($locations as $location) {
                $candidate = trim((string) $location);

                if ($isIndex && count($queue) < 20 && $this->fetcher->allowsUrl($key, $candidate, 'sitemap')) {
                    $queue[] = $candidate;
                } elseif (! $isIndex && $this->fetcher->allowsUrl($key, $candidate)) {
                    $urls[$candidate] = $candidate;

                    if (count($urls) >= $maximumUrls) {
                        return array_values($urls);
                    }
                }
            }
        }

        return array_values($urls);
    }
}

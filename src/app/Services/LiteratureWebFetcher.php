<?php

namespace App\Services;

use App\Exceptions\LiteratureHarvestException;
use App\Support\LiteratureRobotsRules;
use GuzzleHttp\Exception\TransferException;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;
use Illuminate\Support\Str;
use Psr\Http\Message\ResponseInterface;

class LiteratureWebFetcher
{
    public function __construct(private LiteratureRobotsRules $robotsRules) {}

    /** @return array<string, string> */
    public function repository(string $key): array
    {
        $repository = config('literature.web_harvest.repositories.'.$key);

        if (! config('literature.web_harvest.enabled') || ! is_array($repository)) {
            throw new LiteratureHarvestException('This repository is not enabled for harvesting.');
        }

        return $repository;
    }

    public function allowsUrl(string $key, string $url, string $kind = 'item'): bool
    {
        $repository = $this->repository($key);
        $parts = parse_url($url);
        $origin = parse_url($repository['origin']);

        if (! is_array($parts) || ($parts['scheme'] ?? '') !== 'https'
            || strtolower($parts['host'] ?? '') !== strtolower($origin['host'] ?? '')
            || isset($parts['user']) || isset($parts['pass']) || isset($parts['fragment'])
            || (isset($parts['port']) && $parts['port'] !== 443)
            || preg_match('/[\x00-\x20\\\\]/', $url) === 1) {
            return false;
        }

        $target = ($parts['path'] ?? '/').(isset($parts['query']) ? '?'.$parts['query'] : '');

        return match ($kind) {
            'robots' => $target === '/robots.txt',
            'sitemap' => preg_match($repository['sitemap_pattern'], $target) === 1,
            'item' => preg_match($repository['item_pattern'], $target) === 1,
            default => false,
        };
    }

    /** @return array{body: string, content_type: string, url: string} */
    public function get(string $key, string $url, string $kind = 'item'): array
    {
        if (! $this->allowsUrl($key, $url, $kind)) {
            throw new LiteratureHarvestException('The URL is outside the repository allowlist.');
        }

        $repository = $this->repository($key);
        $robots = Cache::remember('literature:robots:'.hash('sha256', $repository['origin']), 3600, function () use ($key, $repository): string {
            $robotsUrl = $repository['origin'].'/robots.txt';

            if (! $this->allowsUrl($key, $robotsUrl, 'robots')) {
                throw new LiteratureHarvestException('The repository origin is not a safe HTTPS URL.');
            }

            $response = $this->request($robotsUrl, 5, true);

            if (preg_match('/<(?:!doctype|html|script)\b/i', $response['body']) === 1) {
                throw new LiteratureHarvestException('robots.txt could not be verified.', true);
            }

            return $response['body'];
        });
        $policy = $this->robotsRules->evaluate($robots, $url);

        if (! $policy['allowed']) {
            throw new LiteratureHarvestException('The repository disallows harvesting this URL in robots.txt.');
        }

        return $this->request($url, max(5, $policy['delay'], (int) config('literature.web_harvest.minimum_delay')));
    }

    /** @return array{body: string, content_type: string, url: string} */
    private function request(string $url, int $delay, bool $robots = false): array
    {
        $host = (string) parse_url($url, PHP_URL_HOST);
        $addresses = $this->resolveAddresses($host);

        if ($addresses === [] || collect($addresses)->contains(fn (string $address): bool => filter_var(
            $address, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE,
        ) === false)) {
            throw new LiteratureHarvestException('The repository must resolve only to public internet addresses.');
        }

        $address = str_contains($addresses[0], ':') ? '['.$addresses[0].']' : $addresses[0];
        $maximumBytes = $robots ? 512 * 1024 : (int) config('literature.web_harvest.maximum_bytes');
        $lockKey = 'literature:harvest-host:'.hash('sha256', $host);

        try {
            return Cache::lock($lockKey, 50)->block(5, function () use ($url, $host, $address, $delay, $robots, $maximumBytes, $lockKey): array {
                $wait = max(
                    0,
                    (int) Cache::get($lockKey.':next', 0) - now()->timestamp,
                    (int) Cache::get($lockKey.':last', 0) + $delay - now()->timestamp,
                );

                if ($wait > 20) {
                    throw new LiteratureHarvestException('The repository is cooling down; retry later.', true);
                }

                if ($wait > 0) {
                    Sleep::for($wait)->seconds();
                }

                try {
                    $response = Http::withoutRedirecting()
                        ->withHeaders([
                            'User-Agent' => 'ATHENA-LiteratureHarvester/1.0 (+'.config('app.url').')',
                            'Accept-Encoding' => 'identity',
                        ])
                        ->accept('text/html,application/xhtml+xml,application/xml,text/xml,text/plain')
                        ->connectTimeout((int) config('literature.web_harvest.connect_timeout'))
                        ->timeout((int) config('literature.web_harvest.timeout'))
                        ->withOptions([
                            'proxy' => '',
                            'decode_content' => false,
                            'curl' => [CURLOPT_RESOLVE => [$host.':443:'.$address], CURLOPT_PROTOCOLS => CURLPROTO_HTTPS],
                            'on_headers' => function (ResponseInterface $response) use ($maximumBytes): void {
                                if ((int) $response->getHeaderLine('Content-Length') > $maximumBytes) {
                                    throw new LiteratureHarvestException('The repository response exceeds the download limit.');
                                }
                            },
                            'progress' => function (float $total, float $downloaded, float $uploadTotal, float $uploaded) use ($maximumBytes): void {
                                if ($downloaded > $maximumBytes) {
                                    throw new LiteratureHarvestException('The repository response exceeds the download limit.');
                                }
                            },
                        ])->get($url);
                } finally {
                    Cache::put($lockKey.':last', now()->timestamp, 86400);
                    Cache::put($lockKey.':next', now()->timestamp + $delay, 86400);
                }

                if ($response->status() === 429 || $response->serverError()) {
                    $retryAfter = (string) $response->header('Retry-After');
                    $retryDelay = is_numeric($retryAfter) ? (int) $retryAfter : max(0, (int) strtotime($retryAfter) - now()->timestamp);
                    Cache::put($lockKey.':next', now()->timestamp + max($delay, min(86400, $retryDelay)), 86400);
                    throw new LiteratureHarvestException('The repository is temporarily unavailable (HTTP '.$response->status().').', true);
                }

                if ($robots && $response->status() === 404) {
                    return ['body' => '', 'content_type' => 'text/plain', 'url' => $url];
                }

                if (! $response->successful()) {
                    throw new LiteratureHarvestException('The repository rejected or redirected the request (HTTP '.$response->status().').');
                }

                if (preg_match('/\b(noindex|noarchive|none)\b/i', (string) $response->header('X-Robots-Tag')) === 1) {
                    throw new LiteratureHarvestException('The repository disallows indexing or archiving this response.');
                }

                if (strlen($response->body()) > $maximumBytes) {
                    throw new LiteratureHarvestException('The repository response exceeds the download limit.');
                }

                if (! in_array(Str::lower((string) $response->header('Content-Encoding')), ['', 'identity'], true)) {
                    throw new LiteratureHarvestException('Compressed responses are not accepted by this bounded metadata harvester.');
                }

                return [
                    'body' => $response->body(),
                    'content_type' => Str::lower(Str::before((string) $response->header('Content-Type'), ';')),
                    'url' => $url,
                ];
            });
        } catch (ConnectionException|LockTimeoutException|TransferException $exception) {
            throw new LiteratureHarvestException('The repository could not be reached or is already being harvested.', true, $exception);
        }
    }

    /** @return list<string> */
    protected function resolveAddresses(string $host): array
    {
        return collect(dns_get_record($host, DNS_A | DNS_AAAA) ?: [])
            ->flatMap(fn (array $record): array => array_values(array_filter([$record['ip'] ?? null, $record['ipv6'] ?? null], 'is_string')))
            ->unique()->values()->all();
    }
}

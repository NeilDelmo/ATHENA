<?php

namespace App\Services;

use Carbon\CarbonImmutable;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Pool;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class JournalRecommendationService
{
    private const STOP_WORDS = [
        'about', 'after', 'among', 'and', 'are', 'based', 'between', 'from', 'into',
        'journal', 'paper', 'publication', 'research', 'study', 'that', 'the', 'their',
        'this', 'through', 'using', 'with', 'was', 'were', 'for', 'which', 'has', 'have',
        'can', 'will', 'also', 'our', 'its', 'not', 'these', 'those', 'results', 'result',
        'analysis', 'data', 'method', 'methods', 'findings', 'conclusion', 'abstract',
        'aim', 'aims', 'objective', 'objectives', 'examine', 'examines', 'investigate',
        'investigates', 'evaluate', 'evaluates', 'effect', 'effects', 'significant',
        'participants', 'sample', 'used', 'use', 'shows', 'show', 'found', 'suggest',
    ];

    private bool $scopusUnavailable = false;

    private bool $metadataUnavailable = false;

    public function __construct(private ScopusSourceRegistry $scopusSources) {}

    /** @return array<string, mixed> */
    public function recommend(string $query, ?string $context = null, bool $openAccessOnly = false, int $recentYears = 10, string $indexing = 'prefer_scopus'): array
    {
        $query = $this->text($query, 500);
        $context = $this->text($context, 6000);
        $sourceList = $this->scopusSources->metadata();
        $scopusConfigured = filled(config('services.scopus.key')) || $sourceList !== null;
        if ($indexing === 'scopus_only' && ! $scopusConfigured) {
            throw ValidationException::withMessages(['indexing' => 'Automatic Scopus checks are not connected yet. Use topic matches, then verify the ISSN in Scopus Sources.']);
        }

        $keywords = $this->keywords($query, $context);
        if ($keywords === []) {
            throw ValidationException::withMessages(['query' => 'Include specific subject terms in the title or abstract, such as diabetes, rice yield, or coastal monitoring.']);
        }
        $searches = $this->searches($query, $context, $keywords, $recentYears);
        $payload = $this->getWorks($searches);
        $journals = $this->rankJournals($this->aggregateJournals($payload['results'], $keywords, $openAccessOnly), $keywords);
        $journals = $this->checkScopus($this->enrichJournals(array_slice($journals, 0, 24)));
        if ($indexing !== 'any') {
            $journals = array_values(array_filter($journals, fn (array $journal): bool => ! in_array($journal['scopus']['status'], ['inactive', 'discontinued'], true)));
        }
        if ($indexing === 'scopus_only') {
            $journals = array_values(array_filter($journals, fn (array $journal): bool => in_array($journal['scopus']['status'], ['active', 'listed'], true)));
            if ($journals === [] && $this->scopusUnavailable) {
                throw ValidationException::withMessages(['indexing' => 'Scopus could not be checked right now. Retry later or choose all topic matches.']);
            }
        } elseif ($indexing === 'prefer_scopus') {
            $journals = collect($journals)->sortByDesc(fn (array $journal): array => [match ($journal['scopus']['status']) {
                'active' => 2, 'listed' => 1, default => 0
            }, $journal['fit_score'], $journal['evidence_count']])->values()->all();
        }

        return [
            'results' => array_slice($journals, 0, 12), 'query' => $query,
            'related_articles' => count($payload['results']), 'checked_at' => $payload['checked_at'],
            'source' => 'OpenAlex', 'keywords' => $keywords, 'search_count' => count($searches),
            'scopus_source_list' => $sourceList,
            'warnings' => array_values(array_filter([
                $payload['partial'] ? 'Some article searches were unavailable. These matches use the searches that completed.' : null,
                ! $scopusConfigured ? 'Automatic Scopus checks are not connected. Check current coverage in Scopus Sources using the ISSN.' : null,
                $this->scopusUnavailable ? 'Some Scopus checks were unavailable; those journals remain unverified.' : null,
                $this->metadataUnavailable ? 'Some journal website details could not be loaded. Use the source records to find the official submission website.' : null,
                $sourceList && CarbonImmutable::parse($sourceList['as_of'])->lt(now()->subMonths(4)) ? 'The Scopus source list needs refreshing. Its older coverage is shown as unverified.' : null,
            ])),
            'methodology' => 'Topic relevance, distinct related articles, and recent evidence determine the score (out of 100), not acceptance probability. Scopus records are matched by ISSN; current coverage must be checked in Scopus Sources. Web of Science indexing is checked separately in the Master Journal List.',
        ];
    }

    /** @param list<array<string, mixed>> $journals @return list<array<string, mixed>> */
    private function enrichJournals(array $journals): array
    {
        $this->metadataUnavailable = false;
        $ids = collect($journals)->filter(fn (array $journal): bool => ! $journal['homepage_url'] && preg_match('/^S\d+$/', $journal['id']))->pluck('id')->sort()->values()->all();
        if ($ids === []) {
            return $journals;
        }
        $key = 'journal-source-details:v1:'.hash('sha256', implode('|', $ids));
        $sources = Cache::get($key);
        if (! is_array($sources)) {
            $parameters = ['filter' => 'openalex:'.implode('|', $ids), 'per-page' => count($ids)];
            if (filled(config('services.openalex.key'))) {
                $parameters['api_key'] = config('services.openalex.key');
            }
            try {
                $response = Http::acceptJson()->connectTimeout(3)->timeout(8)->get('https://api.openalex.org/sources', $parameters);
                $sources = $response->successful() ? $response->json('results') : null;
            } catch (ConnectionException) {
                $sources = null;
            }
            if (! is_array($sources)) {
                $this->metadataUnavailable = true;

                return $journals;
            }
            Cache::put($key, $sources, now()->addHours(6));
        }
        $sources = collect($sources)->filter(fn (mixed $source): bool => is_array($source) && isset($source['id']))->keyBy(fn (array $source): string => basename($source['id']));

        return array_map(function (array $journal) use ($sources): array {
            $source = $sources->get($journal['id']);
            if ($source) {
                $journal['homepage_url'] = $this->safeUrl($source['homepage_url'] ?? null);
                $journal['works_count'] = (int) ($source['works_count'] ?? $journal['works_count']);
            }

            return $journal;
        }, $journals);
    }

    /** @param list<string> $keywords @return list<array<string, mixed>> */
    private function searches(string $query, string $context, array $keywords, int $recentYears): array
    {
        $base = ['filter' => $this->workFilters($recentYears), 'per-page' => 50];
        $titleTerms = $this->words($query);
        $searches = [$base + ['search' => implode(' ', array_slice($titleTerms ?: $keywords, 0, 6))]];
        $pairs = array_chunk(array_slice($keywords, 0, 8), 2);
        $broad = implode(' OR ', array_map(fn (array $pair): string => '('.implode(' AND ', $pair).')', $pairs));
        if ($broad !== $searches[0]['search']) {
            $searches[] = $base + ['search' => $broad];
        }
        if (Str::length($context) >= 40 && config('services.openalex.semantic_search')) {
            $searches[] = $base + ['search.semantic' => Str::limit(trim($query.' '.$context), 2000, '')];
        }

        return $searches;
    }

    /** @param list<array<string, mixed>> $searches @return array<string, mixed> */
    private function getWorks(array $searches): array
    {
        $payloads = [];
        $missing = [];
        foreach ($searches as $index => $parameters) {
            $key = 'journal-recommendations:v3:'.hash('sha256', json_encode($parameters));
            $cached = Cache::get($key);
            if (is_array($cached)) {
                $payloads[$index] = $cached;
            } else {
                $missing[$index] = ['key' => $key, 'parameters' => $parameters];
            }
        }
        $responses = $missing === [] ? [] : Http::pool(function (Pool $pool) use ($missing): array {
            $requests = [];
            foreach ($missing as $index => $search) {
                $parameters = $search['parameters'];
                if (filled(config('services.openalex.key'))) {
                    $parameters['api_key'] = config('services.openalex.key');
                }
                $requests[] = $pool->as((string) $index)->acceptJson()->connectTimeout(4)->timeout(15)->get('https://api.openalex.org/works', $parameters);
            }

            return $requests;
        });
        foreach ($missing as $index => $search) {
            $response = $responses[$index] ?? null;
            if ($response instanceof Response && $response->successful() && is_array($response->json('results'))) {
                $payloads[$index] = ['results' => $response->json('results'), 'checked_at' => now()->toIso8601String()];
                Cache::put($search['key'], $payloads[$index], now()->addHours(6));
            }
        }
        if ($payloads === []) {
            throw ValidationException::withMessages(['journal_search' => 'The article index is unavailable or has reached its request limit. Try again later, or use the official journal matchers below.']);
        }
        ksort($payloads);
        $works = [];
        foreach ($payloads as $payload) {
            foreach ($payload['results'] as $work) {
                if (is_array($work) && filled($work['id'] ?? null)) {
                    $works[$work['id']] ??= $work;
                }
            }
        }

        return ['results' => array_values($works), 'checked_at' => min(array_column($payloads, 'checked_at')), 'partial' => count($payloads) !== count($searches)];
    }

    /** @param list<array<string, mixed>> $works @param list<string> $keywords @return array<string, array<string, mixed>> */
    private function aggregateJournals(array $works, array $keywords, bool $openAccessOnly): array
    {
        $journals = [];
        foreach ($works as $work) {
            $source = Arr::get($work, 'primary_location.source');
            if (! is_array($source) || ! filled($source['id'] ?? null) || ($source['type'] ?? '') !== 'journal') {
                continue;
            }
            $oa = (bool) ($source['is_oa'] ?? false) || (bool) ($source['is_in_doaj'] ?? false);
            if ($openAccessOnly && ! $oa) {
                continue;
            }
            $title = $this->text($work['display_name'] ?? $work['title'] ?? '', 1000);
            $terms = $this->words($title.' '.collect($work['topics'] ?? [])->pluck('display_name')->join(' ').' '.implode(' ', array_keys($work['abstract_inverted_index'] ?? [])));
            $matched = array_values(array_intersect($keywords, $terms));
            if ($title === '' || count($matched) < min(2, count($keywords))) {
                continue;
            }
            $id = basename($source['id']);
            $journals[$id] ??= [
                'id' => $id, 'name' => $this->text($source['display_name'] ?? '', 500),
                'publisher' => $this->text($source['host_organization_name'] ?? '', 500) ?: null,
                'issn' => $this->text($source['issn_l'] ?? Arr::first($source['issn'] ?? []), 32) ?: null,
                'is_open_access' => $oa, 'is_in_doaj' => (bool) ($source['is_in_doaj'] ?? false),
                'works_count' => (int) ($source['works_count'] ?? 0),
                'homepage_url' => $this->safeUrl($source['homepage_url'] ?? null),
                'openalex_url' => $this->safeUrl($source['id']),
                'evidence_count' => 0, 'recent_evidence_count' => 0, 'matched_keywords' => [], 'sample_articles' => [],
            ];
            $journals[$id]['evidence_count']++;
            $journals[$id]['recent_evidence_count'] += (int) (($work['publication_year'] ?? 0) >= now()->year - 5);
            $journals[$id]['matched_keywords'] = array_values(array_unique([...$journals[$id]['matched_keywords'], ...$matched]));
            $journals[$id]['sample_articles'][] = [
                'title' => $title, 'year' => $work['publication_year'] ?? null, 'match_count' => count($matched),
                'url' => $this->safeUrl($work['doi'] ?? Arr::get($work, 'primary_location.landing_page_url')),
            ];
        }

        return $journals;
    }

    /** @param array<string, array<string, mixed>> $journals @param list<string> $keywords @return list<array<string, mixed>> */
    private function rankJournals(array $journals, array $keywords): array
    {
        return collect($journals)->map(function (array $journal) use ($keywords): array {
            $coverage = min(1, count($journal['matched_keywords']) / max(1, min(8, count($keywords))));
            $evidence = min(1, $journal['evidence_count'] / 5);
            $recent = $journal['recent_evidence_count'] / $journal['evidence_count'];
            $score = (int) round(65 * $coverage + 25 * $evidence + 10 * $recent);
            $journal['sample_articles'] = collect($journal['sample_articles'])->sortByDesc('match_count')->take(3)->values()->all();

            return $journal + [
                'fit_score' => $score,
                'fit_label' => $score >= 75 ? 'Strong topic evidence' : ($score >= 55 ? 'Good topic evidence' : 'Limited topic evidence'),
                'reasons' => [
                    $journal['evidence_count'].' distinct related '.Str::plural('article', $journal['evidence_count']).' appeared in this journal.',
                    'Matching terms: '.implode(', ', array_slice($journal['matched_keywords'], 0, 8)).'.',
                    $journal['recent_evidence_count'].' of these articles appeared in the past five years.',
                ],
            ];
        })->sortByDesc(fn (array $journal): array => [$journal['fit_score'], $journal['evidence_count']])->values()->all();
    }

    /** @param list<array<string, mixed>> $journals @return list<array<string, mixed>> */
    private function checkScopus(array $journals): array
    {
        $this->scopusUnavailable = false;
        $missing = [];
        $checks = [];
        foreach ($journals as $journal) {
            $source = $this->scopusSources->find($journal['issn']);
            if ($source) {
                if ($source['stale'] && $source['status'] === 'active') {
                    $source['status'] = 'unverified';
                    $source['label'] = 'Scopus coverage needs rechecking · '.$source['edition'];
                }
                $checks[$journal['issn']] = $source;
            }
        }
        if (filled(config('services.scopus.key'))) {
            foreach ($journals as $journal) {
                $issn = $journal['issn'];
                if (isset($checks[$issn ?? ''])) {
                    continue;
                }
                if (! is_string($issn) || ! preg_match('/^\d{4}-?\d{3}[\dX]$/i', $issn)) {
                    continue;
                }
                $key = 'scopus-serial:v1:'.strtoupper(str_replace('-', '', $issn));
                $cached = Cache::get($key);
                if (is_array($cached)) {
                    $checks[$issn] = $cached;
                } else {
                    $missing[$issn] = $key;
                }
            }
        }
        $responses = $missing === [] ? [] : Http::pool(function (Pool $pool) use ($missing): array {
            $requests = [];
            foreach ($missing as $issn => $key) {
                $headers = ['X-ELS-APIKey' => config('services.scopus.key')];
                if (filled(config('services.scopus.institution_token'))) {
                    $headers['X-ELS-Insttoken'] = config('services.scopus.institution_token');
                }
                $requests[] = $pool->as($issn)->acceptJson()->withHeaders($headers)->connectTimeout(3)->timeout(8)
                    ->get('https://api.elsevier.com/content/serial/title/issn/'.rawurlencode($issn), ['view' => 'STANDARD']);
            }

            return $requests;
        });
        foreach ($missing as $issn => $key) {
            $response = $responses[$issn] ?? null;
            $entries = $response instanceof Response && $response->successful() ? $response->json('serial-metadata-response.entry') : null;
            if (! is_array($entries)) {
                $this->scopusUnavailable = true;

                continue;
            }
            $entry = collect($entries)->first(function (mixed $entry) use ($issn): bool {
                if (! is_array($entry) || ($entry['prism:aggregationType'] ?? '') !== 'journal' || ! preg_match('/^\d+$/', (string) ($entry['source-id'] ?? ''))) {
                    return false;
                }

                return collect([$entry['prism:issn'] ?? '', $entry['prism:eIssn'] ?? ''])->contains(fn (string $value): bool => strtoupper(str_replace('-', '', $value)) === strtoupper(str_replace('-', '', $issn)));
            });
            $checks[$issn] = [
                'status' => $entry ? 'listed' : 'not_found',
                'label' => $entry ? 'Scopus source record found' : 'No matching Scopus record returned',
                'url' => $entry ? 'https://www.scopus.com/sourceid/'.$entry['source-id'] : 'https://www.scopus.com/sources',
                'checked_at' => now()->toIso8601String(),
            ];
            Cache::put($key, $checks[$issn], now()->addDay());
        }

        if ($this->scopusSources->metadata()) {
            foreach ($journals as $journal) {
                $checks[$journal['issn'] ?? ''] ??= [
                    'status' => 'not_found', 'label' => 'Not found in the imported Scopus source list',
                    'url' => 'https://www.scopus.com/sources', 'checked_at' => null,
                ];
            }
        }

        return array_map(fn (array $journal): array => $journal + [
            'scopus' => $checks[$journal['issn'] ?? ''] ?? ['status' => 'unverified', 'label' => 'Scopus indexing unverified', 'url' => 'https://www.scopus.com/sources', 'checked_at' => null],
            'wos_url' => 'https://mjl.clarivate.com/home?'.http_build_query(['issn' => $journal['issn'] ?? '', 'hide_exact_match_fl' => 'true']),
        ], $journals);
    }

    private function workFilters(int $recentYears): string
    {
        return 'type:article'.($recentYears > 0 ? ',from_publication_date:'.now()->subYears($recentYears)->startOfYear()->toDateString() : '');
    }

    /** @return list<string> */
    private function keywords(string $query, string $context): array
    {
        $weights = array_count_values($this->words($context));
        foreach ($this->words($query) as $word) {
            $weights[$word] = ($weights[$word] ?? 0) + 4;
        }
        arsort($weights);

        return array_slice(array_keys($weights), 0, 20);
    }

    /** @return list<string> */
    private function words(string $value): array
    {
        return array_values(array_filter(preg_split('/[^\pL\pN]+/u', Str::lower(strip_tags($value))) ?: [], fn (string $word): bool => Str::length($word) >= 3 && ! in_array($word, self::STOP_WORDS, true)));
    }

    private function text(mixed $value, int $limit): string
    {
        return Str::limit(Str::squish(strip_tags(is_string($value) ? $value : '')), $limit, '');
    }

    private function safeUrl(mixed $url): ?string
    {
        return is_string($url) && filter_var($url, FILTER_VALIDATE_URL) && in_array(parse_url($url, PHP_URL_SCHEME), ['http', 'https'], true) ? $url : null;
    }
}

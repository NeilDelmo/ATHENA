<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class JournalRecommendationService
{
    private const SOURCE = 'OpenAlex';

    /**
     * @var list<string>
     */
    private const STOP_WORDS = [
        'about', 'after', 'among', 'and', 'are', 'based', 'between', 'from',
        'into', 'journal', 'paper', 'publication', 'research', 'study', 'that',
        'the', 'their', 'this', 'through', 'using', 'with',
    ];

    /**
     * @return array{
     *     results: list<array<string, mixed>>,
     *     query: string,
     *     related_articles: int,
     *     checked_at: string,
     *     source: string,
     *     methodology: string
     * }
     */
    public function recommend(
        string $query,
        ?string $context = null,
        bool $openAccessOnly = false,
        int $recentYears = 10,
    ): array {
        $query = $this->text($query, 500);
        $context = $this->text($context, 3000);
        $searchText = Str::limit(Str::squish($query.' '.$context), 1500, '');
        $parameters = [
            'search' => $searchText,
            'filter' => $this->workFilters($recentYears),
            'per-page' => 50,
        ];

        $payload = $this->getWorks($parameters);
        $keywords = $this->keywords($query.' '.$context);
        $journals = $this->aggregateJournals($payload['results'], $keywords, $openAccessOnly);

        return [
            'results' => $this->rankJournals($journals, $keywords),
            'query' => $query,
            'related_articles' => count($payload['results']),
            'checked_at' => $payload['_checked_at'],
            'source' => self::SOURCE,
            'methodology' => 'Ranked from journals used by related OpenAlex-indexed articles. Fit is not an acceptance, quality, or legitimacy guarantee.',
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $works
     * @param  list<string>  $keywords
     * @return array<string, array<string, mixed>>
     */
    private function aggregateJournals(array $works, array $keywords, bool $openAccessOnly): array
    {
        $journals = [];

        foreach ($works as $work) {
            $source = Arr::get($work, 'primary_location.source');
            if (! is_array($source) || ! filled($source['id'] ?? null)) {
                continue;
            }

            $sourceType = Str::lower((string) ($source['type'] ?? 'journal'));
            if ($sourceType !== '' && $sourceType !== 'journal') {
                continue;
            }

            $isOpenAccess = (bool) ($source['is_oa'] ?? false) || (bool) ($source['is_in_doaj'] ?? false);
            if ($openAccessOnly && ! $isOpenAccess) {
                continue;
            }

            $sourceId = basename((string) $source['id']);
            $title = $this->text($work['display_name'] ?? $work['title'] ?? '', 1000);
            if ($sourceId === '' || $title === '') {
                continue;
            }

            $searchableWork = Str::lower($title.' '.collect($work['topics'] ?? [])->pluck('display_name')->join(' '));
            $matchedKeywords = collect($keywords)
                ->filter(fn (string $keyword): bool => Str::contains($searchableWork, $keyword))
                ->values()
                ->all();

            if (! isset($journals[$sourceId])) {
                $journals[$sourceId] = [
                    'id' => $sourceId,
                    'name' => $this->text($source['display_name'] ?? 'Unnamed journal', 500),
                    'publisher' => $this->text($source['host_organization_name'] ?? '', 500) ?: null,
                    'issn' => $this->text($source['issn_l'] ?? Arr::first($source['issn'] ?? []), 32) ?: null,
                    'is_open_access' => $isOpenAccess,
                    'is_in_doaj' => (bool) ($source['is_in_doaj'] ?? false),
                    'works_count' => (int) ($source['works_count'] ?? 0),
                    'cited_by_count' => (int) ($source['cited_by_count'] ?? 0),
                    'homepage_url' => $this->safeUrl($source['homepage_url'] ?? null),
                    'openalex_url' => $this->safeUrl($source['id'] ?? null),
                    'evidence_count' => 0,
                    'recent_evidence_count' => 0,
                    'matched_keywords' => [],
                    'sample_articles' => [],
                ];
            }

            $journals[$sourceId]['evidence_count']++;
            if ((int) ($work['publication_year'] ?? 0) >= now()->year - 5) {
                $journals[$sourceId]['recent_evidence_count']++;
            }
            $journals[$sourceId]['matched_keywords'] = array_values(array_unique([
                ...$journals[$sourceId]['matched_keywords'],
                ...$matchedKeywords,
            ]));

            if (count($journals[$sourceId]['sample_articles']) < 3) {
                $journals[$sourceId]['sample_articles'][] = [
                    'title' => $title,
                    'year' => isset($work['publication_year']) ? (int) $work['publication_year'] : null,
                    'url' => $this->safeUrl($work['doi'] ?? Arr::get($work, 'primary_location.landing_page_url')),
                ];
            }
        }

        return $journals;
    }

    /**
     * @param  array<string, array<string, mixed>>  $journals
     * @param  list<string>  $keywords
     * @return list<array<string, mixed>>
     */
    private function rankJournals(array $journals, array $keywords): array
    {
        if ($journals === []) {
            return [];
        }

        $maximumEvidence = max(array_column($journals, 'evidence_count'));
        $keywordDivisor = max(1, min(6, count($keywords)));

        return collect($journals)
            ->map(function (array $journal) use ($maximumEvidence, $keywordDivisor): array {
                $evidenceStrength = $journal['evidence_count'] / max(1, $maximumEvidence);
                $keywordStrength = min(1, count($journal['matched_keywords']) / $keywordDivisor);
                $recencyStrength = $journal['recent_evidence_count'] / max(1, $journal['evidence_count']);
                $fitScore = (int) round(($evidenceStrength * 45) + ($keywordStrength * 40) + ($recencyStrength * 15));

                $reasons = [
                    $journal['evidence_count'].' related '.Str::plural('article', $journal['evidence_count']).' appeared in this journal.',
                ];
                if ($journal['matched_keywords'] !== []) {
                    $reasons[] = 'Matching topics include '.implode(', ', array_slice($journal['matched_keywords'], 0, 5)).'.';
                }
                if ($journal['is_in_doaj']) {
                    $reasons[] = 'OpenAlex identifies the journal as listed in DOAJ.';
                } elseif ($journal['is_open_access']) {
                    $reasons[] = 'OpenAlex identifies the journal as open access.';
                }

                return [
                    ...$journal,
                    'fit_score' => $fitScore,
                    'fit_label' => match (true) {
                        $fitScore >= 75 => 'Strong topic fit',
                        $fitScore >= 55 => 'Good topic fit',
                        default => 'Worth reviewing',
                    },
                    'reasons' => $reasons,
                ];
            })
            ->sortByDesc(fn (array $journal): array => [$journal['fit_score'], $journal['evidence_count'], $journal['works_count']])
            ->take(12)
            ->values()
            ->all();
    }

    /**
     * @param  array<string, mixed>  $parameters
     * @return array{results: list<array<string, mixed>>, _checked_at: string}
     */
    private function getWorks(array $parameters): array
    {
        $cacheKey = 'journal-recommendations:v1:'.hash('sha256', (string) json_encode($parameters));

        return Cache::remember($cacheKey, now()->addHours(6), function () use ($parameters): array {
            if (filled(config('services.openalex.key'))) {
                $parameters['api_key'] = config('services.openalex.key');
            }

            try {
                $response = Http::acceptJson()
                    ->connectTimeout(4)
                    ->timeout(15)
                    ->retry([200, 500], throw: false)
                    ->get('https://api.openalex.org/works', $parameters);
            } catch (ConnectionException) {
                throw ValidationException::withMessages([
                    'journal_search' => 'The journal index is unavailable right now. Try the recommendation search again later.',
                ]);
            }

            if (! $response->successful() || ! is_array($response->json('results'))) {
                throw ValidationException::withMessages([
                    'journal_search' => $response->status() === 429
                        ? 'The journal index has reached its request limit. Please wait a moment and try again.'
                        : 'The journal index returned an incomplete response. Please try again later.',
                ]);
            }

            return [
                'results' => $response->json('results'),
                '_checked_at' => now()->toIso8601String(),
            ];
        });
    }

    private function workFilters(int $recentYears): string
    {
        $filters = ['type:article'];
        if ($recentYears > 0) {
            $filters[] = 'from_publication_date:'.now()->subYears($recentYears)->startOfYear()->toDateString();
        }

        return implode(',', $filters);
    }

    /**
     * @return list<string>
     */
    private function keywords(string $value): array
    {
        $words = preg_split('/[^\pL\pN]+/u', Str::lower(strip_tags($value))) ?: [];

        return collect($words)
            ->filter(fn (string $word): bool => Str::length($word) >= 3 && ! in_array($word, self::STOP_WORDS, true))
            ->unique()
            ->take(20)
            ->values()
            ->all();
    }

    private function text(mixed $value, int $limit): string
    {
        return Str::limit(Str::squish(strip_tags(is_string($value) ? $value : '')), $limit, '');
    }

    private function safeUrl(mixed $url): ?string
    {
        if (! is_string($url) || ! filter_var($url, FILTER_VALIDATE_URL)) {
            return null;
        }

        return in_array(parse_url($url, PHP_URL_SCHEME), ['http', 'https'], true) ? $url : null;
    }
}

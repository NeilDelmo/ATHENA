<?php

namespace App\Services;

use DOMDocument;
use DOMElement;
use DOMXPath;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class ConferenceScraperService
{
    private const MIN_RELEVANCE_SCORE = 25;

    private const SOURCE = 'WikiCFP';

    private const BASE_URL = 'http://www.wikicfp.com';

    private const SEARCH_PATH = '/cfp/servlet/tool.search';

    /**
     * @var list<string>
     */
    private const STOP_WORDS = [
        'about',
        'after',
        'also',
        'and',
        'are',
        'based',
        'conference',
        'for',
        'from',
        'into',
        'journal',
        'paper',
        'publication',
        'research',
        'study',
        'that',
        'the',
        'their',
        'this',
        'title',
        'using',
        'with',
    ];

    /**
     * @var list<string>
     */
    private const LOCAL_LOCATION_TERMS = [
        'bacolod',
        'baguio',
        'batangas',
        'cagayan de oro',
        'cebu',
        'davao',
        'iloilo',
        'laguna',
        'manila',
        'pampanga',
        'philippines',
        'quezon city',
    ];

    /**
     * @var list<string>
     */
    private const ONLINE_LOCATION_TERMS = [
        'hybrid',
        'online',
        'remote',
        'virtual',
    ];

    private bool $failed = false;

    /**
     * @return array{results: list<array<string, mixed>>, failed_sources: list<string>, sources: list<string>}
     */
    public function search(string $query): array
    {
        $this->failed = false;

        try {
            $response = Http::accept('text/html')
                ->withUserAgent('Athena Research Support conference scraper')
                ->connectTimeout(8)
                ->timeout(18)
                ->get(self::BASE_URL.self::SEARCH_PATH, [
                    'q' => $query,
                    'year' => 't',
                ]);
        } catch (ConnectionException $exception) {
            $this->recordFailure($exception::class);

            return $this->emptyPayload();
        }

        if ($response->failed()) {
            $this->recordFailure('HTTP '.$response->status());

            return $this->emptyPayload();
        }

        return [
            'results' => $this->parseWikiCfpResults((string) $response->body(), $query),
            'failed_sources' => [],
            'sources' => [self::SOURCE],
        ];
    }

    public function allSourcesFailed(): bool
    {
        return $this->failed;
    }

    /**
     * @return array{results: list<array<string, mixed>>, failed_sources: list<string>, sources: list<string>}
     */
    private function emptyPayload(): array
    {
        return [
            'results' => [],
            'failed_sources' => [self::SOURCE],
            'sources' => [self::SOURCE],
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function parseWikiCfpResults(string $html, string $query): array
    {
        $keywords = $this->queryKeywords($query);
        $document = new DOMDocument;

        libxml_use_internal_errors(true);
        $document->loadHTML($html);
        libxml_clear_errors();

        $xpath = new DOMXPath($document);
        $links = $xpath->query('//a[contains(@href, "event.showcfp")]');

        if (! $links) {
            return [];
        }

        $results = [];

        foreach ($links as $link) {
            if (! $link instanceof DOMElement) {
                continue;
            }

            $row = $this->ancestorRow($link);
            $title = $this->cleanText($link->textContent);
            $url = $this->absoluteUrl($link->getAttribute('href'));

            if ($title === '' || ! $row) {
                continue;
            }

            $rowText = $this->cleanText($row->textContent);
            $nextRowText = $this->cleanText($this->nextElementText($row));
            $description = $this->description($rowText, $title, $nextRowText);
            $location = $this->matchLabel($rowText, ['Where', 'Location']);
            $scope = $this->conferenceScope($location);

            $results[] = [
                'title' => $title,
                'description' => $description,
                'location' => $location,
                'deadline' => $this->matchLabel($rowText, ['Submission Deadline', 'Deadline', 'When']),
                'event_date' => $this->matchLabel($rowText, ['When', 'Event Date']),
                'url' => $url,
                'source' => self::SOURCE,
                'scope' => $scope['scope'],
                'scope_label' => $scope['label'],
                ...$this->relevance($keywords, $title, $description),
            ];
        }

        return collect($results)
            ->filter(fn (array $result) => $this->isRelevant($keywords, $result))
            ->sortByDesc('relevance_score')
            ->unique(fn (array $result) => Str::lower($result['url'] ?: $result['title']))
            ->take(12)
            ->values()
            ->all();
    }

    private function ancestorRow(DOMElement $node): ?DOMElement
    {
        $current = $node->parentNode;

        while ($current) {
            if ($current instanceof DOMElement && Str::lower($current->nodeName) === 'tr') {
                return $current;
            }

            $current = $current->parentNode;
        }

        return null;
    }

    private function nextElementText(DOMElement $row): string
    {
        $current = $row->nextSibling;

        while ($current) {
            if ($current instanceof DOMElement) {
                return $current->textContent;
            }

            $current = $current->nextSibling;
        }

        return '';
    }

    private function description(string $rowText, string $title, string $fallback): string
    {
        $description = trim(Str::of($rowText)
            ->replace($title, '')
            ->replaceMatches('/\s+/', ' ')
            ->toString());

        if ($description === '') {
            $description = $fallback;
        }

        return $description !== ''
            ? Str::limit($description, 420)
            : 'No description available from source.';
    }

    /**
     * @param  list<string>  $labels
     */
    private function matchLabel(string $text, array $labels): ?string
    {
        $knownLabels = 'Submission Deadline|Event Date|Location|Deadline|Where|When';

        foreach ($labels as $label) {
            $pattern = '/'.preg_quote($label, '/').'\s*:\s*(.+?)(?=\s+(?:'.$knownLabels.')\s*:|$)/';

            if (preg_match($pattern, $text, $matches)) {
                return trim($matches[1]);
            }
        }

        return null;
    }

    /**
     * @return list<string>
     */
    private function queryKeywords(string $query): array
    {
        $tokens = preg_split('/[^a-z0-9]+/', Str::lower($query), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $shortTerms = ['ai', 'ar', 'ml', 'ux', 'vr'];

        return collect($tokens)
            ->filter(fn (string $token) => strlen($token) >= 3 || in_array($token, $shortTerms, true))
            ->reject(fn (string $token) => in_array($token, self::STOP_WORDS, true))
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @param  list<string>  $keywords
     * @return array{relevance_score: int, relevance_label: string, matched_keywords: list<string>}
     */
    private function relevance(array $keywords, string $title, string $description): array
    {
        $matchedKeywords = collect($keywords)
            ->filter(fn (string $keyword) => $this->containsKeyword($title, $keyword)
                || $this->containsKeyword($description, $keyword))
            ->values()
            ->all();

        $score = $keywords === []
            ? 0
            : (int) round((count($matchedKeywords) / count($keywords)) * 100);

        return [
            'relevance_score' => $score,
            'relevance_label' => $this->relevanceLabel($score),
            'matched_keywords' => $matchedKeywords,
        ];
    }

    /**
     * @param  list<string>  $keywords
     * @param  array<string, mixed>  $result
     */
    private function isRelevant(array $keywords, array $result): bool
    {
        if ($keywords === []) {
            return true;
        }

        return ($result['relevance_score'] ?? 0) >= self::MIN_RELEVANCE_SCORE;
    }

    private function containsKeyword(string $text, string $keyword): bool
    {
        $text = Str::lower($text);

        foreach ($this->keywordVariants($keyword) as $variant) {
            if (Str::contains($text, $variant)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return list<string>
     */
    private function keywordVariants(string $keyword): array
    {
        $variants = [$keyword];

        if (Str::endsWith($keyword, 'ies')) {
            $variants[] = Str::substr($keyword, 0, -3).'y';
        }

        if (Str::endsWith($keyword, 's') && strlen($keyword) > 3) {
            $variants[] = Str::substr($keyword, 0, -1);
        }

        if (Str::endsWith($keyword, 'ing') && strlen($keyword) > 5) {
            $variants[] = Str::substr($keyword, 0, -3);
        }

        if (Str::endsWith($keyword, 'education')) {
            $variants[] = 'educational';
        }

        if (Str::endsWith($keyword, 'educational')) {
            $variants[] = 'education';
        }

        if ($keyword === 'ai') {
            $variants[] = 'artificial intelligence';
        }

        return collect($variants)
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    private function relevanceLabel(int $score): string
    {
        return match (true) {
            $score >= 75 => 'Highly relevant',
            $score >= 40 => 'Relevant',
            default => 'Possible match',
        };
    }

    /**
     * @return array{scope: string, label: string}
     */
    private function conferenceScope(?string $location): array
    {
        if (! $location) {
            return ['scope' => 'unknown', 'label' => 'Scope not listed'];
        }

        $normalizedLocation = Str::lower($location);

        if (Str::contains($normalizedLocation, self::ONLINE_LOCATION_TERMS)) {
            return ['scope' => 'online', 'label' => 'Online'];
        }

        if (Str::contains($normalizedLocation, self::LOCAL_LOCATION_TERMS)) {
            return ['scope' => 'local', 'label' => 'Local'];
        }

        return ['scope' => 'international', 'label' => 'International'];
    }

    private function cleanText(mixed $value): string
    {
        if (! is_string($value)) {
            return '';
        }

        return trim((string) preg_replace('/\s+/', ' ', html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8')));
    }

    private function absoluteUrl(string $href): ?string
    {
        if ($href === '') {
            return null;
        }

        if (Str::startsWith($href, ['http://', 'https://'])) {
            return $href;
        }

        return self::BASE_URL.'/'.ltrim($href, '/');
    }

    private function recordFailure(string $reason): void
    {
        $this->failed = true;

        Log::warning('Conference scraper source failed.', [
            'source' => self::SOURCE,
            'reason' => $reason,
        ]);
    }
}

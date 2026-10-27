<?php

namespace App\Services;

use Carbon\CarbonImmutable;
use DOMDocument;
use DOMElement;
use DOMXPath;
use Illuminate\Http\Client\Pool;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

class ConferenceScraperService
{
    private const MIN_RELEVANCE_SCORE = 25;

    private const SOURCE = 'WikiCFP';

    private const BASE_URL = 'http://www.wikicfp.com';

    private const SEARCH_PATH = '/cfp/servlet/tool.search';

    private const EASYCHAIR_URL = 'https://easychair.org/cfp/';

    private const ACRONYMS = [
        'ai' => 'artificial intelligence', 'ml' => 'machine learning',
        'nlp' => 'natural language processing', 'iot' => 'internet of things',
        'llm' => 'large language models', 'ar' => 'augmented reality',
        'vr' => 'virtual reality', 'ux' => 'user experience',
    ];

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

    /** @return array<string, mixed> */
    public function search(string $query): array
    {
        $this->failed = false;
        $query = Str::squish($query);
        $keywords = $this->queryKeywords($query);
        if ($keywords === []) {
            return ['results' => [], 'sources' => [], 'failed_sources' => [],
                'warnings' => ['Enter specific research topics or methods, such as coastal monitoring or machine learning.']];
        }
        $cacheKey = 'conference-search:v3:'.hash('sha256', Str::lower($query));
        if ($cached = Cache::get($cacheKey)) {
            return $cached;
        }

        // EasyChair receives no project data: its public catalogue is matched locally.
        $requests = [
            self::BASE_URL.self::SEARCH_PATH.'?'.http_build_query(['q' => $query, 'year' => 'f'], '', '&', PHP_QUERY_RFC3986) => self::SOURCE,
            self::EASYCHAIR_URL => 'EasyChair',
        ];
        $pages = $this->fetchPages($requests);
        $categories = [];
        foreach ($pages as $url => $page) {
            if ($page !== null && $requests[$url] === self::SOURCE) {
                $categories = $this->categories($page['html'], $keywords);
            }
        }
        $categoryRequests = [];
        foreach (array_slice($categories, 0, 2) as $category) {
            foreach ([1, 2] as $page) {
                $url = self::BASE_URL.'/cfp/call?'.http_build_query(['conference' => $category, 'page' => $page], '', '&', PHP_QUERY_RFC3986);
                $categoryRequests[$url] = self::SOURCE;
            }
        }
        $pages += $this->fetchPages($categoryRequests);
        $requests += $categoryRequests;
        $results = [];
        $sources = [];
        $failedSources = [];
        foreach ($pages as $url => $page) {
            $source = $requests[$url];
            if ($page === null) {
                $failedSources[] = $source;

                continue;
            }
            $sources[] = $source;
            $items = $source === self::SOURCE ? $this->parseWikiCfpResults($page['html']) : $this->parseEasyChairResults($page['html']);
            foreach ($items as $item) {
                if ($item['event_starts_on'] !== null && $item['event_starts_on'] < now()->toDateString()) {
                    continue;
                }
                $item = [...$item, 'source_checked_at' => $page['checked_at'], ...$this->relevance($keywords, $item['title'], $item['description'])];
                if ($this->isRelevant($keywords, $item)) {
                    $results[] = $item;
                }
            }
        }
        $sources = array_values(array_intersect([self::SOURCE, 'EasyChair'], $sources));
        $failedSources = array_values(array_intersect([self::SOURCE, 'EasyChair'], $failedSources));
        $this->failed = $sources === [];
        $results = collect($results)->sortBy(fn (array $item): array => [
            -$item['relevance_score'], $item['deadline_status'] === 'open' ? 0 : 1,
            $item['submission_deadline'] ?? '9999', $item['title'],
        ])->unique(fn (array $item): string => $this->identity($item))->values()->all();
        $payload = [
            'results' => $results, 'query' => $query,
            'failed_sources' => $failedSources,
            'sources' => $sources,
            'warnings' => $failedSources !== [] ? ['Some conference listings could not be checked: '.implode(', ', $failedSources).'. Available matches are shown.'] : [],
            'checked_at' => now()->toIso8601String(),
        ];
        if (! $this->failed) {
            Cache::put($cacheKey, $payload, now()->addMinutes($failedSources === [] ? 30 : 1));
        }

        return $payload;
    }

    public function suggestedQuery(string $title): string
    {
        return Str::limit(implode(' ', array_slice($this->queryKeywords($title), 0, 7)), 140, '');
    }

    public function allSourcesFailed(): bool
    {
        return $this->failed;
    }

    /** @param array<string, string> $requests @return array<string, array{html: string, checked_at: string}|null> */
    private function fetchPages(array $requests): array
    {
        $pages = [];
        $pending = [];
        foreach ($requests as $url => $source) {
            $cached = Cache::get('conference-page:v1:'.hash('sha256', $url));
            if (is_array($cached)) {
                $pages[$url] = $cached;
            } else {
                $pending[$url] = $source;
            }
        }
        if ($pending === []) {
            return $pages;
        }
        $responses = Http::pool(function (Pool $pool) use ($pending): array {
            $requests = [];
            foreach ($pending as $url => $source) {
                $requests[] = $pool->as($url)->accept('text/html')->withUserAgent('Athena Research Support conference discovery')
                    ->connectTimeout(4)->timeout(12)->get($url);
            }

            return $requests;
        }, concurrency: 4);
        foreach ($pending as $url => $source) {
            $response = $responses[$url];
            if ($response instanceof Throwable || ! $response->successful()
                || ! str_contains(strtolower($response->body()), '<table')
                || ($source === 'EasyChair' && ! str_contains($response->body(), 'Acronym'))) {
                Log::warning('Conference discovery page unavailable.', ['source' => $source, 'reason' => $response instanceof Throwable ? $response::class : 'HTTP '.$response->status().' or unexpected markup']);
                $pages[$url] = null;

                continue;
            }
            $pages[$url] = ['html' => $response->body(), 'checked_at' => now()->toIso8601String()];
            Cache::put('conference-page:v1:'.hash('sha256', $url), $pages[$url], now()->addMinutes(30));
        }

        return $pages;
    }

    /** @param list<string> $keywords @return list<string> */
    private function categories(string $html, array $keywords): array
    {
        $xpath = $this->xpath($html);
        $categories = [];
        foreach ($xpath->query('//a[contains(@href, "conference=")]') as $link) {
            $url = $this->absoluteUrl($link->getAttribute('href'));
            if ($url === null || parse_url($url, PHP_URL_PATH) !== '/cfp/call') {
                continue;
            }
            parse_str((string) parse_url($url, PHP_URL_QUERY), $params);
            $category = $params['conference'] ?? null;
            if (is_string($category) && strlen($category) <= 140) {
                $score = $this->relevance($keywords, $category, '');
                if ($this->isRelevant($keywords, $score)) {
                    $categories[$category] = $score['relevance_score'];
                }
            }
        }
        arsort($categories);

        return array_keys($categories);
    }

    /** @return list<array<string, mixed>> */
    private function parseEasyChairResults(string $html): array
    {
        $xpath = $this->xpath($html);
        $results = [];
        foreach ($xpath->query('//tr[count(td) = 6 and td[1]/a[starts-with(@href, "/cfp/")]]') as $row) {
            $cells = $xpath->query('./td', $row);
            $link = $xpath->query('./td[1]/a', $row)->item(0);
            $href = $link->getAttribute('href');
            if (! preg_match('~^/cfp/[a-zA-Z0-9_.\'-]+$~D', $href)) {
                continue;
            }
            $location = $this->cleanText($cells->item(2)->textContent);
            $deadline = $this->cleanText($cells->item(3)->textContent);
            $event = $this->cleanText($cells->item(4)->textContent);
            $scope = $this->conferenceScope($location);
            $deadlineDate = $this->isoDate($deadline);
            $results[] = [
                'title' => $this->cleanText($link->textContent).': '.$this->cleanText($cells->item(1)->textContent),
                'description' => $this->cleanText($cells->item(5)->textContent),
                'location' => $location, 'deadline' => $deadline ?: null, 'event_date' => $event ?: null,
                'submission_deadline' => $deadlineDate, 'event_starts_on' => $this->isoDate($event),
                'deadline_status' => $deadlineDate === null ? 'unknown' : ($deadlineDate < now()->toDateString() ? 'closed' : 'open'),
                'url' => 'https://easychair.org'.$href, 'source' => 'EasyChair',
                'scope' => $scope['scope'], 'scope_label' => $scope['label'],
            ];
        }

        return $results;
    }

    private function xpath(string $html): DOMXPath
    {
        $document = new DOMDocument;
        $previous = libxml_use_internal_errors(true);
        $document->loadHTML('<?xml encoding="UTF-8">'.$html);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        return new DOMXPath($document);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function parseWikiCfpResults(string $html): array
    {
        $xpath = $this->xpath($html);
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

            if ($title === '' || $url === null || ! $row) {
                continue;
            }

            $rowText = $this->cleanText($row->textContent);
            $nextRowText = $this->cleanText($this->nextElementText($row));
            $metadata = $this->metadataCells($row);
            $titleCells = $xpath->query('./td', $row);
            if ($metadata !== [] && $titleCells?->length > 1) {
                $longTitle = $this->cleanText($titleCells->item(1)->textContent);
                if ($longTitle !== '' && ! Str::contains($title, $longTitle)) {
                    $title .= ': '.$longTitle;
                }
            }
            $description = $this->description($rowText, $title, $nextRowText);
            $location = $this->matchLabel($rowText, ['Where', 'Location']) ?? ($metadata[1] ?? null);
            $deadline = $this->matchLabel($rowText, ['Submission Deadline', 'Deadline']) ?? ($metadata[2] ?? null);
            $eventDate = $this->matchLabel($rowText, ['When', 'Event Date']) ?? ($metadata[0] ?? null);
            $deadlineDate = $this->isoDate($deadline);
            $scope = $this->conferenceScope($location);

            $results[] = [
                'title' => $title,
                'description' => $description,
                'location' => $location,
                'deadline' => $deadline,
                'event_date' => $eventDate,
                'submission_deadline' => $deadlineDate,
                'event_starts_on' => $this->isoDate($eventDate),
                'deadline_status' => $deadlineDate === null ? 'unknown' : ($deadlineDate < now()->toDateString() ? 'closed' : 'open'),
                'url' => $url,
                'source' => self::SOURCE,
                'scope' => $scope['scope'],
                'scope_label' => $scope['label'],
            ];
        }

        return $results;
    }

    /** @return list<string> */
    private function metadataCells(DOMElement $row): array
    {
        $next = $row->nextSibling;
        while ($next && ! $next instanceof DOMElement) {
            $next = $next->nextSibling;
        }
        if (! $next instanceof DOMElement || $next->getElementsByTagName('a')->length > 0) {
            return [];
        }
        $cells = [];
        foreach ($next->getElementsByTagName('td') as $cell) {
            $cells[] = $this->cleanText($cell->textContent);
        }

        return count($cells) >= 3 ? $cells : [];
    }

    private function isoDate(?string $date): ?string
    {
        if (! $date || ! preg_match('/^([A-Za-z]{3,9} \\d{1,2}, \\d{4}|\\d{4}-\\d{2}-\\d{2})/', trim($date), $match)) {
            return null;
        }
        try {
            return CarbonImmutable::parse($match[1])->toDateString();
        } catch (Throwable) {
            return null;
        }
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
        $tokens = preg_split('/[^a-z0-9]+/', $this->normalizedText($query), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $shortTerms = ['ai', 'ar', 'ml', 'ux', 'vr'];

        return collect($tokens)
            ->filter(fn (string $token) => (strlen($token) >= 3 || in_array($token, $shortTerms, true)) && ! preg_match('/^(?:19|20)\d{2}$/', $token))
            ->reject(fn (string $token) => in_array($token, self::STOP_WORDS, true))
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @param  list<string>  $keywords
     * @return array{relevance_score: int, relevance_label: string, matched_keywords: list<string>, match_reasons: list<string>}
     */
    private function relevance(array $keywords, string $title, string $description): array
    {
        $matchedKeywords = collect($keywords)
            ->filter(fn (string $keyword) => $this->containsKeyword($title, $keyword)
                || $this->containsKeyword($description, $keyword))
            ->values()
            ->all();

        $titleMatches = array_values(array_filter($matchedKeywords, fn (string $keyword): bool => $this->containsKeyword($title, $keyword)));
        $topicMatches = array_values(array_diff($matchedKeywords, $titleMatches));
        $score = $keywords === [] ? 0 : (int) round((count($titleMatches) + count($topicMatches) * 0.75) / count($keywords) * 100);

        return [
            'relevance_score' => $score,
            'relevance_label' => $this->relevanceLabel($score),
            'matched_keywords' => $matchedKeywords,
            'match_reasons' => array_values(array_filter([
                $titleMatches !== [] ? 'Title matches: '.implode(', ', $titleMatches) : null,
                $topicMatches !== [] ? 'Listing text matches: '.implode(', ', $topicMatches) : null,
            ])),
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

        return ($result['relevance_score'] ?? 0) >= self::MIN_RELEVANCE_SCORE
            && count($result['matched_keywords'] ?? []) >= (count($keywords) >= 3 ? 2 : 1);
    }

    private function containsKeyword(string $text, string $keyword): bool
    {
        $text = $this->normalizedText($text);

        foreach ($this->keywordVariants($keyword) as $variant) {
            if (preg_match('/(?<![a-z0-9])'.preg_quote($variant, '/').'(?![a-z0-9])/', $text)) {
                return true;
            }
        }

        return false;
    }

    private function normalizedText(string $text): string
    {
        $text = Str::lower(Str::ascii($text));
        $text = preg_replace_callback('/\b('.implode('|', array_keys(self::ACRONYMS)).')\b/', fn (array $match): string => self::ACRONYMS[$match[1]], $text);

        return Str::squish(preg_replace('/[^a-z0-9]+/', ' ', $text));
    }

    /** @param array<string, mixed> $item */
    private function identity(array $item): string
    {
        $title = $item['source'] === 'EasyChair'
            ? Str::after($item['title'], ': ')
            : preg_replace('/^[A-Z0-9][A-Z0-9 .&-]{1,30}: /', '', $item['title']);
        $normalized = preg_replace('/[^a-z0-9]+/', ' ', Str::lower(Str::ascii($title)));

        return $normalized.'|'.($item['event_starts_on'] ?? $item['url']);
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

        if (Str::endsWith($keyword, 'y') && ! preg_match('/[aeiou]y$/', $keyword)) {
            $variants[] = Str::substr($keyword, 0, -1).'ies';
        }

        $variants[] = $keyword.(Str::endsWith($keyword, ['s', 'x', 'ch', 'sh']) ? 'es' : 's');

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

        $url = Str::startsWith($href, ['http://', 'https://']) ? $href : self::BASE_URL.'/'.ltrim($href, '/');
        if (! in_array(parse_url($url, PHP_URL_HOST), ['www.wikicfp.com', 'wikicfp.com'], true)
            || parse_url($url, PHP_URL_USER) !== null || parse_url($url, PHP_URL_PASS) !== null
            || ! in_array(parse_url($url, PHP_URL_PATH), ['/cfp/call', '/cfp/servlet/event.showcfp'], true)) {
            return null;
        }

        return $url;
    }
}

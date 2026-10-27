<?php

namespace App\Services;

use Illuminate\Contracts\Filesystem\FileNotFoundException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use JsonException;

class ResearchManualRetriever
{
    private const CACHE_VERSION = 9;

    /** @var list<string> */
    private const STOP_WORDS = [
        'about', 'after', 'also', 'an', 'and', 'are', 'as', 'at', 'be', 'before', 'by', 'can', 'could',
        'do', 'does', 'during', 'explain', 'for', 'from', 'give', 'had', 'has', 'have', 'help', 'how',
        'i', 'in', 'into', 'is', 'it', 'its', 'may', 'me', 'more', 'must', 'my', 'of', 'on', 'or', 'our',
        'please', 'research', 'researcher', 'manual', 'university', 'institutional', 'policy', 'process',
        'procedure', 'rule', 'requirement', 'required', 'need', 'should', 'some', 'such', 'than', 'that',
        'the', 'their', 'them', 'then', 'there', 'these', 'they', 'this', 'those', 'to', 'under', 'us',
        'was', 'were', 'what', 'when', 'where', 'which', 'who', 'why', 'will', 'with', 'would', 'you',
        'your', 'much', 'receive', 'allowed', 'according', 'state', 'batangas', 'show', 'list', 'tell', 'say', 'section',
        'through', 'developed', 'often', 'ongoing', 'earned', 'part',
    ];

    /** @var list<string> */
    private const MANUAL_TOPICS = [
        'funding', 'eligibility', 'honorarium', 'incentive', 'clearance', 'ethics', 'publication',
        'dissemination', 'leave', 'royalty', 'patent', 'commercialization', 'ntp', 'approval', 'relatedliterature',
    ];

    /**
     * @param  list<array{role?: string, content?: string}>  $recentMessages
     * @return list<array<string, mixed>>
     */
    public function retrieve(string $question, array $recentMessages = [], ?string $indexPath = null): array
    {
        $question = $this->effectiveQuestion($question, $recentMessages);
        $requestedSections = $this->sectionReferences($question);
        $prefersForms = preg_match('/\b(?:template|blank|sample)s?\b|\b(?:show|open|download|get|find|view)\b.*\b(?:form|checklist)s?\b/i', $question) === 1
            && preg_match('/\b(?:required|requirements?|conditions?|mandatory)\b/i', $question) !== 1;
        $searchQuestion = $this->searchQuestion($question);

        if (! $prefersForms && preg_match('/\b(?:required|requirements?|conditions?|mandatory)\b/i', $question)) {
            $searchQuestion = preg_replace('/\bforms?\b/i', '', $searchQuestion) ?? $searchQuestion;
        }

        $terms = array_values(array_unique($this->tokens($searchQuestion)));

        if (($terms === [] && $requestedSections === []) || ! ($document = $this->readDocument($indexPath))) {
            return [];
        }

        $maximumChunks = max(1, min(5, (int) config('research_assistant.manual.maximum_chunks', 4)));
        $minimumScore = max(0.0, (float) config('research_assistant.manual.minimum_score', 1.0));
        $seconds = max(0, (int) config('research_assistant.manual.retrieval_cache_seconds', 3600));
        $key = 'research-manual:results:'.self::CACHE_VERSION.':'.hash('sha256', json_encode([
            $document['fingerprint'], $terms, $requestedSections, $maximumChunks, $minimumScore, $prefersForms,
        ], JSON_THROW_ON_ERROR));
        $retrieve = function () use ($document, $terms, $requestedSections, $maximumChunks, $minimumScore, $prefersForms): array {
            $index = Cache::remember(
                'research-manual:index:'.self::CACHE_VERSION.':'.$document['fingerprint'],
                max(3600, (int) config('research_assistant.manual.retrieval_cache_seconds', 3600)),
                fn (): ?array => $this->prepareIndex($document['json'], $document['fingerprint']),
            );

            return $index ? $this->rank($index, $terms, $requestedSections, $maximumChunks, $minimumScore, $prefersForms) : [];
        };

        $sources = $seconds > 0 ? Cache::remember($key, $seconds, $retrieve) : $retrieve();

        return array_map(fn (array $source): array => [...$source,
            'url' => route('research-manuals.read', ['version' => $source['document_version']]).'#page='.$source['page_start'],
        ], $sources);
    }

    /** @param list<array{role?: string, content?: string}> $recentMessages */
    public function isManualQuestion(string $question, array $recentMessages = []): bool
    {
        if (! config('research_assistant.manual.enabled', true)) {
            return false;
        }

        $question = $this->effectiveQuestion($question, $recentMessages);

        return preg_match('/\b(?:manual|university|institutional|batangas state|bsu|intellectual property|gad|notice to proceed|progress reports?)\b/i', $question) === 1
            || array_intersect($this->tokens($question), self::MANUAL_TOPICS) !== [];
    }

    /** @return array{json: string, fingerprint: string}|null */
    private function readDocument(?string $indexPath = null): ?array
    {
        if (! config('research_assistant.manual.enabled', true)) {
            return null;
        }

        $path = $indexPath ?? (string) config('research_assistant.manual.index_path', storage_path('app/private/research-manual/index.json'));

        if (! File::isFile($path)) {
            return null;
        }

        $lock = @fopen($path.'.lock', 'c');
        if ($lock === false) {
            return null;
        }

        try {
            if (! flock($lock, LOCK_SH)) {
                return null;
            }

            try {
                $json = File::get($path);
            } catch (FileNotFoundException) {
                return null;
            }

            return ['json' => $json, 'fingerprint' => hash('sha256', $json)];
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }

    /** @return array<string, mixed>|null */
    private function prepareIndex(string $json, string $fingerprint): ?array
    {
        try {
            $document = json_decode($json, true, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return null;
        }

        if (! is_array($document) || ($document['schema_version'] ?? null) !== 1
            || ! is_string($document['version'] ?? null) || preg_match('/^[a-f0-9]{64}$/i', $document['version']) !== 1
            || ! is_string($document['title'] ?? null) || blank($document['title']) || ! is_array($document['chunks'] ?? null)) {
            return null;
        }

        $chunks = [];
        $documentFrequencies = [];
        $chapters = [];

        foreach ($document['chunks'] as $chunk) {
            if (is_array($chunk) && preg_match('/^([IVX]+)\.\s+(.+)$/', (string) ($chunk['section'] ?? ''), $chapter)) {
                $number = array_search($chapter[1], ['I', 'II', 'III', 'IV', 'V', 'VI', 'VII', 'VIII', 'IX', 'X'], true);

                if ($number !== false) {
                    $chapters[$number + 1] = $chapter[2];
                }
            }
        }

        foreach ($document['chunks'] as $chunk) {
            if (! is_array($chunk) || ! is_string($chunk['id'] ?? null) || isset($chunks[$chunk['id']])
                || ! is_string($chunk['section'] ?? null) || ! is_string($chunk['content'] ?? null) || blank($chunk['content'])
                || ! is_int($chunk['page_start'] ?? null) || ! is_int($chunk['page_end'] ?? null)
                || $chunk['page_start'] < 1 || $chunk['page_end'] < $chunk['page_start']) {
                continue;
            }

            preg_match('/^(\d+)\./', $chunk['section'], $sectionNumber);
            $chapter = $chapters[(int) ($sectionNumber[1] ?? 0)] ?? '';
            $headingTerms = $this->tokens($chunk['section'].' '.$chapter);
            $eligibilityTerms = preg_match('/\b(?:only|all|must|required|at least)\b/i', $chunk['content'])
                && preg_match('/\b(?:permanent|plantilla|qualifications?|eligible|eligibility)\b/i', $chunk['content'])
                ? ['eligibility'] : [];
            $frequencies = array_count_values([
                ...$this->tokens($chunk['content']),
                ...$headingTerms,
                ...$eligibilityTerms,
                ...$headingTerms,
            ]);

            foreach (array_keys($frequencies) as $term) {
                $documentFrequencies[$term] = ($documentFrequencies[$term] ?? 0) + 1;
            }

            $chunks[$chunk['id']] = [...$chunk,
                'source_kind' => ($chunk['source_kind'] ?? 'policy') === 'appendix' ? 'appendix' : 'policy',
                'heading_terms' => array_values(array_unique($headingTerms)),
                'frequencies' => $frequencies, 'length' => array_sum($frequencies),
            ];
        }

        return [
            'fingerprint' => $fingerprint,
            'version' => $document['version'],
            'title' => $document['title'],
            'chunks' => array_values($chunks),
            'document_frequencies' => $documentFrequencies,
            'average_length' => max(1, array_sum(array_column($chunks, 'length')) / max(1, count($chunks))),
        ];
    }

    /**
     * @param  array<string, mixed>  $index
     * @param  list<string>  $terms
     * @param  list<string>  $requestedSections
     * @return list<array<string, mixed>>
     */
    private function rank(array $index, array $terms, array $requestedSections, int $maximumChunks, float $minimumScore, bool $prefersForms): array
    {
        $sources = [];
        $documentCount = count($index['chunks']);
        $asksLiteratureCount = in_array('relatedliterature', $terms, true)
            && (array_intersect($terms, ['many', 'minimum', 'maximum', 'least', 'most', 'count', 'number', 'enough']) !== []
                || preg_grep('/^\d+$/', $terms) !== []);

        foreach ($index['chunks'] as $chunk) {
            $score = $this->sectionScore($chunk['section'], $requestedSections);

            if ($requestedSections !== [] && $score === 0.0) {
                continue;
            }

            $matchedTerms = array_intersect($terms, array_keys($chunk['frequencies']));

            if ($requestedSections === []) {
                $matchesRelatedLiterature = in_array('relatedliterature', $terms, true)
                    && in_array('relatedliterature', $matchedTerms, true);

                if (in_array('relatedliterature', $terms, true) && ! $matchesRelatedLiterature) {
                    continue;
                }

                if (! $matchesRelatedLiterature && (count($matchedTerms) < min(2, count($terms)) || count($matchedTerms) / count($terms) < 0.4)) {
                    continue;
                }
            }

            foreach ($matchedTerms as $term) {
                $frequency = $chunk['frequencies'][$term];
                $documentFrequency = $index['document_frequencies'][$term];
                $inverseFrequency = log(1 + ($documentCount - $documentFrequency + 0.5) / ($documentFrequency + 0.5));
                $score += $inverseFrequency * ($frequency * 2.2)
                    / ($frequency + 1.2 * (0.25 + 0.75 * $chunk['length'] / $index['average_length']));
                $score += in_array($term, $chunk['heading_terms'], true) ? $inverseFrequency * 1.5 : 0;
                $score += in_array($term, ['relatedliterature', 'intellectualproperty'], true) ? $inverseFrequency * 4 : 0;
            }

            $hasWrittenLiteratureRequirement = in_array('relatedliterature', $matchedTerms, true)
                && preg_match('/\b(?:minimum(?: of)?|at least|maximum(?: of)?|no (?:more|less) than)\s+(?:\d+|one|two|three|four|five|six|seven|eight|nine|ten)\s+(?:related\s+)?(?:[a-z]+\/)?(?:literature|studies|references?|sources?)\b/i', $chunk['content']) === 1;
            $score *= ($chunk['source_kind'] === 'appendix') === $prefersForms || $hasWrittenLiteratureRequirement ? 1.25 : 0.6;
            $score += $asksLiteratureCount && $hasWrittenLiteratureRequirement ? 10.0 : 0.0;

            if ($score < $minimumScore) {
                continue;
            }

            $pages = $chunk['page_start'] === $chunk['page_end']
                ? 'p. '.$chunk['page_start']
                : 'pp. '.$chunk['page_start'].'–'.$chunk['page_end'];
            $sources[] = [
                'title' => $index['title'].' — '.$chunk['section'].' ('.$pages.')',
                'type' => 'Official Research Manual',
                'category' => $chunk['source_kind'] === 'appendix' ? 'Manual appendix' : 'Institutional policy',
                'content' => $chunk['content'],
                'url' => null,
                'section' => $chunk['section'],
                'page_start' => $chunk['page_start'],
                'page_end' => $chunk['page_end'],
                'document_version' => $index['version'],
                'source_kind' => $chunk['source_kind'],
                'chunk_id' => $chunk['id'],
                'score' => round($score, 4),
            ];
        }

        $ranked = collect($sources)->sortByDesc('score')->values()->all();
        $selected = [];
        $families = [];

        if (count($requestedSections) > 1) {
            foreach ($requestedSections as $requestedSection) {
                $match = collect($ranked)->first(fn (array $source): bool => $this->sectionScore($source['section'], [$requestedSection]) > 0);

                if ($match && ! in_array($match['chunk_id'], array_column($selected, 'chunk_id'), true)) {
                    $selected[] = $match;
                }
            }

            return array_slice($selected, 0, $maximumChunks);
        }

        foreach ($ranked as $source) {
            preg_match('/^(\d+\.\d+)\b/', $source['section'], $family);
            $family = $family[1] ?? $source['section'];

            if ($requestedSections === [] && ($families[$family] ?? 0) >= 2) {
                continue;
            }

            $selected[] = $source;
            $families[$family] = ($families[$family] ?? 0) + 1;

            if (count($selected) >= $maximumChunks) {
                break;
            }
        }

        return $this->includeConditions($selected, $index, $terms, $maximumChunks, $prefersForms);
    }

    /**
     * @param  list<array<string, mixed>>  $selected
     * @param  array<string, mixed>  $index
     * @param  list<string>  $terms
     * @return list<array<string, mixed>>
     */
    private function includeConditions(array $selected, array $index, array $terms, int $maximumChunks, bool $prefersForms): array
    {
        if ($prefersForms || $maximumChunks < 2 || $selected === []
            || array_intersect($terms, ['incentive', 'honorarium', 'eligibility', 'funding']) === []) {
            return $selected;
        }

        $primary = $selected[0];

        if ($primary['source_kind'] !== 'policy') {
            return $selected;
        }

        foreach ($index['chunks'] as $chunk) {
            if ($chunk['section'] !== $primary['section'] || $chunk['source_kind'] !== 'policy'
                || $chunk['page_end'] < $primary['page_start'] - 3 || $chunk['page_start'] > $primary['page_end'] + 1
                || preg_match('/\b(?:only|must|shall|required|eligible|eligibility|except|conditions?|qualify)\b/i', $chunk['content']) !== 1) {
                continue;
            }

            if (in_array($chunk['id'], array_column($selected, 'chunk_id'), true)) {
                return $selected;
            }

            $pages = $chunk['page_start'] === $chunk['page_end'] ? 'p. '.$chunk['page_start'] : 'pp. '.$chunk['page_start'].'–'.$chunk['page_end'];
            $conditions = [...$primary,
                'title' => $index['title'].' — '.$chunk['section'].' ('.$pages.')',
                'content' => $chunk['content'], 'page_start' => $chunk['page_start'], 'page_end' => $chunk['page_end'],
                'chunk_id' => $chunk['id'], 'score' => $primary['score'] * 0.95,
            ];
            array_splice($selected, 1, 0, [$conditions]);

            return array_slice($selected, 0, $maximumChunks);
        }

        return $selected;
    }

    private function searchQuestion(string $question): string
    {
        $question = preg_replace('/\bsections?\s+\d+(?:\.\d+)+\b/i', '', $question) ?? $question;
        $question = preg_replace('/\bwho\s+(?:(?:may|can|is allowed to)\s+)?(?:apply|qualifies?)\b/i', 'eligibility application', $question) ?? $question;

        if (preg_match('/\b(?:study|research|project)\b/i', $question)) {
            $question = preg_replace('/\bupdates?\b/i', 'progress report', $question) ?? $question;
        }

        if (preg_match('/\bimplementation|\bstart(?:s|ing)?\b/i', $question)) {
            $question = preg_replace('/\bauthorization\b/i', 'notice to proceed', $question) ?? $question;
        }

        return $question;
    }

    /** @return list<string> */
    private function sectionReferences(string $question): array
    {
        preg_match_all('/\bsections?\s+\d+(?:\.\d+)+(?:\s*(?:,|and|&)\s*\d+(?:\.\d+)+)*/i', $question, $references);
        preg_match_all('/\d+(?:\.\d+)+/', implode(' ', $references[0]), $matches);

        return array_values(array_unique($matches[0]));
    }

    /** @param list<string> $requestedSections */
    private function sectionScore(string $section, array $requestedSections): float
    {
        if ($requestedSections === []) {
            return 0.0;
        }

        preg_match_all('/(?<![\d.])\d+(?:\.\d+)+(?![\d.])/', $section, $matches);
        $number = end($matches[0]);

        if (! is_string($number)) {
            return 0.0;
        }

        $score = 0.0;

        foreach ($requestedSections as $requestedSection) {
            if ($number === $requestedSection) {
                return 100.0;
            }

            if (Str::startsWith($number, $requestedSection.'.')) {
                $score = 90.0;
            }
        }

        return $score;
    }

    /** @param list<array{role?: string, content?: string}> $recentMessages */
    private function effectiveQuestion(string $question, array $recentMessages): string
    {
        $question = trim($question);

        if (Str::length($question) > 160
            || preg_match('/^(?:what about|how about|and\b|tell me more|more details)|\b(?:it|that|this|those|these|them|they)\b/i', $question) !== 1) {
            return $question;
        }

        $currentTerms = array_values(array_unique($this->tokens($question)));

        foreach (array_reverse($recentMessages, true) as $messageIndex => $message) {
            if (($message['role'] ?? null) !== 'user' || ! is_string($message['content'] ?? null)
                || blank($message['content']) || Str::lower(trim($message['content'])) === Str::lower($question)) {
                continue;
            }

            $previous = Str::limit(trim($message['content']), 384, '');
            $relatesToPrevious = $currentTerms === []
                || array_diff($currentTerms, ['eligibility', 'condition', 'amount', 'fee', 'limit', 'application', 'form', 'template']) === []
                || count(array_intersect($currentTerms, $this->tokens($previous))) / count($currentTerms) >= 0.5;

            return $relatesToPrevious
                ? $question."\n".$this->effectiveQuestion($previous, array_slice($recentMessages, 0, $messageIndex))
                : $question;
        }

        return $question;
    }

    /** @return list<string> */
    private function tokens(string $text): array
    {
        $text = preg_replace('/\b(?:rrl|rrs|review\s+of\s+(?:related\s+)?(?:literature|studies)|related\s+(?:literature|studies)|appropriate\s+literature\s+cited)\b/i', 'relatedliterature', $text) ?? $text;
        $text = preg_replace('/\bintellectual\s+property\b/i', 'intellectualproperty', $text) ?? $text;
        $words = preg_split('/[^a-z0-9]+/', Str::lower(Str::ascii($text)), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $tokens = [];

        foreach ($words as $word) {
            if (in_array($word, self::STOP_WORDS, true)) {
                continue;
            }

            $word = match ($word) {
                'fund', 'funds', 'funding', 'funded', 'grant', 'grants' => 'funding',
                'study', 'studies' => 'project',
                'approve', 'approves', 'approved', 'approving', 'approval' => 'approval',
                'buy', 'buying', 'purchase', 'purchasing', 'procure', 'procurement', 'acquire', 'acquires', 'acquiring', 'acquisition' => 'acquisition',
                'own', 'owns', 'owned', 'ownership' => 'ownership',
                'invention', 'inventions', 'patent', 'patents', 'intellectualproperty' => 'intellectualproperty',
                'submit', 'submits', 'submitted', 'submitting', 'submission' => 'submission',
                'reward', 'rewards', 'incentive', 'incentives' => 'incentive',
                'eligible', 'eligibility', 'qualified', 'qualify', 'qualification', 'qualifications' => 'eligibility',
                'apply', 'applying', 'application', 'applications' => 'application',
                'ethical', 'ethic', 'ethics' => 'ethics',
                'honoraria', 'honorarium', 'honorariums' => 'honorarium',
                'publish', 'published', 'publishing', 'publication', 'publications' => 'publication',
                'utilise', 'utilize', 'utilisation', 'utilization' => 'utilization',
                default => Str::endsWith($word, 'ies') && Str::length($word) > 4
                    ? Str::substr($word, 0, -3).'y'
                    : (Str::endsWith($word, 's') && Str::length($word) > 3 && ! preg_match('/(?:ss|us|is)$/', $word)
                        ? Str::substr($word, 0, -1)
                        : $word),
            };

            if ((Str::length($word) >= 2 || ctype_digit($word)) && ! in_array($word, self::STOP_WORDS, true)) {
                $tokens[] = $word;
            }
        }

        return $tokens;
    }
}

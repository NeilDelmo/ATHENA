<?php

namespace App\Services;

use Illuminate\Support\Str;

class ResearchAssistantPassageRetriever
{
    /**
     * @param  list<array<string, mixed>>  $passages
     * @param  list<array{role?: string, content?: string}>  $recentMessages
     * @return array{passages: list<array<string, mixed>>, total_passages: int, omitted_passages: int, truncated: bool, no_match?: bool}
     */
    public function select(array $passages, string $question, array $recentMessages = [], int $byteBudget = 10000, int $maxPassages = 8): array
    {
        $byteBudget = max(256, min(20000, $byteBudget));
        $maxPassages = max(1, min(12, $maxPassages));
        $passages = $this->splitPassages($passages);
        $total = count($passages);

        if ($total === 0) {
            return ['passages' => [], 'total_passages' => 0, 'omitted_passages' => 0, 'truncated' => false];
        }

        $query = $question;
        if (count($this->terms($question)) < 4 || preg_match('/\b(?:that|those|them|it|this)\b/i', $question)) {
            $previous = collect($recentMessages)->where('role', 'user')->take(-2)->pluck('content')->filter(fn (mixed $content): bool => is_string($content));
            $query .= ' '.Str::limit($previous->join(' '), 1200, '');
        }
        $terms = $this->terms($query);
        $frequencies = [];
        $documents = [];

        foreach ($passages as $index => $passage) {
            $heading = implode(' ', array_filter([
                $passage['title'] ?? null, $passage['label'] ?? null,
                $passage['location'] ?? null, $passage['field'] ?? null,
            ], 'is_string'));
            $documents[$index] = [
                'frequencies' => array_count_values($this->terms($heading.' '.$passage['text'])),
                'heading_terms' => $this->terms($heading),
            ];
            foreach (array_keys($documents[$index]['frequencies']) as $term) {
                $frequencies[$term] = ($frequencies[$term] ?? 0) + 1;
            }
        }

        preg_match_all('/\b(?:pages?|p\.?|pp\.?)\s*(\d{1,3})\b/i', $question, $pageMatches);
        $requestedPages = array_map('intval', $pageMatches[1]);
        $scores = [];
        foreach ($passages as $index => $passage) {
            $score = 0.0;
            foreach ($terms as $term) {
                $frequency = $documents[$index]['frequencies'][$term] ?? 0;
                if ($frequency === 0) {
                    continue;
                }
                $weight = log(1 + ($total + 1) / (($frequencies[$term] ?? 0) + 1));
                $score += $weight * (1 + log($frequency));
                if (in_array($term, $documents[$index]['heading_terms'], true)) {
                    $score += $weight * 2;
                }
            }
            if (in_array($passage['page'] ?? null, $requestedPages, true)) {
                $score += 100;
            }
            $scores[$index] = $score;
        }
        arsort($scores, SORT_NUMERIC);

        $overview = $this->isOverview($question, $terms);
        if (reset($scores) <= 0.0 && ! $overview) {
            return ['passages' => [], 'total_passages' => $total, 'omitted_passages' => $total, 'truncated' => true, 'no_match' => true];
        }
        if ($overview) {
            $indices = $this->spreadIndices($total, $maxPassages);
        } else {
            $indices = array_slice(array_keys(array_filter($scores, fn (float $score): bool => $score > 0)), 0, max(1, $maxPassages - 2));
            foreach (array_slice($indices, 0, 2) as $index) {
                foreach ([$index - 1, $index + 1] as $neighbor) {
                    if (isset($passages[$neighbor]) && $this->sameSource($passages[$index], $passages[$neighbor])
                        && ! in_array($neighbor, $indices, true) && count($indices) < $maxPassages) {
                        $indices[] = $neighbor;
                    }
                }
            }
            foreach (array_keys($scores) as $index) {
                if (count($indices) >= $maxPassages) {
                    break;
                }
                if (! in_array($index, $indices, true) && $scores[$index] > 0) {
                    $indices[] = $index;
                }
            }
        }

        sort($indices, SORT_NUMERIC);
        $selected = [];
        $trimmed = false;
        foreach ($indices as $position => $index) {
            $passage = $passages[$index];
            $remainingCount = count($indices) - $position;
            $remainingBytes = $byteBudget - strlen($this->json($selected)) - 1;
            $allowance = (int) floor($remainingBytes / max(1, $remainingCount));
            $metadata = [...$passage, 'text' => ''];
            $textBudget = $allowance - strlen($this->json($metadata)) - 1;
            if ($textBudget < 40) {
                $trimmed = true;

                continue;
            }
            $text = $passage['text'];
            if (! $overview && strlen($text) > $textBudget) {
                $text = $this->matchingWindow($text, $terms, $frequencies, $textBudget);
            }
            while (strlen($this->json([...$passage, 'text' => $text])) > $allowance && $text !== '') {
                $text = mb_strcut($text, 0, max(0, strlen($text) - max(16, strlen($this->json([...$passage, 'text' => $text])) - $allowance)), 'UTF-8');
            }
            $trimmed = $trimmed || $text !== $passage['text'];
            if ($text !== '') {
                $selected[] = [...$passage, 'text' => $text];
            }
        }

        return [
            'passages' => $selected,
            'total_passages' => $total,
            'omitted_passages' => $total - count($selected),
            'truncated' => $trimmed || count($selected) < $total,
        ];
    }

    /** @param list<array<string, mixed>> $passages @return list<array<string, mixed>> */
    private function splitPassages(array $passages): array
    {
        $result = [];
        $remainingBytes = 1024 * 1024;
        foreach ($passages as $index => $passage) {
            if (! is_array($passage) || ! is_string($passage['text'] ?? null) || blank($passage['text']) || $remainingBytes <= 0) {
                continue;
            }
            $text = mb_strcut($passage['text'], 0, $remainingBytes, 'UTF-8');
            $remainingBytes -= strlen($text);
            $length = mb_strlen($text);
            $offset = 0;
            $part = 0;
            do {
                $chunk = mb_substr($text, $offset, 1800);
                if ($offset + 1800 < $length) {
                    $boundary = mb_strrpos($chunk, "\n");
                    if ($boundary === false || $boundary < 900) {
                        $boundary = mb_strrpos($chunk, ' ');
                    }
                    if ($boundary !== false && $boundary >= 900) {
                        $chunk = mb_substr($chunk, 0, $boundary);
                    }
                }
                $result[] = [...$passage,
                    'id' => (string) ($passage['id'] ?? $index).($length > 1800 ? ':'.(++$part) : ''),
                    'text' => trim($chunk),
                ];
                $offset += max(1, mb_strlen($chunk));
            } while ($offset < $length);
        }

        return $result;
    }

    /** @return list<string> */
    private function terms(string $value): array
    {
        $value = Str::lower(Str::ascii($value));
        $value = preg_replace('/\b(?:rrl|rrs|related studies|review of (?:the )?related literature)\b/', 'related literature', $value) ?? $value;
        $value = preg_replace('/\b(?:statistical|statistics|statistic)\b/', 'analysis', $value) ?? $value;
        $words = preg_split('/[^\pL\pN]+/u', $value, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $stop = ['the', 'and', 'for', 'are', 'what', 'how', 'why', 'when', 'where', 'which', 'who', 'this', 'that', 'these', 'those', 'them', 'with', 'from', 'about', 'does', 'have', 'has', 'can', 'you', 'your', 'its', 'our', 'their', 'would', 'should', 'could', 'please', 'document', 'selected', 'analyze', 'read', 'tell', 'say', 'need', 'into', 'only', 'then', 'than', 'been', 'will', 'not'];

        return array_values(array_unique(array_map(function (string $word): string {
            if (strlen($word) > 5 && str_ends_with($word, 'ies')) {
                return substr($word, 0, -3).'y';
            }
            if (strlen($word) > 5 && str_ends_with($word, 'ing')) {
                return substr($word, 0, -3);
            }

            return strlen($word) > 4 && str_ends_with($word, 's') ? substr($word, 0, -1) : $word;
        }, array_filter($words, fn (string $word): bool => strlen($word) >= 3 && ! in_array($word, $stop, true)))));
    }

    /** @param list<string> $terms */
    private function isOverview(string $question, array $terms): bool
    {
        if ($terms === [] || (in_array('purpose', $terms, true)
            && array_diff($terms, ['purpose', 'paper', 'overall', 'main', 'research', 'study']) === [])) {
            return true;
        }

        return preg_match('/\b(?:summari[sz]e|overview(?:\s+of)?)\s+(?:(?:this|the|selected|entire|whole|saved|current|my|our|its|proposal|document|paper|form|purpose|and|key|points)\b\s*)+(?:[.!?,;:]|$)|\boverview\s*[.!?]?$/i', $question) === 1;
    }

    /** @return list<int> */
    private function spreadIndices(int $total, int $maximum): array
    {
        $count = min($total, $maximum);
        if ($count === 1) {
            return [0];
        }

        return array_values(array_unique(array_map(fn (int $position): int => (int) round($position * ($total - 1) / ($count - 1)), range(0, $count - 1))));
    }

    private function json(array $value): string
    {
        return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    }

    /** @param list<string> $terms @param array<string, int> $frequencies */
    private function matchingWindow(string $text, array $terms, array $frequencies, int $budget): string
    {
        usort($terms, fn (string $first, string $second): int => ($frequencies[$first] ?? PHP_INT_MAX) <=> ($frequencies[$second] ?? PHP_INT_MAX));
        foreach ($terms as $term) {
            if (preg_match('/\b'.preg_quote($term, '/').'\pL*/iu', $text, $matches, PREG_OFFSET_CAPTURE) !== 1) {
                continue;
            }
            $offset = $matches[0][1];
            $start = max(0, $offset - (int) floor($budget / 4));
            $previousLine = strrpos(substr($text, 0, $offset), "\n");
            if ($previousLine !== false && $offset - $previousLine < $budget / 2) {
                $start = $previousLine + 1;
            }

            return mb_strcut($text, $start, $budget, 'UTF-8');
        }

        return mb_strcut($text, 0, $budget, 'UTF-8');
    }

    private function sameSource(array $first, array $second): bool
    {
        foreach (['paper_slug', 'reference', 'document_id', 'field'] as $key) {
            if (($first[$key] ?? null) !== ($second[$key] ?? null)) {
                return false;
            }
        }

        return true;
    }
}

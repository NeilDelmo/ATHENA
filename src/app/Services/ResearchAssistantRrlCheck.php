<?php

namespace App\Services;

use App\Models\LiteratureSource;
use App\Models\ProposalDraftLiteratureSource;
use DOMDocument;
use DOMXPath;
use Illuminate\Support\Str;

class ResearchAssistantRrlCheck
{
    public const MAX_ENTRIES = 50;

    private const YEAR = '(?:18|19|20)\d{2}[a-z]?';

    private const AUTHOR = "[\\p{L}\\p{M}][\\p{L}\\p{M}'’–-]*";

    private const NUMBER = '[1-9]\d{0,4}';

    private const DISCUSSION = '/\b(?:found|reported|showed|demonstrated|observed|concluded|examined|evaluated|investigated|measured|compared|identified|improved|increased|decreased|reduced|suggested|indicated|outperformed|predicted|associated|correlated)\b/iu';

    private const PLANNED_WORK = '/\b(?:our\s+(?:proposed\s+)?(?:study|project|research|method(?:ology)?)|we\s+(?:will|plan|aim|propose|found|observed)|(?:will|would|could|might)\s+(?:test|examine|evaluate|investigate|measure|compare))\b/iu';

    /** @param iterable<ProposalDraftLiteratureSource> $links */
    public function assess(?array $data, iterable $links, ?int $minimum): array
    {
        $references = $this->blocks($data['references'] ?? '', 'data-proposal-managed-reference');
        [$works, $byLink, $byNumber, $byAuthor, $listed] = $this->catalog($references, $links);
        $body = $this->blocks('<div>'.($data['introduction'] ?? '').'</div><div>'.($data['related_literature'] ?? '').'</div>', 'data-proposal-citation');
        $entries = [];
        $issues = [];
        $mentioned = [];
        $hasText = false;

        foreach ($body['lines'] as $line) {
            $hasText = true;
            foreach (preg_split('/(?<=[.!?])\s+(?=[\p{Lu}“"])/u', $line) ?: [] as $sentence) {
                $excerpt = Str::squish($this->restoreMarkers($sentence, $body['markers']));
                $cited = [];
                $uncertain = false;
                foreach ($this->citations($sentence, $body['markers'], $byLink, $byNumber, $byAuthor) as [$label, $keys]) {
                    foreach ($keys as $key) {
                        $mentioned[$key] = true;
                    }
                    if (count($keys) === 1) {
                        $cited[$keys[0]] = true;

                        continue;
                    }
                    $uncertain = true;
                    $issues[Str::lower($label)] ??= $this->entry('needs_review', $label, $excerpt,
                        $keys === [] ? 'No matching reference found.' : 'More than one reference matches.');
                }
                foreach (array_keys($cited) as $key) {
                    // shortcut: recognizable discussion wording is a signal, expand if more citation styles or languages are needed.
                    $supported = ! $uncertain && count($cited) === 1
                        && preg_match(self::DISCUSSION, $sentence) === 1 && preg_match(self::PLANNED_WORK, $sentence) !== 1
                        && preg_match_all('/\p{L}+/u', $sentence) >= 5;
                    if (! isset($entries[$key]) || ($supported && $entries[$key]['status'] !== 'supported')) {
                        $entries[$key] = $this->entry($supported ? 'supported' : 'needs_review', $works[$key], $excerpt,
                            $supported ? '' : 'Check which findings, methods, or contribution this citation supports.');
                    }
                }
            }
        }

        $supported = array_filter($entries, fn (array $entry): bool => $entry['status'] === 'supported');
        $needsReview = [...array_values(array_diff_key($entries, $supported)), ...array_values($issues)];
        $referencesOnly = array_map(fn (string $key): array => $this->entry('references_only', $works[$key]),
            array_keys(array_diff_key($listed, $mentioned)));
        $count = count($supported);
        $reviewCount = count($needsReview);
        $met = $minimum !== null && $count >= $minimum;
        $empty = $data !== null && ! $hasText;
        $summary = $minimum === null ? 'The form minimum could not be verified. ' : "Section XI requires at least {$minimum} reviewed studies. ";
        if ($data === null) {
            $summary .= 'Section XI text is unavailable for this saved paper.';
        } elseif ($empty) {
            $summary .= 'Section XI is empty in the saved proposal.';
        } else {
            $summary .= $count === 1
                ? 'The saved text supports 1 distinct study.'
                : "The saved text supports {$count} distinct studies.";
        }
        if ($met) {
            $summary .= ' The numerical minimum is met.';
        }
        if ($reviewCount > 0) {
            $summary .= $reviewCount === 1 ? ' There is 1 citation to review.' : " There are {$reviewCount} citations to review.";
        }
        if (! $met && ! $empty && $data !== null) {
            $summary .= ' Verify any studies this check missed or flagged before deciding how many to add.';
        }

        $details = [...$needsReview, ...array_values($supported), ...$referencesOnly];

        return [
            'count' => $empty ? 0 : null,
            'known_count' => $count,
            'count_status' => $empty ? 'empty' : ($count > 0 ? 'partial' : 'unknown'),
            'minimum_satisfied' => $met ? true : ($empty && $minimum !== null ? false : null),
            'shortfall' => $met ? 0 : ($empty ? $minimum : null),
            'study_check' => [
                'summary' => $summary,
                'entries' => array_slice($details, 0, self::MAX_ENTRIES),
                'more' => max(0, count($details) - self::MAX_ENTRIES),
            ],
        ];
    }

    private function entry(string $status, string $label, string $excerpt = '', string $reason = ''): array
    {
        return ['status' => $status, 'label' => Str::limit($label, 220), 'excerpt' => Str::limit($excerpt, 420), 'reason' => $reason];
    }

    /** @param iterable<ProposalDraftLiteratureSource> $links */
    private function catalog(array $references, iterable $links): array
    {
        $works = $byLink = $byNumber = $byAuthor = $byTitle = $listed = $linkAuthors = [];
        $rows = [];
        foreach ($references['lines'] as $line) {
            preg_match('/\x{E000}(\d+)\x{E001}/u', $line, $marker);
            $linkId = $references['markers'][(int) ($marker[1] ?? -1)]['id'] ?? '';
            $line = Str::squish($this->restoreMarkers($line, $references['markers']));
            preg_match('/^(?:\[('.self::NUMBER.')\]|('.self::NUMBER.')\.(?=\s))\s*/', $line, $number);
            $text = substr($line, strlen($number[0] ?? ''));
            preg_match('/^(?<author>[^,()]+),(?<names>[^()]*)\(\s*(?<year>'.self::YEAR.')\s*\)\.?\s*(?<title>.+?)(?:\.\s|$)/iu', $text, $authorDate);
            preg_match('~\b10\.\d{4,9}/[^\s<>"”]+~iu', $text, $doi);
            $doiKey = isset($doi[0]) ? LiteratureSource::fingerprintFor(rtrim($doi[0], '.,;)'), '') : null;
            $author = isset($authorDate['author']) ? $this->authorKey($authorDate['author'], $authorDate['year']) : '';
            $title = trim($authorDate['title'] ?? '');
            $titleKey = $author !== '' && $title !== '' ? LiteratureSource::fingerprintFor(null, $title, (int) $authorDate['year']) : null;
            $textKey = hash('sha256', Str::lower($titleKey === null ? $text : $titleKey.'|'.$author.'|'.Str::squish($authorDate['names'])));
            $rows[] = ['line' => $line, 'link_id' => $linkId, 'number' => (int) ($number[2] ?? $number[1] ?? 0),
                'author' => $author, 'title_key' => $titleKey, 'doi_key' => $doiKey, 'text_key' => $textKey];
            if ($titleKey !== null && $doiKey !== null) {
                $byTitle[$titleKey][$author][$doiKey] = true;
            }
        }

        foreach ($links as $link) {
            $titleKey = LiteratureSource::fingerprintFor(null, $link->title, $link->publication_year);
            $author = $link->publication_year ? $this->authorKey(Str::before((string) $link->authors, ','), (string) $link->publication_year) : '';
            $doiMatches = array_keys($byTitle[$titleKey][$author] ?? []);
            $key = blank($link->doi) && count($doiMatches) === 1 ? $doiMatches[0]
                : LiteratureSource::fingerprintFor($link->doi, $link->title, $link->publication_year);
            $byLink[$link->id] = $key;
            $byTitle[$titleKey][$author][$key] = true;
            $works[$key] = $link->title;
            $linkAuthors[$key] = $author;
        }

        foreach ($rows as $row) {
            $managed = $byLink[$row['link_id']] ?? null;
            $titles = $row['title_key'] === null ? [] : ($byTitle[$row['title_key']] ?? []);
            $titleMatches = array_keys(($titles[$row['author']] ?? []) + ($titles[''] ?? []));
            $direct = $managed ?? $row['doi_key'];
            $keys = $direct !== null ? [$direct] : ($titleMatches ?: [$row['text_key']]);
            if (count($keys) === 1) {
                $works[$keys[0]] ??= $row['line'];
            }
            foreach ($keys as $key) {
                $listed[$key] = true;
                if ($row['number'] > 0) {
                    $byNumber[$row['number']][$key] = true;
                }
                $author = $row['author'] ?: ($linkAuthors[$key] ?? '');
                if ($author !== '') {
                    $byAuthor[$author][$key] = true;
                }
            }
        }

        return [$works, $byLink, $byNumber, $byAuthor, $listed];
    }

    private function authorKey(string $author, string $year): string
    {
        return preg_match('/'.self::AUTHOR.'$/u', trim($author), $surname) ? Str::lower($surname[0].' '.$year) : '';
    }

    private function citations(string $sentence, array $markers, array $byLink, array $byNumber, array $byAuthor): array
    {
        $citations = [];
        preg_match_all('/\x{E000}(\d+)\x{E001}/u', $sentence, $tokens);
        foreach ($tokens[1] as $token) {
            $marker = $markers[(int) $token] ?? null;
            if ($marker === null) {
                continue;
            }
            $key = $byLink[$marker['id']] ?? null;
            $citations[] = [Str::squish($marker['label']), $key === null ? [] : [$key]];
        }

        $number = self::NUMBER;
        preg_match_all('/\[('.$number.'(?:\s*[-–,;]\s*'.$number.')*)(?:,\s*pp?\.\s*'.$number.'(?:\s*[-–]\s*'.$number.')?)?\]/iu',
            $sentence, $numbered, PREG_SET_ORDER);
        foreach ($numbered as [$label, $group]) {
            foreach (preg_split('/\s*[,;]\s*/', $group) ?: [] as $part) {
                [$start, $end] = array_pad(preg_split('/\s*[-–]\s*/u', $part, 2) ?: [], 2, null);
                $end ??= $start;
                if ((int) $end < (int) $start || (int) $end - (int) $start >= self::MAX_ENTRIES) {
                    $citations[] = [$label, []];

                    continue;
                }
                foreach (range((int) $start, (int) $end) as $referenceNumber) {
                    $citations[] = ['['.$referenceNumber.']', array_keys($byNumber[$referenceNumber] ?? [])];
                }
            }
        }

        $author = self::AUTHOR;
        $year = self::YEAR;
        preg_match_all('/(?<![\p{L}\p{M}])('.$author.')(?:\s+(?:et\s+al\.?|(?:and|&)\s+'.$author.'))?\s*(?:,\s*|\(\s*)('.$year.'(?:\s*,\s*'.$year.')*)(?=[\s),;.]|$)/iu',
            $sentence, $authorDates, PREG_SET_ORDER);
        foreach ($authorDates as [, $surname, $years]) {
            foreach (explode(',', $years) as $year) {
                $year = trim($year);
                $citations[] = [$surname.' ('.$year.')', array_keys($byAuthor[Str::lower($surname.' '.$year)] ?? [])];
            }
        }

        return $citations;
    }

    private function blocks(string $html, string $attribute): array
    {
        $html = str_replace(["\u{E000}", "\u{E001}"], '', $html);
        $html = preg_replace('/<br\b[^>]*>|<\/(?:p|div|li|td|tr|h[1-6])\s*>/i', "$0\n", $html) ?? $html;
        $document = new DOMDocument;
        $document->loadHTML('<?xml encoding="UTF-8"><body>'.$html.'</body>', LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);
        $xpath = new DOMXPath($document);
        foreach ($xpath->query('//script|//style') as $node) {
            $node->parentNode->removeChild($node);
        }
        foreach ($xpath->query('//text()[contains(., "\u{E000}") or contains(., "\u{E001}")]') as $node) {
            $node->nodeValue = str_replace(["\u{E000}", "\u{E001}"], '', $node->nodeValue);
        }
        if ($attribute === 'data-proposal-managed-reference') {
            foreach ($xpath->query('//ol[not(ancestor::li)]') as $list) {
                $number = $list->hasAttribute('start') ? (int) $list->getAttribute('start') : 1;
                foreach ($xpath->query('./li', $list) as $item) {
                    if ($item->hasAttribute('value')) {
                        $number = (int) $item->getAttribute('value');
                    }
                    if ($number > 0 && preg_match('/^\s*(?:\[\d+\]|\d+\.\s)/', $item->textContent) !== 1) {
                        $item->insertBefore($document->createTextNode('['.$number.'] '), $item->firstChild);
                    }
                    $number++;
                }
            }
        }
        $markers = [];
        foreach ($xpath->query('//*[@'.$attribute.']') as $node) {
            $label = $node->textContent;
            if (Str::squish($label) !== '') {
                $index = count($markers);
                $markers[] = ['id' => $node->getAttribute($attribute), 'label' => $label];
                $node->textContent = "\u{E000}".$index."\u{E001}";
            }
        }
        $lines = preg_split('/\R+/u', $document->getElementsByTagName('body')->item(0)?->textContent ?? '') ?: [];

        return ['lines' => array_filter(array_map(Str::squish(...), $lines), fn (string $line): bool => $line !== ''), 'markers' => $markers];
    }

    private function restoreMarkers(string $text, array $markers): string
    {
        return preg_replace_callback('/\x{E000}(\d+)\x{E001}/u', fn (array $match): string => $markers[(int) $match[1]]['label'] ?? '', $text) ?? $text;
    }
}

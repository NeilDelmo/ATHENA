<?php

namespace App\Services;

use Illuminate\Support\Str;

class ResearchManualComparison
{
    /**
     * @param  array<string, mixed>  $current
     * @param  array<string, mixed>  $candidate
     * @return array{changes: list<array<string, mixed>>, unchanged_sections: int}
     */
    public function compare(array $current, array $candidate): array
    {
        $before = $this->sections($current);
        $after = $this->sections($candidate);
        $changes = [];
        $unchanged = 0;

        foreach (array_unique([...array_keys($after), ...array_keys($before)]) as $key) {
            $old = $before[$key] ?? [];
            $new = $after[$key] ?? [];

            if ($old !== [] && $new !== [] && Str::squish(implode(' ', array_column($old, 'text'))) === Str::squish(implode(' ', array_column($new, 'text')))) {
                $unchanged++;

                continue;
            }

            $positions = [];
            foreach ($old as $position => $passage) {
                $positions[$passage['text']][] = $position;
            }

            $cursors = [];
            $anchors = [];
            $nextOld = 0;
            foreach ($new as $position => $passage) {
                $text = $passage['text'];
                $cursor = $cursors[$text] ?? 0;
                while (isset($positions[$text][$cursor]) && $positions[$text][$cursor] < $nextOld) {
                    $cursor++;
                }
                $cursors[$text] = $cursor;

                if (isset($positions[$text][$cursor])) {
                    $oldPosition = $positions[$text][$cursor];
                    $anchors[] = [$oldPosition, $position];
                    $nextOld = $oldPosition + 1;
                }
            }

            $oldStart = $newStart = 0;
            foreach ([...$anchors, [count($old), count($new)]] as [$oldEnd, $newEnd]) {
                $length = max($oldEnd - $oldStart, $newEnd - $newStart);
                for ($offset = 0; $offset < $length; $offset++) {
                    $oldPassage = $oldStart + $offset < $oldEnd ? $old[$oldStart + $offset] : null;
                    $newPassage = $newStart + $offset < $newEnd ? $new[$newStart + $offset] : null;
                    [$oldSegments, $newSegments] = $this->highlight($oldPassage['text'] ?? '', $newPassage['text'] ?? '');
                    $type = $oldPassage === null ? 'added' : ($newPassage === null ? 'removed' : 'changed');
                    $uncertain = ($oldPassage === null && $current['unindexed_pages'] !== []) || ($newPassage === null && $candidate['unindexed_pages'] !== []);
                    $changes[] = [
                        'section' => ($newPassage ?? $oldPassage)['section'],
                        'source_kind' => ($newPassage ?? $oldPassage)['source_kind'],
                        'type' => $uncertain ? 'unreadable' : $type,
                        'before' => $oldPassage !== null ? [...$oldPassage, 'segments' => $oldSegments] : null,
                        'after' => $newPassage !== null ? [...$newPassage, 'segments' => $newSegments] : null,
                    ];
                }
                $oldStart = $oldEnd + 1;
                $newStart = $newEnd + 1;
            }
        }

        return ['changes' => $changes, 'unchanged_sections' => $unchanged];
    }

    /** @param array<string, mixed> $index @return array<string, list<array<string, mixed>>> */
    private function sections(array $index): array
    {
        $sections = [];
        foreach ($index['chunks'] as $chunk) {
            $kind = ($chunk['source_kind'] ?? 'policy') === 'appendix' ? 'appendix' : 'policy';
            $key = $kind.':'.Str::lower(Str::squish($chunk['section']));
            foreach (preg_split('/\n\s*\n/u', $chunk['content'], flags: PREG_SPLIT_NO_EMPTY) ?: [] as $text) {
                $text = Str::squish($text);
                if ($text !== '') {
                    $sections[$key][] = [
                        'section' => $chunk['section'], 'source_kind' => $kind, 'text' => $text,
                        'page_start' => $chunk['page_start'], 'page_end' => $chunk['page_end'],
                    ];
                }
            }
        }

        return $sections;
    }

    /** @return array{list<array{text: string, changed: bool}>, list<array{text: string, changed: bool}>} */
    private function highlight(string $before, string $after): array
    {
        // shortcut: one highlight spans edits within a paragraph; use a word diff when separate edits need individual highlights.
        $old = preg_split('/(\s+)/u', $before, flags: PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY) ?: [];
        $new = preg_split('/(\s+)/u', $after, flags: PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY) ?: [];
        $prefix = 0;
        while (isset($old[$prefix], $new[$prefix]) && $old[$prefix] === $new[$prefix]) {
            $prefix++;
        }
        $suffix = 0;
        while ($suffix < min(count($old), count($new)) - $prefix && $old[count($old) - $suffix - 1] === $new[count($new) - $suffix - 1]) {
            $suffix++;
        }

        return array_map(fn (array $tokens): array => [
            ['text' => implode('', array_slice($tokens, 0, $prefix)), 'changed' => false],
            ['text' => implode('', array_slice($tokens, $prefix, count($tokens) - $prefix - $suffix)), 'changed' => true],
            ['text' => $suffix > 0 ? implode('', array_slice($tokens, -$suffix)) : '', 'changed' => false],
        ], [$old, $new]);
    }
}

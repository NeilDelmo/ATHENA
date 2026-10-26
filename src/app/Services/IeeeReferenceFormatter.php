<?php

namespace App\Services;

use App\Models\LiteratureSource;
use App\Models\ProposalDraftLiteratureSource;
use Illuminate\Support\Str;

class IeeeReferenceFormatter
{
    public static function format(LiteratureSource|ProposalDraftLiteratureSource $source, ?int $number = null): string
    {
        $prefix = $number !== null ? "[{$number}] " : '';
        $authors = self::authors((string) $source->authors);
        $title = self::sentencePart($source->title);
        $venue = self::sentencePart($source->venue);
        $year = $source->publication_date?->year ?? $source->publication_year;
        $doi = filled($source->doi) ? 'doi: '.LiteratureSource::normalizeDoi($source->doi).'.' : null;
        $url = filled($source->url) ? '[Online]. Available: '.trim($source->url).'.' : null;
        $pages = self::sentencePart($source->pages);

        return $prefix.collect([
            $authors !== '' ? $authors.',' : null,
            $title !== '' ? '"'.$title.',"' : null,
            $venue !== '' ? $venue.',' : null,
            filled($source->volume) ? 'vol. '.self::sentencePart($source->volume).',' : null,
            filled($source->issue) ? 'no. '.self::sentencePart($source->issue).',' : null,
            $pages !== '' ? (Str::startsWith($pages, 'Art. no. ') ? $pages : 'pp. '.$pages).',' : null,
            filled($source->publisher) ? self::sentencePart($source->publisher).',' : null,
            $year ? $year.'.' : null,
            $doi,
            $url,
        ])->filter()->join(' ');
    }

    public static function isIncomplete(LiteratureSource|ProposalDraftLiteratureSource $source): bool
    {
        return blank($source->authors)
            || Str::lower(trim((string) $source->authors)) === 'authors not listed'
            || (blank($source->publication_year) && blank($source->publication_date))
            || blank($source->venue)
            || (blank($source->doi) && blank($source->url));
    }

    private static function authors(string $authors): string
    {
        $authors = Str::squish($authors);
        $etAlPattern = '/(?:^|[\s,;])et al\.?$/iu';
        $hasEtAl = preg_match($etAlPattern, $authors) === 1;
        $authors = preg_replace($etAlPattern, '', $authors) ?? $authors;
        $separator = Str::contains($authors, ';') ? '/\s*;\s*/u' : '/\s*,\s*/u';
        $names = collect(preg_split($separator, $authors) ?: [])
            ->filter(fn (string $name): bool => $name !== '')
            ->map(fn (string $name): string => self::author($name))
            ->values();

        if ($names->isEmpty()) {
            return $hasEtAl ? 'et al.' : 'Author not listed';
        }

        if ($hasEtAl) {
            return $names->join(', ').', et al.';
        }

        if ($names->count() === 1) {
            return $names->first();
        }

        return $names->slice(0, -1)->join(', ').' and '.$names->last();
    }

    private static function author(string $name): string
    {
        if (Str::lower($name) === 'authors not listed') {
            return 'Author not listed';
        }

        $surname = Str::contains($name, ',') ? trim(Str::before($name, ',')) : null;
        $name = $surname !== null ? Str::after($name, ',') : $name;
        $parts = collect(preg_split('/\s+/u', trim($name)) ?: [])->filter()->values();

        if ($parts->count() < 2 && $surname === null) {
            return $parts->first() ?: 'Author not listed';
        }

        $surname ??= $parts->pop();
        $initials = $parts
            ->map(fn (string $part): string => collect(explode('-', $part))
                ->filter()->map(fn (string $segment): string => Str::upper(Str::substr($segment, 0, 1)).'.')->join('-'))
            ->join(' ');

        return trim($initials.' '.$surname);
    }

    private static function sentencePart(?string $value): string
    {
        return rtrim(trim((string) $value), " .\t\n\r\0\x0B");
    }
}

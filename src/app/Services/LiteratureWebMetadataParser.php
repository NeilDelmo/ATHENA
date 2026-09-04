<?php

namespace App\Services;

use App\Exceptions\LiteratureHarvestException;
use App\Models\LiteratureSource;
use DOMDocument;
use Illuminate\Support\Str;

class LiteratureWebMetadataParser
{
    /** @return array<string, mixed>|null */
    public function parse(string $html): ?array
    {
        if (preg_match('/<!ENTITY\b/i', $html) === 1) {
            throw new LiteratureHarvestException('Entity declarations are not accepted.');
        }

        $document = new DOMDocument;
        $previous = libxml_use_internal_errors(true);

        try {
            $document->loadHTML('<?xml encoding="UTF-8">'.$html, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }

        $values = [];

        foreach ($document->getElementsByTagName('meta') as $meta) {
            $name = Str::lower($meta->getAttribute('name') ?: $meta->getAttribute('property'));
            $content = Str::squish(strip_tags($meta->getAttribute('content')));

            if ($name !== '' && $content !== '') {
                $values[$name][] = $content;
            }
        }

        foreach (['robots', 'athena-literatureharvester'] as $name) {
            if (preg_match('/\b(noindex|noarchive|none)\b/i', implode(',', $values[$name] ?? [])) === 1) {
                throw new LiteratureHarvestException('The page disallows indexing or archiving its metadata.');
            }
        }

        foreach ($document->getElementsByTagName('input') as $input) {
            if (Str::lower($input->getAttribute('type')) === 'password') {
                throw new LiteratureHarvestException('Authentication pages are not harvested.');
            }
        }

        $first = function (array $keys) use ($values): ?string {
            foreach ($keys as $key) {
                if (isset($values[$key][0])) {
                    return $values[$key][0];
                }
            }

            return null;
        };
        $title = $first(['citation_title', 'dc.title', 'dcterms.title']);
        $authors = $values['citation_author'] ?? $values['dc.creator'] ?? $values['dc.contributor.author'] ?? [];
        $date = $first(['citation_publication_date', 'citation_date', 'dcterms.issued', 'dc.date.issued']);
        $year = preg_match('/\b(1[5-9]\d{2}|20\d{2})\b/', $date ?? '', $matches) === 1 ? (int) $matches[1] : null;

        if (blank($title) || ($authors === [] && $year === null)) {
            return null;
        }

        $doi = null;

        foreach (['citation_doi', 'dc.identifier.doi', 'dc.identifier', 'dcterms.identifier'] as $key) {
            foreach ($values[$key] ?? [] as $identifier) {
                $candidate = LiteratureSource::normalizeDoi($identifier);

                if (preg_match('~^10\.\d{4,9}/[^\s<>"]+$~', $candidate) === 1 && strlen($candidate) <= 255) {
                    $doi = $candidate;
                    break 2;
                }
            }
        }

        return [
            'title' => Str::limit($title, 500, ''),
            'authors' => Str::limit(implode('; ', array_unique($authors)), 8000, '') ?: null,
            'abstract' => Str::limit($first(['citation_abstract', 'dcterms.abstract', 'dc.description.abstract', 'dc.description']) ?? '', 20000, '') ?: null,
            'publication_year' => $year,
            'doi' => $doi,
            'metadata' => [
                'method' => 'html_metadata',
                'venue' => Str::limit($first(['citation_journal_title', 'citation_inbook_title', 'citation_conference_title']) ?? '', 500, '') ?: null,
                'publisher' => Str::limit($first(['citation_publisher', 'dc.publisher']) ?? '', 500, '') ?: null,
                'type' => Str::limit($first(['dc.type', 'dcterms.type']) ?? '', 100, '') ?: null,
                'language' => Str::limit($first(['citation_language', 'dc.language']) ?? '', 100, '') ?: null,
                'license' => Str::limit($first(['dcterms.license', 'dc.rights.uri', 'dc.rights', 'dcterms.rights']) ?? '', 1000, '') ?: null,
            ],
        ];
    }
}

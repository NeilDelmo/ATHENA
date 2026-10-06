<?php

namespace App\Services;

use App\Models\LiteratureSource;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

class LiteratureMetadataService
{
    /** @return array{source: array<string, mixed>, notice: string} */
    public function lookup(string $identifier): array
    {
        $doi = LiteratureSource::normalizeDoi($identifier);

        if (Str::length($doi) > 255 || preg_match('/^10\.\d{4,9}\/[^\s]+$/iu', $doi) !== 1) {
            throw ValidationException::withMessages(['identifier' => 'Enter a valid DOI or doi.org link. Other source URLs can be entered manually for review.']);
        }

        return Cache::remember('literature-metadata:'.hash('sha256', Str::lower($doi)), now()->addMinutes(30), function () use ($doi): array {
            try {
                $response = Http::acceptJson()->connectTimeout(4)->timeout(12)->withoutRedirecting()
                    ->get('https://api.crossref.org/works/'.rawurlencode($doi));
            } catch (Throwable $exception) {
                report($exception);
                abort(503, 'Publication metadata could not be reached. You can enter the source details manually.');
            }

            abort_if($response->status() === 404, 404, 'No publication metadata was found for this DOI. Enter the source details manually.');
            abort_unless($response->successful(), 503, 'Publication metadata is unavailable. You can enter the source details manually.');
            $work = $response->json('message');
            abort_unless(is_array($work), 502, 'The metadata provider returned an unreadable result.');
            $title = Str::squish(strip_tags((string) ($work['title'][0] ?? '')));
            abort_if($title === '', 502, 'The metadata result has no title. Enter the source details manually.');
            $year = $work['issued']['date-parts'][0][0] ?? $work['published']['date-parts'][0][0] ?? null;
            $year = is_numeric($year) && (int) $year >= 1900 && (int) $year <= now()->year ? (int) $year : null;
            $authors = collect($work['author'] ?? [])->filter(fn (mixed $author): bool => is_array($author))
                ->map(fn (array $author): string => Str::squish(($author['given'] ?? '').' '.($author['family'] ?? '')))->filter()->implode(', ');

            return [
                'source' => [
                    'title' => Str::limit($title, 500, ''),
                    'authors' => Str::limit($authors, 2000, '') ?: null,
                    'year' => $year,
                    'venue' => Str::limit(Str::squish((string) ($work['container-title'][0] ?? '')), 500, '') ?: null,
                    'volume' => Str::limit((string) ($work['volume'] ?? ''), 100, '') ?: null,
                    'issue' => Str::limit((string) ($work['issue'] ?? ''), 100, '') ?: null,
                    'pages' => Str::limit((string) ($work['page'] ?? ''), 100, '') ?: null,
                    'publisher' => Str::limit((string) ($work['publisher'] ?? ''), 500, '') ?: null,
                    'doi' => $doi,
                    'url' => 'https://doi.org/'.$doi,
                    'description' => Str::limit(Str::squish(strip_tags((string) ($work['abstract'] ?? ''))), 12000, '') ?: null,
                    'source' => 'Crossref',
                    'type' => Str::limit((string) ($work['type'] ?? ''), 100, '') ?: null,
                ],
                'notice' => 'Publication metadata was retrieved from Crossref. Review these details before saving; this lookup does not establish the paper\'s findings or provide its full text.',
            ];
        });
    }
}

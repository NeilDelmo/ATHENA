<?php

namespace App\Services;

use App\Models\ResearchPublication;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ResearchPublicationDiscoveryService
{
    public function authors(string $query, ?string $institution = null): array
    {
        $query = Str::squish($query);
        if (preg_match('~^(?:https?://orcid.org/)?(\\d{4}-\\d{4}-\\d{4}-\\d{3}[\\dX])$~i', $query, $matches)) {
            $data = $this->get('authors', ['filter' => 'orcid:'.$matches[1], 'per-page' => 10]);
        } else {
            $data = $this->get('authors', ['search' => $query, 'per-page' => 10]);
        }

        $authors = collect($data['results'] ?? [])->map(fn (array $author): array => $this->authorData($author));
        if (filled($institution)) {
            $authors = $authors->sortByDesc(fn (array $author): bool => Str::contains(Str::lower($author['affiliation']), Str::lower($institution)));
        }

        return ['results' => $authors->values()->all(), 'checked_at' => $data['_checked_at']];
    }

    public function author(string $id): array
    {
        $data = $this->get('authors/'.$id);

        return $this->authorData($data);
    }

    public function papers(string $authorId, int $page): array
    {
        $data = $this->get('works', [
            'filter' => 'author.id:'.$authorId,
            'sort' => 'publication_date:desc',
            'per-page' => 20,
            'page' => $page,
        ]);

        return [
            'results' => collect($data['results'] ?? [])->map(fn (array $work): array => $this->workData($work, $data['_checked_at']))->all(),
            'page' => $page,
            'has_more' => (int) data_get($data, 'meta.count', 0) > $page * 20,
            'checked_at' => $data['_checked_at'],
        ];
    }

    public function work(string $id, ?string $authorId = null): array
    {
        $data = $this->get('works/'.$id);
        if ($authorId !== null && ! collect($data['authorships'] ?? [])->contains(fn (array $authorship): bool => basename((string) data_get($authorship, 'author.id')) === $authorId)) {
            throw ValidationException::withMessages(['publication' => 'This paper is not listed under your selected author profile. Check the profile or use a DOI lookup.']);
        }

        return $this->workData($data, $data['_checked_at']);
    }

    public function doi(string $doi): array
    {
        $data = $this->get('works', ['filter' => 'doi:https://doi.org/'.ResearchPublication::normalizeDoi($doi), 'per-page' => 1]);
        $work = $data['results'][0] ?? null;
        if (! is_array($work)) {
            throw ValidationException::withMessages(['doi' => 'No indexed paper was found for that DOI. You can add its details manually.']);
        }

        return $this->workData($work, $data['_checked_at']);
    }

    private function get(string $path, array $parameters = []): array
    {
        return Cache::remember('publication-discovery:v1:'.hash('sha256', $path.json_encode($parameters)), now()->addHours(6), function () use ($path, $parameters): array {
            $key = config('services.openalex.key');
            if (filled($key)) {
                $parameters['api_key'] = $key;
            }
            try {
                $response = Http::acceptJson()->connectTimeout(4)->timeout(15)
                    ->get('https://api.openalex.org/'.$path, $parameters);
            } catch (ConnectionException) {
                throw ValidationException::withMessages(['discovery' => 'OpenAlex is unavailable right now. Your saved records are still available; try the search again later.']);
            }

            if (! $response->successful() || ! is_array($response->json())) {
                throw ValidationException::withMessages(['discovery' => $response->status() === 429
                    ? 'OpenAlex has reached its request limit. Try again later or add the paper manually.'
                    : 'OpenAlex could not return this record. Check the identifier or try again later.']);
            }

            $data = $response->json();
            if (in_array($path, ['authors', 'works'], true) ? ! is_array($data['results'] ?? null) : ! is_string($data['id'] ?? null)) {
                throw ValidationException::withMessages(['discovery' => 'OpenAlex returned an incomplete response. Please try again later.']);
            }

            return [...$data, '_checked_at' => now()->toIso8601String()];
        });
    }

    private function authorData(array $author): array
    {
        return [
            'id' => basename((string) ($author['id'] ?? '')),
            'name' => $this->text($author['display_name'] ?? '', 500),
            'affiliation' => collect($author['last_known_institutions'] ?? [])->pluck('display_name')->map(fn ($name): string => $this->text($name, 500))->join('; '),
            'orcid' => $author['orcid'] ?? null,
            'works_count' => (int) ($author['works_count'] ?? 0),
        ];
    }

    private function workData(array $work, string $checkedAt): array
    {
        $doi = ResearchPublication::normalizeDoi($work['doi'] ?? null);

        return [
            'openalex_id' => basename((string) ($work['id'] ?? '')),
            'doi' => $doi,
            'title' => $this->text($work['display_name'] ?? $work['title'] ?? 'Untitled work', 1000),
            'authors' => collect($work['authorships'] ?? [])->map(fn (array $authorship): string => $this->text(data_get($authorship, 'author.display_name', ''), 500))->join(', '),
            'venue' => $this->text(data_get($work, 'primary_location.source.display_name', ''), 500) ?: null,
            'year' => $work['publication_year'] ?? null,
            'type' => $this->text($work['type'] ?? 'Other', 255),
            'url' => $this->safeUrl($doi ? 'https://doi.org/'.$doi : data_get($work, 'primary_location.landing_page_url')),
            'source' => 'OpenAlex',
            'source_checked_at' => $checkedAt,
        ];
    }

    private function text(mixed $value, int $limit): string
    {
        return Str::limit(Str::squish(strip_tags(is_string($value) ? $value : '')), $limit, '');
    }

    private function safeUrl(?string $url): ?string
    {
        return $url && filter_var($url, FILTER_VALIDATE_URL) && in_array(parse_url($url, PHP_URL_SCHEME), ['http', 'https'], true) ? $url : null;
    }
}

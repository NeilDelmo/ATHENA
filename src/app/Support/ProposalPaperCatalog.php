<?php

namespace App\Support;

use Illuminate\Support\Collection;
use InvalidArgumentException;

class ProposalPaperCatalog
{
    /** @return Collection<string, array<string, mixed>> */
    public function all(): Collection
    {
        return collect(config('proposal_papers', []))
            ->map(fn (array $paper, string $slug): array => ['slug' => $slug, ...$paper])
            ->sortBy('order');
    }

    /** @return Collection<string, array<string, mixed>> */
    public function uploadPapers(): Collection
    {
        return $this->all()->where('mode', 'upload');
    }

    /** @return array<string, mixed>|null */
    public function find(string $slug): ?array
    {
        $paper = config('proposal_papers.'.$slug);

        if (! is_array($paper)) {
            return null;
        }

        return ['slug' => $slug, ...$paper];
    }

    /** @return array<string, mixed> */
    public function get(string $slug): array
    {
        return $this->find($slug)
            ?? throw new InvalidArgumentException("Unknown proposal paper [{$slug}].");
    }

    /** @return array<string, mixed>|null */
    public function forDocumentType(string $documentType): ?array
    {
        $slug = $this->all()
            ->search(fn (array $paper): bool => $paper['document_type'] === $documentType);

        return is_string($slug) ? $this->get($slug) : null;
    }

    public function label(string $documentType): ?string
    {
        return $this->forDocumentType($documentType)['label'] ?? null;
    }
}

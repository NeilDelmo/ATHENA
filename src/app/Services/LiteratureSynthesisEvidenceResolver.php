<?php

namespace App\Services;

use App\Exceptions\LiteratureFullTextPreviewException;
use App\Models\ProposalDraftLiteratureSource;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class LiteratureSynthesisEvidenceResolver
{
    public function __construct(private LiteratureFullTextPreviewService $preview) {}

    /** @param array<string, mixed> $source
     * @return array{evidence_basis: string, abstract: string, evidence_text: ?string, coverage: ?array, notice: string}
     */
    public function resolve(array $source, string $basis, ?ProposalDraftLiteratureSource $linkedSource = null): array
    {
        $abstract = trim((string) ($source['description'] ?? $source['abstract'] ?? ''));
        if ($basis === 'abstract') {
            if (Str::length($abstract) < 80 || $abstract === 'No description available from source.') {
                throw ValidationException::withMessages(['abstract' => 'This source does not have a usable indexed abstract. Attach a readable PDF in Sources to draft from its paper.']);
            }

            return ['evidence_basis' => 'abstract', 'abstract' => Str::limit($abstract, 6000, ''), 'evidence_text' => null,
                'coverage' => null, 'notice' => 'Drafted only from the indexed abstract. No restricted or paywalled full text was accessed.'];
        }

        $document = $linkedSource?->evidence_document;
        $pages = is_array($document) ? ($document['pages'] ?? []) : [];
        $privateText = collect(is_array($pages) ? $pages : [])->filter(fn (mixed $page): bool => is_array($page) && is_string($page['text'] ?? null))
            ->map(fn (array $page): string => $page['text'])->join("\n\n");
        $privateText = Str::limit(trim($privateText), (int) config('literature.evidence.maximum_characters', 120000), '');

        if (Str::length($privateText) >= 500) {
            $coverage = is_array($document['coverage'] ?? null) ? $document['coverage'] : [];

            return ['evidence_basis' => 'full_text', 'abstract' => $abstract, 'evidence_text' => $privateText,
                'coverage' => [...$coverage, 'characters' => Str::length($privateText), 'origin' => 'uploaded_pdf'],
                'notice' => 'Drafted from readable text extracted from this proposal’s attached source PDF. '.($document['notice'] ?? 'Check the original PDF for tables, figures, extraction gaps, and any omitted pages.')];
        }

        $token = $source['full_text_token'] ?? null;
        if (! is_string($token) || $token === '') {
            $this->unavailable();
        }

        try {
            $loaded = $this->preview->preview($token);
        } catch (LiteratureFullTextPreviewException|ValidationException) {
            $this->unavailable();
        }

        $text = trim((string) ($loaded['preview_text'] ?? ''));
        if (Str::length($text) < 500) {
            $this->unavailable();
        }

        return ['evidence_basis' => 'full_text', 'abstract' => $abstract, 'evidence_text' => $text,
            'coverage' => is_array($loaded['coverage'] ?? null) ? $loaded['coverage'] : null,
            'notice' => 'Drafted from the source paper text loaded from its provider-designated open-access link. '.($loaded['notice'] ?? 'Check the original paper and extraction coverage before accepting the draft.')];
    }

    /** @param array<string, mixed> $source */
    public function canReadPaper(array $source, ?ProposalDraftLiteratureSource $linkedSource = null): bool
    {
        $pages = $linkedSource?->evidence_document['pages'] ?? [];
        $characters = collect(is_array($pages) ? $pages : [])->sum(fn (mixed $page): int => is_array($page) && is_string($page['text'] ?? null) ? Str::length($page['text']) : 0);

        return $characters >= 500 || filled($source['full_text_token'] ?? null);
    }

    private function unavailable(): never
    {
        throw ValidationException::withMessages(['evidence_basis' => 'The source paper could not be read. Attach a readable PDF in Sources or choose “Use abstracts instead”. No abstract fallback was applied.']);
    }
}

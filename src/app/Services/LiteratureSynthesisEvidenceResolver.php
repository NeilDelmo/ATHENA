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
        if ($this->hasCompleteDocument($document) && Str::length($privateText) >= 500) {
            $coverage = is_array($document['coverage'] ?? null) ? $document['coverage'] : [];

            return ['evidence_basis' => 'full_text', 'abstract' => $abstract, 'evidence_text' => $privateText,
                'coverage' => [...$coverage, 'characters' => Str::length($privateText), 'origin' => 'uploaded_pdf'],
                'notice' => 'Drafted from all extractable text across this proposal’s attached source PDF. '.($document['notice'] ?? 'Check the original PDF for tables, figures, and extraction errors.')];
        }

        $token = $source['full_text_token'] ?? null;
        if (! is_string($token) || $token === '') {
            $this->unavailable(is_array($document) ? ($document['coverage']['reason'] ?? 'This saved PDF has no verified complete extraction. Reattach it in Sources to process every page.') : null);
        }

        try {
            $loaded = $this->preview->preview($token);
        } catch (LiteratureFullTextPreviewException $exception) {
            $this->unavailable($exception->getMessage());
        } catch (ValidationException) {
            $this->unavailable();
        }

        $text = trim((string) ($loaded['preview_text'] ?? ''));
        $coverage = is_array($loaded['coverage'] ?? null) ? $loaded['coverage'] : [];
        if (Str::length($text) < 500 || ($coverage['complete'] ?? false) !== true
            || ($coverage['limited'] ?? true) !== false || ($coverage['partial_or_unknown'] ?? true) !== false
            || ($coverage['truncated'] ?? true) !== false || ($coverage['unreadable_pages'] ?? null) !== []
            || ! is_int($coverage['total_pages'] ?? null) || $coverage['total_pages'] < 1
            || ($coverage['extracted_pages'] ?? null) !== $coverage['total_pages']) {
            $this->unavailable($coverage['reason'] ?? 'The complete paper could not be extracted. Attach its readable PDF in Sources.');
        }

        return ['evidence_basis' => 'full_text', 'abstract' => $abstract, 'evidence_text' => $text,
            'coverage' => is_array($loaded['coverage'] ?? null) ? $loaded['coverage'] : null,
            'notice' => 'Drafted from all extractable text across the PDF loaded from its provider-designated open-access link. '.($loaded['notice'] ?? 'Check the original paper for tables, figures, and extraction errors before accepting the draft.')];
    }

    /** @param array<string, mixed> $source */
    public function canReadPaper(array $source, ?ProposalDraftLiteratureSource $linkedSource = null): bool
    {
        return $this->hasCompleteDocument($linkedSource?->evidence_document) || filled($source['full_text_token'] ?? null);
    }

    /** @param array<string, mixed>|null $document */
    private function hasCompleteDocument(?array $document): bool
    {
        $coverage = $document['coverage'] ?? [];
        $pages = $document['pages'] ?? [];
        $total = $coverage['total_pages'] ?? null;
        if (($coverage['complete'] ?? false) !== true || ($coverage['limited'] ?? true) !== false
            || ($coverage['partial_or_unknown'] ?? true) !== false
            || ($coverage['truncated'] ?? true) !== false || ($coverage['unreadable_pages'] ?? null) !== []
            || ! is_int($total) || $total < 1 || $total > (int) config('literature.evidence.maximum_pdf_pages')
            || ! is_array($pages) || count($pages) !== $total || ($coverage['extracted_pages'] ?? null) !== $total) {
            return false;
        }
        $characters = 0;
        foreach ($pages as $index => $page) {
            if (! is_array($page) || ($page['number'] ?? null) !== $index + 1 || ! is_string($page['text'] ?? null) || trim($page['text']) === '') {
                return false;
            }
            $characters += Str::length($page['text']);
        }

        return $characters >= 500 && $characters <= (int) config('literature.evidence.maximum_characters');
    }

    private function unavailable(?string $reason = null): never
    {
        throw ValidationException::withMessages(['evidence_basis' => ($reason ? $reason.' ' : '').'The whole source paper could not be read. Attach a complete readable PDF in Sources or choose “Use abstracts instead”. No abstract fallback was applied.']);
    }
}

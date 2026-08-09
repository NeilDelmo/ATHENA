<?php

namespace App\Actions;

use App\Models\LiteratureSource;
use App\Models\ProposalDraft;
use App\Models\ProposalDraftLiteratureSource;
use App\Models\User;
use Illuminate\Support\Str;

class LinkLiteratureSourceToProposal
{
    /** @return array{link: ProposalDraftLiteratureSource, already_linked: bool} */
    public function handle(
        ProposalDraft $proposalDraft,
        LiteratureSource $source,
        User $user,
        ?string $rrlNote = null,
        ?string $evidenceBasis = null,
    ): array {
        $link = $proposalDraft->literatureSources()->firstOrNew([
            'fingerprint' => $source->fingerprint,
        ]);
        $alreadyLinked = $link->exists;

        $link->fill([
            'literature_source_id' => $source->getKey(),
            'saved_by' => $user->getKey(),
            'title' => $source->title,
            'authors' => $source->authors,
            'abstract' => $source->abstract,
            'publication_year' => $source->publication_year,
            'publication_date' => $source->publication_date,
            'venue' => $source->venue,
            'volume' => $source->volume,
            'issue' => $source->issue,
            'pages' => $source->pages,
            'publisher' => $source->publisher,
            'doi' => $source->doi,
            'url' => $source->url,
            'full_text_url' => $source->full_text_url,
            'provider' => $source->provider,
            'provider_identifier' => $source->provider_identifier,
            'citation_count' => $source->citation_count,
            'is_open_access' => $source->is_open_access,
            'access_status' => $source->effectiveAccessStatus(),
            'publication_type' => $source->publication_type,
        ]);

        if (filled($rrlNote)) {
            $link->rrl_note = trim($rrlNote);
            $link->rrl_draft_status = ProposalDraftLiteratureSource::DRAFT_SAVED;
            $link->rrl_evidence_basis = $evidenceBasis ?: 'abstract';
            $link->rrl_word_count = Str::wordCount($rrlNote);
            $link->rrl_generated_at = now();
        }

        if (blank($link->reference_text)) {
            $link->reference_text = $source->referenceDraft();
        }

        $link->save();
        $link->load(['literatureSource.collections:id,name,slug', 'literatureSource.addedBy:id,name']);

        return ['link' => $link, 'already_linked' => $alreadyLinked];
    }
}

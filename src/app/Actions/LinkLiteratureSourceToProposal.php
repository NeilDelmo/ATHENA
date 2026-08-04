<?php

namespace App\Actions;

use App\Models\LiteratureSource;
use App\Models\ProposalDraft;
use App\Models\ProposalDraftLiteratureSource;
use App\Models\User;

class LinkLiteratureSourceToProposal
{
    /** @return array{link: ProposalDraftLiteratureSource, already_linked: bool} */
    public function handle(
        ProposalDraft $proposalDraft,
        LiteratureSource $source,
        User $user,
        ?string $rrlNote = null,
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
            'venue' => $source->venue,
            'doi' => $source->doi,
            'url' => $source->url,
            'provider' => $source->provider,
            'citation_count' => $source->citation_count,
            'is_open_access' => $source->is_open_access,
            'publication_type' => $source->publication_type,
        ]);

        if (filled($rrlNote)) {
            $link->rrl_note = trim($rrlNote);
        }

        if (blank($link->reference_text)) {
            $link->reference_text = $source->referenceDraft();
        }

        $link->save();
        $link->load(['literatureSource.collections:id,name,slug', 'literatureSource.addedBy:id,name']);

        return ['link' => $link, 'already_linked' => $alreadyLinked];
    }
}

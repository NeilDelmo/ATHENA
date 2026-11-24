<?php

namespace App\Services;

use App\Models\ProposalDraft;
use App\Models\TopicProposal;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

class ProposalTitleSimilarity
{
    private const COMMON_WORDS = ['a', 'an', 'and', 'by', 'for', 'from', 'in', 'of', 'on', 'or', 'research', 'study', 'the', 'to', 'with'];

    public function hasSimilarTitle(string $title, User $user): bool
    {
        $titleWords = $this->words($title);
        $titleKeywords = $this->keywords($titleWords);

        $draftTitles = ProposalDraft::query()
            ->accessibleTo($user)
            ->whereNull('topic_id')
            ->where('status', ProposalDraft::STATUS_DRAFT)
            ->select('id', 'project_title')
            ->toBase()
            ->lazyById()
            ->pluck('project_title');

        $proposalTitles = TopicProposal::query()
            ->where(fn (Builder $query): Builder => $query->approved()
                ->orWhere(fn (Builder $query): Builder => $query->accessibleTo($user)))
            ->select('id', 'title')
            ->toBase()
            ->lazyById()
            ->pluck('title');

        return $draftTitles->concat($proposalTitles)->contains(function (string $candidate) use ($titleWords, $titleKeywords): bool {
            $candidateWords = $this->words($candidate);
            if ($titleWords !== [] && $titleWords === $candidateWords) {
                return true;
            }

            $candidateKeywords = $this->keywords($candidateWords);
            $sharedWords = count(array_intersect($titleKeywords, $candidateKeywords));

            return $sharedWords >= 2
                && (2 * $sharedWords) / (count($titleKeywords) + count($candidateKeywords)) >= 0.75;
        });
    }

    /** @return list<string> */
    private function words(string $title): array
    {
        return preg_split('/[^\p{L}\p{M}\p{N}]+/u', Str::lower($title), -1, PREG_SPLIT_NO_EMPTY) ?: [];
    }

    /**
     * @param  list<string>  $words
     * @return list<string>
     */
    private function keywords(array $words): array
    {
        return array_values(array_unique(array_filter(
            $words,
            fn (string $word): bool => ! in_array($word, self::COMMON_WORDS, true) && preg_match('/\p{L}/u', $word) === 1,
        )));
    }
}

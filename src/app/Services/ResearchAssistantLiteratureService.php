<?php

namespace App\Services;

use App\Actions\LinkLiteratureSourceToProposal;
use App\Actions\SaveLiteratureSource;
use App\Models\ProposalDraft;
use App\Models\ProposalDraftLiteratureSource;
use App\Models\User;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ResearchAssistantLiteratureService
{
    private const ACTIONS = ['search_literature', 'draft_literature', 'confirm_literature'];

    public function __construct(
        private LiteratureSearchService $search,
        private LiteratureSynthesisService $synthesis,
        private SaveLiteratureSource $saveSource,
        private LinkLiteratureSourceToProposal $linkSource,
    ) {}

    public function isLiteratureRequest(string $question, ?string $action = null): bool
    {
        if ($action !== null) {
            return in_array($action, self::ACTIONS, true);
        }

        if (preg_match('/^\s*(?:(?:please|can\s+you|could\s+you|would\s+you)\s+)*(?:how\b|what\s+(?:is|are)\b|explain\b)/iu', $question) === 1
            || preg_match('/\b(?:give|show)\s+(?:me\s+)?(?:an?\s+)?(?:examples?|samples?)\b/iu', $question) === 1) {
            return false;
        }

        return preg_match('/\b(?:find|search(?:\s+for)?|look\s+for|suggest|recommend|show|give)\b.{0,80}\b(?:rrl|related\s+literature|studies|papers|sources|literature|research\s+articles)\b/iu', $question) === 1
            && preg_match('/\b(?:do\s+not|don[\x{2019}\x27]t|never)\s+(?:find|search|suggest|recommend|show|give)\b/iu', $question) !== 1;
    }

    /** @param array<string, mixed> $packet
     * @return array<string, mixed>
     */
    public function historyPacket(array $packet): array
    {
        $clean = [];

        foreach (Arr::only($packet, ['kind', 'proposal_draft_id', 'proposal_title', 'query', 'context_basis', 'year_from', 'year_to', 'editor_url', 'notice', 'confirmed', 'applied']) as $key => $value) {
            if (is_scalar($value) || $value === null) {
                $clean[$key] = is_string($value) ? Str::limit($value, 2048, '') : $value;
            }
        }

        foreach (['results' => 5, 'drafts' => 3, 'sources' => 3] as $key => $limit) {
            if (isset($packet[$key]) && is_array($packet[$key])) {
                $clean[$key] = array_map(fn (array $item): array => $this->boundedHistoryItem($item), array_slice(array_values(array_filter($packet[$key], 'is_array')), 0, $limit));
            }
        }

        return $clean;
    }

    /** @param array<string, mixed> $item
     * @return array<string, mixed>
     */
    private function boundedHistoryItem(array $item, int $depth = 0): array
    {
        $clean = [];

        foreach (array_slice($item, 0, 40, true) as $key => $value) {
            if (is_scalar($value) || $value === null) {
                $clean[$key] = is_string($value) ? Str::limit($value, in_array($key, ['paragraph', 'rrl_note'], true) ? 5000 : 2048, '') : $value;
            } elseif (is_array($value) && $depth < 1) {
                $clean[$key] = $this->boundedHistoryItem($value, $depth + 1);
            }
        }

        return $clean;
    }

    /** @param array<string, mixed> $context
     * @param  array<string, mixed>  $action
     * @return array<string, mixed>
     */
    public function respond(User $user, array $context, string $question, array $action = []): array
    {
        abort_unless($user->isUsingWorkspace([User::WORKSPACE_FACULTY, User::WORKSPACE_FACULTY_RESEARCHER]), 403);
        $draft = $this->draft($user, $context);

        if (! $draft) {
            return $this->envelope('Open or select an editable proposal, then ask me to find studies for its objectives. You can also include the topic you want to search.');
        }

        Gate::forUser($user)->authorize('update', $draft);

        return match ($action['type'] ?? 'search_literature') {
            'draft_literature' => $this->draftParagraphs($user, $draft, $context, $action['source_tokens']),
            'confirm_literature' => $this->confirmParagraphs($user, $draft, $action['drafts']),
            default => $this->findStudies($user, $draft, $context, $action['query'] ?? $question),
        };
    }

    /** @param array<string, mixed> $context */
    private function draft(User $user, array $context): ?ProposalDraft
    {
        $query = ProposalDraft::query()->accessibleTo($user)->with('documents');

        if (! empty($context['proposal_draft_id'])) {
            $draft = $query->find($context['proposal_draft_id']);
            abort_unless($draft, 403, 'That proposal draft is unavailable for your account.');

            return $draft;
        }

        return ! empty($context['topic_id'])
            ? $query->where('topic_id', $context['topic_id'])->first()
            : null;
    }

    /** @param array<string, mixed> $context
     * @return array<string, mixed>
     */
    private function findStudies(User $user, ProposalDraft $draft, array $context, string $question): array
    {
        $researchContext = $this->researchContext($draft, $context, $question);
        $query = $this->searchQuery($question, $researchContext['objectives'], $draft->project_title);
        $filters = $this->yearFilters($question);
        $payload = $this->search->search($query, $filters, $researchContext['search_context']);

        if ($payload['results'] === [] && $this->search->allProvidersFailed()) {
            abort(503, 'The academic search providers could not be reached. Please try the search again.');
        }

        $count = 5;

        if (preg_match('/\b(\d+|one|two|three|four|five)\s+(?:(?:relevant|recent|related|academic)\s+)*(?:studies|papers|sources|articles)\b/iu', $question, $match) === 1) {
            $count = min(5, max(1, ['one' => 1, 'two' => 2, 'three' => 3, 'four' => 4, 'five' => 5][Str::lower($match[1])] ?? (int) $match[1]));
        }

        $results = collect($payload['results'])->take($count)->map(function (array $result) use ($user, $draft, $researchContext): array {
            $evidence = trim((string) ($result['description'] ?? ''));
            $canSynthesize = Str::length($evidence) >= 80 && $evidence !== 'No description available from source.';
            $token = $this->cacheToken('source', $user, $draft, ['source' => $result, 'objectives' => $researchContext['objectives']]);

            return [
                ...Arr::only($result, ['title', 'authors', 'year', 'url', 'doi', 'description', 'source', 'citation_count', 'access_label']),
                'source_token' => $token,
                'relevance' => [
                    'score' => $result['relevance_score'] ?? 0,
                    'label' => $result['relevance_label'] ?? 'Review relevance',
                    'reason' => $result['match_reason'] ?? 'Review the source to assess its connection to your objective.',
                    'matched_terms' => $result['matched_terms'] ?? [],
                ],
                'evidence_basis' => $canSynthesize ? 'abstract' : 'metadata_only',
                'can_synthesize' => $canSynthesize,
            ];
        })->values()->all();
        $notice = collect([$payload['provider_notice'] ?? null, $payload['search_notice'] ?? null])
            ->filter()->implode(' ');
        $reply = $results === []
            ? 'I could not find sufficiently relevant studies for this search. Try a more specific topic or a different objective.'
            : 'I found '.count($results).' studies to review for your proposal. Select up to three to draft editable RRL paragraphs. Search relevance is a starting point; check each paper before using its claims.';

        return $this->envelope($reply, [
            'kind' => 'results',
            'proposal_draft_id' => $draft->getKey(),
            'proposal_title' => $draft->project_title,
            'query' => $query,
            'context_basis' => $researchContext['basis'],
            'year_from' => $filters['year_from'] ?? null,
            'year_to' => $filters['year_to'] ?? null,
            'results' => $results,
            'notice' => $notice ?: 'Paragraphs use the indexed abstracts. Open the full papers to check methods and limitations.',
        ]);
    }

    /** @param array<string, mixed> $context
     * @return array{objectives: string, search_context: string, preceding_rrl: string, basis: string}
     */
    private function researchContext(ProposalDraft $draft, array $context, string $question): array
    {
        $data = $draft->documents->firstWhere('document_type', config('proposal_papers.detailed-proposal.document_type'))?->source_data ?? [];
        $general = Str::squish((string) ($data['general_objective'] ?? ''));
        $specific = collect($data['specific_objectives'] ?? [])->map(fn (mixed $objective): string => Str::squish(is_array($objective) ? (string) ($objective['description'] ?? '') : (string) $objective))->filter()->values()->all();
        $basis = 'Saved proposal objectives';
        $preceding = (string) ($data['related_literature'] ?? '');
        $browserObjectives = [];
        $browserSpecific = [];
        $objectiveNumber = $this->objectiveNumber($question);

        foreach ($context['form']['values'] ?? [] as $value) {
            $field = Str::lower((string) ($value['field'] ?? ''));
            $text = Str::squish((string) ($value['value'] ?? ''));

            if ($text !== '' && Str::contains($field, ['general_objective', 'specific_objective'])) {
                $browserObjectives[] = $text;

                if (preg_match('/specific_objectives?[.\[]([0-9]+)\]?/iu', $field, $match) === 1) {
                    $browserSpecific[(int) $match[1] + 1] = $text;
                } elseif (preg_match('/\bobjective\s*(\d+)\b/iu', (string) ($value['label'] ?? ''), $match) === 1) {
                    $browserSpecific[(int) $match[1]] = $text;
                }
            }

            if ($field === 'related_literature' && $text !== '') {
                $preceding = $text;
            }
        }

        if ($objectiveNumber !== null) {
            if (isset($browserSpecific[$objectiveNumber])) {
                $objectives = $browserSpecific[$objectiveNumber];
                $basis = 'Current browser specific objective '.$objectiveNumber.' (may be unsaved)';
            } elseif (isset($specific[$objectiveNumber - 1])) {
                $objectives = $specific[$objectiveNumber - 1];
                $basis = 'Saved specific objective '.$objectiveNumber;
            } else {
                throw ValidationException::withMessages(['action.query' => 'That specific objective is unavailable. Open the objective or include its text in your request.']);
            }
        } elseif ($browserObjectives !== []) {
            $objectives = implode(' ', $browserObjectives);
            $basis = 'Current browser objective values (may be unsaved)';
        } else {
            $objectives = implode(' ', array_filter([$general, ...array_slice($specific, 0, 3)]));
        }

        return [
            'objectives' => Str::limit($objectives, 1200, ''),
            'search_context' => Str::limit($draft->project_title.' '.$objectives, 6000, ''),
            'preceding_rrl' => Str::substr(Str::squish($preceding), -2500),
            'basis' => $objectives !== '' ? $basis : 'Saved proposal title',
        ];
    }

    private function objectiveNumber(string $question): ?int
    {
        if (preg_match('/\bobjectives?\s*(?:number\s*)?#?\s*(\d+)\b/iu', $question, $match) === 1) {
            return (int) $match[1];
        }

        if (preg_match('/\b(first|second|third|fourth|fifth|sixth|seventh|eighth|ninth|tenth)\s+(?:specific\s+)?objective\b/iu', $question, $match) === 1) {
            return array_search(Str::lower($match[1]), ['first', 'second', 'third', 'fourth', 'fifth', 'sixth', 'seventh', 'eighth', 'ninth', 'tenth'], true) + 1;
        }

        return null;
    }

    private function searchQuery(string $question, string $objectives, string $title): string
    {
        $topic = '';

        if (preg_match('/\b(?:about|on|for)\s+(.+?)(?:[?.!]|$)/iu', $question, $match) === 1
            && preg_match('/\b(?:my|our|this|these|objective|objectives|proposal)\b/iu', $match[1]) !== 1) {
            $topic = $match[1];
        }

        if ($topic === '' && ! $this->isLiteratureRequest($question)) {
            $topic = $question;
        }

        $topic = preg_replace('/\s+(?:since|from)\s+\d{4}.*$/iu', '', $topic) ?? $topic;

        return Str::limit(Str::squish($topic ?: ($objectives ?: $title)), 500, '');
    }

    /** @return array{year_from?: int, year_to?: int} */
    private function yearFilters(string $question): array
    {
        $currentYear = now()->year;

        if (preg_match('/\b(?:since|from)\s+(\d{4})(?:\s*(?:to|through|[-–])\s*(\d{4}))?/iu', $question, $match) === 1) {
            $from = (int) $match[1];
            $to = isset($match[2]) ? (int) $match[2] : $currentYear;

            if ($from >= 1500 && $from <= $to && $to <= $currentYear) {
                return ['year_from' => $from, 'year_to' => $to];
            }
        }

        if (preg_match('/\b(?:recent|latest|last\s+(\d+)\s+years?)\b/iu', $question, $match) === 1) {
            $years = min(30, max(1, (int) ($match[1] ?? 5)));

            return ['year_from' => $currentYear - $years + 1, 'year_to' => $currentYear];
        }

        return [];
    }

    /** @param array<string, mixed> $context
     * @param  list<string>  $sourceTokens
     * @return array<string, mixed>
     */
    private function draftParagraphs(User $user, ProposalDraft $draft, array $context, array $sourceTokens): array
    {
        $sources = array_map(fn (string $token): array => $this->resolveToken('source', $token, $user, $draft), $sourceTokens);
        $researchContext = $this->researchContext($draft, $context, '');
        $preceding = $researchContext['preceding_rrl'];
        $drafts = [];

        foreach ($sources as $index => $cached) {
            $source = $cached['source'];
            $abstract = trim((string) ($source['description'] ?? ''));

            if (Str::length($abstract) < 80 || $abstract === 'No description available from source.') {
                throw ValidationException::withMessages(['action.source_tokens' => 'This source has insufficient abstract evidence. Select a study with an available abstract.']);
            }

            $generated = $this->synthesis->synthesize([
                'title' => $source['title'],
                'authors' => $source['authors'] ?? null,
                'year' => $source['year'] ?? null,
                'abstract' => Str::limit($abstract, 6000, ''),
                'evidence_basis' => 'abstract',
                'proposal_title' => $draft->project_title,
                'proposal_objectives' => $cached['objectives'] ?? $researchContext['objectives'],
                'preceding_rrl_context' => $preceding,
                'connection_mode' => 'auto',
            ]);
            $token = $this->cacheToken('draft', $user, $draft, ['source' => $source, 'basis' => 'abstract']);
            $drafts[] = [
                'draft_token' => $token,
                'source_token' => $sourceTokens[$index],
                ...Arr::only($source, ['title', 'authors', 'year', 'url', 'doi']),
                'paragraph' => $generated['synthesis'],
                'evidence_basis' => 'abstract',
                'relationship' => $generated['relationship'],
                'notice' => $generated['notice'],
            ];
            $preceding = Str::substr($preceding.' '.$generated['synthesis'], -2500);
        }

        return $this->envelope('Review and edit each paragraph, then choose Add to paper to insert it with its IEEE citation and reference.', [
            'kind' => 'draft',
            'proposal_draft_id' => $draft->getKey(),
            'proposal_title' => $draft->project_title,
            'drafts' => $drafts,
            'notice' => 'Each paragraph is based on its source abstract. Verify the full paper before relying on methods, limitations, or causal claims.',
        ]);
    }

    /** @param list<array{draft_token: string, paragraph: string}> $paragraphs
     * @return array<string, mixed>
     */
    private function confirmParagraphs(User $user, ProposalDraft $draft, array $paragraphs): array
    {
        $cachedDrafts = array_map(fn (array $paragraph): array => $this->resolveToken('draft', $paragraph['draft_token'], $user, $draft), $paragraphs);
        $sources = Cache::lock('assistant-literature:confirm:'.$user->getKey().':'.$draft->getKey(), 15)->block(3, function () use ($paragraphs, $cachedDrafts, $user, $draft): array {
            $confirmations = [];
            $sources = DB::transaction(function () use ($paragraphs, $cachedDrafts, $user, $draft, &$confirmations): array {
                $draft->ensureEditable();
                $sources = [];

                foreach ($paragraphs as $index => $paragraph) {
                    $note = trim($paragraph['paragraph']);
                    $cached = $cachedDrafts[$index];
                    $key = $this->tokenKey('confirmed', $paragraph['draft_token']);
                    $confirmed = Cache::get($key);

                    if (is_array($confirmed)) {
                        if (! hash_equals($confirmed['paragraph_hash'], hash('sha256', $note))) {
                            throw ValidationException::withMessages(['action.drafts' => 'This draft has already been added. Edit the paragraph in your paper or generate a new draft.']);
                        }

                        $sources[] = $confirmed['source'];

                        continue;
                    }

                    $saved = $this->saveSource->handle($cached['source'], $user);
                    $linked = $this->linkSource->handle($draft, $saved['source'], $user, $note, $cached['basis']);
                    $link = $linked['link'];
                    $link->update(['rrl_draft_status' => ProposalDraftLiteratureSource::DRAFT_CONFIRMED]);
                    $source = $link->toLibraryArray();
                    $confirmations[$key] = ['source' => $source, 'paragraph_hash' => hash('sha256', $note)];
                    $sources[] = $source;
                }

                return $sources;
            });

            foreach ($confirmations as $key => $confirmation) {
                Cache::put($key, $confirmation, now()->addMinutes(30));
            }

            return $sources;
        });

        return $this->envelope('Your reviewed paragraphs are ready to insert into the proposal with their citations and references.', [
            'kind' => 'insertion',
            'proposal_draft_id' => $draft->getKey(),
            'proposal_title' => $draft->project_title,
            'sources' => $sources,
            'editor_url' => route('faculty.proposal-drafts.detailed-proposal.edit', $draft),
            'notice' => 'The paper editor applies the reviewed paragraphs and saves the updated paper.',
        ]);
    }

    /** @param array<string, mixed> $value */
    private function cacheToken(string $kind, User $user, ProposalDraft $draft, array $value): string
    {
        $token = Str::random(64);
        Cache::put($this->tokenKey($kind, $token), [...$value, 'user_id' => $user->getKey(), 'proposal_draft_id' => $draft->getKey()], now()->addMinutes(30));

        return $token;
    }

    /** @return array<string, mixed> */
    private function resolveToken(string $kind, string $token, User $user, ProposalDraft $draft): array
    {
        $value = Cache::get($this->tokenKey($kind, $token));

        if (! is_array($value) || ($value['user_id'] ?? null) !== $user->getKey() || ($value['proposal_draft_id'] ?? null) !== $draft->getKey()) {
            throw ValidationException::withMessages(['action' => 'This literature selection has expired or belongs to another proposal. Search again to continue.']);
        }

        return $value;
    }

    private function tokenKey(string $kind, string $token): string
    {
        return 'assistant-literature:'.$kind.':'.hash('sha256', $token);
    }

    /** @param array<string, mixed>|null $literature
     * @return array<string, mixed>
     */
    private function envelope(string $reply, ?array $literature = null): array
    {
        return [
            'reply' => $reply,
            'model' => 'athena-literature',
            'sources' => [],
            'usage' => ['prompt_tokens' => 0, 'completion_tokens' => 0],
            ...($literature ? ['literature' => $literature] : []),
        ];
    }
}

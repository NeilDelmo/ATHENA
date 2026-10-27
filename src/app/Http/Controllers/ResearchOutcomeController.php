<?php

namespace App\Http\Controllers;

use App\Http\Requests\SaveResearchOutcomeRequest;
use App\Models\ResearchOutcome;
use App\Models\TopicProposal;
use App\Models\User;
use App\Notifications\ProposalActivityNotification;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class ResearchOutcomeController extends Controller
{
    public function index(Request $request): View
    {
        Gate::authorize('viewAny', ResearchOutcome::class);
        $user = $request->user();
        $projects = TopicProposal::query()->completedProject()->withProjectCollege()->with(['user', 'researchOutcome'])
            ->when($user->isUsingWorkspace(User::WORKSPACE_FACULTY_RESEARCHER), fn (Builder $query) => $query->accessibleTo($user))
            ->when($user->isUsingWorkspace(User::WORKSPACE_RESEARCH_OFFICE), fn (Builder $query) => $query->forCollege($user->college))
            ->orderBy('title')->orderBy('id')->get()
            ->map(fn (TopicProposal $topic): ResearchOutcome => $this->projectOutcome($topic));
        $other = ResearchOutcome::query()->whereNull('topic_id')->with('leader')
            ->when($user->isUsingWorkspace(User::WORKSPACE_FACULTY_RESEARCHER), fn (Builder $query) => $query->where('leader_id', $user->id))
            ->when($user->isUsingWorkspace(User::WORKSPACE_RESEARCH_OFFICE), fn (Builder $query) => $query
                ->whereHas('leader', fn (Builder $leader) => $leader->where('college', $user->college))
                ->when(blank($user->college), fn (Builder $empty) => $empty->whereKey(-1)))
            ->orderBy('title')->orderBy('id')->get();
        $rows = $projects->toBase()->concat($other);
        $page = LengthAwarePaginator::resolveCurrentPage();

        return view('research-outcomes.index', [
            'outcomes' => new LengthAwarePaginator($rows->forPage($page, 15)->values(), $rows->count(), 15, $page, [
                'path' => $request->url(), 'query' => $request->query(),
            ]),
        ]);
    }

    public function create(): View
    {
        Gate::authorize('create', ResearchOutcome::class);

        return view('research-outcomes.create');
    }

    public function store(SaveResearchOutcomeRequest $request): RedirectResponse
    {
        [$outcome, $message] = DB::transaction(function () use ($request): array {
            $outcome = ResearchOutcome::create([
                ...$request->safe()->only(['title', 'team_members', 'funding_source']),
                'leader_id' => $request->user()->id,
                'labels' => $this->labels($request),
                'lock_version' => 1,
            ]);

            return [$outcome, $this->notifyCoordinators($outcome)];
        });

        return redirect($outcome->detailUrl())->with('success', 'Research registered. '.$message);
    }

    public function showProject(TopicProposal $topic): View
    {
        $topic = TopicProposal::query()->withProjectCollege()->findOrFail($topic->id);
        $outcome = $this->projectOutcome($topic);
        Gate::authorize('view', $outcome);
        $topic->load(['user', 'collaborators.user', 'revisionDraft.members.user']);

        return view('research-outcomes.show', ['outcome' => $outcome]);
    }

    public function updateProject(SaveResearchOutcomeRequest $request, TopicProposal $topic): RedirectResponse
    {
        $message = DB::transaction(function () use ($request, $topic): string {
            $lockedTopic = TopicProposal::query()->lockForUpdate()->findOrFail($topic->id);
            $outcome = $lockedTopic->researchOutcome()->lockForUpdate()->first()
                ?? $lockedTopic->researchOutcome()->make(['leader_id' => $lockedTopic->user_id, 'funding_source' => 'school']);
            $outcome->setRelation('topic', $lockedTopic);

            return $this->saveChanges($request, $outcome);
        });

        return redirect()->route('research-outcomes.projects.show', $topic)->with('success', $message);
    }

    public function show(ResearchOutcome $outcome): View
    {
        abort_if($outcome->topic_id !== null, 404);
        Gate::authorize('view', $outcome);

        return view('research-outcomes.show', ['outcome' => $outcome]);
    }

    public function update(SaveResearchOutcomeRequest $request, ResearchOutcome $outcome): RedirectResponse
    {
        $message = DB::transaction(function () use ($request, $outcome): string {
            $locked = ResearchOutcome::query()->lockForUpdate()->findOrFail($outcome->id);
            abort_if($locked->topic_id !== null, 404);

            return $this->saveChanges($request, $locked);
        });

        return redirect($outcome->detailUrl())->with('success', $message);
    }

    private function projectOutcome(TopicProposal $topic): ResearchOutcome
    {
        $outcome = $topic->researchOutcome ?? $topic->researchOutcome()->make(['leader_id' => $topic->user_id, 'funding_source' => 'school']);

        return $outcome->setRelation('topic', $topic);
    }

    private function saveChanges(SaveResearchOutcomeRequest $request, ResearchOutcome $outcome): string
    {
        Gate::authorize('update', $outcome);
        if ($outcome->lock_version !== $request->integer('lock_version')) {
            throw ValidationException::withMessages(['lock_version' => 'This record changed in another tab. Reload the page before saving again.']);
        }
        $outcome->labels = $this->labels($request);
        if ($outcome->topic_id === null) {
            $outcome->fill($request->safe()->only(['title', 'team_members', 'funding_source']));
        }
        if (! $outcome->isDirty(['labels', 'title', 'team_members', 'funding_source'])
            || (! $outcome->exists && $outcome->topic_id && $outcome->labels === [])) {
            return 'No changes to save.';
        }
        $outcome->lock_version++;
        $outcome->save();

        return 'Labels saved. '.$this->notifyCoordinators($outcome);
    }

    /** @return list<string> */
    private function labels(SaveResearchOutcomeRequest $request): array
    {
        return array_values(array_intersect(array_keys(ResearchOutcome::LABELS), $request->validated('labels', [])));
    }

    private function notifyCoordinators(ResearchOutcome $outcome): string
    {
        $leader = $outcome->projectLeader();
        $college = $outcome->projectCollege();
        $recipients = User::query()->assignedToRole('research_coordinator')
            ->whereIn('college', TopicProposal::collegeAliases($college))->get();
        $labels = collect($outcome->labels)->map(fn (string $label): string => ResearchOutcome::LABELS[$label])->implode(', ');
        foreach ($recipients as $recipient) {
            $recipient->notify(new ProposalActivityNotification(
                title: 'Post-implementation update',
                message: $leader->name.' updated '.$outcome->projectTitle().'. Current labels: '.($labels ?: 'None').'.',
                url: $outcome->detailUrl(),
                topicId: $outcome->topic_id,
                workspace: User::WORKSPACE_RESEARCH_OFFICE,
                sidebarArea: ProposalActivityNotification::SIDEBAR_AREA_RESEARCH_OUTCOMES,
            ));
        }

        return $recipients->isNotEmpty() ? 'Your college coordinator has been notified.' : 'No coordinator is assigned to your college.';
    }
}

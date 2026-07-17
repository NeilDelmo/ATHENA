<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreProposalDraftRequest;
use App\Models\ProposalDraft;
use App\Models\ProposalTemplate;
use App\Models\ResearchCall;
use App\Support\ProposalDraftReadiness;
use App\Support\ProposalPaperCatalog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class ProposalDraftController extends Controller
{
    public function index(Request $request): View
    {
        Gate::authorize('viewAny', ProposalDraft::class);

        $proposalDrafts = $request->user()->proposalDrafts()
            ->with(['researchCall', 'documents'])
            ->latest()
            ->paginate(12);

        return view('faculty.proposal-drafts.index', compact('proposalDrafts'));
    }

    public function create(): View
    {
        Gate::authorize('create', ProposalDraft::class);

        $researchCalls = ResearchCall::query()
            ->where('status', 'open')
            ->where('opens_at', '<=', now())
            ->where('closes_at', '>=', now())
            ->orderBy('closes_at')
            ->get();

        return view('faculty.proposal-drafts.create', compact('researchCalls'));
    }

    public function store(StoreProposalDraftRequest $request): RedirectResponse
    {
        Gate::authorize('create', ProposalDraft::class);

        $proposalDraft = $request->user()->proposalDrafts()->create($request->validated());

        return redirect()
            ->route('faculty.proposal-drafts.details.edit', $proposalDraft)
            ->with('success', 'Proposal draft created. Complete the shared project details next.');
    }

    public function show(
        ProposalDraft $proposalDraft,
        ProposalPaperCatalog $catalog,
        ProposalDraftReadiness $readiness,
    ): View {
        Gate::authorize('view', $proposalDraft);

        $proposalDraft->load(['researchCall', 'documents']);
        $checklist = $readiness->checklist($proposalDraft);
        $projectDetailsComplete = $readiness->projectDetailsAreComplete($proposalDraft);
        $readinessErrors = $readiness->errors($proposalDraft);
        $templates = $this->activeTemplates($catalog);

        return view('faculty.proposal-drafts.show', compact(
            'proposalDraft',
            'checklist',
            'projectDetailsComplete',
            'readinessErrors',
            'templates',
        ));
    }

    public function destroy(ProposalDraft $proposalDraft): RedirectResponse
    {
        Gate::authorize('delete', $proposalDraft);

        $storageDirectory = $proposalDraft->storageDirectory();
        $proposalDraft->delete();
        Storage::disk('local')->deleteDirectory($storageDirectory);

        return redirect()
            ->route('faculty.proposal-drafts.index')
            ->with('success', 'Proposal draft deleted.');
    }

    private function activeTemplates(ProposalPaperCatalog $catalog): Collection
    {
        $templateSlugs = $catalog->all()
            ->pluck('template_slug')
            ->filter()
            ->unique()
            ->values();

        return ProposalTemplate::query()
            ->active()
            ->where('workflow_stage', ProposalTemplate::STAGE_INITIAL_SUBMISSION)
            ->whereIn('slug', $templateSlugs)
            ->get()
            ->filter(fn (ProposalTemplate $template): bool => Storage::disk('local')->exists($template->file_path))
            ->keyBy('slug');
    }
}

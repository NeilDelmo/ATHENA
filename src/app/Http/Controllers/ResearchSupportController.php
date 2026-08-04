<?php

namespace App\Http\Controllers;

use App\Models\LiteratureCollection;
use App\Models\LiteratureSource;
use App\Models\ProposalDraft;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class ResearchSupportController extends Controller
{
    public function index(Request $request): View
    {
        $proposalDrafts = collect();
        $literatureCollections = collect();
        $sharedLiteratureSources = collect();

        if ($request->user()->isUsingWorkspace([
            User::WORKSPACE_FACULTY,
            User::WORKSPACE_FACULTY_RESEARCHER,
        ])) {
            Gate::authorize('viewAny', ProposalDraft::class);

            $proposalDrafts = ProposalDraft::query()
                ->accessibleTo($request->user())
                ->where('status', ProposalDraft::STATUS_DRAFT)
                ->with('owner:id,name')
                ->latest('updated_at')
                ->limit(30)
                ->get(['id', 'user_id', 'project_title', 'updated_at']);

            $literatureCollections = LiteratureCollection::query()
                ->withCount('sources')
                ->orderBy('name')
                ->get(['id', 'name', 'slug']);

            $sharedLiteratureSources = LiteratureSource::query()
                ->with(['addedBy:id,name', 'collections:id,name,slug'])
                ->latest()
                ->limit(100)
                ->get()
                ->map(fn (LiteratureSource $source): array => $source->toLibraryArray())
                ->values();
        }

        return view('faculty.research_support.index', compact(
            'proposalDrafts',
            'literatureCollections',
            'sharedLiteratureSources',
        ));
    }
}

<?php

namespace App\Http\Controllers;

use App\Models\ProposalDraft;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class ProposalDraftTrashController extends Controller
{
    public function index(Request $request): View
    {
        Gate::authorize('viewAny', ProposalDraft::class);

        $proposalDrafts = ProposalDraft::onlyTrashed()
            ->whereBelongsTo($request->user(), 'owner')
            ->where('status', ProposalDraft::STATUS_DRAFT)
            ->latest('deleted_at')
            ->paginate(12);

        return view('faculty.proposal-drafts.trash', compact('proposalDrafts'));
    }

    public function restore(Request $request, int $proposalDraft): RedirectResponse
    {
        $draft = DB::transaction(function () use ($request, $proposalDraft): ProposalDraft {
            $draft = ProposalDraft::onlyTrashed()
                ->whereBelongsTo($request->user(), 'owner')
                ->lockForUpdate()
                ->findOrFail($proposalDraft);
            Gate::authorize('restore', $draft);
            $draft->restore();

            return $draft;
        });

        return redirect()->route('faculty.proposal-drafts.show', $draft)
            ->with('success', 'Proposal draft restored with its saved papers and history.');
    }
}

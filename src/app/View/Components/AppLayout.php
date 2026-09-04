<?php

namespace App\View\Components;

use App\Models\ProposalDraft;
use App\Models\TopicProposal;
use Illuminate\View\Component;
use Illuminate\View\View;

class AppLayout extends Component
{
    /**
     * Get the view / contents that represents the component.
     */
    public function render(): View
    {
        $request = request();
        if ($request->boolean('revision_embed')) {
            $draft = $request->route('proposalDraft');
            $topic = $request->route('topic');
            $isRevisionEditor = $draft instanceof ProposalDraft
                && $draft->topic?->status === 'revision_requested'
                && $request->user()?->can('update', $draft)
                && $request->routeIs(
                    'faculty.proposal-drafts.detailed-proposal.edit',
                    'faculty.proposal-drafts.work-plan.edit',
                    'faculty.proposal-drafts.line-item-budget.edit',
                    'faculty.proposal-drafts.expense-breakdown.edit',
                    'faculty.proposal-drafts.curriculum-vitae.edit',
                );
            $isRevisionPreview = $topic instanceof TopicProposal
                && $topic->user_id === $request->user()?->id
                && $topic->status === 'revision_requested'
                && $request->routeIs('topics.versions.files.annotations.index');

            abort_unless($isRevisionEditor || $isRevisionPreview, 403);

            return view('layouts.revision-editor');
        }

        return view('layouts.app');
    }
}

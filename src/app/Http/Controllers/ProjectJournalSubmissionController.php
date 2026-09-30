<?php

namespace App\Http\Controllers;

use App\Http\Requests\SaveProjectJournalSubmissionRequest;
use App\Models\ProjectJournalSubmission;
use App\Models\TopicProposal;
use Illuminate\Http\RedirectResponse;

class ProjectJournalSubmissionController extends Controller
{
    public function store(SaveProjectJournalSubmissionRequest $request, TopicProposal $topic): RedirectResponse
    {
        $topic->journalSubmissions()->create($request->validated() + ['added_by' => $request->user()->id]);

        return redirect()->route('research.dissemination.show', $topic)->withFragment('journal-submissions')->with('status', 'Journal submission added.');
    }

    public function update(SaveProjectJournalSubmissionRequest $request, TopicProposal $topic, ProjectJournalSubmission $journalSubmission): RedirectResponse
    {
        $journalSubmission->update($request->validated());

        return redirect()->route('research.dissemination.show', $topic)->withFragment('journal-submissions')->with('status', 'Journal submission updated.');
    }
}

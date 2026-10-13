<?php

namespace App\Http\Controllers;

use App\Http\Requests\SaveProjectJournalSubmissionRequest;
use App\Models\ProjectJournalSubmission;
use App\Models\TopicProposal;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;

class ProjectJournalSubmissionController extends Controller
{
    public function store(SaveProjectJournalSubmissionRequest $request, TopicProposal $topic): RedirectResponse|JsonResponse
    {
        $submission = $topic->journalSubmissions()->create($request->safe()->except('return_to_finder') + ['added_by' => $request->user()->id]);

        if ($request->expectsJson()) {
            return response()->json(['id' => $submission->id, 'message' => 'Journal submission added.'], 201);
        }

        return redirect()->route(
            $request->boolean('return_to_finder') ? 'research-support.index' : 'research.dissemination.show',
            $request->boolean('return_to_finder') ? ['topic' => $topic->id] : $topic,
        )->withFragment('journal-submissions')->with('status', 'Journal submission added.');
    }

    public function update(SaveProjectJournalSubmissionRequest $request, TopicProposal $topic, ProjectJournalSubmission $journalSubmission): RedirectResponse
    {
        $journalSubmission->update($request->safe()->except('return_to_finder'));

        return redirect()->route(
            $request->boolean('return_to_finder') ? 'research-support.index' : 'research.dissemination.show',
            $request->boolean('return_to_finder') ? ['topic' => $topic->id] : $topic,
        )->withFragment('journal-submissions')->with('status', 'Journal submission updated.');
    }
}

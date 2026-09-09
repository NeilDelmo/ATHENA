<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateProposalSimilarityCheckRequest;
use App\Models\ProposalSimilarityCheck;
use App\Models\TopicProposal;
use App\Models\User;
use App\Notifications\ProposalActivityNotification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ProposalSimilarityCheckController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();
        abort_unless($user->isUsingWorkspace(['faculty', 'faculty_researcher', 'research_head']), 403);
        $isHead = $user->isUsingWorkspace('research_head');
        $request->validate(['topic' => ['nullable', 'integer']]);
        $checks = ProposalSimilarityCheck::query()->with('file.version.topic')
            ->when(! $isHead, fn ($query) => $query->whereHas('file.version.topic', fn ($topics) => $topics->accessibleTo($user)))
            ->when($request->integer('topic'), fn ($query) => $query->whereHas('file.version', fn ($versions) => $versions->where('topic_id', $request->integer('topic'))))
            ->orderByRaw("CASE WHEN status = 'completed' THEN 1 ELSE 0 END")->latest()->paginate(15)->withQueryString();
        $topics = $isHead ? collect() : TopicProposal::query()->accessibleTo($user)
            ->whereHas('versions')->with(['versions' => fn ($query) => $query->reorder('version_number', 'desc')->limit(1)->with('files')])
            ->when($request->integer('topic'), fn ($query) => $query->whereKey($request->integer('topic')))->latest()->get();

        return view('faculty.research_support.similarity-checks', compact('checks', 'topics', 'isHead'));
    }

    public function store(Request $request, TopicProposal $topic): RedirectResponse
    {
        Gate::authorize('view', $topic);
        abort_unless($request->user()->isUsingWorkspace(['faculty', 'faculty_researcher']) && $topic->isAccessibleTo($request->user()), 403);
        $request->validate(['proposal_version_file_id' => ['required', 'integer']]);
        $check = DB::transaction(function () use ($request, $topic) {
            TopicProposal::whereKey($topic->id)->lockForUpdate()->firstOrFail();
            $version = $topic->versions()->reorder('version_number', 'desc')->firstOrFail();
            $file = $version->files()->where('document_type', 'detailed_proposal')->firstOrFail();
            abort_unless($request->integer('proposal_version_file_id') === $file->id, 409, 'A newer proposal version is available. Reload and request a check for that version.');
            abort_unless(Storage::disk('local')->exists($file->file_path), 422, 'The submitted proposal file is unavailable. Contact the research office.');

            return ProposalSimilarityCheck::firstOrCreate(['proposal_version_file_id' => $file->id], ['requested_by' => $request->user()->id, 'status' => 'requested']);
        });
        if ($check->wasRecentlyCreated) {
            User::role('research_head')->get()->each->notify(new ProposalActivityNotification('Similarity check requested', 'A document similarity check was requested for '.$topic->title.'.', route('similarity-checks.index', ['topic' => $topic]), 'info', $topic->id, workspace: 'research_head'));
        }

        return redirect()->route('similarity-checks.index', ['topic' => $topic])->with('success', $check->wasRecentlyCreated ? 'Similarity check requested for the latest submitted document.' : 'This proposal version already has a similarity-check record.');
    }

    public function update(UpdateProposalSimilarityCheckRequest $request, ProposalSimilarityCheck $check): RedirectResponse
    {
        $data = $request->validated();
        $path = $request->file('report')?->store('similarity-reports/'.$check->id, 'local');
        try {
            DB::transaction(function () use ($check, $request, $data, $path): void {
                $locked = ProposalSimilarityCheck::whereKey($check->id)->lockForUpdate()->firstOrFail();
                abort_if($locked->status === 'completed', 409, 'This completed result is preserved for this proposal version.');
                $locked->update(['status' => $data['status'], 'handled_by' => $request->user()->id, 'similarity_score' => $data['similarity_score'] ?? null, 'notes' => $data['notes'] ?? null, 'report_path' => $path, 'completed_at' => $data['status'] === 'completed' ? now() : null]);
            });
        } catch (\Throwable $exception) {
            if ($path) {
                Storage::disk('local')->delete($path);
            }
            throw $exception;
        }
        if ($data['status'] === 'completed') {
            $topic = $check->file->version->topic;
            $topic->user->notify(new ProposalActivityNotification('Similarity report available', 'The similarity report for '.$topic->title.' is ready to view.', route('similarity-checks.index', ['topic' => $topic]), 'info', $topic->id, workspace: 'faculty'));
        }

        return back()->with('success', 'Similarity-check status updated.');
    }

    public function download(Request $request, ProposalSimilarityCheck $check): BinaryFileResponse
    {
        Gate::authorize('view', $check->file->version->topic);
        abort_unless($check->status === 'completed' && $check->report_path && Storage::disk('local')->exists($check->report_path), 404);

        return response()->download(Storage::disk('local')->path($check->report_path), 'similarity-report-version-'.$check->file->version->version_number.'.pdf', ['Content-Type' => 'application/pdf']);
    }
}

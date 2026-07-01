<?php

namespace App\Http\Controllers;

use App\Models\TopicProposal;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Throwable;

class TopicController extends Controller
{
    public function index()
    {
        $topics = Auth::user()->proposals()
            ->with(['reviews' => fn ($query) => $query->with('reviewer')->oldest()])
            ->latest()
            ->get();

        return view('dashboard', compact('topics'));
    }

    public function researchIndex(Request $request)
    {
        $status = $request->string('status')->toString();
        $search = trim($request->string('search')->toString());
        $allowedStatuses = ['pending', 'revision_requested', 'resubmitted', 'approved', 'rejected'];

        $topics = TopicProposal::query()
            ->where('user_id', $request->user()->id)
            ->when(in_array($status, $allowedStatuses, true), fn ($query) => $query->where('status', $status))
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($query) use ($search) {
                    $query->where('title', 'like', "%{$search}%")
                        ->orWhere('description', 'like', "%{$search}%");
                });
            })
            ->latest()
            ->paginate(15)
            ->withQueryString();

        return view('research.index', compact('topics', 'status', 'search'));
    }

    public function researchShow(Request $request, TopicProposal $topic)
    {
        $this->ensureCanViewTopic($request, $topic);

        $topic->load([
            'user',
            'reviews' => fn ($query) => $query->with('reviewer')->oldest(),
        ]);

        return view('research.show', compact('topic'));
    }

    public function store(Request $request)
    {
        $validated = $request->validateWithBag('submission', [
            'title' => 'required|string|max:255',
            'description' => 'nullable|string|max:5000',
            'estimated_budget' => 'required|numeric|min:0|max:9999999999.99',
            'document' => 'required|file|mimes:pdf,doc,docx|max:25600',
        ], [], [
            'estimated_budget' => 'estimated proposal budget',
            'document' => 'proposal document',
        ]);

        try {
            $path = $request->file('document')->store('proposals', 'local');
        } catch (Throwable) {
            $path = false;
        }

        if (! $path) {
            return back()
                ->withInput()
                ->withErrors(['document' => 'The proposal document could not be uploaded. Please try again.']);
        }

        Auth::user()->proposals()->create([
            'title' => $validated['title'],
            'description' => $validated['description'] ?? null,
            'estimated_budget' => $validated['estimated_budget'],
            'initial_file_path' => $path,
            'status' => 'pending',
        ]);

        return redirect()->route('faculty.dashboard')->with('success', 'Proposal submitted successfully and sent to the Research Head.');
    }

    public function resubmit(Request $request, TopicProposal $topic)
    {
        abort_unless($topic->user_id === $request->user()->id, 403);

        if ($topic->status !== 'revision_requested') {
            return back()
                ->withInput()
                ->withErrors(['status' => 'Only proposals with a requested revision can be resubmitted.'], 'resubmission');
        }

        $validated = $request->validateWithBag('resubmission', [
            'title' => 'required|string|max:255',
            'description' => 'nullable|string|max:5000',
            'estimated_budget' => 'required|numeric|min:0|max:9999999999.99',
            'document' => 'required|file|mimes:pdf,doc,docx|max:25600',
        ], [], [
            'estimated_budget' => 'estimated proposal budget',
            'document' => 'revised proposal document',
        ]);

        try {
            $path = $request->file('document')->store('proposals/revisions', 'local');
        } catch (Throwable) {
            $path = false;
        }

        if (! $path) {
            return back()
                ->withInput()
                ->withErrors(['document' => 'The revised proposal could not be uploaded. Please try again.'], 'resubmission');
        }

        $result = ['updated' => false, 'old_path' => null];

        try {
            DB::transaction(function () use ($request, $topic, $validated, $path, &$result) {
                $revisedTopic = TopicProposal::query()
                    ->whereKey($topic->getKey())
                    ->lockForUpdate()
                    ->firstOrFail();

                if ($revisedTopic->user_id !== $request->user()->id || $revisedTopic->status !== 'revision_requested') {
                    return;
                }

                $result['old_path'] = $revisedTopic->final_file_path;

                $revisedTopic->update([
                    'title' => $validated['title'],
                    'description' => $validated['description'] ?? null,
                    'estimated_budget' => $validated['estimated_budget'],
                    'final_file_path' => $path,
                    'status' => 'resubmitted',
                ]);

                $result['updated'] = true;
            });
        } catch (Throwable $exception) {
            Storage::disk('local')->delete($path);

            throw $exception;
        }

        if (! $result['updated']) {
            Storage::disk('local')->delete($path);

            return back()
                ->withInput()
                ->withErrors(['status' => 'This proposal is no longer awaiting a revision.'], 'resubmission');
        }

        if ($result['old_path'] && $result['old_path'] !== $path) {
            Storage::disk('local')->delete($result['old_path']);
        }

        return redirect()->route('faculty.dashboard')->with('success', 'Revised proposal submitted for another review.');
    }

    public function download(TopicProposal $topic)
    {
        $this->ensureCanViewTopic(request(), $topic);

        $path = $topic->final_file_path ?: $topic->initial_file_path;

        abort_unless(Storage::disk('local')->exists($path), 404);

        return Storage::disk('local')->download($path, basename($path));
    }

    private function ensureCanViewTopic(Request $request, TopicProposal $topic): void
    {
        $user = $request->user();

        abort_unless($user->hasRole('research_head') || $topic->user_id === $user->id, 403);
    }
}

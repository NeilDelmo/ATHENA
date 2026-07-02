<?php

namespace App\Http\Controllers;

use App\Models\ProposalVersion;
use App\Models\ResearchCall;
use App\Models\TopicProposal;
use App\Models\User;
use App\Notifications\ProposalActivityNotification;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Throwable;

class TopicController extends Controller
{
    public function index()
    {
        $topics = Auth::user()->proposals()
            ->with([
                'researchCall', 'category',
                'reviews' => fn ($query) => $query->with('reviewer')->oldest(),
                'expertAssignments.expert',
                'versions.submitter',
            ])
            ->latest()
            ->get();

        $activeCalls = ResearchCall::with('categories')
            ->where('status', 'open')
            ->where('opens_at', '<=', now())
            ->where('closes_at', '>=', now())
            ->orderBy('closes_at')
            ->get();

        $proposalTemplates = collect(config('proposal_templates', []))
            ->filter(fn (array $template) => Storage::disk('local')->exists($template['path']))
            ->map(function (array $template, string $key) {
                return [
                    ...$template,
                    'key' => $key,
                    'size' => Storage::disk('local')->size($template['path']),
                    'extension' => strtoupper(pathinfo($template['path'], PATHINFO_EXTENSION)),
                ];
            })
            ->values();

        return view('faculty.dashboard', compact('topics', 'activeCalls', 'proposalTemplates'));
    }

    public function researchIndex(Request $request)
    {
        $status = $request->string('status')->toString();
        $search = trim($request->string('search')->toString());
        $allowedStatuses = ['pending', 'expert_review', 'for_final_decision', 'revision_requested', 'resubmitted', 'approved', 'rejected'];

        $topics = TopicProposal::query()
            ->with(['researchCall', 'category', 'latestVersion'])
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
            'user', 'researchCall', 'category', 'expertAssignments.expert', 'versions.submitter',
            'reviews' => fn ($query) => $query->with('reviewer')->oldest(),
        ]);

        return view('research.show', compact('topic'));
    }

    public function store(Request $request)
    {
        $validated = $request->validateWithBag('submission', [
            'title' => 'required|string|max:255',
            'research_call_id' => ['required', 'integer', 'exists:research_calls,id'],
            'research_category_id' => ['required', 'integer', 'exists:research_categories,id'],
            'description' => 'nullable|string|max:5000',
            'estimated_budget' => 'required|numeric|min:0|max:9999999999.99',
            'estimated_duration_months' => 'required|integer|min:1|max:120',
            'document' => 'required|file|mimes:pdf,doc,docx|max:25600',
        ], [], [
            'estimated_budget' => 'estimated proposal budget',
            'document' => 'proposal document',
        ]);

        $call = ResearchCall::with('categories')->findOrFail($validated['research_call_id']);

        if (! $call->isAcceptingSubmissions()) {
            return back()->withInput()->withErrors([
                'research_call_id' => 'This research call is not accepting submissions.',
            ], 'submission');
        }

        if (! $call->categories->contains('id', (int) $validated['research_category_id'])) {
            return back()->withInput()->withErrors([
                'research_category_id' => 'Select a category offered by this research call.',
            ], 'submission');
        }

        if ($call->maximum_budget !== null && (float) $validated['estimated_budget'] > (float) $call->maximum_budget) {
            return back()->withInput()->withErrors([
                'estimated_budget' => 'The estimated budget exceeds this call\'s maximum budget.',
            ], 'submission');
        }

        if (Auth::user()->proposals()->where('research_call_id', $call->id)->count() >= $call->max_proposals_per_faculty) {
            return back()->withInput()->withErrors([
                'research_call_id' => "You have reached the {$call->max_proposals_per_faculty}-proposal limit for this research call.",
            ], 'submission');
        }

        $document = $request->file('document');
        $fileMetadata = $this->fileMetadata($document);

        try {
            $path = $document->store('proposals', 'local');
        } catch (Throwable) {
            $path = false;
        }

        if (! $path) {
            return back()
                ->withInput()
                ->withErrors(['document' => 'The proposal document could not be uploaded. Please try again.']);
        }

        try {
            $topic = DB::transaction(function () use ($validated, $call, $path, $fileMetadata) {
                $topic = Auth::user()->proposals()->create([
                    'title' => $validated['title'],
                    'research_call_id' => $call->id,
                    'research_category_id' => $validated['research_category_id'],
                    'description' => $validated['description'] ?? null,
                    'estimated_budget' => $validated['estimated_budget'],
                    'estimated_duration_months' => $validated['estimated_duration_months'],
                    'status' => 'pending',
                ]);

                $topic->versions()->create($this->versionAttributes(
                    $validated,
                    $path,
                    $fileMetadata,
                    1,
                    'initial',
                    Auth::id(),
                ));

                return $topic;
            });
        } catch (Throwable $exception) {
            Storage::disk('local')->delete($path);

            throw $exception;
        }

        Notification::send(
            User::role('research_head')->get(),
            new ProposalActivityNotification(
                'New proposal submitted',
                Auth::user()->name.' submitted “'.$topic->title.'” for review.',
                route('research_head.dashboard'),
                'info',
                $topic->id,
            ),
        );

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
            'estimated_duration_months' => 'required|integer|min:1|max:120',
            'document' => 'required|file|mimes:pdf,doc,docx|max:25600',
        ], [], [
            'estimated_budget' => 'estimated proposal budget',
            'document' => 'revised proposal document',
        ]);

        $document = $request->file('document');
        $fileMetadata = $this->fileMetadata($document);

        try {
            $path = $document->store('proposals/revisions', 'local');
        } catch (Throwable) {
            $path = false;
        }

        if (! $path) {
            return back()
                ->withInput()
                ->withErrors(['document' => 'The revised proposal could not be uploaded. Please try again.'], 'resubmission');
        }

        $result = ['updated' => false];

        try {
            DB::transaction(function () use ($request, $topic, $validated, $path, $fileMetadata, &$result) {
                $revisedTopic = TopicProposal::query()
                    ->whereKey($topic->getKey())
                    ->lockForUpdate()
                    ->firstOrFail();

                if ($revisedTopic->user_id !== $request->user()->id || $revisedTopic->status !== 'revision_requested') {
                    return;
                }

                $nextVersion = ((int) $revisedTopic->versions()->max('version_number')) + 1;

                $revisedTopic->update([
                    'title' => $validated['title'],
                    'description' => $validated['description'] ?? null,
                    'estimated_budget' => $validated['estimated_budget'],
                    'estimated_duration_months' => $validated['estimated_duration_months'],
                    'status' => 'resubmitted',
                ]);

                $revisedTopic->versions()->create($this->versionAttributes(
                    $validated,
                    $path,
                    $fileMetadata,
                    $nextVersion,
                    'revision',
                    $request->user()->id,
                ));

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

        Notification::send(
            User::role('research_head')->get(),
            new ProposalActivityNotification(
                'Proposal revision submitted',
                $request->user()->name.' submitted a new version of “'.$topic->fresh()->title.'”.',
                route('research_head.dashboard'),
                'info',
                $topic->id,
            ),
        );

        return redirect()->route('faculty.dashboard')->with('success', 'Revised proposal submitted for another review.');
    }

    public function download(TopicProposal $topic)
    {
        $this->ensureCanViewTopic(request(), $topic);

        $version = $topic->latestVersion()->first();
        $path = $version?->file_path ?: ($topic->final_file_path ?: $topic->initial_file_path);

        abort_unless($path && Storage::disk('local')->exists($path), 404);

        return Storage::disk('local')->download($path, $version?->original_filename ?: basename($path));
    }

    public function downloadVersion(Request $request, TopicProposal $topic, ProposalVersion $version)
    {
        $this->ensureCanViewTopic($request, $topic);
        abort_unless($version->topic_id === $topic->id, 404);
        abort_unless(Storage::disk('local')->exists($version->file_path), 404);

        return Storage::disk('local')->download($version->file_path, $version->original_filename);
    }

    public function downloadApproval(TopicProposal $topic)
    {
        $this->ensureCanViewTopic(request(), $topic);
        abort_unless($topic->signed_approval_path, 404);
        abort_unless(Storage::disk('local')->exists($topic->signed_approval_path), 404);

        return Storage::disk('local')->download($topic->signed_approval_path, 'signed-approval-'.$topic->id.'.pdf');
    }

    public function downloadTemplate(string $template)
    {
        $templateDetails = config("proposal_templates.{$template}");

        abort_unless(is_array($templateDetails) && isset($templateDetails['path']), 404);
        abort_unless(Storage::disk('local')->exists($templateDetails['path']), 404);

        return Storage::disk('local')->download(
            $templateDetails['path'],
            basename($templateDetails['path']),
        );
    }

    private function ensureCanViewTopic(Request $request, TopicProposal $topic): void
    {
        $user = $request->user();

        $canExpertView = $user->hasRole('expert')
            && $topic->expertAssignments()->where('expert_id', $user->id)->exists();

        abort_unless($user->hasRole('research_head') || $canExpertView || $topic->user_id === $user->id, 403);
    }

    /**
     * @return array{original_filename: string, mime_type: ?string, file_size: ?int, checksum: ?string}
     */
    private function fileMetadata(UploadedFile $document): array
    {
        $realPath = $document->getRealPath();

        return [
            'original_filename' => $document->getClientOriginalName(),
            'mime_type' => $document->getClientMimeType(),
            'file_size' => $document->getSize() ?: null,
            'checksum' => $realPath ? hash_file('sha256', $realPath) ?: null : null,
        ];
    }

    /**
     * @param  array<string, mixed>  $validated
     * @param  array{original_filename: string, mime_type: ?string, file_size: ?int, checksum: ?string}  $fileMetadata
     * @return array<string, mixed>
     */
    private function versionAttributes(
        array $validated,
        string $path,
        array $fileMetadata,
        int $versionNumber,
        string $submissionType,
        int $submittedBy,
    ): array {
        return [
            'submitted_by' => $submittedBy,
            'version_number' => $versionNumber,
            'submission_type' => $submissionType,
            'file_path' => $path,
            ...$fileMetadata,
            'title' => $validated['title'],
            'description' => $validated['description'] ?? null,
            'estimated_budget' => $validated['estimated_budget'],
            'estimated_duration_months' => $validated['estimated_duration_months'],
        ];
    }
}

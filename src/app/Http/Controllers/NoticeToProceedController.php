<?php

namespace App\Http\Controllers;

use App\Http\Requests\IssueNoticeToProceedRequest;
use App\Models\TopicProposal;
use App\Models\User;
use App\Notifications\ProposalActivityNotification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Spatie\Permission\Models\Role;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class NoticeToProceedController extends Controller
{
    public function store(IssueNoticeToProceedRequest $request, TopicProposal $topic): RedirectResponse
    {
        $notice = $request->file('notice_to_proceed');
        $path = $notice->store('notices-to-proceed/'.$topic->id, 'local');

        if (! is_string($path)) {
            throw new RuntimeException('The Notice to Proceed could not be stored.');
        }

        $originalFilename = Str::limit($notice->getClientOriginalName(), 255, '');
        $previousPath = null;
        $firstIssuance = false;

        try {
            DB::transaction(function () use (
                $request,
                $topic,
                $path,
                $originalFilename,
                &$previousPath,
                &$firstIssuance,
            ): void {
                $approvedTopic = TopicProposal::query()
                    ->whereKey($topic->getKey())
                    ->lockForUpdate()
                    ->firstOrFail();

                if ($approvedTopic->status !== 'approved') {
                    throw ValidationException::withMessages([
                        'notice_to_proceed' => 'The proposal papers must be approved before a Notice to Proceed can be issued.',
                    ]);
                }

                $previousPath = $approvedTopic->notice_to_proceed_path;
                $firstIssuance = $approvedTopic->notice_to_proceed_issued_at === null;

                $approvedTopic->update([
                    'notice_to_proceed_path' => $path,
                    'notice_to_proceed_original_filename' => $originalFilename,
                    'notice_to_proceed_issued_by' => $request->user()->id,
                    'notice_to_proceed_issued_at' => now(),
                    'project_status' => $approvedTopic->project_status ?? 'ongoing',
                ]);

                $facultyRole = Role::findOrCreate('faculty', 'web');
                $facultyResearcherRole = Role::findOrCreate('faculty_researcher', 'web');

                $approvedTopic->user()->firstOrFail()->assignRole([
                    $facultyRole,
                    $facultyResearcherRole,
                ]);
            });
        } catch (Throwable $exception) {
            Storage::disk('local')->delete($path);

            throw $exception;
        }

        if ($previousPath && $previousPath !== $path) {
            Storage::disk('local')->delete($previousPath);
        }

        $topic->user()->firstOrFail()->notify(new ProposalActivityNotification(
            $firstIssuance ? 'Notice to Proceed issued' : 'Notice to Proceed updated',
            $firstIssuance
                ? 'Your Notice to Proceed for "'.$topic->title.'" is ready. Project monitoring is now open.'
                : 'The Research Head replaced the Notice to Proceed for "'.$topic->title.'".',
            route('topics.show', $topic).'#notice-to-proceed',
            'success',
            $topic->id,
            workspace: [
                User::WORKSPACE_FACULTY_RESEARCHER,
                User::WORKSPACE_FACULTY,
            ],
        ));

        return redirect()
            ->to(route('topics.show', $topic).'#notice-to-proceed')
            ->with('success', $firstIssuance
                ? 'Notice to Proceed issued. Faculty Researcher access and project monitoring are now open.'
                : 'Notice to Proceed replaced successfully.');
    }

    public function download(Request $request, TopicProposal $topic): StreamedResponse
    {
        abort_unless(
            $request->user()->isUsingWorkspace('research_head')
                || $topic->user_id === $request->user()->id,
            403,
        );
        abort_unless($topic->notice_to_proceed_issued_at, 404);
        abort_unless($topic->notice_to_proceed_path, 404);
        abort_unless(Storage::disk('local')->exists($topic->notice_to_proceed_path), 404);

        return Storage::disk('local')->download(
            $topic->notice_to_proceed_path,
            $topic->notice_to_proceed_original_filename ?: 'notice-to-proceed-'.$topic->id.'.pdf',
            [
                'Content-Type' => 'application/pdf',
                'X-Content-Type-Options' => 'nosniff',
            ],
        );
    }
}

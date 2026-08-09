<?php

namespace App\Http\Controllers;

use App\Contracts\DocumentPdfConverter;
use App\Http\Requests\IssueNoticeToProceedRequest;
use App\Models\TopicProposal;
use App\Models\User;
use App\Notifications\ProposalActivityNotification;
use App\Services\NoticeToProceedDataService;
use App\Services\NoticeToProceedDocumentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Spatie\Permission\Models\Role;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class NoticeToProceedController extends Controller
{
    public function __construct(
        private readonly NoticeToProceedDataService $dataService,
        private readonly NoticeToProceedDocumentService $documentService,
        private readonly DocumentPdfConverter $pdfConverter,
    ) {}

    public function store(IssueNoticeToProceedRequest $request, TopicProposal $topic): RedirectResponse
    {
        $this->ensureProposalIsApproved($topic);

        $noticeData = $this->dataService->snapshot($request->validated());

        try {
            $pdf = $this->generatePdf($noticeData);
        } catch (Throwable $exception) {
            report($exception);

            return back()
                ->withInput()
                ->withErrors([
                    'notice_to_proceed' => 'The Notice to Proceed PDF could not be generated. Please verify the notice details and try again.',
                ]);
        }

        $slug = Str::limit(Str::slug($noticeData['project_title']), 150, '');
        $originalFilename = 'notice-to-proceed-'.($slug !== '' ? $slug : $topic->id).'.pdf';
        $path = 'notices-to-proceed/'.$topic->id.'/'.Str::uuid().'.pdf';

        if (! Storage::disk('local')->put($path, $pdf)) {
            throw new RuntimeException('The Notice to Proceed could not be stored.');
        }

        $previousPath = null;
        $firstIssuance = false;

        try {
            DB::transaction(function () use (
                $request,
                $topic,
                $path,
                $originalFilename,
                $noticeData,
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
                    'notice_to_proceed_data' => $noticeData,
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
            route('topics.show', $topic).'#project-monitoring',
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
                ? 'Notice to Proceed generated and issued. Faculty Researcher access and project monitoring are now open.'
                : 'Notice to Proceed regenerated successfully.');
    }

    public function preview(IssueNoticeToProceedRequest $request, TopicProposal $topic): Response
    {
        $this->ensureProposalIsApproved($topic);

        $noticeData = $this->dataService->snapshot($request->validated());

        try {
            $pdf = $this->generatePdf($noticeData);
        } catch (Throwable $exception) {
            report($exception);

            throw ValidationException::withMessages([
                'notice_to_proceed' => 'The Notice to Proceed preview could not be generated. Please verify the notice details and try again.',
            ]);
        }

        return response($pdf, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="notice-to-proceed-preview.pdf"',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function download(Request $request, TopicProposal $topic): StreamedResponse
    {
        Gate::forUser($request->user())->authorize('view', $topic);
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

    /** @param array<string, mixed> $noticeData */
    private function generatePdf(array $noticeData): string
    {
        $document = $this->documentService->generate(
            $this->dataService->documentValues($noticeData),
        );

        return $this->pdfConverter->convertDocx($document);
    }

    private function ensureProposalIsApproved(TopicProposal $topic): void
    {
        if ($topic->status !== 'approved') {
            throw ValidationException::withMessages([
                'notice_to_proceed' => 'The proposal papers must be approved before a Notice to Proceed can be issued.',
            ]);
        }
    }
}

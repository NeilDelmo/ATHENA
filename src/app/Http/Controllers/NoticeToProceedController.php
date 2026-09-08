<?php

namespace App\Http\Controllers;

use App\Actions\PromoteTopicTeam;
use App\Contracts\DocumentPdfConverter;
use App\Http\Requests\IssueNoticeToProceedRequest;
use App\Http\Requests\UploadSignedNoticeToProceedRequest;
use App\Models\TopicProposal;
use App\Models\User;
use App\Notifications\ProposalActivityNotification;
use App\Services\FacultyProjectCapacityService;
use App\Services\NoticeToProceedDataService;
use App\Services\NoticeToProceedDocumentService;
use App\Services\ProposalSignatureWorkflow;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class NoticeToProceedController extends Controller
{
    public function __construct(
        private readonly NoticeToProceedDataService $dataService,
        private readonly NoticeToProceedDocumentService $documentService,
        private readonly DocumentPdfConverter $pdfConverter,
        private readonly PromoteTopicTeam $promoteTopicTeam,
    ) {}

    public function prepare(IssueNoticeToProceedRequest $request, TopicProposal $topic): JsonResponse|RedirectResponse
    {
        $this->ensureNoticeCanBePrepared($topic);

        $noticeData = $this->dataService->snapshot($request->validated());

        DB::transaction(function () use ($topic, $noticeData): void {
            $approvedTopic = TopicProposal::query()
                ->whereKey($topic->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            $this->ensureNoticeCanBePrepared($approvedTopic);

            $approvedTopic->update([
                'notice_to_proceed_data' => $noticeData,
            ]);
        });

        if ($request->expectsJson()) {
            return response()->json([
                'saved' => true,
                'message' => 'Notice details saved.',
            ]);
        }

        return redirect()
            ->to(route('topics.show', $topic).'#notice-to-proceed')
            ->with('success', 'Notice details saved. Download the unsigned PDF, obtain the required signatures, then upload the signed copy to release it to the faculty researcher.');
    }

    public function preview(IssueNoticeToProceedRequest $request, TopicProposal $topic): View
    {
        $this->ensureProposalIsApproved($topic);

        $noticeData = $this->dataService->snapshot($request->validated());

        return view('topics.notice-to-proceed-preview', [
            'notice' => $this->dataService->documentValues($noticeData),
        ]);
    }

    public function downloadUnsigned(Request $request, TopicProposal $topic): Response
    {
        Gate::forUser($request->user())->authorize('view', $topic);
        $this->ensureNoticeCanBePrepared($topic);

        if (! $topic->hasPreparedNoticeToProceed()) {
            throw ValidationException::withMessages([
                'notice_to_proceed' => 'Save the Notice to Proceed details before downloading the unsigned PDF.',
            ]);
        }

        try {
            $pdf = $this->generatePdf($topic->notice_to_proceed_data);
        } catch (Throwable $exception) {
            report($exception);

            throw ValidationException::withMessages([
                'notice_to_proceed' => 'The unsigned Notice to Proceed PDF could not be generated. Please verify the saved notice details and try again.',
            ]);
        }

        return response($pdf, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$this->noticeFilename($topic, 'unsigned-notice-to-proceed').'"',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function uploadSigned(UploadSignedNoticeToProceedRequest $request, TopicProposal $topic): RedirectResponse
    {
        $this->ensureNoticeCanBePrepared($topic);

        if (! $topic->hasPreparedNoticeToProceed()) {
            return back()->withErrors([
                'signed_notice_to_proceed' => 'Save the notice details and download the unsigned PDF before uploading a signed copy.',
            ]);
        }

        $path = $request->file('signed_notice_to_proceed')?->store(
            'notices-to-proceed/'.$topic->id,
            'local',
        );

        if (! $path) {
            throw new RuntimeException('The signed Notice to Proceed could not be stored.');
        }

        try {
            DB::transaction(function () use ($request, $topic, $path): void {
                $approvedTopic = TopicProposal::query()
                    ->whereKey($topic->getKey())
                    ->lockForUpdate()
                    ->firstOrFail();

                $this->ensureNoticeCanBePrepared($approvedTopic);

                if (! $approvedTopic->hasPreparedNoticeToProceed()) {
                    throw ValidationException::withMessages([
                        'signed_notice_to_proceed' => 'Save the notice details and download the unsigned PDF before uploading a signed copy.',
                    ]);
                }

                $version = $approvedTopic->latestVersion()->with('files')->lockForUpdate()->firstOrFail();
                if (! app(ProposalSignatureWorkflow::class)->isComplete($version)) {
                    throw ValidationException::withMessages(['signed_notice_to_proceed' => 'Upload all required signed proposal papers before releasing the Notice to Proceed.']);
                }
                app(FacultyProjectCapacityService::class)->ensureAvailableFor($approvedTopic);

                $approvedTopic->update([
                    'status' => 'approved',
                    'notice_to_proceed_path' => $path,
                    'notice_to_proceed_original_filename' => $this->noticeFilename($approvedTopic, 'signed-notice-to-proceed'),
                    'notice_to_proceed_issued_by' => $request->user()->id,
                    'notice_to_proceed_issued_at' => now(),
                    'project_status' => $approvedTopic->project_status ?? TopicProposal::PROJECT_STATUS_ONGOING,
                ]);

                $approvedTopic->reviews()->create([
                    'reviewer_id' => $request->user()->id,
                    'review_stage' => $approvedTopic->review_stage,
                    'decision' => 'approved',
                    'comment' => 'Signed proposal papers and signed Notice to Proceed released together to faculty.',
                ]);
                $this->promoteTopicTeam->handle($approvedTopic);
            });
        } catch (Throwable $exception) {
            Storage::disk('local')->delete($path);

            throw $exception;
        }

        $issuedTopic = $topic->fresh();
        $recipients = collect([$issuedTopic->user()->firstOrFail()])
            ->merge($issuedTopic->collaborators()
                ->whereNotNull('accepted_at')
                ->with('user')
                ->get()
                ->pluck('user')
                ->filter())
            ->unique(fn (User $user): int => $user->getKey())
            ->values();

        Notification::send($recipients, new ProposalActivityNotification(
            'Signed papers and Notice to Proceed released',
            'Your signed proposal papers and Notice to Proceed for "'.$issuedTopic->title.'" is ready. Project monitoring is now open.',
            route('topics.show', $issuedTopic).'#project-monitoring',
            'success',
            $issuedTopic->id,
            workspace: [
                User::WORKSPACE_FACULTY_RESEARCHER,
                User::WORKSPACE_FACULTY,
            ],
            sidebarArea: ProposalActivityNotification::SIDEBAR_AREA_MY_PROJECTS,
        ));

        return redirect()
            ->to(route('topics.show', $topic).'#notice-to-proceed')
            ->with('success', 'Signed papers and Notice to Proceed released together. Faculty Researcher access and project monitoring are now open.');
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
        if (! in_array($topic->status, ['approved', TopicProposal::STATUS_READY_FOR_SIGNATURE], true)) {
            throw ValidationException::withMessages([
                'notice_to_proceed' => 'Complete the LREC review and proceed to signing before preparing the Notice to Proceed.',
            ]);
        }
    }

    private function ensureNoticeCanBePrepared(TopicProposal $topic): void
    {
        $this->ensureProposalIsApproved($topic);

        if ($topic->notice_to_proceed_issued_at !== null) {
            throw ValidationException::withMessages([
                'notice_to_proceed' => 'The signed Notice to Proceed has already been issued and cannot be replaced from this screen.',
            ]);
        }
    }

    private function noticeFilename(TopicProposal $topic, string $prefix): string
    {
        $slug = Str::limit(Str::slug((string) data_get($topic->notice_to_proceed_data, 'project_title', $topic->title)), 150, '');

        return $prefix.'-'.($slug !== '' ? $slug : $topic->id).'.pdf';
    }
}

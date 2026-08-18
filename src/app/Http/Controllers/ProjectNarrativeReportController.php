<?php

namespace App\Http\Controllers;

use App\Actions\PrepareProjectNarrativeReport;
use App\Actions\SaveProjectNarrativeReportDraft;
use App\Contracts\DocumentPdfConverter;
use App\Http\Requests\SaveProjectNarrativeReportDraftRequest;
use App\Http\Requests\StoreProjectNarrativeReportRequest;
use App\Http\Requests\SubmitPreparedProjectNarrativeReportRequest;
use App\Models\ProjectNarrativeReport;
use App\Models\ProjectNarrativeReportDraft;
use App\Models\TopicProposal;
use App\Models\User;
use App\Notifications\ProposalActivityNotification;
use App\Services\ProgressReportDocumentService;
use App\Services\ProjectMonitoringFormDataService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class ProjectNarrativeReportController extends Controller
{
    public function create(Request $request, TopicProposal $topic, ProjectMonitoringFormDataService $formData): View
    {
        Gate::forUser($request->user())->authorize('view', $topic);

        abort_unless($topic->isMonitoringAvailable() && $topic->isAccessibleTo($request->user()), 404);

        return view('faculty.progress-reports.create', [
            'topic' => $topic,
            ...$formData->narrativeProgress($request->user(), $topic),
        ]);
    }

    public function preview(StoreProjectNarrativeReportRequest $request, TopicProposal $topic): View
    {
        $validated = $request->validated();
        $figureIndexes = range(1, (int) config('progress_report.max_figures'));
        $photoFields = collect($figureIndexes)
            ->flatMap(fn (int $index): array => [
                'photo_'.$index,
                'photo_caption_'.$index,
                'photo_section_'.$index,
            ])
            ->all();
        $photos = collect($figureIndexes)
            ->filter(fn (int $index): bool => $request->hasFile("photo_{$index}"))
            ->map(fn (int $index): array => [
                'preview_file_input' => "photo_{$index}",
                'caption' => $validated["photo_caption_{$index}"],
                'section' => $validated["photo_section_{$index}"],
            ])
            ->values()
            ->all();

        $report = new ProjectNarrativeReport([
            ...collect($validated)->except($photoFields)->all(),
            'topic_id' => $topic->id,
            'submitted_by' => $request->user()->id,
            'budget' => $topic->estimated_budget,
            'accomplishment_summary' => collect($validated['accomplishments'])
                ->pluck('actual')
                ->implode("\n"),
            'photos' => $photos,
        ]);
        $report->setRelation('topic', $topic->loadMissing('user'));
        $report->setRelation('submitter', $request->user());

        return view('faculty.progress-reports.preview', compact('report'));
    }

    public function prepare(
        StoreProjectNarrativeReportRequest $request,
        TopicProposal $topic,
        PrepareProjectNarrativeReport $prepareProjectNarrativeReport,
    ): RedirectResponse {
        $existingPreparedReport = ProjectNarrativeReport::query()
            ->prepared()
            ->whereBelongsTo($topic, 'topic')
            ->where('submitted_by', $request->user()->id)
            ->exists();

        if ($existingPreparedReport) {
            return back()->withErrors([
                'preparation' => 'Discard the prepared Progress Report before preparing another one.',
            ], 'narrativeProgress');
        }

        try {
            $prepareProjectNarrativeReport->handle(
                $topic,
                $request->user(),
                $request->validated(),
                $request->allFiles(),
            );
        } catch (Throwable $exception) {
            report($exception);

            return back()
                ->withInput()
                ->withErrors([
                    'preparation' => 'The Progress Report PDF could not be prepared. Your form data was kept, so you can try again.',
                ], 'narrativeProgress');
        }

        ProjectNarrativeReportDraft::query()
            ->whereBelongsTo($topic, 'topic')
            ->whereBelongsTo($request->user(), 'user')
            ->delete();

        return back()->with('success', 'Progress Report PDF prepared. Review it, then submit the exact file to the Research Head.');
    }

    public function saveDraft(
        SaveProjectNarrativeReportDraftRequest $request,
        TopicProposal $topic,
        SaveProjectNarrativeReportDraft $saveProjectNarrativeReportDraft,
    ): JsonResponse {
        $validated = $request->validated();
        $draft = $saveProjectNarrativeReportDraft->handle(
            $topic,
            $request->user(),
            $request->integer('draft_version'),
            collect($validated)->except('draft_version')->all(),
        );

        return response()->json([
            'message' => 'Progress Report draft saved.',
            'draft_version' => $draft->lock_version,
        ]);
    }

    public function submitPrepared(
        SubmitPreparedProjectNarrativeReportRequest $request,
        TopicProposal $topic,
        ProjectNarrativeReport $report,
    ): RedirectResponse {
        if (! filled($report->official_pdf_path) || ! Storage::disk('local')->exists($report->official_pdf_path)) {
            return back()->withErrors([
                'preparation' => 'The prepared Progress Report PDF is unavailable. Discard it and prepare the form again.',
            ], 'narrativeProgress');
        }

        $report->update([
            'submission_status' => ProjectNarrativeReport::SUBMISSION_STATUS_SUBMITTED,
            'submitted_at' => now(),
        ]);

        User::role('research_head')->get()->each->notify(new ProposalActivityNotification(
            'Progress report submitted',
            $request->user()->name.' submitted a progress report for '.$topic->title.'.',
            route('topics.show', $topic).'#project-monitoring',
            'info',
            $topic->id,
            workspace: User::WORKSPACE_RESEARCH_HEAD,
            sidebarArea: ProposalActivityNotification::SIDEBAR_AREA_PROJECT_MONITORING,
        ));

        return back()->with('success', 'Official progress report submitted for Research Head review.');
    }

    public function discardPrepared(
        SubmitPreparedProjectNarrativeReportRequest $request,
        TopicProposal $topic,
        ProjectNarrativeReport $report,
    ): RedirectResponse {
        $this->deletePreparedReportFiles($report);
        $report->delete();

        return back()->with('success', 'Prepared Progress Report discarded. You can now prepare a new PDF.');
    }

    public function store(StoreProjectNarrativeReportRequest $request, TopicProposal $topic): RedirectResponse
    {
        $validated = $request->validated();
        $storedPaths = [];

        try {
            $figureIndexes = range(1, (int) config('progress_report.max_figures'));
            $photos = collect($figureIndexes)
                ->filter(fn (int $index): bool => $request->hasFile("photo_{$index}"))
                ->map(function (int $index) use ($request, $topic, $validated, &$storedPaths): array {
                    $file = $request->file("photo_{$index}");
                    $path = $file->store("narrative-progress-reports/{$topic->id}", 'local');
                    $storedPaths[] = $path;

                    return [
                        'path' => $path,
                        'original_name' => $file->getClientOriginalName(),
                        'mime_type' => $file->getMimeType(),
                        'size' => $file->getSize(),
                        'caption' => $validated["photo_caption_{$index}"],
                        'section' => $validated["photo_section_{$index}"],
                    ];
                })
                ->values()
                ->all();

            $photoFields = collect($figureIndexes)
                ->flatMap(fn (int $index): array => [
                    'photo_'.$index,
                    'photo_caption_'.$index,
                    'photo_section_'.$index,
                ])
                ->all();

            $report = ProjectNarrativeReport::create([
                ...collect($validated)->except($photoFields)->all(),
                'topic_id' => $topic->id,
                'submitted_by' => $request->user()->id,
                'budget' => $topic->estimated_budget,
                'accomplishment_summary' => collect($validated['accomplishments'])
                    ->pluck('actual')
                    ->implode("\n"),
                'photos' => $photos,
            ]);
        } catch (Throwable $exception) {
            collect($storedPaths)->each(fn (string $path) => Storage::disk('local')->delete($path));

            throw $exception;
        }

        User::role('research_head')->get()->each->notify(new ProposalActivityNotification(
            'Progress report submitted',
            $request->user()->name.' submitted a progress report for “'.$topic->title.'”.',
            route('topics.show', $topic).'#project-monitoring',
            'info',
            $topic->id,
            workspace: User::WORKSPACE_RESEARCH_HEAD,
            sidebarArea: ProposalActivityNotification::SIDEBAR_AREA_PROJECT_MONITORING,
        ));

        return back()->with('success', 'Official progress report submitted for Research Head review.');
    }

    public function review(Request $request, ProjectNarrativeReport $report): RedirectResponse
    {
        abort_unless($report->topic()->withIssuedNotice()->exists(), 404);
        abort_unless($report->isSubmitted(), 404);

        $validated = $request->validate([
            'review_status' => ['required', Rule::in([
                ProjectNarrativeReport::STATUS_REVIEWED,
                ProjectNarrativeReport::STATUS_REVISION_REQUESTED,
            ])],
            'research_head_remarks' => ['nullable', 'required_if:review_status,'.ProjectNarrativeReport::STATUS_REVISION_REQUESTED, 'string', 'max:5000'],
        ]);

        $report->update([
            ...$validated,
            'reviewed_by' => $request->user()->id,
            'reviewed_at' => now(),
        ]);

        $report->load('topic.user');
        $report->topic->user->notify(new ProposalActivityNotification(
            $validated['review_status'] === ProjectNarrativeReport::STATUS_REVIEWED
                ? 'Progress report reviewed'
                : 'Progress report needs revision',
            'The Research Head reviewed your progress report for “'.$report->topic->title.'”.',
            route('topics.show', $report->topic).'#project-monitoring',
            $validated['review_status'] === ProjectNarrativeReport::STATUS_REVIEWED ? 'success' : 'warning',
            $report->topic_id,
            workspace: User::WORKSPACE_FACULTY_RESEARCHER,
            sidebarArea: ProposalActivityNotification::SIDEBAR_AREA_MY_PROJECTS,
        ));

        return back()->with('success', 'Progress report review saved.');
    }

    public function download(
        Request $request,
        ProjectNarrativeReport $report,
        ProgressReportDocumentService $documentService,
        DocumentPdfConverter $pdfConverter,
    ): StreamedResponse {
        $this->authorizeViewer($request, $report);

        if (filled($report->official_pdf_path)
            && filled($report->official_pdf_filename)
            && Storage::disk('local')->exists($report->official_pdf_path)) {
            return Storage::disk('local')->download(
                $report->official_pdf_path,
                $report->official_pdf_filename,
                ['Content-Type' => 'application/pdf', 'X-Content-Type-Options' => 'nosniff'],
            );
        }

        abort_unless($report->isSubmitted(), 404);
        $report->loadMissing(['topic.user', 'submitter']);
        $pdf = $pdfConverter->convertDocx($documentService->generate($report));

        return response()->streamDownload(
            static fn () => print $pdf,
            Str::slug($report->topic->title).'-progress-report.pdf',
            ['Content-Type' => 'application/pdf', 'X-Content-Type-Options' => 'nosniff'],
        );
    }

    public function downloadPhoto(Request $request, ProjectNarrativeReport $report, int $photoIndex): StreamedResponse
    {
        $this->authorizeViewer($request, $report);
        $photo = ($report->photos ?? [])[$photoIndex] ?? null;
        abort_unless(is_array($photo) && Storage::disk('local')->exists($photo['path'] ?? ''), 404);

        return Storage::disk('local')->download($photo['path'], $photo['original_name']);
    }

    private function authorizeViewer(Request $request, ProjectNarrativeReport $report): void
    {
        if ($report->isPrepared()) {
            abort_unless(
                $request->user()->id === $report->submitted_by
                    && $report->topic->isAccessibleTo($request->user()),
                403,
            );

            return;
        }

        abort_unless(
            $request->user()->isUsingWorkspace(User::WORKSPACE_RESEARCH_HEAD)
                || $report->topic->isAccessibleTo($request->user()),
            403,
        );
    }

    private function deletePreparedReportFiles(ProjectNarrativeReport $report): void
    {
        collect($report->photos ?? [])
            ->pluck('path')
            ->push($report->official_pdf_path)
            ->filter()
            ->each(fn (string $path) => Storage::disk('local')->delete($path));
    }
}

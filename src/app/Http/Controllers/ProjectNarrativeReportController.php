<?php

namespace App\Http\Controllers;

use App\Actions\PrepareProjectNarrativeReport;
use App\Actions\SaveProjectNarrativeReportDraft;
use App\Contracts\DocumentPdfConverter;
use App\Http\Requests\ReviewImplementationReportRequest;
use App\Http\Requests\SaveProjectNarrativeReportDraftRequest;
use App\Http\Requests\StoreProjectNarrativeReportRequest;
use App\Http\Requests\StoreSignedTerminalReportRequest;
use App\Http\Requests\SubmitPreparedProjectNarrativeReportRequest;
use App\Models\ProjectNarrativeReport;
use App\Models\ProjectNarrativeReportDraft;
use App\Models\TopicProposal;
use App\Models\User;
use App\Notifications\ProposalActivityNotification;
use App\Services\ImagePreviewService;
use App\Services\MonitoringQuarterService;
use App\Services\ProgressReportDocumentService;
use App\Services\ProgressReportEvidenceService;
use App\Services\ProjectMonitoringFormDataService;
use App\Services\ProjectReportDraftState;
use App\Services\ProjectReportReviewService;
use App\Services\ReportFigureDraftService;
use App\Services\SidebarAttentionService;
use App\Support\ProgressReportData;
use App\Support\TerminalReportData;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class ProjectNarrativeReportController extends Controller
{
    public function create(Request $request, TopicProposal $topic, ProjectMonitoringFormDataService $formData): View|RedirectResponse
    {
        Gate::forUser($request->user())->authorize('view', $topic);

        abort_unless($topic->isMonitoringAvailable() && $topic->isAccessibleTo($request->user()), 404);

        $schedule = app(MonitoringQuarterService::class);
        $request->validate(['reporting_date' => ['nullable', 'date_format:Y-m-d']]);

        $reportType = $request->query('report_type') === 'terminal' ? 'terminal' : 'progress';
        $data = $formData->narrativeProgress($request->user(), $topic, $reportType, $request->query('reporting_date'));
        if ($reportType === 'progress' && $request->filled('reporting_date') && $data['selectedReportingDate'] !== $request->query('reporting_date')) {
            return redirect()->route('project-narrative-reports.create', ['topic' => $topic, 'report_type' => $reportType, 'reporting_date' => $data['selectedReportingDate']])
                ->with('success', 'Your unfinished quarterly draft was reopened. Finish this report before starting another quarter.');
        }
        if ($reportType === 'progress' && $data['selectedReportingDate'] && ! $schedule->canDraftForDate($topic, $data['selectedReportingDate'])) {
            return redirect()->to(route('topics.show', $topic).'#project-monitoring')
                ->withErrors(['reporting_date' => 'Choose a reporting quarter within the approved project schedule.'], 'narrativeProgress');
        }
        if ($reportType === 'progress' && $data['quarterOptions']->isEmpty() && $data['preparedReport'] === null) {
            return redirect()->to(route('topics.show', $topic).'#project-monitoring')
                ->withErrors(['reporting_date' => 'All available quarters already have a submitted Progress Report.'], 'narrativeProgress');
        }

        return view('faculty.progress-reports.create', [
            'topic' => $topic,
            ...$data,
            'draftRecoveryState' => app(ProjectReportDraftState::class)->forEditor($topic, $request->user(), $reportType, $data['selectedReportingDate']),
        ]);
    }

    public function preview(StoreProjectNarrativeReportRequest $request, TopicProposal $topic): View
    {
        $validated = app(ReportFigureDraftService::class)->withSavedImages($request, app(TerminalReportData::class)->normalize($topic, $request->validated()));
        if (($validated['report_type'] ?? 'progress') === 'progress') {
            $period = app(MonitoringQuarterService::class)->forDate($validated['reporting_date'], $topic);
            $validated = [...$validated, 'reporting_quarter' => $period['quarter'], 'period_start' => $period['start']->toDateString(), 'period_end' => $period['end']->toDateString()];
        }
        $figureIndexes = (($validated['report_type'] ?? 'progress') === 'terminal' ? range(1, 30) : []);
        $photoFields = collect($figureIndexes)
            ->flatMap(fn (int $index): array => [
                'photo_'.$index,
                'photo_caption_'.$index,
                'photo_section_'.$index,
                'reuse_photo_'.$index,
                'saved_photo_'.$index,
                'photo_after_paragraph_'.$index,
            ])
            ->prepend('figures')
            ->prepend('saved_images')
            ->prepend('saved_cover_image')
            ->prepend('cover_image_caption')
            ->prepend('reuse_cover_image')
            ->prepend('cover_image')
            ->prepend('accomplishment_evidence')
            ->all();
        $photos = ($validated['report_type'] ?? 'progress') === 'terminal'
            ? app(TerminalReportData::class)->photos($topic, $validated, $request->allFiles(), true)
            : app(ProgressReportData::class)->photos($topic, $validated, $request->allFiles(), true);
        $validated['accomplishments'] = array_map(fn (array $row): array => array_intersect_key($row, array_flip(['objective', 'target', 'actual', 'activities'])), $validated['accomplishments']);

        $report = new ProjectNarrativeReport([
            ...collect($validated)->except($photoFields)->reject(fn (mixed $value, string $key): bool => str_starts_with($key, 'photo_'))->all(),
            'topic_id' => $topic->id,
            'submitted_by' => $request->user()->id,
            'budget' => $validated['terminal_data']['approved_budget'] ?? $topic->estimated_budget,
            'accomplishment_summary' => collect($validated['accomplishments'])
                ->pluck('actual')
                ->implode("\n"),
            'photos' => $photos,
        ]);
        $report->setRelation('topic', $topic->loadMissing('user'));
        $report->setRelation('submitter', $request->user());

        return view($report->report_type === 'terminal' ? 'faculty.progress-reports.terminal-preview' : 'faculty.progress-reports.preview', compact('report'));
    }

    public function prepare(
        StoreProjectNarrativeReportRequest $request,
        TopicProposal $topic,
        PrepareProjectNarrativeReport $prepareProjectNarrativeReport,
        ProgressReportEvidenceService $evidenceService,
    ): RedirectResponse {
        if ($request->input('report_type', 'progress') === 'progress') {
            $savedDraft = ProjectNarrativeReportDraft::query()->whereBelongsTo($topic, 'topic')->whereBelongsTo($request->user(), 'user')->where('report_type', 'progress')->first();
            $savedDate = data_get($savedDraft?->source_data, 'reporting_date');
            $schedule = app(MonitoringQuarterService::class);
            if ($savedDate && $schedule->forDate($savedDate, $topic)['start']->ne($schedule->forDate($request->input('reporting_date'), $topic)['start'])) {
                return back()->withInput()->withErrors(['reporting_date' => 'Finish your saved Progress Report draft before preparing another quarter.'], 'narrativeProgress');
            }
        }
        if ($request->input('report_type') === 'terminal') {
            $missing = app(MonitoringQuarterService::class)->missingTerminalReportPeriods($topic);
            if ($missing !== []) {
                return back()->withInput()->withErrors(['preparation' => 'Complete the required Monitoring Tools and Progress Reports before preparing the Terminal Report: '.implode(', ', $missing).'.'], 'narrativeProgress');
            }
        }
        $existingPreparedReport = ProjectNarrativeReport::query()
            ->prepared()
            ->whereBelongsTo($topic, 'topic')
            ->where('report_type', $request->input('report_type', 'progress'))
            ->exists();

        if ($existingPreparedReport) {
            return back()->withErrors([
                'preparation' => 'Discard the prepared report of this type before preparing another one.',
            ], 'narrativeProgress');
        }

        try {
            $evidenceService->store(
                $topic,
                [...app(ReportFigureDraftService::class)->withSavedImages($request, app(TerminalReportData::class)->normalize($topic, $request->validated())), 'review_stage' => $request->input('report_type', 'progress') === 'progress' ? 'secretary' : null],
                $request->file('accomplishment_evidence', []),
                fn (array $data): ProjectNarrativeReport => $prepareProjectNarrativeReport->handle(
                    $topic, $request->user(), $data, $request->allFiles(),
                ),
            );
        } catch (ValidationException $exception) {
            return back()->withInput()->withErrors($exception->errors(), 'narrativeProgress');
        } catch (Throwable $exception) {
            report($exception);

            return back()
                ->withInput()
                ->withErrors([
                    'preparation' => 'The report PDF could not be prepared. Your form data was kept, so you can try again.',
                ], 'narrativeProgress');
        }

        ProjectNarrativeReportDraft::query()
            ->whereBelongsTo($topic, 'topic')
            ->whereBelongsTo($request->user(), 'user')
            ->where('report_type', $request->input('report_type', 'progress'))
            ->delete();
        $evidenceService->deleteUnreferenced($topic, $request->attributes->get('progress_report_evidence_paths', []));
        app(ReportFigureDraftService::class)->deleteUnreferenced($topic, $request->attributes->get('report_figure_previous_paths', []));

        return back()->with('success', ($request->input('report_type') === 'terminal' ? 'Terminal Report' : 'Progress Report').' PDF prepared. Review it, then submit the exact file to the '.($request->input('report_type') === 'terminal' ? 'Research Head' : 'Research Office Secretary').'.');
    }

    public function saveDraft(
        SaveProjectNarrativeReportDraftRequest $request,
        TopicProposal $topic,
        SaveProjectNarrativeReportDraft $saveProjectNarrativeReportDraft,
        ProgressReportEvidenceService $evidenceService,
    ): JsonResponse {
        $validated = app(TerminalReportData::class)->normalize($topic, $request->validated());
        $draft = app(ReportFigureDraftService::class)->store($request, $topic, $validated,
            fn (array $data): ProjectNarrativeReportDraft => $evidenceService->store(
                $topic, $data, $request->file('accomplishment_evidence', []),
                fn (array $data): ProjectNarrativeReportDraft => $saveProjectNarrativeReportDraft->handle(
                    $topic, $request->user(), $request->integer('draft_version'),
                    collect($data)->except('draft_version')->all(),
                ),
            ),
        );
        $evidenceService->deleteUnreferenced($topic, $request->attributes->get('progress_report_evidence_paths', []));

        return response()->json([
            'message' => 'Progress Report draft saved.',
            'draft_version' => $draft->lock_version,
            'accomplishments' => $draft->source_data['accomplishments'] ?? [],
            'images' => app(ReportFigureDraftService::class)->displayImages($topic, $draft->source_data),
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

        DB::transaction(function () use ($request, $topic, $report): void {
            $lockedTopic = TopicProposal::query()->whereKey($topic->id)->lockForUpdate()->firstOrFail();
            $lockedReport = ProjectNarrativeReport::query()->whereKey($report->id)->lockForUpdate()->firstOrFail();
            if ($lockedReport->report_type === 'terminal' && app(MonitoringQuarterService::class)->missingTerminalReportPeriods($lockedTopic) !== []) {
                throw ValidationException::withMessages(['preparation' => 'Complete all quarterly Monitoring Tools and Progress Reports before submitting the Terminal Report.']);
            }
            if (! $lockedTopic->isMonitoringAvailable() || ! $lockedReport->isPrepared() || $lockedTopic->user_id !== $request->user()->id) {
                throw ValidationException::withMessages(['preparation' => 'This report is no longer available for submission. Reload the project.']);
            }
            $lockedReport->update([
                'submission_status' => ProjectNarrativeReport::SUBMISSION_STATUS_SUBMITTED,
                'submitted_by' => $request->user()->id,
                'submitted_at' => now(),
                'review_stage' => $lockedReport->report_type === 'progress' ? 'secretary' : null,
            ]);
        });

        if ($report->report_type === 'progress') {
            app(ProjectReportReviewService::class)->notifySecretarySubmission($report);
        } else {
            User::role('research_head')->get()->each->notify(new ProposalActivityNotification(
                ucfirst($report->report_label).' submitted',
                $request->user()->name.' submitted a '.strtolower($report->report_label).' for '.$topic->title.'.',
                route('topics.show', $topic).'#project-monitoring',
                'info',
                $topic->id,
                workspace: User::WORKSPACE_RESEARCH_HEAD,
                sidebarArea: ProposalActivityNotification::SIDEBAR_AREA_PROJECT_MONITORING,
            ));
        }

        return back()->with('success', 'Official '.strtolower($report->report_label).' submitted for '.($report->report_type === 'terminal' ? 'Research Head' : 'Research Office Secretary').' review.');
    }

    public function discardPrepared(
        SubmitPreparedProjectNarrativeReportRequest $request,
        TopicProposal $topic,
        ProjectNarrativeReport $report,
        ProgressReportEvidenceService $evidenceService,
    ): RedirectResponse {
        app(ReportFigureDraftService::class)->restoreDraft($report, $request->user());
        $this->deletePreparedReportFiles($report);
        $evidencePaths = $evidenceService->paths($report->accomplishments ?? []);
        $report->delete();
        $evidenceService->deleteUnreferenced($topic, $evidencePaths);

        return back()->with('success', $report->report_type === 'terminal'
            ? 'Prepared Terminal Report discarded. Your text and images were kept as a draft.'
            : 'Prepared Progress Report discarded. Your text and images were kept as a draft.');
    }

    public function store(StoreProjectNarrativeReportRequest $request, TopicProposal $topic, ProgressReportEvidenceService $evidenceService): RedirectResponse
    {
        abort_unless($topic->user_id === $request->user()->id, 403);
        $validated = app(ReportFigureDraftService::class)->withSavedImages($request, app(TerminalReportData::class)->normalize($topic, $request->validated()));
        $storedPaths = [];

        try {
            $report = $evidenceService->store($topic, $validated, $request->file('accomplishment_evidence', []), function (array $validated) use ($topic, $request, &$storedPaths): ProjectNarrativeReport {
                $figureIndexes = (($validated['report_type'] ?? 'progress') === 'terminal' ? range(1, 30) : []);
                $photos = ($validated['report_type'] ?? 'progress') === 'terminal'
                ? app(TerminalReportData::class)->photos($topic, $validated, $request->allFiles(), false, $storedPaths)
                : app(ProgressReportData::class)->photos($topic, $validated, $request->allFiles(), false, $storedPaths);

                $photoFields = collect($figureIndexes)
                    ->flatMap(fn (int $index): array => [
                        'photo_'.$index,
                        'photo_caption_'.$index,
                        'photo_section_'.$index,
                        'reuse_photo_'.$index,
                        'saved_photo_'.$index,
                        'photo_after_paragraph_'.$index,
                    ])
                    ->prepend('figures')
                    ->prepend('saved_images')
                    ->prepend('saved_cover_image')
                    ->prepend('cover_image_caption')
                    ->prepend('reuse_cover_image')
                    ->prepend('cover_image')
                    ->all();

                return ProjectNarrativeReport::create([
                    ...collect($validated)->except($photoFields)->reject(fn (mixed $value, string $key): bool => str_starts_with($key, 'photo_'))->all(),
                    'topic_id' => $topic->id,
                    'submitted_by' => $request->user()->id,
                    'review_stage' => ($validated['report_type'] ?? 'progress') === 'progress' ? 'secretary' : null,
                    'budget' => $validated['terminal_data']['approved_budget'] ?? $topic->estimated_budget,
                    'accomplishment_summary' => collect($validated['accomplishments'])
                        ->pluck('actual')
                        ->implode("\n"),
                    'photos' => $photos,
                ]);
            });
        } catch (Throwable $exception) {
            collect($storedPaths)->each(fn (string $path) => Storage::disk('local')->delete($path));

            throw $exception;
        }

        if ($report->report_type === 'progress') {
            app(ProjectReportReviewService::class)->notifySecretarySubmission($report);
        } else {
            User::role('research_head')->get()->each->notify(new ProposalActivityNotification(
                ucfirst($report->report_label).' submitted',
                $request->user()->name.' submitted a terminal report for “'.$topic->title.'”.',
                route('topics.show', $topic).'#project-monitoring',
                'info',
                $topic->id,
                workspace: User::WORKSPACE_RESEARCH_HEAD,
                sidebarArea: ProposalActivityNotification::SIDEBAR_AREA_PROJECT_MONITORING,
            ));
        }

        return back()->with('success', 'Official '.strtolower($report->report_label).' submitted for '.($report->report_type === 'terminal' ? 'Research Head' : 'Research Office Secretary').' review.');
    }

    public function show(Request $request, ProjectNarrativeReport $report): View
    {
        $this->authorizeViewer($request, $report);
        abort_unless($report->isSubmitted(), 404);
        $report->loadMissing(['topic', 'submitter', 'reviewer', 'secretaryReviewer']);
        $topic = $report->topic;
        $isLatestTerminal = $report->report_type === 'terminal'
            && $topic->narrativeReports()->where('report_type', 'terminal')->reorder()->latest('id')->value('id') === $report->id;
        $canManage = $request->user()->isUsingWorkspace(User::WORKSPACE_RESEARCH_HEAD)
            && ! $topic->isCompletedProject()
            && $topic->notice_to_proceed_issued_at !== null
            && ($report->report_type === 'progress' || $isLatestTerminal);

        return view('faculty.progress-reports.show', [
            'report' => $report, 'topic' => $topic,
            'canReview' => $canManage && $report->isAvailableToResearchHead() && ($report->report_type === 'terminal'
                ? $report->review_status !== ProjectNarrativeReport::STATUS_REVIEWED
                : $report->isLatestSubmittedVersion() && $report->review_status === ProjectNarrativeReport::STATUS_PENDING),
            'canSecretaryReview' => $topic->canReviewImplementationReports($request->user()) && $topic->isMonitoringAvailable() && $report->isAwaitingSecretaryReview() && $report->isLatestSubmittedVersion(),
            'canRecordSignedCopy' => $canManage && $isLatestTerminal && $report->review_status === ProjectNarrativeReport::STATUS_REVIEWED,
        ]);
    }

    public function review(
        Request $request,
        ProjectNarrativeReport $report,
        SidebarAttentionService $sidebarAttention,
    ): RedirectResponse {
        abort_unless($report->topic->isMonitoringAvailable(), 404);
        abort_unless($report->topic()->withIssuedNotice()->exists(), 404);
        abort_unless($report->isSubmitted(), 404);

        $validated = $request->validate([
            'review_status' => ['required', Rule::in([
                ProjectNarrativeReport::STATUS_REVIEWED,
                ProjectNarrativeReport::STATUS_REVISION_REQUESTED,
            ])],
            'research_head_remarks' => ['nullable', 'required_if:review_status,'.ProjectNarrativeReport::STATUS_REVISION_REQUESTED, 'string', 'max:5000'],
        ]);

        if ($report->report_type === 'progress') {
            app(ProjectReportReviewService::class)->reviewByHead($report, $request->user(), $validated['review_status'], $validated['research_head_remarks'] ?? null);
            $sidebarAttention->markTopicAsRead($request->user(), ProposalActivityNotification::SIDEBAR_AREA_PROJECT_MONITORING, $report->topic_id);

            return back()->with('success', $validated['review_status'] === 'reviewed' ? 'Progress Report accepted.' : 'Concerns returned to the Research Office Secretary.');
        }

        $reviewAttributes = [
            ...$validated,
            'reviewed_by' => $request->user()->id,
            'reviewed_at' => now(),
        ];
        $signedCopyPath = null;

        if ($validated['review_status'] === ProjectNarrativeReport::STATUS_REVISION_REQUESTED
            && $report->report_type === 'terminal'
            && $report->hasSignedCopy()) {
            $terminalData = $report->terminal_data ?? [];
            $signedCopyPath = $report->signedCopy()['path'];
            unset($terminalData['signed_copy']);
            $reviewAttributes['terminal_data'] = $terminalData;
        }

        $report->update($reviewAttributes);

        if (filled($signedCopyPath)) {
            Storage::disk('local')->delete($signedCopyPath);
        }

        $sidebarAttention->markTopicAsRead(
            $request->user(),
            ProposalActivityNotification::SIDEBAR_AREA_PROJECT_MONITORING,
            $report->topic_id,
        );

        $report->load('topic.user');
        $report->topic->user->notify(new ProposalActivityNotification(
            $validated['review_status'] === ProjectNarrativeReport::STATUS_REVIEWED
                ? 'Progress report reviewed'
                : 'Progress report corrections requested',
            'The Research Head reviewed your progress report for “'.$report->topic->title.'”.',
            route('topics.show', $report->topic).'#project-monitoring',
            $validated['review_status'] === ProjectNarrativeReport::STATUS_REVIEWED ? 'success' : 'warning',
            $report->topic_id,
            workspace: User::WORKSPACE_FACULTY_RESEARCHER,
            sidebarArea: ProposalActivityNotification::SIDEBAR_AREA_MY_PROJECTS,
        ));

        return back()->with('success', 'Progress report review saved.');
    }

    public function secretaryReview(ReviewImplementationReportRequest $request, ProjectNarrativeReport $report, ProjectReportReviewService $reviews, SidebarAttentionService $sidebarAttention): RedirectResponse
    {
        $validated = $request->validated();
        $reviews->reviewBySecretary($report, $request->user(), $validated['decision'], $validated['secretary_remarks'] ?? null);
        $sidebarAttention->markTopicAsRead($request->user(), ProposalActivityNotification::SIDEBAR_AREA_PROJECT_MONITORING, $report->topic_id);

        return back()->with('success', $validated['decision'] === 'forward' ? 'Progress Report forwarded to the Research Head.' : 'Progress Report corrections requested from the researcher.');
    }

    public function download(
        Request $request,
        ProjectNarrativeReport $report,
        ProgressReportDocumentService $documentService,
        DocumentPdfConverter $pdfConverter,
    ): StreamedResponse {
        $this->authorizeViewer($request, $report);
        $disposition = $request->routeIs('project-narrative-reports.view') ? 'inline' : 'attachment';

        if (filled($report->official_pdf_path)
            && filled($report->official_pdf_filename)
            && Storage::disk('local')->exists($report->official_pdf_path)) {
            return Storage::disk('local')->response(
                $report->official_pdf_path,
                $report->official_pdf_filename,
                ['Content-Type' => 'application/pdf', 'X-Content-Type-Options' => 'nosniff'],
                $disposition,
            );
        }

        abort_unless($report->isSubmitted(), 404);
        $report->loadMissing(['topic.user', 'submitter']);
        $pdf = $pdfConverter->convertDocx($documentService->generate($report));

        return response()->streamDownload(
            static fn () => print $pdf,
            Str::slug($report->terminal_data['project_title'] ?? $report->topic->title).'-'.Str::slug($report->report_label).'.pdf',
            ['Content-Type' => 'application/pdf', 'X-Content-Type-Options' => 'nosniff'],
            $disposition,
        );
    }

    public function downloadEvidence(Request $request, TopicProposal $topic, string $evidence, ProgressReportEvidenceService $evidenceService, ImagePreviewService $previews): StreamedResponse
    {
        $file = null;
        if ($request->user()->isUsingWorkspace(User::WORKSPACE_FACULTY_RESEARCHER) && $topic->isAccessibleTo($request->user())) {
            foreach (ProjectNarrativeReportDraft::query()->whereBelongsTo($topic, 'topic')->whereBelongsTo($request->user(), 'user')->where('report_type', 'progress')->get(['source_data']) as $draft) {
                $file = collect($draft->source_data['accomplishments'] ?? [])->flatMap(fn (array $row): array => $row['evidence'] ?? [])->firstWhere('id', $evidence);
                if ($file) {
                    break;
                }
            }
        }
        if (! $file) {
            foreach (ProjectNarrativeReport::query()->whereBelongsTo($topic, 'topic')
                ->when($request->user()->isUsingWorkspace(User::WORKSPACE_RESEARCH_HEAD), fn ($query) => $query->visibleToResearchHead())
                ->orderByDesc('submission_status')->orderByDesc('id')->get(['id', 'topic_id', 'submission_status', 'report_type', 'review_stage', 'accomplishments']) as $report) {
                $candidate = collect($report->accomplishments ?? [])->flatMap(fn (array $row): array => $row['evidence'] ?? [])->firstWhere('id', $evidence);
                if ($candidate) {
                    if ($report->isSubmitted()) {
                        if ($request->user()->isUsingWorkspace([User::WORKSPACE_RESEARCH_HEAD, User::WORKSPACE_RESEARCH_SECRETARY])) {
                            $this->authorizeViewer($request, $report);
                        } else {
                            Gate::forUser($request->user())->authorize('view', $topic);
                        }
                    } else {
                        abort_unless($request->user()->isUsingWorkspace(User::WORKSPACE_FACULTY_RESEARCHER)
                            && $topic->isAccessibleTo($request->user()), 403);
                    }
                    $file = $candidate;
                    break;
                }
            }
        }
        abort_unless($file && $evidenceService->isStoredEvidence($topic, $file), 404);

        $mimeType = Storage::disk('local')->mimeType($file['path']);
        if ($request->boolean('preview')) {
            abort_unless(in_array($mimeType, ['image/jpeg', 'image/png'], true), 404);

            return $previews->response($file['path'], $file['name']);
        }

        return Storage::disk('local')->download($file['path'], $file['name'], ['X-Content-Type-Options' => 'nosniff']);
    }

    public function draftImage(Request $request, TopicProposal $topic, string $image, ReportFigureDraftService $images, ImagePreviewService $previews): StreamedResponse
    {
        abort_unless($request->user()->isUsingWorkspace(User::WORKSPACE_FACULTY_RESEARCHER) && $topic->isAccessibleTo($request->user()), 403);
        $file = ProjectNarrativeReportDraft::query()->whereBelongsTo($topic, 'topic')->whereBelongsTo($request->user(), 'user')->pluck('source_data')
            ->map(fn (array $data): ?array => $data['saved_images'][$image] ?? null)->filter()->first();
        abort_unless($file && $images->isStored($topic, $file), 404);
        $mimeType = Storage::disk('local')->mimeType($file['path']);
        abort_unless(in_array($mimeType, ['image/jpeg', 'image/png'], true), 404);

        if ($request->boolean('preview')) {
            return $previews->response($file['path'], $file['original_name'] ?? basename($file['path']));
        }

        return Storage::disk('local')->response($file['path'], $file['original_name'] ?? basename($file['path']), ['Content-Type' => $mimeType, 'Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff']);
    }

    public function downloadPhoto(Request $request, ProjectNarrativeReport $report, int $photoIndex, ImagePreviewService $previews): StreamedResponse
    {
        $this->authorizeViewer($request, $report);
        $photo = ($report->photos ?? [])[$photoIndex] ?? null;
        abort_unless(is_array($photo) && Storage::disk('local')->exists($photo['path'] ?? ''), 404);

        $filename = $photo['original_name'] ?? basename($photo['path']);

        if ($request->routeIs('project-narrative-reports.photos.view')) {
            $mimeType = Storage::disk('local')->mimeType($photo['path']);
            abort_unless(in_array($mimeType, ['image/jpeg', 'image/png', 'image/webp'], true), 415);

            if ($request->boolean('preview')) {
                return $previews->response($photo['path'], $filename);
            }

            return Storage::disk('local')->response($photo['path'], $filename, [
                'Content-Type' => $mimeType,
                'X-Content-Type-Options' => 'nosniff',
            ]);
        }

        return Storage::disk('local')->download($photo['path'], $filename);
    }

    public function storeSignedCopy(
        StoreSignedTerminalReportRequest $request,
        ProjectNarrativeReport $report,
    ): RedirectResponse {
        $file = $request->file('signed_report');
        $directory = 'narrative-progress-reports/'.$report->topic_id.'/signed';
        $path = $file->storeAs($directory, Str::uuid().'.pdf', 'local');
        $previousPath = null;

        try {
            DB::transaction(function () use ($request, $report, $file, $path, &$previousPath): void {
                $lockedReport = ProjectNarrativeReport::query()
                    ->whereKey($report->id)
                    ->lockForUpdate()
                    ->firstOrFail();

                abort_unless($lockedReport->report_type === 'terminal'
                    && $lockedReport->isSubmitted()
                    && $lockedReport->review_status === ProjectNarrativeReport::STATUS_REVIEWED, 422);

                $previousPath = $lockedReport->signedCopy()['path'] ?? null;
                $lockedReport->update([
                    'terminal_data' => [
                        ...($lockedReport->terminal_data ?? []),
                        'signed_copy' => [
                            'path' => $path,
                            'original_filename' => $file->getClientOriginalName(),
                            'mime_type' => 'application/pdf',
                            'size' => $file->getSize(),
                            'checksum' => hash_file('sha256', $file->getRealPath()),
                            'uploaded_by' => $request->user()->id,
                            'uploaded_at' => now()->toIso8601String(),
                        ],
                    ],
                ]);
            });
        } catch (Throwable $exception) {
            Storage::disk('local')->delete($path);

            throw $exception;
        }

        if (filled($previousPath) && $previousPath !== $path) {
            Storage::disk('local')->delete($previousPath);
        }

        return back()->with('success', 'Signed Terminal Report recorded. The project may now be completed when every other completion requirement is satisfied.');
    }

    public function downloadSignedCopy(Request $request, ProjectNarrativeReport $report): StreamedResponse
    {
        $this->authorizeViewer($request, $report);
        $signedCopy = $report->signedCopy();
        abort_unless($signedCopy !== null && Storage::disk('local')->exists($signedCopy['path']), 404);

        return Storage::disk('local')->download(
            $signedCopy['path'],
            $signedCopy['original_filename'],
            ['Content-Type' => 'application/pdf', 'X-Content-Type-Options' => 'nosniff'],
        );
    }

    private function authorizeViewer(Request $request, ProjectNarrativeReport $report): void
    {
        if ($report->isPrepared()) {
            abort_unless(
                $request->user()->isUsingWorkspace('faculty_researcher')
                    && $report->topic->isAccessibleTo($request->user()),
                403,
            );

            return;
        }

        if ($request->user()->isUsingWorkspace(User::WORKSPACE_RESEARCH_HEAD)) {
            abort_unless($report->isAvailableToResearchHead(), 403);

            return;
        }

        if ($request->user()->isUsingWorkspace(User::WORKSPACE_RESEARCH_SECRETARY)) {
            abort_unless($report->report_type === 'progress' && $report->topic->canReviewImplementationReports($request->user()), 403);

            return;
        }

        abort_unless(
            $report->topic->isAccessibleTo($request->user()),
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

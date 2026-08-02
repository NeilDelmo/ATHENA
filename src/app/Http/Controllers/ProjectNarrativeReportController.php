<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreProjectNarrativeReportRequest;
use App\Models\ProjectNarrativeReport;
use App\Models\TopicProposal;
use App\Models\User;
use App\Notifications\ProposalActivityNotification;
use App\Services\ProgressReportDocumentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class ProjectNarrativeReportController extends Controller
{
    public function __construct(
        private readonly ProgressReportDocumentService $documentService,
    ) {}

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
        ));

        return back()->with('success', 'Official progress report submitted for Research Head review.');
    }

    public function review(Request $request, ProjectNarrativeReport $report): RedirectResponse
    {
        abort_unless($report->topic()->monitoringAvailable()->exists(), 404);

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
        ));

        return back()->with('success', 'Progress report review saved.');
    }

    public function download(Request $request, ProjectNarrativeReport $report): StreamedResponse
    {
        $this->authorizeViewer($request, $report);
        $report->loadMissing(['topic.user', 'submitter']);
        $filename = Str::slug($report->topic->title).'-progress-report.docx';

        return response()->streamDownload(
            fn () => print $this->documentService->generate($report),
            $filename,
            ['Content-Type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'],
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
        abort_unless(
            $request->user()->isUsingWorkspace(User::WORKSPACE_RESEARCH_HEAD)
                || $report->topic->user_id === $request->user()->id,
            403,
        );
    }
}

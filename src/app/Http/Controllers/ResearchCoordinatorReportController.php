<?php

namespace App\Http\Controllers;

use App\Actions\NotifyRmsQuarterlyReportStatus;
use App\Actions\StoreProjectDocuments;
use App\Http\Requests\PreviewCollegeQuarterlyReportRequest;
use App\Http\Requests\SaveCollegeQuarterlyReportRequest;
use App\Models\CollegeQuarterlyReport;
use App\Models\CollegeQuarterlyReportSubmission;
use App\Models\ProjectDocument;
use App\Models\TopicProposal;
use App\Models\User;
use App\Services\CollegeQuarterlyReportData;
use App\Services\CollegeQuarterlyReportDocument;
use App\Services\CollegeQuarterlyReportFacultyEvidence;
use App\Services\ImagePreviewService;
use App\Services\MonitoringQuarterService;
use App\Support\ProposalRichText;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use RuntimeException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class ResearchCoordinatorReportController extends Controller
{
    public function index(Request $request, CollegeQuarterlyReportData $data, MonitoringQuarterService $quarters): View
    {
        return view('research_coordinator.reports', $this->viewData($request, $data, $quarters));
    }

    public function print(Request $request, CollegeQuarterlyReportData $data, MonitoringQuarterService $quarters): View
    {
        return view('research_coordinator.report-print', $this->viewData($request, $data, $quarters));
    }

    public function preview(PreviewCollegeQuarterlyReportRequest $request, CollegeQuarterlyReportData $data, MonitoringQuarterService $quarters): View
    {
        $validated = $request->validated();
        $request->merge(['year' => $validated['reporting_year'], 'quarter' => $validated['reporting_quarter']]);
        $view = $this->viewData($request, $data, $quarters);
        $report = $view['report'] !== null ? clone $view['report'] : new CollegeQuarterlyReport(['college' => $request->user()->college]);
        if ($report->submitted_at !== null) {
            return view('research_coordinator.report-print', [...$view, 'embedded' => true]);
        }
        $report->fill(Arr::only($validated, ['presentations', 'publications', 'seminars', 'manual_research', 'ip_assets', 'other_accomplishments', 'reviewed_by', 'reviewed_by_title']));
        $evidence = collect($view['reportEvidence'])->reject(fn (array $image): bool => in_array(hash('sha256', $image['path']), $validated['remove_evidence'] ?? [], true)
            || (isset($image['section']) && ! collect($report->{$image['section']} ?? [])->contains('id', $image['row_id'] ?? null)))
            ->map(fn (array $image): array => [...$image, 'caption' => $validated['saved_evidence_captions'][hash('sha256', $image['path'])] ?? $image['caption'] ?? ''])->values()->all();
        foreach ($request->file('evidence_images', []) as $index => $image) {
            $evidence[] = ['url' => 'data:'.$image->getMimeType().';base64,'.base64_encode($image->getContent()), 'mime_type' => $image->getMimeType(), 'caption' => $validated['evidence_captions'][$index] ?? ''];
        }
        foreach ($request->file('row_evidence', []) as $section => $rows) {
            foreach ($rows as $rowId => $images) {
                foreach ($images as $image) {
                    $evidence[] = ['url' => 'data:'.$image->getMimeType().';base64,'.base64_encode($image->getContent()), 'mime_type' => $image->getMimeType(), 'section' => $section, 'row_id' => $rowId];
                }
            }
        }

        return view('research_coordinator.report-print', [...$view, 'report' => $report, 'embedded' => true, 'reportEvidence' => $evidence,
            'reportData' => [...$view['reportData'], 'presentations' => $report->activityRows('presentations'), 'publications' => $report->activityRows('publications'),
                'research' => [...$view['automaticResearch'], ...$report->activityRows('manual_research')]],
        ]);
    }

    public function template(Request $request): BinaryFileResponse
    {
        $this->authorizeCoordinator($request);

        return response()->download(resource_path('documents/College-Quarterly-Accomplishment-Report.docx'));
    }

    public function download(Request $request, CollegeQuarterlyReportData $data, MonitoringQuarterService $quarters, CollegeQuarterlyReportDocument $document): Response
    {
        $view = $this->viewData($request, $data, $quarters);

        $report = $view['report'] !== null ? clone $view['report'] : new CollegeQuarterlyReport;
        $report->evidence = $view['reportEvidence'];

        return response($document->generate($view['coordinator'], $view['period'], $view['reportData'], $report), 200, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'Content-Disposition' => 'attachment; filename="College-Quarterly-Accomplishment-Report-'.$view['year'].'-Q'.$view['quarter'].'.docx"',
        ]);
    }

    public function evidence(Request $request, CollegeQuarterlyReport $report, int $index, ImagePreviewService $previews): BinaryFileResponse|StreamedResponse
    {
        if ($request->user()->hasRole('research_head') && $request->user()->isUsingWorkspace(User::WORKSPACE_RESEARCH_HEAD)) {
            abort_unless($report->submitted_at !== null, 403);
        } else {
            $this->authorizeCoordinator($request);
            abort_unless($request->user()->isUsingWorkspace(User::WORKSPACE_RESEARCH_OFFICE) && $report->college === $request->user()->college, 403);
        }
        $evidence = $report->evidence[$index] ?? null;
        abort_unless(is_array($evidence) && Storage::disk('local')->exists($evidence['path']), 404);

        if ($request->boolean('preview') && in_array($evidence['mime_type'], ['image/jpeg', 'image/png'], true)) {
            return $previews->response($evidence['path'], $evidence['original_filename']);
        }

        $headers = [
            'Content-Type' => $evidence['mime_type'], 'X-Content-Type-Options' => 'nosniff', 'Cache-Control' => 'private, no-store',
        ];

        return in_array($evidence['mime_type'], ['image/jpeg', 'image/png'], true)
            ? response()->file(Storage::disk('local')->path($evidence['path']), $headers)
            : response()->download(Storage::disk('local')->path($evidence['path']), $evidence['original_filename'], $headers);
    }

    public function store(SaveCollegeQuarterlyReportRequest $request, CollegeQuarterlyReportData $data, MonitoringQuarterService $quarters, ProposalRichText $richText, StoreProjectDocuments $storeProjectDocuments, NotifyRmsQuarterlyReportStatus $notifyRmsStatus): RedirectResponse
    {
        $validated = $request->validated();
        if (is_string($validated['other_accomplishments'] ?? null) && strip_tags($validated['other_accomplishments']) !== $validated['other_accomplishments']) {
            $validated['other_accomplishments'] = $richText->sanitize($validated['other_accomplishments']);
        }
        $period = $quarters->forYearAndQuarter($validated['reporting_year'], $validated['reporting_quarter']);
        $submitting = $validated['intent'] === 'submit';
        if ($submitting && now()->lessThanOrEqualTo($period['end'])) {
            throw ValidationException::withMessages(['intent' => 'Submit the report after the selected calendar quarter has ended.']);
        }

        $storedPaths = [];
        $removedPaths = [];
        try {
            DB::transaction(function () use ($request, $validated, $submitting, $data, $storeProjectDocuments, &$storedPaths, &$removedPaths): void {
                $coordinator = $request->user();
                $report = CollegeQuarterlyReport::firstOrCreate([
                    'college' => $coordinator->college,
                    'reporting_year' => $validated['reporting_year'],
                    'reporting_quarter' => $validated['reporting_quarter'],
                ], ['prepared_by' => $coordinator->id]);
                $report = CollegeQuarterlyReport::whereKey($report->id)->lockForUpdate()->firstOrFail();
                if ($report->submitted_at !== null) {
                    throw ValidationException::withMessages(['intent' => 'This quarterly report has already been submitted and cannot be changed.']);
                }
                if (! hash_equals($report->draftToken(), $validated['draft_token'])) {
                    throw ValidationException::withMessages(['draft_token' => 'This report changed in another tab. Your changes were not saved. Copy any work you need, then reload this page to load the latest draft.']);
                }

                $evidence = array_values(array_filter($report->evidence ?? [], fn (array $image): bool => ($image['source'] ?? null) !== 'faculty_report'));
                foreach ($validated['saved_evidence_captions'] ?? [] as $token => $caption) {
                    $index = is_string($token) ? array_search($token, array_map(fn (array $image): string => hash('sha256', $image['path']), $evidence), true) : false;
                    if ($index === false) {
                        throw ValidationException::withMessages(['saved_evidence_captions' => 'A supporting image changed. Reload this report before editing its caption.']);
                    }
                    $evidence[$index]['caption'] = $caption ?? '';
                }
                $hasHistory = ($validated['remove_evidence'] ?? []) !== []
                    && Schema::hasTable((new CollegeQuarterlyReportSubmission)->getTable()) && $report->submissions()->exists();
                foreach ($validated['remove_evidence'] ?? [] as $token) {
                    $index = array_search($token, array_map(fn (array $image): string => hash('sha256', $image['path']), $evidence), true);
                    if ($index === false) {
                        throw ValidationException::withMessages(['remove_evidence' => 'A selected image has changed. Reload the report before removing photos.']);
                    }
                    if (empty($evidence[$index]['section']) && empty($evidence[$index]['document_id']) && ! $hasHistory) {
                        $removedPaths[] = $evidence[$index]['path'];
                    }
                    unset($evidence[$index]);
                }
                $evidence = array_values($evidence);
                $images = $request->file('evidence_images', []);
                if (collect($evidence)->filter(fn (array $image): bool => empty($image['section']))->count() + count($images) > 10) {
                    throw ValidationException::withMessages(['evidence_images' => 'A quarterly report may contain up to 10 supporting images.']);
                }
                foreach ($images as $index => $image) {
                    $path = $image->store('college-quarterly-reports/'.$report->id, 'local');
                    if ($path === false) {
                        throw new RuntimeException('The supporting image could not be stored.');
                    }
                    $storedPaths[] = $path;
                    $evidence[] = [
                        'path' => $path, 'caption' => $validated['evidence_captions'][$index] ?? '',
                        'mime_type' => $image->getMimeType(), 'original_filename' => $image->getClientOriginalName(),
                    ];
                }
                $report->fill([
                    'prepared_by' => $coordinator->id, 'presentations' => $validated['presentations'] ?? [],
                    'publications' => $validated['publications'] ?? [], 'seminars' => $validated['seminars'] ?? [],
                    'manual_research' => $validated['manual_research'] ?? [],
                    'ip_assets' => $validated['ip_assets'] ?? [],
                    'other_accomplishments' => $validated['other_accomplishments'] ?? null,
                    'reviewed_by' => $validated['reviewed_by'] ?? null, 'reviewed_by_title' => $validated['reviewed_by_title'] ?? null,
                    'evidence' => $evidence,
                ]);
                $report->evidence = $this->storeActivityEvidence($request, $report, $evidence, $submitting, $storeProjectDocuments, $storedPaths);
                if ($submitting) {
                    $facultyEvidence = app(CollegeQuarterlyReportFacultyEvidence::class);
                    $report->evidence = [...$report->evidence, ...$facultyEvidence->freeze($report,
                        $facultyEvidence->forQuarter($coordinator, $report->reporting_year, $report->reporting_quarter), $storedPaths)];
                }
                $evidence = $report->evidence;
                $report->snapshot = [...$data->build($coordinator, $report->reporting_year, $report->reporting_quarter),
                    'presentations' => $report->activityRows('presentations'), 'publications' => $report->activityRows('publications'),
                ];
                $report->snapshot = [...$report->snapshot, 'research' => [...$report->snapshot['research'], ...$report->activityRows('manual_research')]];
                if ($submitting) {
                    $report->snapshot = [...$report->snapshot,
                        'manual_presentations' => $report->presentations, 'manual_publications' => $report->publications,
                        'manual_research' => $report->manual_research,
                        'ip_assets' => $report->ip_assets,
                        'seminars' => $report->seminars, 'other_accomplishments' => $report->other_accomplishments,
                        'reviewed_by' => $report->reviewed_by, 'reviewed_by_title' => $report->reviewed_by_title,
                        'prepared_by_name' => $coordinator->name, 'college' => $coordinator->college, 'evidence' => $evidence,
                    ];
                    $report->submitted_at = now();
                }
                $report->save();
                if ($submitting) {
                    $report->submissions()->create([
                        'version_number' => (int) $report->submissions()->max('version_number') + 1,
                        'submitted_by' => $coordinator->id, 'snapshot' => $report->snapshot, 'submitted_at' => $report->submitted_at,
                    ]);
                }
            });
        } catch (Throwable $exception) {
            Storage::disk('local')->delete($storedPaths);
            throw $exception;
        }
        Storage::disk('local')->delete($removedPaths);
        if ($submitting) {
            $notifyRmsStatus->handle($validated['reporting_year'], $validated['reporting_quarter']);
        }

        $period = ['year' => $validated['reporting_year'], 'quarter' => $validated['reporting_quarter']];
        if ($validated['intent'] === 'preview') {
            $period['preview'] = 1;
        }
        $response = redirect()->route('research_coordinator.reports.index', $period)
            ->with('status', $submitting ? 'Quarterly accomplishment report submitted.' : 'Quarterly accomplishment report draft saved.');
        if ($validated['intent'] === 'download') {
            $response->with('report_download_url', route('research_coordinator.reports.download', $period));
        }

        return $response;
    }

    /** @return array<string, mixed> */
    private function viewData(Request $request, CollegeQuarterlyReportData $data, MonitoringQuarterService $quarters): array
    {
        $this->authorizeCoordinator($request);
        $validated = $request->validate(['year' => ['nullable', 'integer', 'between:2000,2100'], 'quarter' => ['nullable', 'integer', 'between:1,4']]);
        $year = (int) ($validated['year'] ?? now()->year);
        $quarter = (int) ($validated['quarter'] ?? now()->quarter);
        $coordinator = $request->user();
        $reports = CollegeQuarterlyReport::where('college', $coordinator->college)->where('reporting_year', $year)
            ->with(['preparedBy:id,name', 'submissions' => fn ($query) => $query
                ->select(['id', 'report_id', 'version_number', 'submitted_at', 'returned_at', 'return_reason'])
                ->orderByDesc('version_number')])->get()->keyBy('reporting_quarter');
        $report = $reports->get($quarter);
        $reportData = $report?->submitted_at !== null ? $report->snapshot : $data->build($coordinator, $year, $quarter);
        $automaticResearch = $report?->submitted_at !== null ? [] : $reportData['research'];
        if ($report?->submitted_at === null) {
            $reportData = [...$reportData,
                'presentations' => $report?->activityRows('presentations') ?? [], 'publications' => $report?->activityRows('publications') ?? [],
                'research' => [...$automaticResearch, ...($report?->activityRows('manual_research') ?? [])],
            ];
        }
        $evidenceProjects = TopicProposal::query()->whereIn('status', [TopicProposal::STATUS_READY_FOR_SIGNATURE, 'approved'])
            ->accessibleForDocumentRelease($coordinator)->orderBy('title')->get(['id', 'title']);
        $reportEvidence = $report?->evidence ?? [];
        if ($report?->submitted_at === null) {
            $reportEvidence = [...array_values(array_filter($reportEvidence, fn (array $file): bool => ($file['source'] ?? null) !== 'faculty_report')),
                ...app(CollegeQuarterlyReportFacultyEvidence::class)->forQuarter($coordinator, $year, $quarter)];
        } elseif (($submission = $report->submissions->first()) !== null) {
            $reportEvidence = array_map(fn (array $file, int $index): array => [
                ...$file, 'url' => route('college-quarterly-submissions.evidence', ['submission' => $submission, 'index' => $index]),
            ], $reportEvidence, array_keys($reportEvidence));
        }

        return [
            'coordinator' => $coordinator, 'year' => $year, 'quarter' => $quarter,
            'period' => $quarters->forYearAndQuarter($year, $quarter), 'report' => $report,
            'reportData' => $reportData,
            'evidenceProjects' => $evidenceProjects,
            'reportEvidence' => $reportEvidence,
            'automaticResearch' => $automaticResearch,
            'hasSourceChanges' => $report !== null && $report->submitted_at === null && $report->snapshot !== null && $report->snapshot != $reportData,
            'quarters' => collect(range(1, 4))->map(function (int $number) use ($year, $quarters, $reports): array {
                $period = $quarters->forYearAndQuarter($year, $number);
                $report = $reports->get($number);

                return [...$period, 'report' => $report, 'status' => match (true) {
                    $report?->submitted_at !== null => 'Submitted',
                    $report !== null => 'Draft',
                    now()->lessThan($period['start']) => 'Upcoming',
                    now()->greaterThan($period['end']) => 'Overdue',
                    default => 'Not submitted',
                }];
            }),
        ];
    }

    private function authorizeCoordinator(Request $request): void
    {
        abort_unless($request->user()?->hasRole('research_coordinator') && filled($request->user()->college), 403);
    }

    /**
     * @param  list<array<string, mixed>>  $evidence
     * @param  list<string>  $storedPaths
     * @return list<array<string, mixed>>
     */
    private function storeActivityEvidence(SaveCollegeQuarterlyReportRequest $request, CollegeQuarterlyReport $report, array $evidence, bool $submitting, StoreProjectDocuments $storeProjectDocuments, array &$storedPaths): array
    {
        $evidence = array_values(array_filter($evidence, function (array $image) use ($report): bool {
            $section = $image['section'] ?? null;

            return $section === null || (in_array($section, ['presentations', 'publications', 'seminars'], true)
                && collect($report->getAttribute($section) ?? [])->contains('id', $image['row_id'] ?? null));
        }));

        foreach (['presentations', 'publications', 'seminars'] as $section) {
            foreach ($report->getAttribute($section) ?? [] as $row) {
                $files = array_values($request->file('row_evidence.'.$section.'.'.$row['id'], []));
                $retained = collect($evidence)->where('section', $section)->where('row_id', $row['id']);
                $field = 'row_evidence.'.$section.'.'.$row['id'];
                if ($retained->count() + count($files) > 5) {
                    throw ValidationException::withMessages([$field => 'Each activity may contain up to five proof images.']);
                }
                $hasProof = $files !== [] || $retained->contains(function (array $image): bool {
                    return Storage::disk('local')->exists($image['path'])
                        && in_array(Storage::disk('local')->mimeType($image['path']), ['image/jpeg', 'image/png'], true);
                });
                if ($submitting && ! $hasProof) {
                    throw ValidationException::withMessages([$field => 'Add at least one proof image for this activity before submitting.']);
                }

                $folder = filled($row['research_title'] ?? null) ? $row['research_title'] : $row['event'];
                foreach ($evidence as &$image) {
                    if (($image['section'] ?? null) === $section && ($image['row_id'] ?? null) === $row['id']) {
                        $image['folder'] = $folder;
                    }
                }
                unset($image);

                $project = filled($row['project_id'] ?? null) ? TopicProposal::query()->whereIn('status', [TopicProposal::STATUS_READY_FOR_SIGNATURE, 'approved'])
                    ->accessibleForDocumentRelease($request->user())->findOrFail($row['project_id']) : null;
                $subdirectory = $report->reporting_year.'/q'.$report->reporting_quarter.'/'.$section.'/'.$row['id'];
                $copyCaptions = [];
                foreach ($retained as $index => $image) {
                    $folderChanged = ($image['project_id'] ?? null) !== $project?->id
                        || ($project === null && ($image['folder'] ?? null) !== $folder);
                    if ($folderChanged && Storage::disk('local')->exists($image['path'])
                        && in_array(Storage::disk('local')->mimeType($image['path']), ['image/jpeg', 'image/png'], true)) {
                        $copyCaptions[count($files)] = $image['caption'] ?? '';
                        $files[] = new UploadedFile(Storage::disk('local')->path($image['path']), $image['original_filename'], $image['mime_type'], null, true);
                        unset($evidence[$index]);
                    }
                }
                if ($project !== null && $files !== []) {
                    $documents = $storeProjectDocuments->handle($project, $request->user(), $files, ProjectDocument::CATEGORY_ACCOMPLISHMENT_EVIDENCE,
                        $report->reporting_year.' Q'.$report->reporting_quarter.' · '.Str::headline($section).' · '.$folder, 'accomplishment-evidence/'.$subdirectory);
                    foreach ($documents as $index => $document) {
                        $storedPaths[] = $document->file_path;
                        $evidence[] = [
                            'path' => $document->file_path, 'caption' => $copyCaptions[$index] ?? '', 'mime_type' => $document->mime_type,
                            'original_filename' => $document->original_filename, 'section' => $section, 'row_id' => $row['id'],
                            'folder' => $folder, 'project_id' => $project->id, 'document_id' => $document->id,
                        ];
                    }
                } else {
                    foreach ($files as $index => $file) {
                        $directory = 'college-quarterly-reports/'.$report->id.'/'.$report->reporting_year.'/q'.$report->reporting_quarter.'/'.(Str::limit(Str::slug($folder), 80, '') ?: 'activity').'/'.$section.'/'.$row['id'];
                        $path = $file->store($directory, 'local');
                        if ($path === false) {
                            throw new RuntimeException('The activity proof could not be stored.');
                        }
                        $storedPaths[] = $path;
                        $evidence[] = [
                            'path' => $path, 'caption' => $copyCaptions[$index] ?? '', 'mime_type' => $file->getMimeType(), 'original_filename' => $file->getClientOriginalName(),
                            'section' => $section, 'row_id' => $row['id'], 'folder' => $folder,
                        ];
                    }
                }
            }
        }

        return array_values($evidence);
    }
}

<?php

use App\Contracts\DocumentPdfConverter;
use App\Models\ProjectDocument;
use App\Models\ProjectNarrativeReport;
use App\Models\ProjectProgressReport;
use App\Models\TopicProposal;
use App\Models\User;
use App\Services\ProjectDocumentLibrary;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    Storage::fake('local');
    $this->withoutVite();
    Role::findOrCreate('research_head', 'web');
    Role::findOrCreate('faculty_researcher', 'web');
    $this->head = User::factory()->create();
    $this->head->assignRole('research_head');
    $this->researcher = User::factory()->create(['name' => 'Elena Santos']);
    $this->researcher->assignRole('faculty_researcher');
    $this->project = TopicProposal::create([
        'user_id' => $this->researcher->id,
        'title' => 'Coastal communities research',
        'status' => 'approved',
        'project_status' => 'ongoing',
        'notice_to_proceed_issued_by' => $this->head->id,
        'notice_to_proceed_issued_at' => '2025-06-01',
    ]);
    $this->documentsUrl = route('research_head.projects.workspace.documents', $this->project);
    $this->reportAttributes = [
        'submitted_by' => $this->researcher->id, 'report_type' => 'terminal', 'submission_date' => '2026-06-30',
        'researchers' => $this->researcher->name, 'implementation_start' => '2025-06-01', 'implementation_end' => '2026-06-30',
        'budget' => 25000, 'funding_agency' => 'University', 'accomplishment_summary' => 'Coastal mapping completed.',
        'introduction' => 'Coastal community research.', 'objectives' => 'Map coastal conditions.',
        'methodology' => 'Community observations.', 'results_discussion' => 'Mapping completed.', 'photos' => [],
    ];
});

test('research head opens project files through the existing private document routes', function () {
    $contents = "%PDF-1.7\nproject evidence";
    $path = 'project-documents/private/coastal-evidence.pdf';
    Storage::disk('local')->put($path, $contents);
    $document = ProjectDocument::factory()->for($this->project, 'topic')->for($this->researcher, 'uploadedBy')->create([
        'title' => 'Coastal evidence',
        'file_path' => $path,
        'original_filename' => 'coastal-evidence.pdf',
        'file_size' => strlen($contents),
    ]);

    $this->actingAs($this->head)->withSession([User::ACTIVE_WORKSPACE_SESSION_KEY => User::WORKSPACE_RESEARCH_HEAD]);
    $response = $this->getJson($this->documentsUrl)
        ->assertSuccessful()
        ->assertJsonPath('total', 1)
        ->assertJsonPath('canUpload', false)
        ->assertJsonPath('categories.0.key', ProjectDocument::CATEGORY_SUPPORTING_FILES)
        ->assertJsonPath('categories.0.count', 1)
        ->assertJsonPath('documents.0.key', 'project-document-'.$document->id)
        ->assertJsonPath('documents.0.title', 'Coastal evidence')
        ->assertJsonPath('documents.0.filename', 'coastal-evidence.pdf')
        ->assertJsonPath('documents.0.uploaded_by', $this->researcher->name)
        ->assertJsonPath('documents.0.file_size', strlen($contents))
        ->assertJsonPath('documents.0.view_url', route('topics.documents.view', [$this->project, $document]))
        ->assertJsonPath('documents.0.download_url', route('topics.documents.download', [$this->project, $document]))
        ->assertJsonMissingPath('documents.0.file_path')
        ->assertJsonMissingPath('documents.0.checksum');

    expect($response->getContent())->not->toContain($path);
    $preview = $this->get($response->json('documents.0.view_url'))
        ->assertSuccessful()->assertHeader('Content-Type', 'application/pdf');
    expect($preview->streamedContent())->toBe($contents);
    $this->get($response->json('documents.0.download_url'))->assertDownload('coastal-evidence.pdf');

    $this->get(route('research_head.projects.index'))->assertSuccessful()
        ->assertViewHas('workspaceProjects', fn (array $projects): bool => $projects[0]['documents_url'] === $this->documentsUrl && $projects[0]['poster_url'] === null);
});

test('project files require authentication', function () {
    $this->getJson($this->documentsUrl)->assertUnauthorized();
});

test('project files require the active research head workspace even for a research head role', function (string $workspace) {
    $role = $workspace === User::WORKSPACE_RESEARCH_OFFICE ? 'research_coordinator' : $workspace;
    Role::findOrCreate($role, 'web');
    $this->head->assignRole($role);

    $this->actingAs($this->head)->withSession([User::ACTIVE_WORKSPACE_SESSION_KEY => $workspace])
        ->getJson($this->documentsUrl)->assertForbidden()->assertJsonStructure(['message']);
})->with(['faculty', 'faculty_researcher', 'research_office', 'research_secretary']);

test('projects outside the issued portfolio cannot expose files', function (array $attributes) {
    $this->project->update($attributes);

    $this->actingAs($this->head)->withSession([User::ACTIVE_WORKSPACE_SESSION_KEY => User::WORKSPACE_RESEARCH_HEAD])
        ->getJson($this->documentsUrl)->assertNotFound()->assertJsonStructure(['message']);
})->with([
    'no notice issued' => [['notice_to_proceed_issued_at' => null]],
    'not approved' => [['status' => 'pending']],
]);

test('project file browsing respects topic authorization', function () {
    Gate::before(fn (User $user, string $ability): ?bool => $ability === 'view' ? false : null);

    $this->actingAs($this->head)->withSession([User::ACTIVE_WORKSPACE_SESSION_KEY => User::WORKSPACE_RESEARCH_HEAD])
        ->getJson($this->documentsUrl)->assertForbidden()->assertJsonStructure(['message']);
});

test('project file browsing skips missing stored files', function () {
    ProjectDocument::factory()->for($this->project, 'topic')->for($this->researcher, 'uploadedBy')->create([
        'file_path' => 'project-documents/missing.pdf',
    ]);

    $this->actingAs($this->head)->withSession([User::ACTIVE_WORKSPACE_SESSION_KEY => User::WORKSPACE_RESEARCH_HEAD])
        ->getJson($this->documentsUrl)->assertSuccessful()
        ->assertJsonPath('total', 0)
        ->assertJsonPath('categories', [])
        ->assertJsonPath('documents', []);
});

test('project folders include submitted Monitoring Tools and Progress Reports with working PDF links', function (bool $storedPdf) {
    $contents = "%PDF-1.7\nproject report";
    $converter = new class implements DocumentPdfConverter
    {
        public int $conversionCount = 0;

        public function convertDocx(string $contents): string
        {
            $this->conversionCount++;

            return "%PDF-1.7\nproject report";
        }

        public function convertXlsx(string $contents): string
        {
            throw new LogicException('This test should not convert spreadsheets.');
        }
    };
    app()->instance(DocumentPdfConverter::class, $converter);

    $monitoring = $this->project->progressReports()->create([
        'submitted_by' => $this->researcher->id, 'reporting_date' => '2026-06-30',
        'reporting_year' => 2026, 'reporting_quarter' => 2, 'submitted_at' => now(),
        'progress_percentage' => 50, 'accomplishments' => 'Coastal mapping completed.',
        'work_plan' => [[
            'activity' => 'Map coastal conditions', 'percent_weight' => 100,
            'physical_target' => 'Survey coastal sites', 'target_completion_date' => '2026-06-30',
            'actual_accomplishment' => 'Coastal mapping completed.', 'accomplished_percentage' => 50,
            'findings' => 'Remaining sites scheduled for the next quarter.',
        ]],
        'budget_utilization' => [['type' => 'Purchase Request', 'details' => 'Survey supplies', 'amount_requested' => 5000, 'actual_amount' => 4000, 'remarks' => 'Delivered']],
        'official_pdf_path' => $storedPdf ? 'progress-reports/monitoring.pdf' : null,
        'official_pdf_filename' => $storedPdf ? 'monitoring-tool.pdf' : null,
        'official_pdf_size' => $storedPdf ? strlen($contents) : null,
    ]);
    $progress = $this->project->narrativeReports()->create([
        ...$this->reportAttributes, 'report_type' => 'progress', 'reporting_date' => '2026-06-30',
        'submitted_at' => now(), 'official_pdf_path' => $storedPdf ? 'narrative-progress-reports/progress.pdf' : null,
        'official_pdf_filename' => $storedPdf ? 'progress-report.pdf' : null,
        'official_pdf_size' => $storedPdf ? strlen($contents) : null,
    ]);
    if ($storedPdf) {
        foreach ([$monitoring, $progress] as $report) {
            Storage::disk('local')->put($report->official_pdf_path, $contents);
        }
    }
    $monitoring->replicate()->fill([
        'reporting_quarter' => 3, 'submission_status' => ProjectProgressReport::SUBMISSION_STATUS_PREPARED,
        'official_pdf_path' => 'reports/prepared-monitoring.pdf', 'official_pdf_filename' => 'prepared-monitoring.pdf',
    ])->save();
    $progress->replicate()->fill([
        'submission_status' => ProjectNarrativeReport::SUBMISSION_STATUS_PREPARED,
        'official_pdf_path' => 'reports/prepared-progress.pdf', 'official_pdf_filename' => 'prepared-progress.pdf',
    ])->save();
    $otherProject = $this->project->replicate();
    $otherProject->fill(['title' => 'Unrelated project'])->save();
    $monitoring->replicate()->fill(['topic_id' => $otherProject->id, 'official_pdf_path' => 'reports/unrelated-monitoring.pdf'])->save();
    $progress->replicate()->fill(['topic_id' => $otherProject->id, 'official_pdf_path' => 'reports/unrelated-progress.pdf'])->save();
    foreach (['prepared-monitoring', 'prepared-progress', 'unrelated-monitoring', 'unrelated-progress'] as $filename) {
        Storage::disk('local')->put('reports/'.$filename.'.pdf', $contents);
    }

    $this->actingAs($this->head)->withSession([User::ACTIVE_WORKSPACE_SESSION_KEY => User::WORKSPACE_RESEARCH_HEAD]);
    $response = $this->getJson($this->documentsUrl)->assertSuccessful()
        ->assertJsonPath('total', 2)
        ->assertJsonPath('categories.0.key', ProjectDocument::CATEGORY_MONITORING_REPORTS)
        ->assertJsonPath('categories.0.count', 2);
    $documents = collect($response->json('documents'))->keyBy('key');
    foreach ([
        ['monitoring-report-'.$monitoring->id, route('project-progress.monitoring-tool.view', $monitoring), route('project-progress.monitoring-tool', $monitoring)],
        ['narrative-report-'.$progress->id, route('project-narrative-reports.view', $progress), route('project-narrative-reports.download', $progress)],
    ] as [$key, $previewUrl, $downloadUrl]) {
        expect($documents->get($key))->toMatchArray([
            'category' => ProjectDocument::CATEGORY_MONITORING_REPORTS,
            'view_url' => $previewUrl, 'download_url' => $downloadUrl, 'official' => true,
        ]);
        $preview = $this->get($previewUrl)->assertSuccessful()->assertHeader('Content-Type', 'application/pdf');
        expect($preview->headers->get('Content-Disposition'))->toStartWith('inline')
            ->and($preview->streamedContent())->toBe($contents);
        $download = $this->get($downloadUrl)->assertSuccessful()->assertDownload();
        expect($download->streamedContent())->toBe($contents);
    }
    expect($converter->conversionCount)->toBe($storedPdf ? 0 : 4)
        ->and($this->project->projectDocuments()->count())->toBe(0);
    expect($response->getContent())->not->toContain('official_pdf_path');
})->with(['stored PDFs' => true, 'legacy reports without stored PDFs' => false]);

test('project folders omit submitted reports whose recorded PDF is missing', function (string $type) {
    $attributes = ['official_pdf_path' => 'reports/missing.pdf', 'official_pdf_filename' => 'missing.pdf'];
    if ($type === 'monitoring') {
        $this->project->progressReports()->create([
            ...$attributes, 'submitted_by' => $this->researcher->id, 'reporting_date' => '2026-06-30',
            'progress_percentage' => 50, 'accomplishments' => 'Coastal mapping completed.',
            'work_plan' => [], 'budget_utilization' => [],
        ]);
    } else {
        $this->project->narrativeReports()->create([...$this->reportAttributes, ...$attributes, 'report_type' => 'progress']);
    }

    $this->actingAs($this->head)->withSession([User::ACTIVE_WORKSPACE_SESSION_KEY => User::WORKSPACE_RESEARCH_HEAD])
        ->getJson($this->documentsUrl)->assertSuccessful()->assertJsonPath('documents', []);
})->with(['monitoring', 'progress']);

test('submitted terminal posters appear once as private image files with their report', function () {
    $file = UploadedFile::fake()->image('coastal-project-poster.png', 240, 360);
    $path = $file->store('narrative-progress-reports/'.$this->project->id, 'local');
    $photo = [
        'path' => $path, 'original_name' => 'coastal-project-poster.png', 'mime_type' => 'image/png',
        'size' => $file->getSize(), 'caption' => 'Community coastal mapping project poster', 'section' => 'cover',
    ];
    $report = $this->project->narrativeReports()->create([
        ...$this->reportAttributes, 'submitted_at' => '2026-06-30 10:00:00',
        'photos' => [
            [...$photo, 'section' => 'results_discussion'],
            $photo,
            $photo,
        ],
    ]);
    $this->actingAs($this->head)->withSession([User::ACTIVE_WORKSPACE_SESSION_KEY => User::WORKSPACE_RESEARCH_HEAD]);

    $response = $this->getJson($this->documentsUrl)->assertSuccessful()
        ->assertJsonPath('total', 1)
        ->assertJsonPath('categories.0.key', ProjectDocument::CATEGORY_MONITORING_REPORTS)
        ->assertJsonPath('categories.0.count', 1)
        ->assertJsonPath('documents.0.key', 'terminal-report-poster-'.$report->id.'-1')
        ->assertJsonPath('documents.0.title', 'Terminal report poster')
        ->assertJsonPath('documents.0.filename', 'coastal-project-poster.png')
        ->assertJsonPath('documents.0.note', $photo['caption'])
        ->assertJsonPath('documents.0.preview_kind', 'image')
        ->assertJsonPath('documents.0.view_url', route('project-narrative-reports.photos.view', [$report, 1, 'preview' => 1]))
        ->assertJsonPath('documents.0.thumbnail_url', route('project-narrative-reports.photos.view', [$report, 1, 'preview' => 1]))
        ->assertJsonPath('documents.0.download_url', route('project-narrative-reports.photos.download', [$report, 1]))
        ->assertJsonMissingPath('documents.0.path')
        ->assertJsonMissingPath('documents.0.file_path');
    expect($response->getContent())->not->toContain($path);

    $preview = $this->get($response->json('documents.0.view_url'))->assertSuccessful()
        ->assertHeader('Content-Type', 'image/png')->assertHeader('X-Content-Type-Options', 'nosniff');
    expect($preview->streamedContent())->toBe(Storage::disk('local')->get($path));
    $this->get($response->json('documents.0.download_url'))->assertDownload($photo['original_name']);

    $index = $this->get(route('research_head.projects.index'))->assertSuccessful()
        ->assertViewHas('workspaceProjects', fn (array $projects): bool => $projects[0]['poster_url'] === $response->json('documents.0.view_url'));
    expect($index->getContent())->not->toContain($path);

    $page = $this->get(route('topics.show', $this->project))->assertSuccessful();
    $dom = new DOMDocument;
    @$dom->loadHTML($page->getContent());
    $xpath = new DOMXPath($dom);
    $modal = '//*[@data-project-document-preview-modal][.//section[@aria-labelledby="project-'.$this->project->id.'-terminal-report-poster-'.$report->id.'-1-heading"]]';
    expect($xpath->query($modal.'//*[@data-project-document-image-preview]')->length)->toBe(1)
        ->and($xpath->query($modal.'//*[@data-pdf-annotation-config]')->length)->toBe(0);
});

test('project folders omit unavailable or private report posters', function (string $reason) {
    $file = UploadedFile::fake()->image('poster.png');
    $path = $file->store('narrative-progress-reports/'.$this->project->id, 'local');
    $photo = ['path' => $path, 'section' => 'cover', 'original_name' => 'poster.png', 'mime_type' => 'image/png'];
    if ($reason === 'missing file') {
        Storage::disk('local')->delete($path);
    }
    if ($reason === 'invalid image') {
        Storage::disk('local')->put($path, '<html>Not a poster image</html>');
    }
    if ($reason === 'ordinary report figure') {
        $photo['section'] = 'results_discussion';
    }
    $preparedPdfPath = null;
    if ($reason === 'prepared report') {
        $preparedPdfPath = 'narrative-progress-reports/'.$this->project->id.'/private-prepared-report.pdf';
        Storage::disk('local')->put($preparedPdfPath, "%PDF-1.7\nprivate draft");
    }
    $this->project->narrativeReports()->create([
        ...$this->reportAttributes,
        'report_type' => $reason === 'progress report' ? 'progress' : 'terminal',
        'submission_status' => $reason === 'prepared report' ? ProjectNarrativeReport::SUBMISSION_STATUS_PREPARED : ProjectNarrativeReport::SUBMISSION_STATUS_SUBMITTED,
        'photos' => $reason === 'no poster' ? [] : [$photo],
        'official_pdf_path' => $preparedPdfPath,
    ]);

    $response = $this->actingAs($this->head)->withSession([User::ACTIVE_WORKSPACE_SESSION_KEY => User::WORKSPACE_RESEARCH_HEAD])
        ->getJson($this->documentsUrl)->assertSuccessful()->assertJsonPath('total', $reason === 'progress report' ? 1 : 0);
    expect(collect($response->json('documents'))->filter(fn (array $document): bool => str_starts_with($document['key'], 'terminal-report-poster-')))->toBeEmpty();
    $this->get(route('research_head.projects.index'))->assertSuccessful()
        ->assertViewHas('workspaceProjects', fn (array $projects): bool => $projects[0]['poster_url'] === null);
})->with(['progress report', 'prepared report', 'missing file', 'invalid image', 'ordinary report figure', 'no poster']);

test('terminal poster previews retain report authorization', function () {
    $file = UploadedFile::fake()->image('poster.jpg');
    $path = $file->store('narrative-progress-reports/'.$this->project->id, 'local');
    $report = $this->project->narrativeReports()->create([
        ...$this->reportAttributes,
        'photos' => [['path' => $path, 'section' => 'cover', 'original_name' => 'poster.jpg']],
    ]);
    $outsider = User::factory()->create();
    $outsider->assignRole('faculty_researcher');
    $this->actingAs($outsider)->withSession([User::ACTIVE_WORKSPACE_SESSION_KEY => User::WORKSPACE_FACULTY_RESEARCHER]);
    $this->get(route('project-narrative-reports.photos.view', [$report, 0]))->assertForbidden();
    $this->get(route('project-narrative-reports.photos.download', [$report, 0]))->assertForbidden();

    Gate::before(fn (User $user, string $ability): ?bool => $ability === 'view' ? false : null);
    $this->actingAs($this->head)->withSession([User::ACTIVE_WORKSPACE_SESSION_KEY => User::WORKSPACE_RESEARCH_HEAD])
        ->get(route('research_head.projects.index'))->assertSuccessful()
        ->assertViewHas('workspaceProjects', fn (array $projects): bool => $projects === []);
});

test('project folder covers use the newest available submitted terminal poster', function () {
    $path = UploadedFile::fake()->image('older-poster.png')->store('narrative-progress-reports/'.$this->project->id, 'local');
    $older = $this->project->narrativeReports()->create([
        ...$this->reportAttributes, 'submission_date' => '2026-03-31',
        'photos' => [
            ['path' => $path, 'section' => 'methodology'],
            ['path' => $path, 'section' => 'cover'],
        ],
    ]);
    $newer = $this->project->narrativeReports()->create([
        ...$this->reportAttributes, 'submission_date' => '2026-06-30',
        'photos' => [['path' => 'narrative-progress-reports/missing-poster.png', 'section' => 'cover']],
    ]);
    $this->project->narrativeReports()->create([
        ...$this->reportAttributes, 'submission_date' => '2026-09-30',
        'submission_status' => ProjectNarrativeReport::SUBMISSION_STATUS_PREPARED,
        'photos' => [['path' => $path, 'section' => 'cover']],
    ]);
    $this->project->update(['project_status' => TopicProposal::PROJECT_STATUS_COMPLETED]);
    $this->actingAs($this->head)->withSession([User::ACTIVE_WORKSPACE_SESSION_KEY => User::WORKSPACE_RESEARCH_HEAD]);

    $this->get(route('research_head.completed-projects.index'))->assertSuccessful()
        ->assertViewHas('workspaceProjects', fn (array $projects): bool => $projects[0]['poster_url'] === route('project-narrative-reports.photos.view', [$older, 1, 'preview' => 1]));

    $newer->update(['photos' => [['path' => $path, 'section' => 'cover']]]);
    $this->get(route('research_head.completed-projects.index'))->assertSuccessful()
        ->assertViewHas('workspaceProjects', fn (array $projects): bool => $projects[0]['poster_url'] === route('project-narrative-reports.photos.view', [$newer, 0, 'preview' => 1]));
});

test('project folder covers are loaded in one batch beyond the fallback table page', function () {
    $path = UploadedFile::fake()->image('portfolio-poster.png')->store('narrative-progress-reports/'.$this->project->id, 'local');
    foreach (range(1, 16) as $number) {
        $project = $number === 1 ? $this->project : $this->project->replicate();
        if ($number !== 1) {
            $project->fill(['title' => 'Coastal project '.$number])->save();
        }
        $project->narrativeReports()->create([
            ...$this->reportAttributes, 'photos' => [['path' => $path, 'section' => 'cover']],
        ]);
    }
    $this->actingAs($this->head)->withSession([User::ACTIVE_WORKSPACE_SESSION_KEY => User::WORKSPACE_RESEARCH_HEAD]);

    DB::flushQueryLog();
    DB::enableQueryLog();
    try {
        $this->get(route('research_head.projects.index'))->assertSuccessful()
            ->assertViewHas('workspaceProjects', function (array $projects): bool {
                expect($projects)->toHaveCount(16)
                    ->and(collect($projects)->whereNotNull('poster_url'))->toHaveCount(16);

                return true;
            });
        $posterQueries = collect(DB::getQueryLog())->filter(fn (array $query): bool => str_contains($query['query'], 'project_narrative_reports') && str_contains($query['query'], '`photos`'));
        expect($posterQueries)->toHaveCount(1);
    } finally {
        DB::disableQueryLog();
        DB::flushQueryLog();
    }
});

test('project file summaries avoid loading report narratives and preserve evidence and signed downloads', function () {
    $largeText = str_repeat('Full report narrative outside the file browser. ', 1000);
    $pdf = "%PDF-1.7\nproject report";
    foreach (['monitoring', 'narrative', 'signed'] as $name) {
        Storage::disk('local')->put('reports/'.$name.'.pdf', $pdf);
    }
    $proofPath = 'narrative-progress-reports/'.$this->project->id.'/evidence/proof.pdf';
    Storage::disk('local')->put($proofPath, $pdf);
    $monitoring = $this->project->progressReports()->create([
        'submitted_by' => $this->researcher->id, 'reporting_date' => '2026-06-30', 'reporting_year' => 2026,
        'reporting_quarter' => 2, 'period_start' => '2026-04-01', 'period_end' => '2026-06-30',
        'version_number' => 1, 'submitted_at' => now(), 'progress_percentage' => 50, 'accomplishments' => $largeText, 'issues' => $largeText,
        'work_plan' => [], 'budget_utilization' => [], 'official_pdf_path' => 'reports/monitoring.pdf',
        'official_pdf_filename' => 'monitoring.pdf', 'official_pdf_size' => strlen($pdf),
    ]);
    $narrative = $this->project->narrativeReports()->create([
        ...$this->reportAttributes, 'report_type' => 'progress', 'reporting_date' => '2026-06-30',
        'reporting_quarter' => 2, 'period_start' => '2026-04-01', 'period_end' => '2026-06-30', 'submitted_at' => now(),
        'introduction' => $largeText, 'objectives' => $largeText, 'methodology' => $largeText,
        'results_discussion' => $largeText, 'accomplishment_summary' => $largeText,
        'accomplishments' => [['objective' => 'Gather field evidence', 'evidence' => [[
            'id' => '11111111-1111-4111-8111-111111111111', 'path' => $proofPath, 'name' => 'proof.pdf',
            'mime_type' => 'application/pdf', 'size' => strlen($pdf), 'checksum' => hash('sha256', $pdf),
        ]]]],
        'official_pdf_path' => 'reports/narrative.pdf', 'official_pdf_filename' => 'narrative.pdf', 'official_pdf_size' => strlen($pdf),
        'reviewed_by' => $this->head->id,
        'terminal_data' => ['signed_copy' => ['path' => 'reports/signed.pdf', 'original_filename' => 'signed.pdf', 'size' => strlen($pdf), 'uploaded_at' => now()->toIso8601String()]],
    ]);
    $this->actingAs($this->head)->withSession([User::ACTIVE_WORKSPACE_SESSION_KEY => User::WORKSPACE_RESEARCH_HEAD]);
    $fullProject = $this->project->fresh(['progressReports', 'narrativeReports']);
    $expectedLibrary = app(ProjectDocumentLibrary::class)->build($fullProject, $this->head);
    $loadedReports = [];
    foreach ([ProjectProgressReport::class, ProjectNarrativeReport::class] as $model) {
        $model::retrieved(function (ProjectProgressReport|ProjectNarrativeReport $report) use (&$loadedReports): void {
            $loadedReports[$report::class] = $report->getAttributes();
        });
    }

    $library = app(ProjectDocumentLibrary::class)->build($this->project->fresh(), $this->head);
    $documents = $library['documents']->keyBy('key');

    expect($library['documents']->all())->toEqual($expectedLibrary['documents']->all())
        ->and($fullProject->narrativeReports->first()->introduction)->toBe($largeText)
        ->and($library['total'])->toBe(4)
        ->and($documents['monitoring-report-'.$monitoring->id])->toMatchArray([
            'title' => 'Monitoring Tool · Q2 2026', 'filename' => 'monitoring.pdf',
            'source' => 'Apr 1, 2026 – Jun 30, 2026', 'uploaded_by' => $this->researcher->name,
        ])
        ->and($documents['narrative-report-'.$narrative->id]['download_url'])->toBe(route('project-narrative-reports.download', $narrative))
        ->and($documents['signed-narrative-report-'.$narrative->id]['download_url'])->toBe(route('project-narrative-reports.signed-copy.download', $narrative))
        ->and($documents['signed-narrative-report-'.$narrative->id]['uploaded_by'])->toBe($this->head->name);
    $proof = $documents->firstWhere('filename', 'proof.pdf');
    expect($proof['download_url'])->toBe(route('project-narrative-reports.evidence', [$this->project, '11111111-1111-4111-8111-111111111111']))
        ->and($proof['uploaded_by'])->toBe($this->researcher->name)
        ->and($loadedReports)->toHaveCount(2);
    expect(strlen(json_encode($loadedReports, JSON_THROW_ON_ERROR)))->toBeLessThan(5000);
    foreach ($loadedReports as $attributes) {
        expect(array_intersect(['introduction', 'objectives', 'methodology', 'results_discussion', 'accomplishment_summary', 'issues'], array_keys($attributes)))->toBe([]);
    }
    expect(array_key_exists('accomplishments', $loadedReports[ProjectProgressReport::class]))->toBeFalse();
    $this->get($proof['download_url'])->assertSuccessful()->assertDownload('proof.pdf');
    $this->get($documents['signed-narrative-report-'.$narrative->id]['download_url'])->assertSuccessful()->assertDownload('signed.pdf');
});

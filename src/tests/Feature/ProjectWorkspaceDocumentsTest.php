<?php

use App\Models\ProjectDocument;
use App\Models\ProjectNarrativeReport;
use App\Models\TopicProposal;
use App\Models\User;
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
        ->assertJsonPath('documents.0.view_url', route('project-narrative-reports.photos.view', [$report, 1]))
        ->assertJsonPath('documents.0.thumbnail_url', route('project-narrative-reports.photos.view', [$report, 1]))
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

    $this->actingAs($this->head)->withSession([User::ACTIVE_WORKSPACE_SESSION_KEY => User::WORKSPACE_RESEARCH_HEAD])
        ->getJson($this->documentsUrl)->assertSuccessful()->assertJsonPath('documents', []);
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
        ->assertViewHas('workspaceProjects', fn (array $projects): bool => $projects[0]['poster_url'] === null);
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
        ->assertViewHas('workspaceProjects', fn (array $projects): bool => $projects[0]['poster_url'] === route('project-narrative-reports.photos.view', [$older, 1]));

    $newer->update(['photos' => [['path' => $path, 'section' => 'cover']]]);
    $this->get(route('research_head.completed-projects.index'))->assertSuccessful()
        ->assertViewHas('workspaceProjects', fn (array $projects): bool => $projects[0]['poster_url'] === route('project-narrative-reports.photos.view', [$newer, 0]));
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

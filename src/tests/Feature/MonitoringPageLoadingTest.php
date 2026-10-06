<?php

use App\Contracts\DocumentPdfConverter;
use App\Models\ProjectNarrativeReportDraft;
use App\Models\ProjectProgressReport;
use App\Models\ProposalVersionFile;
use App\Models\TopicProposal;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    $this->withoutVite();
    foreach (['faculty_researcher', 'research_head'] as $role) {
        Role::firstOrCreate(['name' => $role]);
    }
    $this->researcher = User::factory()->create();
    $this->researcher->assignRole('faculty_researcher');
    $this->head = User::factory()->create();
    $this->head->assignRole('research_head');
    $this->topic = TopicProposal::create([
        'user_id' => $this->researcher->id, 'title' => 'Monitoring performance project',
        'status' => 'approved', 'project_status' => 'ongoing', 'estimated_duration_months' => 12,
        'notice_to_proceed_issued_at' => '2026-01-01',
        'notice_to_proceed_data' => ['approved_start_date' => '2026-01-01', 'approved_end_date' => '2026-12-31', 'approved_duration_months' => 12],
    ]);
    $this->narrativeReportData = [
        'submitted_by' => $this->researcher->id, 'report_type' => 'progress',
        'submission_date' => '2026-03-31', 'reporting_date' => '2026-03-31', 'reporting_quarter' => 1,
        'researchers' => $this->researcher->name,
        'implementation_start' => '2026-01-01', 'implementation_end' => '2026-12-31',
        'budget' => 50000, 'funding_agency' => 'Batangas State University',
        'accomplishment_summary' => 'Quarterly activities completed.',
        'introduction' => str_repeat('Full narrative outside the project list. ', 1000),
        'objectives' => 'Complete fieldwork.', 'methodology' => 'Site visits.',
        'results_discussion' => 'Activities documented.', 'photos' => [],
    ];
    $converter = Mockery::mock(DocumentPdfConverter::class);
    $converter->shouldNotReceive('convertDocx');
    $converter->shouldNotReceive('convertXlsx');
    app()->instance(DocumentPdfConverter::class, $converter);
});

test('monitoring report forms share the proposal toolbar and paper preview beside their editing fields', function (string $type, string $route) {
    $this->travelTo(now()->setDate(2027, 1, 2));
    $response = $this->actingAs($this->researcher)->get(route($route, [
        'topic' => $this->topic, 'reporting_date' => '2026-03-31', 'report_type' => $type === 'terminal' ? 'terminal' : 'progress',
    ]))->assertSuccessful();
    $document = new DOMDocument;
    $browserHtml = preg_replace('/(?<=\s)@([a-z][\w.-]*)\s*=/', 'x-on:$1=', $response->getContent());
    @$document->loadHTML($browserHtml);
    $xpath = new DOMXPath($document);
    $workspace = '//*[@data-monitoring-paper-workspace]';
    $form = $xpath->query($workspace)->item(0);
    $editorForm = $xpath->query($workspace.'//form[@x-ref="form"]')->item(0);
    $toolbar = $workspace.'//*[@data-monitoring-writing-toolbar and @data-proposal-workspace-toolbar]';
    $previewToggle = $xpath->query($toolbar.'//button[@data-proposal-preview-toggle]')->item(0);
    $prepareButton = $xpath->query($toolbar.'//button[@type="submit"]')->item(0);

    expect($xpath->query($workspace)->length)->toBe(1)
        ->and($xpath->query($toolbar)->length)->toBe(1)
        ->and($xpath->query($toolbar.'//button[@data-writing-toolbar-close]')->length)->toBe(1)
        ->and($xpath->query($toolbar.'//button[@data-writing-toolbar-open]')->length)->toBe(1)
        ->and($xpath->query($toolbar.'//button[normalize-space(.)="Save draft"]')->length)->toBe(1)
        ->and($xpath->query($toolbar.'//a[@data-paper-cancel-exit]')->length)->toBe(1)
        ->and($previewToggle)->not->toBeNull()
        ->and($previewToggle->getAttribute(':aria-expanded'))->toBe('previewPaneOpen')
        ->and($xpath->query($workspace.'//aside/section[@id="'.$previewToggle->getAttribute('aria-controls').'"]')->length)->toBe(1)
        ->and($prepareButton->getAttribute('form'))->toBe($editorForm->getAttribute('id'))
        ->and($editorForm->getAttribute('id'))->not->toBe('')
        ->and($xpath->query($workspace.'//div[contains(@class,"proposal-edit-pane")]//form[@x-ref="form"]')->length)->toBe(1)
        ->and($xpath->query($workspace.'//div[contains(@class,"proposal-edit-pane")]/following-sibling::aside[contains(@class,"proposal-preview-dock")]')->length)->toBe(1)
        ->and($xpath->query($workspace.'//form//iframe')->length)->toBe(0)
        ->and($xpath->query($workspace.'//aside//iframe[@x-ref="previewFrame"]')->length)->toBe(1)
        ->and($xpath->query($workspace.'//button[@aria-label="Enlarge the paper preview"]')->length)->toBe(1)
        ->and($xpath->query($workspace.'//*[@x-ref="previewSection"]')->length)->toBe(0)
        ->and($xpath->query($workspace.'//*[@data-monitoring-action-dock-fixed]')->length)->toBe(0)
        ->and($xpath->query($workspace.'//aside//*[@data-progress-evidence or @data-monitoring-evidence]')->length)->toBe(0)
        ->and($xpath->query($workspace.'//button[@type="submit"]')->length)->toBe(1)
        ->and($response->getContent())->not->toContain('View full paper');

    $fixtureDirectory = getenv('ATHENA_MONITORING_BROWSER_FIXTURE_DIRECTORY');
    if (is_string($fixtureDirectory) && $fixtureDirectory !== '') {
        File::ensureDirectoryExists($fixtureDirectory);
        File::put($fixtureDirectory.'/monitoring-preview-'.$type.'.html', $document->saveHTML($form));
    }
})->with([
    'Monitoring Tool' => ['monitoring', 'project-progress.create'],
    'Progress Report' => ['progress', 'project-narrative-reports.create'],
    'Terminal Report' => ['terminal', 'project-narrative-reports.create'],
]);

test('monitoring lists fetch report summaries without their full contents', function (string $route, string $viewer) {
    $report = ProjectProgressReport::create([
        'topic_id' => $this->topic->id, 'submitted_by' => $this->researcher->id,
        'reporting_date' => '2026-03-31', 'progress_percentage' => 42,
        'accomplishments' => str_repeat('Report details for the full report page. ', 1000),
        'work_plan' => [['activity' => str_repeat('Saved milestone details. ', 5000)]],
        'budget_utilization' => [['remarks' => str_repeat('Saved expense details. ', 5000)]],
    ]);
    $narrative = $this->topic->narrativeReports()->create($this->narrativeReportData);

    $response = $this->actingAs($this->{$viewer})->get(route($route))->assertOk();
    $projects = $route === 'research_head.projects.index' ? $response->viewData('projects')->getCollection() : $response->viewData('activeProjects');
    expect($projects)->toHaveCount(1);
    $project = $projects->first();
    expect($project->latestProgressReport->id)->toBe($report->id)
        ->and($project->latestProgressReport->progress_percentage)->toBe(42)
        ->and(array_keys($project->latestProgressReport->getAttributes()))->not->toContain('work_plan', 'budget_utilization', 'accomplishments');
    if ($route === 'research_head.projects.index') {
        expect($project->latestNarrativeReport->id)->toBe($narrative->id)
            ->and(array_keys($project->latestNarrativeReport->getAttributes()))->not->toContain('introduction', 'terminal_data');
        $response->assertViewHas('summary', fn (array $summary): bool => $summary['pending_reports'] === 2);
    }
})->with([
    'researcher dashboard' => ['faculty.dashboard', 'researcher'],
    'researcher project list' => ['research.index', 'researcher'],
    'research head project list' => ['research_head.projects.index', 'head'],
]);

test('the monitoring editor uses the latest approved work plan without reading previous proposal versions', function () {
    foreach (range(1, 5) as $number) {
        $version = $this->topic->versions()->create([
            'submitted_by' => $this->researcher->id, 'version_number' => $number,
            'submission_type' => $number === 1 ? 'initial' : 'revision', 'title' => $this->topic->title,
            'file_path' => 'version-'.$number.'.pdf', 'original_filename' => 'proposal.pdf', 'mime_type' => 'application/pdf', 'file_size' => 100,
        ]);
        $version->files()->create([
            'document_type' => ProposalVersionFile::TYPE_WORK_PLAN, 'file_path' => 'plan-'.$number.'.pdf',
            'original_filename' => 'work-plan.pdf', 'mime_type' => 'application/pdf', 'file_size' => 100,
            'source_data' => ['total_duration_months' => 12, 'entries' => [[
                'objective' => 'Approved objective '.$number, 'activity' => 'Approved activity '.$number,
                'expected_output' => 'Approved output '.$number, 'months' => [1, 2, 3],
            ]]],
        ]);
        $version->files()->create([
            'document_type' => ProposalVersionFile::TYPE_DETAILED_PROPOSAL, 'file_path' => 'proposal-'.$number.'.pdf',
            'original_filename' => 'proposal.pdf', 'mime_type' => 'application/pdf', 'file_size' => 100,
            'source_data' => ['rationale' => str_repeat('Prior proposal narrative. ', 5000)],
        ]);
    }
    DB::enableQueryLog();
    DB::flushQueryLog();
    $response = $this->actingAs($this->researcher)->get(route('project-progress.create', ['topic' => $this->topic, 'reporting_date' => '2026-03-31']))->assertOk();
    $queries = collect(DB::getQueryLog());
    DB::disableQueryLog();

    $response->assertViewHas('initialWorkPlanRows', fn (array $rows): bool => count($rows) === 1 && $rows[0]['activity'] === 'Approved activity 5');
    expect($response->viewData('topic')->relationLoaded('versions'))->toBeFalse()
        ->and($response->viewData('topic')->latestVersion->id)->toBe($version->id);
    $fileReads = $queries->filter(fn (array $query): bool => str_contains($query['query'], 'from `proposal_version_files`'));
    expect($fileReads)->toHaveCount(1)
        ->and($response->viewData('topic')->latestVersion->files->pluck('proposal_version_id')->unique()->all())->toBe([$version->id]);
});

test('the narrative editor reads its saved draft once and keeps its saved answers', function () {
    ProjectNarrativeReportDraft::create([
        'topic_id' => $this->topic->id, 'user_id' => $this->researcher->id, 'report_type' => 'progress',
        'source_data' => ['reporting_date' => '2026-03-31', 'introduction' => 'Keep my saved narrative draft'],
    ]);
    DB::enableQueryLog();
    DB::flushQueryLog();
    $response = $this->actingAs($this->researcher)->get(route('project-narrative-reports.create', ['topic' => $this->topic, 'reporting_date' => '2026-03-31']))->assertOk();
    $queries = collect(DB::getQueryLog());
    DB::disableQueryLog();

    $response->assertViewHas('selectedReportingDate', '2026-03-31')
        ->assertViewHas('narrativeReportDraft', fn (ProjectNarrativeReportDraft $draft): bool => $draft->source_data['introduction'] === 'Keep my saved narrative draft');
    expect($queries->filter(fn (array $query): bool => str_contains($query['query'], 'from `project_narrative_report_drafts`')))->toHaveCount(1);
});

test('report editors check previous quarters using summaries instead of full report contents', function (string $route, string $table) {
    ProjectProgressReport::create([
        'topic_id' => $this->topic->id, 'submitted_by' => $this->researcher->id,
        'reporting_date' => '2026-03-31', 'version_number' => 1, 'progress_percentage' => 42,
        'accomplishments' => 'Completed the first-quarter fieldwork.',
        'work_plan' => [['activity' => str_repeat('Saved milestone details. ', 5000)]],
    ]);
    $this->topic->narrativeReports()->create($this->narrativeReportData);
    DB::enableQueryLog();
    DB::flushQueryLog();
    $response = $this->actingAs($this->researcher)->get(route($route, ['topic' => $this->topic, 'reporting_date' => '2026-06-30']))->assertOk();
    $queries = collect(DB::getQueryLog());
    DB::disableQueryLog();

    $response->assertViewHas('selectedReportingDate', '2026-06-30');
    expect($response->viewData('quarterOptions')->pluck('quarter')->all())->toBe([2, 3, 4]);
    $summaryReads = $queries->filter(fn (array $query): bool => str_contains($query['query'], 'from `'.$table.'`') && ! str_contains($query['query'], 'limit 1'));
    expect($summaryReads)->toHaveCount(1)
        ->and($summaryReads->first()['query'])->not->toContain('select *');
})->with([
    'monitoring tool' => ['project-progress.create', 'project_progress_reports'],
    'narrative progress report' => ['project-narrative-reports.create', 'project_narrative_reports'],
]);

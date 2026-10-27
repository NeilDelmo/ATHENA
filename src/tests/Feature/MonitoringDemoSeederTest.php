<?php

use App\Contracts\DocumentPdfConverter;
use App\Livewire\ResearchTargetBoard;
use App\Models\CollegeQuarterlyReport;
use App\Models\ResearchAnnualTarget;
use App\Models\RmsQuarterlyReport;
use App\Models\TopicProposal;
use App\Models\User;
use App\Notifications\ProposalActivityNotification;
use App\Services\ApprovedWorkPlanMonitoringService;
use App\Services\MonitoringQuarterService;
use App\Services\ProjectArchive;
use App\Services\ProposalSignatureWorkflow;
use App\Services\ResearchHeadAnalytics;
use App\Services\RmsQuarterlyReportStatus;
use App\Support\TerminalReportRules;
use Database\Seeders\MonitoringDemoSeeder;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    $this->withoutVite();
    $this->travelTo(now()->setDate(2026, 10, 27)->setTime(12, 0));
    Storage::set('local', Storage::fake('monitoring-demo-'.getmypid()));
    Notification::fake();
    foreach (['faculty_researcher', 'research_head', 'research_coordinator', 'research_secretary'] as $role) {
        Role::findOrCreate($role, 'web');
        $user = User::factory()->create(['college' => User::COLLEGES['CICS']]);
        $user->assignRole($role);
        $this->{$role} = $user;
    }
    $this->mock(DocumentPdfConverter::class)->shouldReceive('convertDocx', 'convertXlsx')->andReturn("%PDF-1.4\nDEMO\n%%EOF");
});

test('monitoring demo connects proposal history to reviewed reports archives labels college reports and RMS export', function () {
    $this->seed(MonitoringDemoSeeder::class);
    $projects = TopicProposal::orderBy('id')->get();
    expect($projects)->toHaveCount(2);
    foreach ($projects as $project) {
        expect($project->versions)->toHaveCount(2)
            ->and($project->stageTransitions->pluck('to_status')->all())->toContain('pending', 'revision_requested', 'resubmitted', 'gad_review', 'lrec_queued', 'lrec_review', 'ready_for_signature', 'approved')
            ->and(app(ApprovedWorkPlanMonitoringService::class)->hasApprovedWorkPlan($project))->toBeTrue();
        foreach ($project->versions as $version) {
            expect($version->files->where('document_type', '!=', 'head_upload'))->toHaveCount(7);
            foreach ($version->files as $file) {
                Storage::disk('local')->assertExists($file->file_path);
                expect(hash('sha256', Storage::disk('local')->get($file->file_path)))->toBe($file->checksum);
            }
        }
        expect($project->latestVersion->hasPassingGadAssessment())->toBeTrue()
            ->and($project->latestVersion->hasCoEvaluatorReview())->toBeTrue()
            ->and(app(ProposalSignatureWorkflow::class)->isComplete($project->latestVersion))->toBeTrue();
        $weights = [];
        foreach ($project->progressReports()->reorder('reporting_date')->get() as $report) {
            foreach ($report->work_plan as $row) {
                $key = $row['source_work_plan_index'];
                $weights[$key] = max($weights[$key] ?? 0, (float) $row['accomplished_percentage']);
            }
            expect((int) round(array_sum($weights)))->toBe($report->progress_percentage);
        }
        expect($project->versions->first()->created_at->lt($project->notice_to_proceed_issued_at))->toBeTrue();
        $this->actingAs($this->research_head)->withSession([User::ACTIVE_WORKSPACE_SESSION_KEY => User::WORKSPACE_RESEARCH_HEAD])
            ->get(route('topics.show', $project))->assertOk()->assertSeeText($project->title);
        $archive = app(ProjectArchive::class)->download($project->fresh(), $this->research_head);
        $path = $archive->getFile()->getPathname();
        $zip = new ZipArchive;
        $zip->open($path);
        $manifest = json_decode($zip->getFromName('manifest.json'), true, flags: JSON_THROW_ON_ERROR);
        $zip->close();
        unlink($path);
        expect($manifest['missing_files'])->toBe(0);
    }
    $completed = $projects->firstWhere('project_status', 'completed');
    expect($completed)->not->toBeNull()
        ->and(app(MonitoringQuarterService::class)->missingTerminalReportPeriods($completed))->toBe([])
        ->and($completed->narrativeReports->firstWhere('report_type', 'terminal')->hasSignedCopy())->toBeTrue()
        ->and($completed->narrativeReports->firstWhere('report_type', 'terminal')->accomplishments)->toHaveCount(2);
    $terminal = $completed->narrativeReports->firstWhere('report_type', 'terminal');
    $editableTerminalData = collect($terminal->terminal_data)->only([
        ...TerminalReportRules::NARRATIVES, 'collaborating_agency', 'total_expenditure', 'authors', 'signatories', 'tables',
    ])->all();
    expect(validator(['terminal_data' => $editableTerminalData], TerminalReportRules::rules(approvedBudget: 75000))->errors()->all())->toBe([])
        ->and($terminal->terminal_data['total_expenditure'])->toBe(18000)
        ->and($terminal->secretary_reviewed_at)->toBeNull();
    $this->actingAs($this->faculty_researcher)->withSession([User::ACTIVE_WORKSPACE_SESSION_KEY => User::WORKSPACE_FACULTY_RESEARCHER])
        ->put(route('research-outcomes.projects.update', $completed), ['labels' => ['publication', 'utilization'], 'lock_version' => 1])
        ->assertSessionHasNoErrors()->assertRedirect();
    expect($completed->fresh()->researchOutcome->labels)->toBe(['publication', 'utilization']);
    Notification::assertSentTo($this->research_coordinator, ProposalActivityNotification::class);
    $this->actingAs($this->research_coordinator)->withSession([User::ACTIVE_WORKSPACE_SESSION_KEY => User::WORKSPACE_RESEARCH_OFFICE])
        ->get(route('research-outcomes.projects.show', $completed))->assertOk()->assertSeeText('Utilization')->assertDontSeeText('Save labels');
    $this->get(route('research_coordinator.reports.index', ['year' => 2026, 'quarter' => 2]))->assertOk()->assertSeeText('DEMO');
    $this->get(route('research_coordinator.reports.download', ['year' => 2026, 'quarter' => 2]))->assertOk();
    expect(CollegeQuarterlyReport::whereNotNull('submitted_at')->count())->toBe(count(User::COLLEGES))
        ->and(app(RmsQuarterlyReportStatus::class)->forPeriod(2026, 2)['ready'])->toBeTrue();
    $college = CollegeQuarterlyReport::where('college', $this->research_coordinator->college)->sole();
    expect($college->evidence)->toHaveCount(2);
    foreach ($college->evidence as $proof) {
        Storage::disk('local')->assertExists($proof['path']);
    }
    $this->actingAs($this->research_head)->withSession([User::ACTIVE_WORKSPACE_SESSION_KEY => User::WORKSPACE_RESEARCH_HEAD]);
    $target = ResearchAnnualTarget::sole();
    expect(app(ResearchHeadAnalytics::class)->topics($target->academic_year, $target->starts_on->toDateString(), $target->ends_on->toDateString())->count())->toBe(2);
    $completed->update(['project_status' => 'ongoing']);
    $this->patch(route('research_head.projects.update-status', $completed), ['project_status' => 'completed', 'completion_confirmed' => '1'])
        ->assertSessionHasNoErrors()->assertRedirect();
    expect($completed->fresh()->project_status)->toBe('completed');
    $this->get(route('research_head.rms-quarterly-reports.index', ['year' => 2026, 'quarter' => 2]))->assertOk()->assertSeeText('DEMO');
    $this->get(route('research_head.rms-quarterly-reports.download', ['year' => 2026, 'quarter' => 2]))->assertOk()
        ->assertHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    Livewire::test(ResearchTargetBoard::class)->call('editTargets')->set('targetForm.indicators.publications.total', 50)->call('saveTargets')->assertHasNoErrors();
    expect(ResearchAnnualTarget::sole()->publications_target)->toBe(50);
});

test('rerunning the monitoring demo preserves edited records and unrelated work', function () {
    $this->seed(MonitoringDemoSeeder::class);
    $project = TopicProposal::first();
    $project->update(['title' => 'My edited demo']);
    $target = ResearchAnnualTarget::sole();
    $target->update(['publications_target' => 99]);
    $rms = RmsQuarterlyReport::sole();
    $rms->update(['campus' => 'Edited campus']);
    $counts = [TopicProposal::count(), User::count(), CollegeQuarterlyReport::count()];
    $paths = Storage::disk('local')->allFiles();
    $this->seed(MonitoringDemoSeeder::class);
    expect([TopicProposal::count(), User::count(), CollegeQuarterlyReport::count()])->toBe($counts)
        ->and(Storage::disk('local')->allFiles())->toBe($paths)
        ->and($project->fresh()->title)->toBe('My edited demo')
        ->and($target->fresh()->publications_target)->toBe(99)
        ->and($rms->fresh()->campus)->toBe('Edited campus');
});

test('monitoring demo refuses an occupied period before writing anything', function () {
    CollegeQuarterlyReport::factory()->create(['reporting_year' => 2026, 'reporting_quarter' => 2]);
    expect(fn () => $this->seed(MonitoringDemoSeeder::class))->toThrow(RuntimeException::class, 'already contains')
        ->and(TopicProposal::count())->toBe(0)->and(Storage::disk('local')->allFiles())->toBe([]);
});

test('monitoring demo rolls back records and files when document generation fails', function () {
    $calls = 0;
    $filesBeforeFailure = 0;
    $converter = $this->mock(DocumentPdfConverter::class);
    $converter->shouldReceive('convertXlsx')->andReturn("%PDF-1.4\nDEMO\n%%EOF");
    $converter->shouldReceive('convertDocx')->andReturnUsing(function () use (&$calls, &$filesBeforeFailure): string {
        if (++$calls === 8) {
            $filesBeforeFailure = count(Storage::disk('local')->allFiles());
            throw new RuntimeException('Converter unavailable');
        }

        return "%PDF-1.4\nDEMO\n%%EOF";
    });
    expect(fn () => $this->seed(MonitoringDemoSeeder::class))->toThrow(RuntimeException::class, 'Converter unavailable')
        ->and(TopicProposal::count())->toBe(0)->and(CollegeQuarterlyReport::count())->toBe(0)
        ->and(User::count())->toBe(4)->and(Storage::disk('local')->allFiles())->toBe([]);
    expect($filesBeforeFailure)->toBeGreaterThan(0);
});

test('monitoring demo is restricted to local and testing environments', function () {
    app()->detectEnvironment(fn (): string => 'production');
    try {
        expect(fn () => app()->call([app(MonitoringDemoSeeder::class), 'run']))->toThrow(RuntimeException::class, 'local');
    } finally {
        app()->detectEnvironment(fn (): string => 'testing');
    }
});

test('post implementation navigation and report downloads have visible button styling', function () {
    $project = TopicProposal::create(['user_id' => $this->faculty_researcher->id, 'title' => 'Completed demo', 'status' => 'approved', 'project_status' => 'completed']);
    $this->actingAs($this->faculty_researcher)->withSession([User::ACTIVE_WORKSPACE_SESSION_KEY => User::WORKSPACE_FACULTY_RESEARCHER]);
    $html = $this->get(route('research-outcomes.index'))->assertOk()->getContent();
    $document = new DOMDocument;
    @$document->loadHTML($html);
    $xpath = new DOMXPath($document);
    $action = $xpath->query('//a[@href="'.route('research-outcomes.projects.show', $project).'"]')->item(0);
    expect($action->getAttribute('class'))->toContain('dashboard-action')->not->toContain('underline');
    $this->get(route('research-outcomes.projects.show', $project))->assertOk()->assertSee('Project archive');
    foreach ([
        [$this->research_coordinator, User::WORKSPACE_RESEARCH_OFFICE, 'research_coordinator.reports.index'],
        [$this->research_head, User::WORKSPACE_RESEARCH_HEAD, 'research_head.rms-quarterly-reports.index'],
    ] as [$user, $workspace, $route]) {
        $download = 'https://example.test/demo-download';
        $html = $this->actingAs($user)->withSession([User::ACTIVE_WORKSPACE_SESSION_KEY => $workspace, 'report_download_url' => $download])
            ->get(route($route, ['year' => 2026, 'quarter' => 2]))->assertOk()->getContent();
        @$document->loadHTML($html);
        $action = (new DOMXPath($document))->query('//a[@href="'.$download.'"]')->item(0);
        expect($action->getAttribute('class'))->toContain('dashboard-action')->not->toContain('underline');
    }
});

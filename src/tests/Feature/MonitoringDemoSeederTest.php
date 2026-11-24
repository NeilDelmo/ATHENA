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
use App\Services\ProjectReportReviewService;
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
    foreach (['faculty_researcher', 'research_head', 'research_coordinator', 'research_office_secretary'] as $role) {
        Role::findOrCreate($role, 'web');
        $user = User::factory()->create(['college' => User::COLLEGES['CICS']]);
        $user->assignRole($role);
        $this->{$role} = $user;
    }
    $this->mock(DocumentPdfConverter::class)->shouldReceive('convertDocx', 'convertXlsx')->andReturn("%PDF-1.4\nDEMO\n%%EOF");
});

test('a past-quarter walkthrough covers active monitoring completed projects and college reporting', function () {
    $source = TopicProposal::create([
        'user_id' => $this->faculty_researcher->id,
        'title' => 'Existing project',
        'status' => 'approved',
        'project_status' => 'ongoing',
        'notice_to_proceed_issued_at' => now(),
        'notice_to_proceed_path' => 'existing-notice.pdf',
        'notice_to_proceed_data' => ['approved_start_date' => '2026-10-01', 'approved_end_date' => '2027-03-31'],
    ]);
    $this->artisan('monitoring:seed-walkthrough', ['source' => $source->id])->assertSuccessful();
    $project = TopicProposal::where('title', 'Campus Laboratory Energy Monitoring Dashboard')->sole();
    $completed = TopicProposal::whereKeyNot([$source->id, $project->id])->sole();
    $terminal = $completed->narrativeReports()->where('report_type', 'terminal')->sole();
    $schedule = app(MonitoringQuarterService::class);
    expect($project->title)->toBe('Campus Laboratory Energy Monitoring Dashboard')
        ->and($project->user_id)->toBe($source->user_id)
        ->and($project->project_status)->toBe('ongoing')
        ->and($schedule->canSubmitForDate($project, '2026-06-30'))->toBeTrue()
        ->and($schedule->canSubmitTerminal($project))->toBeTrue()
        ->and($schedule->missingTerminalReportPeriods($project))->toBe([])
        ->and($project->progressReports()->reorder('reporting_date')->pluck('review_stage')->all())->toBe(['head', 'secretary'])
        ->and($project->narrativeReports()->reorder('reporting_date')->pluck('review_status')->all())->toBe(['reviewed', 'pending'])
        ->and($project->narrativeReports()->reorder('reporting_date')->pluck('monitoring_report_id')->all())
        ->toBe($project->progressReports()->reorder('reporting_date')->pluck('id')->all())
        ->and($schedule->canSubmitForDate($source, '2026-12-31'))->toBeFalse()
        ->and($source->progressReports()->count())->toBe(0)
        ->and($completed->project_status)->toBe('completed')
        ->and($completed->latestProgressReportForResearchHead()->value('progress_percentage'))->toBe(100)
        ->and($schedule->missingTerminalReportPeriods($completed))->toBe([])
        ->and($terminal->review_status)->toBe('reviewed')
        ->and(app(ProjectReportReviewService::class)->hasAvailableSignedCopy($terminal))->toBeTrue()
        ->and($completed->researchOutcome->labels)->toContain('presentation', 'utilization')
        ->and(CollegeQuarterlyReport::sole()->submitted_at)->toBeNull()
        ->and(CollegeQuarterlyReport::sole()->presentations[0]['project_id'])->toBe($completed->id)
        ->and(RmsQuarterlyReport::count())->toBe(0)
        ->and(User::count())->toBe(4);
    foreach ([...$project->progressReports, ...$project->narrativeReports] as $report) {
        Storage::disk('local')->assertExists($report->official_pdf_path);
    }
    expect(collect($terminal->photos)->pluck('section')->all())->toBe(['cover', 'results_discussion']);
    foreach ($terminal->photos as $photo) {
        Storage::disk('local')->assertExists($photo['path']);
    }
    $this->actingAs($this->research_office_secretary)
        ->withSession([User::ACTIVE_WORKSPACE_SESSION_KEY => User::WORKSPACE_RESEARCH_SECRETARY])
        ->get(route('research_secretary.reports.index'))
        ->assertOk()
        ->assertSeeText($project->title);
    $this->actingAs($this->faculty_researcher)
        ->withSession([User::ACTIVE_WORKSPACE_SESSION_KEY => User::WORKSPACE_FACULTY_RESEARCHER])
        ->get(route('project-narrative-reports.create', ['topic' => $project, 'report_type' => 'terminal']))
        ->assertOk()
        ->assertSeeText('Terminal Report');
    $this->actingAs($this->research_coordinator)
        ->withSession([User::ACTIVE_WORKSPACE_SESSION_KEY => User::WORKSPACE_RESEARCH_OFFICE])
        ->get(route('research_coordinator.reports.index', ['year' => 2026, 'quarter' => 2]))
        ->assertOk()
        ->assertSeeText($project->title)
        ->assertSeeText($completed->title);
    $this->actingAs($this->research_head)
        ->withSession([User::ACTIVE_WORKSPACE_SESSION_KEY => User::WORKSPACE_RESEARCH_HEAD])
        ->get(route('research_head.completed-projects.index'))
        ->assertOk()
        ->assertSeeText($completed->title);
    $project->update(['description' => 'Updated walkthrough notes']);
    CollegeQuarterlyReport::sole()->update(['other_accomplishments' => 'Coordinator saved edits']);
    $this->artisan('monitoring:seed-walkthrough', ['source' => $source->id])->assertSuccessful();
    expect(TopicProposal::count())->toBe(3)
        ->and($project->fresh()->description)->toBe('Updated walkthrough notes')
        ->and(CollegeQuarterlyReport::sole()->other_accomplishments)->toBe('Coordinator saved edits');
    Notification::assertNothingSent();
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

test('the proposal and monitoring portfolio adds varied actionable examples without rewriting them', function () {
    $source = TopicProposal::create([
        'user_id' => $this->faculty_researcher->id, 'title' => 'Existing walkthrough source',
        'status' => 'approved', 'project_status' => 'ongoing', 'notice_to_proceed_issued_at' => now(),
        'notice_to_proceed_path' => 'existing-notice.pdf',
    ]);
    $seeder = app(MonitoringDemoSeeder::class);
    $seeder->seedWalkthrough($source->id);
    $projects = $seeder->seedPortfolio($source->id);

    expect($projects)->toHaveCount(14)
        ->and($projects['pending']->versions()->count())->toBe(1)
        ->and($projects['revision_requested']->status)->toBe('revision_requested')
        ->and($projects['resubmitted']->versions()->count())->toBe(2)
        ->and($projects['gad_review']->hasIssuedNoticeToProceed())->toBeFalse()
        ->and($projects['rejected']->status)->toBe('rejected')
        ->and($projects['report_due']->progressReports()->count())->toBe(0)
        ->and($projects['secretary_pending']->progressReports()->where('reporting_quarter', 2)->sole()->isAwaitingSecretaryReview())->toBeTrue()
        ->and($projects['faculty_corrections']->project_status)->toBe('delayed')
        ->and($projects['faculty_corrections']->narrativeReports()->where('reporting_quarter', 2)->sole()->review_status)->toBe('revision_requested')
        ->and($projects['head_pending']->progressReports()->where('reporting_quarter', 2)->sole()->isAvailableToResearchHead())->toBeTrue()
        ->and($projects['head_concerns']->progressReports()->where('reporting_quarter', 2)->sole()->research_head_remarks)->not->toBeNull();
    $forSecretary = $projects['terminal_pending']->narrativeReports()->where('report_type', 'terminal')->sole();
    $forHead = $projects['terminal_head']->narrativeReports()->where('report_type', 'terminal')->sole();
    $accepted = $projects['awaiting_signed_copy']->narrativeReports()->where('report_type', 'terminal')->sole();
    expect($forSecretary->isAwaitingSecretaryReview())->toBeTrue()
        ->and($forHead->isAvailableToResearchHead())->toBeTrue()
        ->and($forHead->review_status)->toBe('pending')
        ->and($accepted->review_status)->toBe('reviewed')
        ->and($accepted->hasSignedCopy())->toBeFalse()
        ->and($projects['completed']->isCompletedProject())->toBeTrue()
        ->and(CollegeQuarterlyReport::where('reporting_quarter', 1)->sole()->submitted_at)->not->toBeNull()
        ->and(CollegeQuarterlyReport::where('reporting_quarter', 3)->sole()->latestSubmission->return_reason)->not->toBeNull();
    Storage::disk('local')->assertExists($forSecretary->official_pdf_path);

    $projects['terminal_pending']->update(['title' => 'Updated project title']);
    expect($seeder->seedPortfolio($source->id)['terminal_pending']->title)->toBe('Updated project title')
        ->and(TopicProposal::count())->toBe(17);
    Notification::assertNothingSent();
});

test('supplemental monitoring examples cover staggered quarters corrections and closeout', function () {
    $this->travelTo(now()->setDate(2026, 11, 25)->setTime(12, 0));
    $source = TopicProposal::create([
        'user_id' => $this->faculty_researcher->id, 'title' => 'Existing monitoring source',
        'status' => 'approved', 'project_status' => 'ongoing', 'notice_to_proceed_issued_at' => now(),
        'notice_to_proceed_path' => 'existing-notice.pdf',
    ]);
    $seeder = app(MonitoringDemoSeeder::class);
    $seeder->seedWalkthrough($source->id);
    $this->artisan('monitoring:seed-portfolio', ['source' => $source->id, '--monitoring' => true])->assertSuccessful();
    $projects = $seeder->seedPortfolio($source->id, monitoringOnly: true);
    $quarters = app(MonitoringQuarterService::class);

    expect($projects)->toHaveCount(13)
        ->and(TopicProposal::count())->toBe(16)
        ->and(CollegeQuarterlyReport::count())->toBe(1)
        ->and($quarters->narrativeProgressPeriods($projects['recently_issued'])->first()['status'])->toBe('Upcoming')
        ->and($quarters->narrativeProgressPeriods($projects['active_second_quarter'])->pluck('status')->all())->toBe(['Reviewed', 'Upcoming'])
        ->and($quarters->narrativeProgressPeriods($projects['overdue_second_quarter'])->pluck('status')->all())->toBe(['Reviewed', 'Overdue'])
        ->and($projects['monitoring_tool_only']->progressReports()->sole()->isAwaitingSecretaryReview())->toBeTrue()
        ->and($projects['monitoring_tool_only']->narrativeReports()->count())->toBe(0);

    $revised = $projects['revised_quarterly_reports'];
    [$firstTool, $latestTool] = $revised->progressReports()->where('reporting_quarter', 2)->reorder('version_number')->get()->all();
    [$firstProgress, $latestProgress] = $revised->narrativeReports()->where('reporting_quarter', 2)->reorder('version_number')->get()->all();
    expect($firstTool->review_status)->toBe('revision_requested')
        ->and($firstProgress->review_status)->toBe('revision_requested')
        ->and($latestTool->supersedes_report_id)->toBe($firstTool->id)
        ->and($latestProgress->monitoring_report_id)->toBe($latestTool->id)
        ->and($latestTool->isLatestSubmittedVersion())->toBeTrue()
        ->and($latestProgress->isLatestSubmittedVersion())->toBeTrue()
        ->and($firstTool->official_pdf_path)->not->toBe($latestTool->official_pdf_path)
        ->and($firstProgress->official_pdf_path)->not->toBe($latestProgress->official_pdf_path);

    $terminalDue = $projects['terminal_due'];
    $ready = $projects['ready_to_close'];
    $signedReport = $ready->narrativeReports()->where('report_type', 'terminal')->sole();
    expect($quarters->canSubmitTerminal($terminalDue))->toBeTrue()
        ->and($quarters->missingTerminalReportPeriods($terminalDue))->toBe([])
        ->and($terminalDue->narrativeReports()->where('report_type', 'terminal')->exists())->toBeFalse()
        ->and($ready->isCompletedProject())->toBeFalse()
        ->and($signedReport->review_status)->toBe('reviewed')
        ->and(app(ProjectReportReviewService::class)->hasAvailableSignedCopy($signedReport))->toBeTrue()
        ->and($projects['completed_publication']->researchOutcome->labels)->toBe(['publication'])
        ->and($projects['completed_presentation']->researchOutcome->labels)->toContain('presentation');

    $this->actingAs($this->research_office_secretary)
        ->withSession([User::ACTIVE_WORKSPACE_SESSION_KEY => User::WORKSPACE_RESEARCH_SECRETARY])
        ->get(route('research_secretary.reports.index', ['type' => 'terminal']))->assertOk()
        ->assertSee($projects['terminal_secretary_queue']->title);
    Notification::assertNothingSent();
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

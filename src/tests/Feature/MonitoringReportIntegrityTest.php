<?php

use App\Http\Controllers\ProjectNarrativeReportController;
use App\Http\Requests\StoreSignedTerminalReportRequest;
use App\Models\ProjectMonitoringDraft;
use App\Models\ProjectNarrativeReport;
use App\Models\ProjectProgressReport;
use App\Models\TopicProposal;
use App\Models\User;
use App\Notifications\ProposalActivityNotification;
use App\Services\MonitoringEvidenceService;
use App\Services\MonitoringQuarterService;
use Illuminate\Http\UploadedFile;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    $this->travelTo(now()->setDate(2026, 10, 1));
    Storage::fake('local');
    Notification::fake();
    $this->withoutVite();
    foreach (['faculty_researcher', 'research_head'] as $role) {
        Role::findOrCreate($role, 'web');
    }
    $this->researcher = User::factory()->create();
    $this->researcher->assignRole('faculty_researcher');
    $this->head = User::factory()->create();
    $this->head->assignRole('research_head');
    $this->topic = TopicProposal::create([
        'title' => 'Report integrity project', 'user_id' => $this->researcher->id,
        'status' => 'approved', 'project_status' => 'ongoing', 'estimated_budget' => 50000,
        'estimated_duration_months' => 9, 'notice_to_proceed_issued_at' => '2026-01-01',
    ]);
    $this->monitoring = fn (array $changes = []): ProjectProgressReport => $this->topic->progressReports()->create([
        'submitted_by' => $this->researcher->id, 'reporting_date' => '2026-03-31',
        'reporting_year' => 2026, 'reporting_quarter' => 1, 'version_number' => 1,
        'period_start' => '2026-01-01', 'period_end' => '2026-03-31',
        'progress_percentage' => 25, 'accomplishments' => 'Initial observations',
        'work_plan' => [['source_work_plan_index' => 0, 'accomplished_percentage' => '25.00', 'evidence' => []]],
        'budget_utilization' => [], ...$changes,
    ]);
    $this->terminal = fn (array $changes = []): ProjectNarrativeReport => $this->topic->narrativeReports()->create([
        'submitted_by' => $this->researcher->id, 'report_type' => 'terminal', 'submission_date' => '2026-10-01',
        'researchers' => $this->researcher->name, 'implementation_start' => '2026-01-01',
        'implementation_end' => '2026-09-30', 'budget' => 50000, 'funding_agency' => 'University',
        'accomplishment_summary' => 'Completed', 'introduction' => 'Introduction', 'rationale' => 'Rationale',
        'objectives' => 'Objectives', 'methodology' => 'Methodology', 'results_discussion' => 'Results',
        'accomplishments' => [], 'photos' => [], ...$changes,
    ]);
});

test('cumulative monitoring uses the latest revision within the latest reporting quarter', function () {
    $version = $this->topic->versions()->create([
        'submitted_by' => $this->researcher->id, 'version_number' => 1, 'submission_type' => 'initial',
        'title' => $this->topic->title, 'file_path' => 'proposal.pdf', 'original_filename' => 'proposal.pdf',
        'mime_type' => 'application/pdf', 'file_size' => 100, 'checksum' => hash('sha256', 'proposal'),
    ]);
    $version->files()->create([
        'document_type' => 'work_plan', 'position' => 0, 'file_path' => 'work-plan.pdf',
        'original_filename' => 'work-plan.pdf', 'mime_type' => 'application/pdf', 'file_size' => 100,
        'checksum' => hash('sha256', 'plan'), 'source_data' => ['total_duration_months' => 9, 'entries' => [
            ['objective' => 'Collect data', 'activity' => 'Interviews', 'expected_output' => '20 interviews', 'months' => range(1, 9)],
        ]],
    ]);
    ($this->monitoring)(['version_number' => 2]);
    ($this->monitoring)([
        'reporting_date' => '2026-06-25', 'reporting_quarter' => 2, 'version_number' => 2,
        'period_start' => '2026-04-01', 'period_end' => '2026-06-30',
        'work_plan' => [['source_work_plan_index' => 0, 'accomplished_percentage' => '90.00', 'evidence' => []]],
    ]);
    ($this->monitoring)([
        'reporting_date' => '2026-06-30', 'reporting_quarter' => 2,
        'period_start' => '2026-04-01', 'period_end' => '2026-06-30',
        'work_plan' => [['source_work_plan_index' => 0, 'accomplished_percentage' => '75.00', 'evidence' => []]],
    ]);
    ($this->monitoring)([
        'reporting_date' => '2026-06-30', 'reporting_quarter' => 2, 'version_number' => 3,
        'period_start' => '2026-04-01', 'period_end' => '2026-06-30', 'submission_status' => 'prepared',
        'work_plan' => [['source_work_plan_index' => 0, 'accomplished_percentage' => '95.00', 'evidence' => []]],
    ]);

    $evidence = app(MonitoringEvidenceService::class);
    expect($evidence->previousRows($this->topic, '2026-09-30')['plan-0']['accomplished_percentage'])->toBe('90.00')
        ->and($evidence->overallProgress($this->topic, '2026-09-30', [['source_work_plan_index' => 1, 'accomplished_percentage' => '10.00', 'evidence' => []]]))->toBe(100);
});

test('discarding a prepared monitoring tool restores its text and evidence as a private draft', function (bool $revision) {
    $source = $revision ? ($this->monitoring)(['review_status' => 'revision_requested']) : null;
    $proofPath = 'progress-reports/'.$this->topic->id.'/evidence/interview.pdf';
    Storage::disk('local')->put($proofPath, 'evidence');
    $report = ($this->monitoring)([
        'submission_status' => 'prepared', 'supersedes_report_id' => $source?->id,
        'version_number' => $revision ? 2 : 1,
        'tracking_number' => 'Restore this tracking number', 'official_pdf_path' => 'prepared.pdf',
        'work_plan' => [['activity' => 'Interviews', 'actual_accomplishment' => 'Saved fieldwork', 'evidence' => [['path' => $proofPath]]]],
    ]);
    Storage::disk('local')->put('prepared.pdf', 'pdf');
    $this->actingAs($this->researcher)->delete(route('project-progress.discard-prepared', [$this->topic, $report]))
        ->assertRedirect()->assertSessionHasNoErrors();

    expect(ProjectMonitoringDraft::count())->toBe(1);
    $draft = ProjectMonitoringDraft::sole();
    expect($draft->source_report_id)->toBe($source?->id)->and($draft->source_key)->toBe($source ? 'revision:'.$source->id : 'new')
        ->and($draft->source_data['tracking_number'])->toBe('Restore this tracking number')
        ->and($draft->source_data['reporting_date'])->toBe('2026-03-31')
        ->and($draft->source_data['work_plan'])->toEqual($report->work_plan);
    Storage::disk('local')->assertExists($proofPath);
    Storage::disk('local')->assertMissing('prepared.pdf');
    $this->assertModelMissing($report);
})->with([false, true]);

test('discarding monitoring cannot overwrite an existing private draft', function () {
    $report = ($this->monitoring)(['submission_status' => 'prepared', 'official_pdf_path' => 'prepared.pdf']);
    Storage::disk('local')->put('prepared.pdf', 'pdf');
    $draft = ProjectMonitoringDraft::create([
        'topic_id' => $this->topic->id, 'user_id' => $this->researcher->id, 'source_key' => 'new',
        'lock_version' => 4, 'source_data' => ['tracking_number' => 'Newer private work'],
    ]);
    $this->actingAs($this->researcher)->delete(route('project-progress.discard-prepared', [$this->topic, $report]))
        ->assertSessionHasErrors('preparation');
    $this->assertModelExists($report);
    expect($draft->fresh()->source_data['tracking_number'])->toBe('Newer private work');
    Storage::disk('local')->assertExists('prepared.pdf');
});

test('terminal reviews reject historical reports and retain signed copies', function () {
    $report = ($this->terminal)(['review_status' => 'reviewed',
        'terminal_data' => ['signed_copy' => ['path' => 'signed.pdf', 'original_filename' => 'signed.pdf']]]);
    Storage::disk('local')->put('signed.pdf', 'signed');
    ($this->terminal)();
    $this->actingAs($this->head)->withSession([User::ACTIVE_WORKSPACE_SESSION_KEY => User::WORKSPACE_RESEARCH_HEAD])
        ->patch(route('research_head.narrative-progress-reports.review', $report), [
            'review_status' => 'revision_requested', 'research_head_remarks' => 'Stale request',
        ])->assertSessionHasErrors('review');

    expect($report->fresh()->review_status)->toBe('reviewed')
        ->and($report->fresh()->hasSignedCopy())->toBeTrue();
    Storage::disk('local')->assertExists('signed.pdf');
    Notification::assertNothingSent();
});

test('terminal signing rechecks project and latest report after authorization', function (string $change) {
    $report = ($this->terminal)(['review_status' => 'reviewed']);
    $this->actingAs($this->head)->withSession([User::ACTIVE_WORKSPACE_SESSION_KEY => User::WORKSPACE_RESEARCH_HEAD]);
    $request = StoreSignedTerminalReportRequest::create('/', 'POST', files: [
        'signed_report' => UploadedFile::fake()->create('signed.pdf', 1, 'application/pdf'),
    ]);
    $request->setUserResolver(fn (): User => $this->head);
    $route = new Route(['POST'], '/', fn () => null);
    $route->bind($request);
    $route->setParameter('report', $report);
    $request->setRouteResolver(fn (): Route => $route);
    expect($request->authorize())->toBeTrue();
    if ($change === 'completed') {
        $this->topic->update(['project_status' => 'completed']);
    } else {
        ($this->terminal)();
    }
    $rejected = false;
    try {
        app(ProjectNarrativeReportController::class)->storeSignedCopy($request, $report);
    } catch (ValidationException $exception) {
        $rejected = array_key_exists('signed_report', $exception->errors());
    }

    expect($rejected)->toBeTrue()->and($report->fresh()->hasSignedCopy())->toBeFalse()
        ->and(Storage::disk('local')->allFiles('narrative-progress-reports/'.$this->topic->id.'/signed'))->toBe([]);
})->with(['completed', 'newer terminal']);

test('terminal review notifications identify the Terminal Report', function (string $status) {
    $report = ($this->terminal)();
    $this->actingAs($this->head)->withSession([User::ACTIVE_WORKSPACE_SESSION_KEY => User::WORKSPACE_RESEARCH_HEAD])
        ->patch(route('research_head.narrative-progress-reports.review', $report), [
            'review_status' => $status, 'research_head_remarks' => 'Final report assessment',
        ])->assertSessionHasNoErrors();

    Notification::assertSentTo($this->researcher, ProposalActivityNotification::class,
        fn (ProposalActivityNotification $notification): bool => $notification->title === ($status === 'reviewed' ? 'Terminal Report reviewed' : 'Terminal Report corrections requested')
            && str_contains($notification->message, 'terminal report')
            && $notification->workspace === User::WORKSPACE_FACULTY_RESEARCHER);
})->with(['reviewed', 'revision_requested']);

test('completion waits for the Research Head to accept every current quarterly report', function (string $kind) {
    $pending = null;
    foreach (app(MonitoringQuarterService::class)->projectPeriods($this->topic) as $period) {
        $attributes = [
            'reporting_date' => $period['end']->toDateString(), 'reporting_quarter' => $period['quarter'],
            'period_start' => $period['start']->toDateString(), 'period_end' => $period['end']->toDateString(),
            'review_status' => 'reviewed', 'review_stage' => 'head',
        ];
        $monitoring = ($this->monitoring)([...$attributes, 'reporting_year' => $period['year'], 'progress_percentage' => 100]);
        $narrative = ($this->terminal)([...$attributes, 'report_type' => 'progress']);
        $pending ??= $kind === 'monitoring' ? $monitoring : $narrative;
    }
    $pending->update(['review_status' => 'pending']);
    ($this->terminal)(['review_status' => 'reviewed', 'terminal_data' => ['signed_copy' => [
        'path' => 'signed.pdf', 'original_filename' => 'signed.pdf', 'uploaded_at' => now()->toIso8601String(),
        'uploaded_by' => $this->head->id, 'mime_type' => 'application/pdf', 'size' => 6, 'checksum' => hash('sha256', 'signed'),
    ]]]);
    Storage::disk('local')->put('signed.pdf', 'signed');
    $this->actingAs($this->head)->withSession([User::ACTIVE_WORKSPACE_SESSION_KEY => User::WORKSPACE_RESEARCH_HEAD]);
    $this->patch(route('research_head.projects.update-status', $this->topic), ['project_status' => 'completed', 'completion_confirmed' => true])
        ->assertSessionHasErrors('project_status');
    expect($this->topic->fresh()->project_status)->toBe('ongoing');
    $this->get(route('topics.show', $this->topic))->assertSuccessful()->assertSee('accepted quarterly reports');

    $pending->update(['review_status' => 'reviewed']);
    $this->patch(route('research_head.projects.update-status', $this->topic), ['project_status' => 'completed', 'completion_confirmed' => true])
        ->assertSessionHasNoErrors();
    expect($this->topic->fresh()->project_status)->toBe('completed');
})->with(['monitoring', 'progress']);

test('only the current ongoing reviewed Terminal Report offers corrections', function () {
    $old = ($this->terminal)(['review_status' => 'reviewed']);
    $report = ($this->terminal)(['review_status' => 'reviewed']);
    $this->actingAs($this->head)->withSession([User::ACTIVE_WORKSPACE_SESSION_KEY => User::WORKSPACE_RESEARCH_HEAD]);
    $response = $this->get(route('project-narrative-reports.show', $report))->assertSuccessful();
    expect($response->viewData('canReview'))->toBeTrue();
    $document = new DOMDocument;
    @$document->loadHTML($response->getContent());
    $xpath = new DOMXPath($document);
    expect($xpath->query('//*[@data-narrative-report-review]//input[@name="review_status" and @value="revision_requested" and @checked]')->length)->toBe(1)
        ->and($xpath->query('//*[@data-narrative-report-review]//input[@name="review_status" and @value="reviewed"]')->length)->toBe(0);
    $this->get(route('project-narrative-reports.show', $old))->assertSuccessful()->assertDontSee('data-narrative-report-review', false);
    $prepared = $report->replicate()->fill(['submission_status' => 'prepared']);
    $prepared->save();
    $this->get(route('project-narrative-reports.show', $report))->assertSuccessful()->assertDontSee('data-narrative-report-review', false)->assertDontSee('name="signed_report"', false);
    $prepared->delete();
    $this->topic->update(['project_status' => 'completed']);
    $this->get(route('project-narrative-reports.show', $report))->assertSuccessful()->assertDontSee('data-narrative-report-review', false);
});

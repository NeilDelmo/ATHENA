<?php

use App\Models\ProjectNarrativeReport;
use App\Models\ProjectProgressReport;
use App\Models\ResearchCall;
use App\Models\TopicProposal;
use App\Models\User;
use App\Notifications\ProposalActivityNotification;
use App\Services\MonitoringQuarterService;
use App\Services\ProjectDocumentLibrary;
use App\Services\ResearchHeadReportQueue;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    Notification::fake();
    Storage::fake('local');
    $this->withoutVite();
    $this->travelTo(CarbonImmutable::parse('2026-04-15'));
    foreach (['faculty_researcher', 'research_head', 'research_secretary'] as $role) {
        Role::firstOrCreate(['name' => $role]);
    }
    $this->author = User::factory()->create(['college' => 'College of Engineering']);
    $this->author->assignRole('faculty_researcher');
    $this->officeSecretary = User::factory()->create(['college' => $this->author->college]);
    $this->officeSecretary->assignRole('research_secretary');
    $this->otherSecretary = User::factory()->create(['college' => 'College of Education']);
    $this->otherSecretary->assignRole('research_secretary');
    $this->head = User::factory()->create();
    $this->head->assignRole('research_head');
    $call = ResearchCall::create([
        'title' => 'Secretary Review Call', 'academic_year' => '2026-2027',
        'opens_at' => now()->subMonth(), 'closes_at' => now()->addMonth(), 'status' => 'open',
    ]);
    $this->topic = TopicProposal::create([
        'user_id' => $this->author->id, 'research_call_id' => $call->id,
        'title' => 'Secretary Review Research', 'estimated_budget' => 50000,
        'estimated_duration_months' => 12, 'status' => 'approved', 'project_status' => 'ongoing',
        'notice_to_proceed_issued_by' => $this->head->id, 'notice_to_proceed_issued_at' => '2026-01-01',
    ]);
    $this->makeReport = function (string $type, array $overrides = []): ProjectProgressReport|ProjectNarrativeReport {
        $pdf = "%PDF-1.7\nImmutable prepared report";
        $path = 'secretary-review/'.$type.'/'.fake()->uuid().'.pdf';
        Storage::disk('local')->put($path, $pdf);
        $common = [
            'topic_id' => $this->topic->id, 'submitted_by' => $this->author->id,
            'reporting_date' => '2026-03-31', 'reporting_quarter' => 1,
            'period_start' => '2026-01-01', 'period_end' => '2026-03-31', 'version_number' => 1,
            'submission_status' => 'submitted', 'submitted_at' => now(),
            'review_status' => 'pending', 'review_stage' => 'secretary',
            'official_pdf_path' => $path, 'official_pdf_filename' => $type.'.pdf',
            'official_pdf_checksum' => hash('sha256', $pdf), 'official_pdf_size' => strlen($pdf),
        ];

        return $type === 'monitoring'
            ? ProjectProgressReport::create(array_replace([
                ...$common, 'reporting_year' => 2026, 'progress_percentage' => 0,
                'accomplishments' => 'Activities scheduled.', 'work_plan' => [], 'budget_utilization' => [],
            ], $overrides))
            : ProjectNarrativeReport::create(array_replace([
                ...$common, 'report_type' => 'progress', 'submission_date' => '2026-04-15',
                'researchers' => $this->author->name, 'implementation_start' => '2026-01-01',
                'implementation_end' => '2026-12-31', 'budget' => 50000, 'funding_agency' => 'University',
                'accomplishment_summary' => 'Activities scheduled.', 'accomplishments' => [],
                'introduction' => 'Background.', 'rationale' => 'Research need.', 'objectives' => 'Assess outcomes.',
                'methodology' => 'Field observations.', 'results_discussion' => 'Implementation ongoing.', 'photos' => [],
            ], $overrides));
    };
    $this->reportRoutes = fn (string $type): array => $type === 'monitoring'
        ? ['submit' => 'project-progress.submit-prepared', 'secretary' => 'research_secretary.monitoring-reports.review',
            'head' => 'research_head.progress-reports.review', 'pdf' => 'project-progress.monitoring-tool.view', 'queue_type' => 'quarterly']
        : ['submit' => 'project-narrative-reports.submit-prepared', 'secretary' => 'research_secretary.narrative-reports.review',
            'head' => 'research_head.narrative-progress-reports.review', 'pdf' => 'project-narrative-reports.view', 'queue_type' => 'progress'];
});

dataset('secretary reviewed report types', ['Monitoring Tool' => 'monitoring', 'Progress Report' => 'progress']);

test('a changed prepared PDF cannot be submitted as the original report', function (string $type, string $change) {
    $report = ($this->makeReport)($type, ['submission_status' => 'prepared', 'prepared_at' => now(), 'submitted_at' => null]);
    Storage::disk('local')->put($report->official_pdf_path, $change === 'empty' ? '' : '%PDF-1.4 replaced report');
    $routes = ($this->reportRoutes)($type);
    $this->actingAs($this->author)->withSession([User::ACTIVE_WORKSPACE_SESSION_KEY => User::WORKSPACE_FACULTY_RESEARCHER])
        ->post(route($routes['submit'], [$this->topic, $report]))
        ->assertSessionHasErrors('preparation', null, $type === 'monitoring' ? 'default' : 'narrativeProgress');

    expect($report->fresh()->isPrepared())->toBeTrue();
    Notification::assertNothingSent();
})->with('secretary reviewed report types')->with(['replaced', 'empty']);

test('a changed submitted PDF cannot be forwarded to the Research Head', function (string $type, string $change) {
    $report = ($this->makeReport)($type);
    Storage::disk('local')->put($report->official_pdf_path, $change === 'empty' ? '' : '%PDF-1.4 replaced report');
    $routes = ($this->reportRoutes)($type);
    $this->actingAs($this->officeSecretary)->withSession([User::ACTIVE_WORKSPACE_SESSION_KEY => User::WORKSPACE_RESEARCH_SECRETARY])
        ->patch(route($routes['secretary'], $report), ['decision' => 'forward'])
        ->assertSessionHasErrors('review');

    expect($report->fresh()->review_stage)->toBe('secretary')
        ->and($report->fresh()->forwarded_at)->toBeNull();
    Notification::assertNothingSent();
})->with('secretary reviewed report types')->with(['replaced', 'empty']);

test('the Research Head cannot accept a changed report PDF', function (string $type, string $change) {
    $report = ($this->makeReport)($type, ['review_stage' => 'head', 'forwarded_at' => now()]);
    if ($type === 'terminal') {
        $report->update(['report_type' => 'terminal', 'review_stage' => null]);
    }
    Storage::disk('local')->put($report->official_pdf_path, $change === 'empty' ? '' : '%PDF-1.4 replaced report');
    $routes = ($this->reportRoutes)($type);
    $this->actingAs($this->head)->withSession([User::ACTIVE_WORKSPACE_SESSION_KEY => User::WORKSPACE_RESEARCH_HEAD])
        ->patch(route($routes['head'], $report), ['review_status' => 'reviewed'])
        ->assertSessionHasErrors('review');

    expect($report->fresh()->review_status)->toBe('pending')
        ->and($report->fresh()->reviewed_at)->toBeNull();
    Notification::assertNothingSent();
})->with(['monitoring', 'progress', 'terminal'])->with(['replaced', 'empty']);

test('submitted implementation reports reach only the college Research Office Secretary first', function (string $type) {
    $report = ($this->makeReport)($type, ['submission_status' => 'prepared', 'review_stage' => null, 'submitted_at' => null]);
    $routes = ($this->reportRoutes)($type);
    $this->actingAs($this->author)->withSession([User::ACTIVE_WORKSPACE_SESSION_KEY => User::WORKSPACE_FACULTY_RESEARCHER])
        ->post(route($routes['submit'], [$this->topic, $report]))->assertRedirect()->assertSessionHasNoErrors();

    expect($report->fresh()->isSubmitted())->toBeTrue()
        ->and($report->fresh()->review_stage)->toBe('secretary')
        ->and($report->fresh()->isAvailableToResearchHead())->toBeFalse()
        ->and(app(ResearchHeadReportQueue::class)->query(type: $routes['queue_type'])->count())->toBe(0);
    Notification::assertSentTo($this->officeSecretary, ProposalActivityNotification::class,
        fn (ProposalActivityNotification $notification): bool => $notification->workspace === User::WORKSPACE_RESEARCH_SECRETARY);
    Notification::assertNotSentTo([$this->head, $this->otherSecretary], ProposalActivityNotification::class);

    $this->actingAs($this->head)->withSession([User::ACTIVE_WORKSPACE_SESSION_KEY => User::WORKSPACE_RESEARCH_HEAD])
        ->get(route($routes['pdf'], $report))->assertForbidden();
    $this->patch(route($routes['head'], $report), ['review_status' => 'reviewed'])->assertClientError();
    expect($report->fresh()->review_status)->toBe('pending')
        ->and(app(ProjectDocumentLibrary::class)->build($this->topic->fresh(), $this->head)['documents']->pluck('view_url')->all())
        ->not->toContain(route($routes['pdf'], $report));
    $this->get(route('topics.show', $this->topic))->assertOk()->assertDontSee(route($routes['pdf'], $report), false);
})->with('secretary reviewed report types');

test('the office forwards the same report PDF before the Head can accept it', function (string $type) {
    $report = ($this->makeReport)($type);
    $routes = ($this->reportRoutes)($type);
    $original = $report->only(['official_pdf_path', 'official_pdf_checksum', 'official_pdf_size', 'version_number']);
    $this->actingAs($this->officeSecretary)->withSession([User::ACTIVE_WORKSPACE_SESSION_KEY => User::WORKSPACE_RESEARCH_SECRETARY])
        ->get(route($routes['pdf'], $report))->assertOk();
    $this->patch(route($routes['secretary'], $report), ['decision' => 'forward', 'secretary_remarks' => 'Checked against supporting documents.'])
        ->assertRedirect()->assertSessionHasNoErrors();

    $report->refresh();
    expect($report->only(array_keys($original)))->toBe($original)
        ->and($report->review_stage)->toBe('head')->and($report->review_status)->toBe('pending')
        ->and($report->secretary_reviewed_by)->toBe($this->officeSecretary->id)
        ->and($report->secretary_reviewed_at)->not->toBeNull()->and($report->forwarded_at)->not->toBeNull()
        ->and($report->secretaryReviewer->is($this->officeSecretary))->toBeTrue()
        ->and(app(ResearchHeadReportQueue::class)->query(type: $routes['queue_type'])->pluck('id')->all())->toBe([$report->id]);
    Notification::assertSentTo($this->head, ProposalActivityNotification::class);
    expect(hash('sha256', Storage::disk('local')->get($report->official_pdf_path)))->toBe($original['official_pdf_checksum']);
    $this->patch(route($routes['secretary'], $report), ['decision' => 'forward'])->assertRedirect()->assertSessionHasErrors('review');

    $this->actingAs($this->head)->withSession([User::ACTIVE_WORKSPACE_SESSION_KEY => User::WORKSPACE_RESEARCH_HEAD])
        ->get(route($routes['pdf'], $report))->assertOk();
    $this->patch(route($routes['head'], $report), ['review_status' => 'reviewed', 'research_head_remarks' => 'Accepted.'])
        ->assertRedirect()->assertSessionHasNoErrors();
    expect($report->fresh()->review_status)->toBe('reviewed')->and($report->fresh()->reviewed_by)->toBe($this->head->id);
})->with('secretary reviewed report types');

test('Head concerns return to the office before author corrections can begin', function (string $type) {
    $report = ($this->makeReport)($type, ['review_stage' => 'head', 'forwarded_at' => now()]);
    $routes = ($this->reportRoutes)($type);
    $this->actingAs($this->head)->withSession([User::ACTIVE_WORKSPACE_SESSION_KEY => User::WORKSPACE_RESEARCH_HEAD])
        ->patch(route($routes['head'], $report), ['review_status' => 'revision_requested', 'research_head_remarks' => 'Confirm the supporting evidence.'])
        ->assertRedirect()->assertSessionHasNoErrors();

    expect($report->fresh()->review_stage)->toBe('secretary')->and($report->fresh()->review_status)->toBe('pending')
        ->and($report->fresh()->research_head_remarks)->toBe('Confirm the supporting evidence.')
        ->and($report->fresh()->isAwaitingSecretaryReview())->toBeTrue()
        ->and(app(ResearchHeadReportQueue::class)->query(type: $routes['queue_type'])->count())->toBe(0);
    Notification::assertSentTo($this->officeSecretary, ProposalActivityNotification::class);
    Notification::assertNotSentTo([$this->author, $this->otherSecretary], ProposalActivityNotification::class);
    if ($type === 'monitoring') {
        $this->actingAs($this->author)->withSession([User::ACTIVE_WORKSPACE_SESSION_KEY => User::WORKSPACE_FACULTY_RESEARCHER])
            ->get(route('project-progress.create', ['topic' => $this->topic, 'revise_monitoring_report' => $report->id]))->assertNotFound();
    } else {
        expect(app(MonitoringQuarterService::class)->narrativeProgressPeriods($this->topic)->first()['drafting_date'])->toBeNull();
    }

    Notification::fake();
    $this->actingAs($this->officeSecretary)->withSession([User::ACTIVE_WORKSPACE_SESSION_KEY => User::WORKSPACE_RESEARCH_SECRETARY])
        ->patch(route($routes['secretary'], $report), ['decision' => 'revision_requested'])->assertSessionHasErrors('secretary_remarks');
    expect($report->fresh()->review_status)->toBe('pending');
    $this->patch(route($routes['secretary'], $report), ['decision' => 'revision_requested', 'secretary_remarks' => 'Attach the missing survey evidence.'])
        ->assertRedirect()->assertSessionHasNoErrors();
    expect($report->fresh()->review_status)->toBe('revision_requested')
        ->and($report->fresh()->secretary_remarks)->toBe('Attach the missing survey evidence.');
    Notification::assertSentTo($this->author, ProposalActivityNotification::class);
    Notification::assertNotSentTo($this->head, ProposalActivityNotification::class);
    if ($type === 'monitoring') {
        $this->actingAs($this->author)->withSession([User::ACTIVE_WORKSPACE_SESSION_KEY => User::WORKSPACE_FACULTY_RESEARCHER])
            ->get(route('project-progress.create', ['topic' => $this->topic, 'revise_monitoring_report' => $report->id]))->assertOk();
    } else {
        expect(app(MonitoringQuarterService::class)->narrativeProgressPeriods($this->topic)->first()['drafting_date'])->not->toBeNull();
    }

    $replacement = ($this->makeReport)($type, [
        'submission_status' => 'prepared', 'submitted_at' => null, 'version_number' => 2,
        ...($type === 'monitoring' ? ['supersedes_report_id' => $report->id] : []),
    ]);
    Notification::fake();
    $this->actingAs($this->author)->withSession([User::ACTIVE_WORKSPACE_SESSION_KEY => User::WORKSPACE_FACULTY_RESEARCHER])
        ->post(route($routes['submit'], [$this->topic, $replacement]))->assertRedirect()->assertSessionHasNoErrors();
    expect($replacement->fresh()->review_stage)->toBe('secretary')->and($replacement->fresh()->version_number)->toBe(2);
    Notification::assertSentTo($this->officeSecretary, ProposalActivityNotification::class);
    Notification::assertNotSentTo($this->head, ProposalActivityNotification::class);
})->with('secretary reviewed report types');

test('office report review permissions do not come from project secretary assignment', function (string $type) {
    $report = ($this->makeReport)($type);
    $routes = ($this->reportRoutes)($type);
    $teamSecretary = User::factory()->create(['college' => $this->author->college]);
    $teamSecretary->assignRole('faculty_researcher');
    $this->topic->collaborators()->create([
        'user_id' => $teamSecretary->id, 'name' => $teamSecretary->name, 'email' => $teamSecretary->email,
        'accepted_at' => now(), 'project_role' => 'secretary',
    ]);
    $this->topic->update(['research_secretary_id' => $teamSecretary->id]);
    $this->actingAs($teamSecretary)->withSession([User::ACTIVE_WORKSPACE_SESSION_KEY => User::WORKSPACE_FACULTY_RESEARCHER]);
    expect($this->topic->canReviewImplementationReports($teamSecretary))->toBeFalse()
        ->and(TopicProposal::forImplementationReportReview($teamSecretary)->exists())->toBeFalse();
    $this->patch(route($routes['secretary'], $report), ['decision' => 'forward'])->assertForbidden();

    $this->actingAs($this->otherSecretary)->withSession([User::ACTIVE_WORKSPACE_SESSION_KEY => User::WORKSPACE_RESEARCH_SECRETARY]);
    expect($this->topic->canReviewImplementationReports($this->otherSecretary))->toBeFalse()
        ->and(TopicProposal::forImplementationReportReview($this->otherSecretary)->exists())->toBeFalse();
    $this->get(route($routes['pdf'], $report))->assertForbidden();
    $this->patch(route($routes['secretary'], $report), ['decision' => 'forward'])->assertForbidden();

    $this->officeSecretary->assignRole('faculty_researcher');
    $this->actingAs($this->officeSecretary)->withSession([User::ACTIVE_WORKSPACE_SESSION_KEY => User::WORKSPACE_FACULTY_RESEARCHER]);
    expect($this->topic->canReviewImplementationReports($this->officeSecretary))->toBeFalse();
    $this->patch(route($routes['secretary'], $report), ['decision' => 'forward'])->assertForbidden();
    $this->officeSecretary->update(['college' => null]);
    $this->withSession([User::ACTIVE_WORKSPACE_SESSION_KEY => User::WORKSPACE_RESEARCH_SECRETARY]);
    expect($this->topic->canReviewImplementationReports($this->officeSecretary))->toBeFalse();
    $this->patch(route($routes['secretary'], $report), ['decision' => 'forward'])->assertForbidden();
    expect($report->fresh()->review_stage)->toBe('secretary');
    Notification::assertNothingSent();
})->with('secretary reviewed report types');

test('the secretary cannot review prepared superseded closed or already returned reports', function (string $type, string $state) {
    $report = ($this->makeReport)($type, match ($state) {
        'prepared' => ['submission_status' => 'prepared'],
        'returned' => ['review_status' => 'revision_requested'],
        default => [],
    });
    if ($state === 'superseded') {
        ($this->makeReport)($type, ['version_number' => 2, ...($type === 'monitoring' ? ['supersedes_report_id' => $report->id] : [])]);
    }
    if ($state === 'closed') {
        $this->topic->update(['project_status' => 'completed']);
    }
    if ($state === 'without notice') {
        $this->topic->update(['notice_to_proceed_issued_at' => null, 'notice_to_proceed_issued_by' => null]);
    }
    $original = $report->fresh()->getAttributes();
    $response = $this->actingAs($this->officeSecretary)->withSession([User::ACTIVE_WORKSPACE_SESSION_KEY => User::WORKSPACE_RESEARCH_SECRETARY])
        ->patch(route(($this->reportRoutes)($type)['secretary'], $report), ['decision' => 'forward']);
    if (in_array($state, ['superseded', 'returned'], true)) {
        $response->assertRedirect()->assertSessionHasErrors('review');
    } else {
        $response->assertClientError();
    }
    expect($report->fresh()->getAttributes())->toBe($original);
    Notification::assertNothingSent();
})->with('secretary reviewed report types')->with(['prepared', 'superseded', 'closed', 'returned', 'without notice']);

test('legacy submitted reports remain accessible to the Head without a new review stage', function (string $type) {
    $report = ($this->makeReport)($type, ['review_stage' => null]);
    $routes = ($this->reportRoutes)($type);
    expect($report->isAvailableToResearchHead())->toBeTrue()
        ->and(app(ResearchHeadReportQueue::class)->query(type: $routes['queue_type'])->pluck('id')->all())->toBe([$report->id]);
    $this->actingAs($this->head)->withSession([User::ACTIVE_WORKSPACE_SESSION_KEY => User::WORKSPACE_RESEARCH_HEAD])
        ->get(route($routes['pdf'], $report))->assertOk();
    $this->patch(route($routes['head'], $report), ['review_status' => 'reviewed'])->assertRedirect()->assertSessionHasNoErrors();
    expect($report->fresh()->review_status)->toBe('reviewed');
})->with('secretary reviewed report types');

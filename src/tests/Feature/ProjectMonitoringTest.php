<?php

use App\Contracts\DocumentPdfConverter;
use App\Models\ProjectMonitoringDraft;
use App\Models\ProjectNarrativeReport;
use App\Models\ProjectProgressReport;
use App\Models\ProposalVersionFile;
use App\Models\ResearchCall;
use App\Models\TopicProposal;
use App\Models\User;
use App\Notifications\ProposalActivityNotification;
use App\Services\ApprovedWorkPlanMonitoringService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    Notification::fake();
    Storage::fake('local');
    $this->pdfConverter = new class implements DocumentPdfConverter
    {
        public string $sourceDocument = '';

        public int $conversionCount = 0;

        public function convertDocx(string $contents): string
        {
            $this->conversionCount++;
            $this->sourceDocument = $contents;

            return "%PDF-1.7\nGenerated monitoring tool PDF";
        }

        public function convertXlsx(string $contents): string
        {
            return "%PDF-1.7\nGenerated spreadsheet PDF";
        }
    };
    app()->instance(DocumentPdfConverter::class, $this->pdfConverter);

    foreach (['faculty_researcher', 'research_head', 'research_secretary'] as $role) {
        Role::firstOrCreate(['name' => $role]);
    }

    $this->researcher = User::factory()->create();
    $this->researcher->assignRole('faculty_researcher');
    $this->head = User::factory()->create();
    $this->head->assignRole('research_head');
    $this->secretary = User::factory()->create([
        'name' => 'Marina Budget Secretary',
        'email' => 'marina.secretary@g.batstate-u.edu.ph',
        'avatar' => 'https://example.com/marina-secretary.jpg',
    ]);
    $this->secretary->assignRole('research_secretary');
    $call = ResearchCall::create([
        'title' => 'Monitoring Test Call',
        'academic_year' => '2026-2027',
        'opens_at' => now()->subMonth(),
        'closes_at' => now()->addMonth(),
        'status' => 'open',
    ]);
    $this->topic = TopicProposal::create([
        'user_id' => $this->researcher->id,
        'research_call_id' => $call->id,
        'title' => 'Approved Community Research',
        'estimated_budget' => 50000,
        'estimated_duration_months' => 12,
        'status' => 'approved',
        'project_status' => 'ongoing',
        'notice_to_proceed_issued_by' => $this->head->id,
        'notice_to_proceed_issued_at' => now()->subMonths(3),
    ]);

    $this->monitoringPayload = fn (array $overrides = []): array => array_replace([
        'reporting_date' => now()->subDay()->toDateString(),
        'tracking_number' => 'REC-2026-001',
        'work_plan' => [
            [
                'activity' => 'Conduct field interviews',
                'percent_weight' => 50,
                'physical_target' => 'Interview 20 participants',
                'target_completion_date' => now()->addMonth()->toDateString(),
                'actual_accomplishment' => 'Interviewed 12 participants',
                'accomplished_percentage' => 15,
                'findings' => 'Two participants requested a new schedule.',
            ],
            [
                'activity' => 'Encode research data',
                'percent_weight' => 50,
                'physical_target' => 'Encode all completed interviews',
                'target_completion_date' => now()->addMonths(2)->toDateString(),
                'actual_accomplishment' => 'Encoded the first interview batch',
                'accomplished_percentage' => 10,
                'findings' => '',
            ],
        ],
        'budget_utilization' => [
            ['type' => 'Purchase Request', 'details' => 'PR-2026-014, supplies', 'amount_requested' => 10000, 'actual_amount' => 8000, 'remarks' => 'Delivered'],
            ['type' => 'Cash Advance', 'details' => '', 'amount_requested' => 0, 'actual_amount' => 0, 'remarks' => ''],
            ['type' => 'Request of Payment', 'details' => 'Field transport', 'amount_requested' => 5000, 'actual_amount' => 4500, 'remarks' => ''],
        ],
        'prepared_by_date_signed' => now()->toDateString(),
    ], $overrides);

    $this->attachApprovedWorkPlan = function (array $entries, int $durationMonths): void {
        $version = $this->topic->versions()->create([
            'submitted_by' => $this->researcher->id,
            'version_number' => 1,
            'submission_type' => 'initial',
            'file_path' => 'approved-proposal.pdf',
            'original_filename' => 'approved-proposal.pdf',
            'mime_type' => 'application/pdf',
            'file_size' => 100,
            'checksum' => hash('sha256', 'approved-proposal'),
            'title' => $this->topic->title,
            'estimated_budget' => $this->topic->estimated_budget,
            'estimated_duration_months' => $durationMonths,
        ]);
        $version->files()->create([
            'document_type' => ProposalVersionFile::TYPE_WORK_PLAN,
            'position' => 0,
            'file_path' => 'approved-work-plan.pdf',
            'original_filename' => 'approved-work-plan.pdf',
            'mime_type' => 'application/pdf',
            'file_size' => 100,
            'checksum' => hash('sha256', 'approved-work-plan'),
            'source_data' => [
                'total_duration_months' => $durationMonths,
                'entries' => $entries,
            ],
        ]);
    };
});

test('the project leader assigns an accepted group member as project secretary', function () {
    $this->withoutVite();
    $projectMember = User::factory()->create([
        'name' => 'Accepted Project Member',
        'email' => 'accepted.member@g.batstate-u.edu.ph',
        'avatar' => 'https://example.com/accepted-member.jpg',
    ]);
    $projectMember->assignRole('faculty_researcher');
    $this->topic->collaborators()->create([
        'user_id' => $projectMember->id,
        'name' => $projectMember->name,
        'email' => $projectMember->email,
        'accepted_at' => now(),
    ]);
    $outsider = User::factory()->create();
    $outsider->assignRole('faculty_researcher');

    $this->actingAs($this->head)
        ->withSession([User::ACTIVE_WORKSPACE_SESSION_KEY => User::WORKSPACE_RESEARCH_HEAD])
        ->get(route('research_head.projects.index'))
        ->assertOk()
        ->assertDontSee('data-project-secretary-picker', false)
        ->assertDontSee('Assign secretary');

    $this->actingAs($this->researcher)
        ->withSession([User::ACTIVE_WORKSPACE_SESSION_KEY => User::WORKSPACE_FACULTY_RESEARCHER])
        ->get(route('research.show', $this->topic))
        ->assertOk()
        ->assertSee('data-project-secretary-picker', false)
        ->assertSee($projectMember->name)
        ->assertSee($projectMember->email)
        ->assertSee('accepted-member.jpg');

    $this->actingAs($this->researcher)
        ->withSession([User::ACTIVE_WORKSPACE_SESSION_KEY => User::WORKSPACE_FACULTY_RESEARCHER])
        ->patch(route('project-secretary.assign', $this->topic), [
            'research_secretary_id' => $outsider->id,
        ])
        ->assertSessionHasErrors('research_secretary_id');

    expect($this->topic->fresh()->research_secretary_id)->toBeNull();

    $this->actingAs($this->researcher)
        ->withSession([User::ACTIVE_WORKSPACE_SESSION_KEY => User::WORKSPACE_FACULTY_RESEARCHER])
        ->patch(route('project-secretary.assign', $this->topic), [
            'research_secretary_id' => $projectMember->id,
        ])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    expect($this->topic->fresh()->research_secretary_id)->toBe($projectMember->id);

    $this->actingAs($this->researcher)
        ->post(route('project-progress.prepare', $this->topic), ($this->monitoringPayload)())
        ->assertSessionHasNoErrors();

    $report = ProjectProgressReport::query()->sole();

    $this->actingAs($projectMember)
        ->withSession([User::ACTIVE_WORKSPACE_SESSION_KEY => User::WORKSPACE_FACULTY_RESEARCHER])
        ->get(route('project-budget.edit', [$this->topic, $report]))
        ->assertOk()
        ->assertSee('Confirm budget utilization');

    $this->actingAs($projectMember)
        ->withSession([User::ACTIVE_WORKSPACE_SESSION_KEY => User::WORKSPACE_FACULTY_RESEARCHER])
        ->put(route('project-budget.update', [$this->topic, $report]), [
            'budget_utilization' => [
                ['type' => 'Purchase Request', 'details' => 'Laboratory supplies', 'amount_requested' => 12000, 'actual_amount' => 10000, 'remarks' => 'Delivered'],
                ['type' => 'Cash Advance', 'details' => '', 'amount_requested' => 0, 'actual_amount' => 0, 'remarks' => ''],
                ['type' => 'Request of Payment', 'details' => 'Field transport', 'amount_requested' => 5000, 'actual_amount' => 4500, 'remarks' => 'Completed'],
            ],
        ])
        ->assertRedirect(route('research.show', $this->topic).'#project-monitoring')
        ->assertSessionHasNoErrors();

    expect($report->fresh()->budget_prepared_by)->toBe($projectMember->id);
});

test('only the assigned project secretary can complete a prepared report budget before submission', function () {
    $this->topic->update(['research_secretary_id' => $this->secretary->id]);

    $this->actingAs($this->researcher)
        ->post(route('project-progress.prepare', $this->topic), ($this->monitoringPayload)())
        ->assertSessionHasNoErrors();

    $report = ProjectProgressReport::query()->sole();

    $this->actingAs($this->researcher)
        ->post(route('project-progress.submit-prepared', [$this->topic, $report]))
        ->assertSessionHasErrors('preparation');

    expect($report->fresh()->isPrepared())->toBeTrue();

    $this->actingAs($this->researcher)
        ->get(route('project-progress.create', ['topic' => $this->topic, 'reporting_date' => $report->reporting_date->toDateString()]))
        ->assertOk()
        ->assertSee('Waiting for '.$this->secretary->name)
        ->assertSee('disabled', false);

    $otherSecretary = User::factory()->create();
    $otherSecretary->assignRole('research_secretary');

    $this->actingAs($otherSecretary)
        ->withSession([User::ACTIVE_WORKSPACE_SESSION_KEY => User::WORKSPACE_RESEARCH_SECRETARY])
        ->get(route('research_secretary.projects.budget.edit', [$this->topic, $report]))
        ->assertNotFound();

    $this->actingAs($this->secretary)
        ->withSession([User::ACTIVE_WORKSPACE_SESSION_KEY => User::WORKSPACE_RESEARCH_SECRETARY])
        ->get(route('research_secretary.dashboard'))
        ->assertOk()
        ->assertSee($this->topic->title)
        ->assertSee('Complete budget');

    $this->actingAs($this->secretary)
        ->withSession([User::ACTIVE_WORKSPACE_SESSION_KEY => User::WORKSPACE_RESEARCH_SECRETARY])
        ->get(route('research_secretary.projects.budget.edit', [$this->topic, $report]))
        ->assertOk()
        ->assertSee('Confirm budget utilization')
        ->assertSee('budgetUtilizationForm', false);

    $this->actingAs($this->secretary)
        ->withSession([User::ACTIVE_WORKSPACE_SESSION_KEY => User::WORKSPACE_RESEARCH_SECRETARY])
        ->put(route('research_secretary.projects.budget.update', [$this->topic, $report]), [
            'budget_utilization' => [
                ['type' => 'Purchase Request', 'details' => 'Laboratory supplies', 'amount_requested' => 12000, 'actual_amount' => 10000, 'remarks' => 'Delivered'],
                ['type' => 'Cash Advance', 'details' => '', 'amount_requested' => 0, 'actual_amount' => 0, 'remarks' => ''],
                ['type' => 'Request of Payment', 'details' => 'Field transport', 'amount_requested' => 5000, 'actual_amount' => 4500, 'remarks' => 'Completed'],
            ],
        ])
        ->assertRedirect(route('research_secretary.dashboard'))
        ->assertSessionHasNoErrors();

    $report->refresh();
    expect($report->budget_prepared_by)->toBe($this->secretary->id)
        ->and($report->budget_prepared_at)->not->toBeNull()
        ->and($report->budget_utilization[0]['actual_amount'])->toBe(10000);

    $this->actingAs($this->researcher)
        ->withSession([User::ACTIVE_WORKSPACE_SESSION_KEY => User::WORKSPACE_FACULTY_RESEARCHER])
        ->post(route('project-progress.submit-prepared', [$this->topic, $report]))
        ->assertSessionHasNoErrors();

    expect($report->fresh()->isSubmitted())->toBeTrue();
});

test('project secretary budget confirmation keeps the approved project cap', function () {
    $this->topic->update(['research_secretary_id' => $this->secretary->id]);

    $this->actingAs($this->researcher)
        ->post(route('project-progress.prepare', $this->topic), ($this->monitoringPayload)())
        ->assertSessionHasNoErrors();

    $report = ProjectProgressReport::query()->sole();

    $this->actingAs($this->secretary)
        ->withSession([User::ACTIVE_WORKSPACE_SESSION_KEY => User::WORKSPACE_RESEARCH_SECRETARY])
        ->put(route('research_secretary.projects.budget.update', [$this->topic, $report]), [
            'budget_utilization' => [
                ['type' => 'Purchase Request', 'details' => 'Over budget', 'amount_requested' => 51000, 'actual_amount' => 51000, 'remarks' => ''],
                ['type' => 'Cash Advance', 'details' => '', 'amount_requested' => 0, 'actual_amount' => 0, 'remarks' => ''],
                ['type' => 'Request of Payment', 'details' => '', 'amount_requested' => 0, 'actual_amount' => 0, 'remarks' => ''],
            ],
        ])
        ->assertSessionHasErrors('budget_utilization');

    expect($report->fresh()->budget_prepared_at)->toBeNull();
});

test('report schedule blocks early submissions and opens terminal after project end', function () {
    $this->withoutVite();
    $this->topic->update(['notice_to_proceed_issued_at' => '2026-01-15', 'estimated_duration_months' => 6]);
    $this->travelTo(now()->setDate(2026, 4, 14)->startOfDay());
    $this->actingAs($this->researcher)->post(route('project-progress.prepare', $this->topic), ($this->monitoringPayload)(['reporting_date' => '2026-04-14']))
        ->assertSessionHasErrors('reporting_date');
    $this->get(route('project-narrative-reports.create', ['topic' => $this->topic, 'report_type' => 'terminal']))->assertForbidden();
    $this->get(route('topics.show', $this->topic))->assertOk()->assertSee('Quarterly reporting schedule')->assertSee('Not open yet');
    $this->travelTo(now()->setDate(2026, 4, 15)->startOfDay());
    $this->actingAs($this->researcher)->post(route('project-progress.prepare', $this->topic), ($this->monitoringPayload)(['reporting_date' => '2026-04-14']))->assertSessionHasNoErrors()->assertRedirect(route('project-progress.create', ['topic' => $this->topic, 'reporting_date' => '2026-04-14']));
    $report = ProjectProgressReport::where('topic_id', $this->topic->id)->firstOrFail();
    expect($report->period_start->toDateString())->toBe('2026-01-15')
        ->and($report->period_end->toDateString())->toBe('2026-04-14');
    $this->post(route('project-progress.submit-prepared', [$this->topic, $report]))->assertSessionHasNoErrors();
    expect($report->fresh()->isSubmitted())->toBeTrue();
    $this->travelTo(now()->setDate(2026, 7, 15)->startOfDay());
    $this->get(route('project-narrative-reports.create', ['topic' => $this->topic, 'report_type' => 'terminal']))->assertOk()->assertSee('Terminal report')->assertSee('value="terminal"', false);
});

test('a researcher prepares an official monitoring PDF before submitting it to the Research Head', function () {
    $this->actingAs($this->researcher)
        ->post(route('project-progress.store', $this->topic), ($this->monitoringPayload)())
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    $this->assertDatabaseHas('project_progress_reports', [
        'topic_id' => $this->topic->id,
        'progress_percentage' => 25,
        'attachment_path' => null,
        'submission_status' => ProjectProgressReport::SUBMISSION_STATUS_PREPARED,
    ]);
    $report = ProjectProgressReport::firstOrFail();
    Storage::disk('local')->assertExists($report->official_pdf_path);
    expect($report->official_pdf_filename)->toBe('approved-community-research-'.$report->quarter_label.'-v1-monitoring-tool.pdf')
        ->and($this->pdfConverter->conversionCount)->toBe(1);
    Notification::assertNothingSent();

    $this->actingAs($this->researcher)
        ->post(route('project-progress.submit-prepared', [$this->topic, $report]))
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    expect($report->fresh()->submission_status)->toBe(ProjectProgressReport::SUBMISSION_STATUS_SUBMITTED)
        ->and($this->pdfConverter->conversionCount)->toBe(1);
    Notification::assertSentTo(
        $this->head,
        ProposalActivityNotification::class,
        fn (ProposalActivityNotification $notification): bool => $notification->title === 'New '.$report->quarter_label.' Monitoring Tool Submitted'
            && $notification->url === route('topics.show', $this->topic).'#monitoring-tool-'.$report->id,
    );
});

test('the faculty project page opens the monitoring tool in a focused form page', function () {
    $this->actingAs($this->researcher)
        ->get(route('research.show', $this->topic))
        ->assertOk()
        ->assertSee('Quarterly reporting schedule')
        ->assertSee('Start report')
        ->assertSee(route('project-progress.create', $this->topic), false)
        ->assertDontSee('data-monitoring-tool-autosave-form', false);

    $this->actingAs($this->researcher)
        ->get(route('project-progress.create', $this->topic))
        ->assertOk()
        ->assertSee('Submit monitoring tool')
        ->assertSee('Prepare official PDF')
        ->assertSee('Preview monitoring tool')
        ->assertSee('Changes save automatically.')
        ->assertSee('Exit monitoring')
        ->assertSee('data-paper-cancel-exit', false)
        ->assertSee('data-monitoring-action-dock-fixed', false)
        ->assertSee('fixed inset-x-4 bottom-4', false)
        ->assertSee('data-proposal-autosave-status', false)
        ->assertSee('data-monitoring-tool-autosave-form', false)
        ->assertSee('x-ref="previewFrame"', false)
        ->assertSee('Approved Activities')
        ->assertSee('Add Activity')
        ->assertSee('Spending this quarter')
        ->assertSee('Purchase Request')
        ->assertSee('Request of Payment');
});

test('approved Work Plan objectives and activities flow into their monitoring reports', function () {
    ($this->attachApprovedWorkPlan)([
        [
            'objective' => 'Establish the community baseline',
            'activity' => 'Conduct baseline interviews',
            'expected_output' => 'Validated baseline dataset',
            'months' => [1, 2, 3],
        ],
        [
            'objective' => 'Develop the intervention',
            'activity' => 'Build and pilot the training kit',
            'expected_output' => 'Pilot-ready training kit',
            'months' => [4, 5, 6],
        ],
        [
            'objective' => 'Evaluate project outcomes',
            'activity' => 'Analyze results and prepare recommendations',
            'expected_output' => 'Outcome evaluation report',
            'months' => [7, 8, 9],
        ],
    ], 9);
    $this->topic->update([
        'notice_to_proceed_issued_at' => '2026-01-01',
        'estimated_duration_months' => 9,
        'notice_to_proceed_data' => [
            'approved_start_date' => '2026-01-01',
            'approved_end_date' => '2026-09-30',
            'approved_duration_months' => 9,
        ],
    ]);
    $this->travelTo('2026-10-01 08:00:00');

    $mapper = app(ApprovedWorkPlanMonitoringService::class);
    expect($mapper->defaultsForDate($this->topic, '2026-03-31'))
        ->toHaveCount(1)
        ->and($mapper->defaultsForDate($this->topic, '2026-03-31')[0]['objective'])->toBe('Establish the community baseline')
        ->and($mapper->defaultsForDate($this->topic, '2026-06-30')[0]['activity'])->toBe('Build and pilot the training kit')
        ->and($mapper->defaultsForDate($this->topic, '2026-09-30')[0]['physical_target'])->toBe('Outcome evaluation report');

    $this->actingAs($this->researcher)
        ->get(route('project-progress.create', ['topic' => $this->topic, 'reporting_date' => '2026-03-31']))
        ->assertOk()
        ->assertSee('Report')
        ->assertSee('of 3')
        ->assertSee('Synced From Approved Work Plan')
        ->assertSee('Only progress details are editable.');

    $tampered = ($this->monitoringPayload)([
        'reporting_date' => '2026-03-31',
        'work_plan' => [[
            'source_work_plan_index' => 0,
            'objective' => 'Changed objective',
            'activity' => 'Changed activity',
            'percent_weight' => 99,
            'physical_target' => 'Changed target',
            'target_completion_date' => '2027-12-31',
            'work_plan_months' => [9],
            'actual_accomplishment' => 'Completed 18 baseline interviews',
            'accomplished_percentage' => 15,
            'findings' => 'Two respondents need follow-up interviews.',
        ]],
    ]);

    $this->post(route('project-progress.prepare', $this->topic), $tampered)
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    $savedPlan = ProjectProgressReport::sole()->work_plan;
    expect($savedPlan[0]['objective'])->toBe('Establish the community baseline')
        ->and($savedPlan[0]['activity'])->toBe('Conduct baseline interviews')
        ->and($savedPlan[0]['physical_target'])->toBe('Validated baseline dataset')
        ->and($savedPlan[0]['percent_weight'])->toBe('33.33')
        ->and($savedPlan[0]['target_completion_date'])->toBe('2026-03-31')
        ->and($savedPlan[0]['work_plan_months'])->toBe([1, 2, 3])
        ->and($savedPlan[0]['actual_accomplishment'])->toBe('Completed 18 baseline interviews');
});

test('a monitoring form auto-saves a private draft without preparing an official PDF', function () {
    $this->actingAs($this->researcher)
        ->post(route('project-progress.draft', $this->topic), [
            ...($this->monitoringPayload)(),
            'draft_version' => 0,
        ], ['Accept' => 'application/json'])
        ->assertOk()
        ->assertJsonPath('draft_version', 1);

    $draft = ProjectMonitoringDraft::sole();

    expect($draft->topic_id)->toBe($this->topic->id)
        ->and($draft->user_id)->toBe($this->researcher->id)
        ->and($draft->source_data['tracking_number'])->toBe('REC-2026-001')
        ->and(ProjectProgressReport::count())->toBe(0)
        ->and($this->pdfConverter->conversionCount)->toBe(0);

    $this->actingAs($this->researcher)
        ->post(route('project-progress.draft', $this->topic), [
            ...$draft->source_data,
            'draft_version' => 1,
        ], ['Accept' => 'application/json'])
        ->assertOk()
        ->assertJsonPath('draft_version', 1);

    expect($draft->fresh()->lock_version)->toBe(1);

    $this->actingAs($this->researcher)
        ->post(route('project-progress.draft', $this->topic), [
            ...$draft->source_data,
            'tracking_number' => 'STALE-CHANGE',
            'draft_version' => 0,
        ], ['Accept' => 'application/json'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('draft_version');

    expect($draft->fresh()->source_data['tracking_number'])->toBe('REC-2026-001')
        ->and($draft->fresh()->lock_version)->toBe(1);
});

test('a researcher can preview the filled monitoring tool without submitting it', function () {
    $this->actingAs($this->researcher)
        ->post(route('project-progress.preview', $this->topic), ($this->monitoringPayload)())
        ->assertOk()
        ->assertSee('MONITORING TOOL')
        ->assertSee('Approved Community Research')
        ->assertSee('Conduct field interviews')
        ->assertSee('25%')
        ->assertSee('PHP 15,000.00');

    expect(ProjectProgressReport::count())->toBe(0);
});

test('the Research Head topic page shows monitoring in its own tab', function () {
    $this->actingAs($this->head)
        ->get(route('topics.show', $this->topic))
        ->assertOk()
        ->assertSee('id="version-history-tab-button"', false)
        ->assertSee('id="project-monitoring-tab-button"', false)
        ->assertSee('@click="setTopicTab(\'monitoring\', \'project-monitoring\')"', false)
        ->assertSee('id="project-monitoring-tab"', false)
        ->assertSee('x-show="activeTopicTab === \'monitoring\'"', false)
        ->assertSee("window.location.hash === '#project-monitoring'", false);
});

test('monitoring tools validate reporting totals and required activities', function () {
    $futureDate = ($this->monitoringPayload)(['reporting_date' => now()->addDay()->toDateString()]);
    $this->actingAs($this->researcher)
        ->post(route('project-progress.store', $this->topic), $futureDate)
        ->assertSessionHasErrors('reporting_date');

    $excessiveTotal = ($this->monitoringPayload)();
    $excessiveTotal['work_plan'][0]['accomplished_percentage'] = 95;
    $excessiveTotal['work_plan'][1]['accomplished_percentage'] = 20;
    $this->actingAs($this->researcher)
        ->post(route('project-progress.store', $this->topic), $excessiveTotal)
        ->assertSessionHasErrors('work_plan');

    $missingActivities = ($this->monitoringPayload)(['work_plan' => []]);
    $this->actingAs($this->researcher)
        ->post(route('project-progress.store', $this->topic), $missingActivities)
        ->assertSessionHasErrors('work_plan');

    $tooManyActivities = ($this->monitoringPayload)([
        'work_plan' => array_fill(0, 12, ($this->monitoringPayload)()['work_plan'][0]),
    ]);
    $this->actingAs($this->researcher)
        ->post(route('project-progress.store', $this->topic), $tooManyActivities)
        ->assertSessionHasErrors('work_plan');

    expect(ProjectProgressReport::count())->toBe(0);
});

test('the first-quarter monitoring tool accepts eleven work plan activities', function () {
    $activity = ($this->monitoringPayload)()['work_plan'][0];
    $workPlan = collect(range(1, 11))
        ->map(fn (int $number): array => [
            ...$activity,
            'activity' => "Quarterly activity {$number}",
            'percent_weight' => 1,
            'accomplished_percentage' => 1,
        ])
        ->all();

    $this->actingAs($this->researcher)
        ->post(route('project-progress.store', $this->topic), ($this->monitoringPayload)([
            'work_plan' => $workPlan,
        ]))
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    expect(ProjectProgressReport::firstOrFail()->work_plan)->toHaveCount(11);
});

test('a researcher with a Notice to Proceed can submit a progress report', function () {
    Storage::fake('local');

    $this->actingAs($this->researcher)
        ->post(route('project-progress.store', $this->topic), ($this->monitoringPayload)([
            'attachment' => UploadedFile::fake()->create('progress.pdf', 100, 'application/pdf'),
        ]))
        ->assertRedirect()
        ->assertSessionHas('success');

    $report = ProjectProgressReport::firstOrFail();
    expect($report->topic_id)->toBe($this->topic->id)
        ->and($report->progress_percentage)->toBe(25)
        ->and($report->work_plan)->toHaveCount(2)
        ->and($report->budget_utilization)->toHaveCount(3)
        ->and($report->review_status)->toBe('pending');
    Storage::disk('local')->assertExists($report->attachment_path);
});

test('another researcher cannot report progress for a project they do not own', function () {
    $other = User::factory()->create();
    $other->assignRole('faculty_researcher');

    $this->actingAs($other)
        ->get(route('project-progress.create', $this->topic))
        ->assertForbidden();

    $this->actingAs($other)
        ->post(route('project-progress.store', $this->topic), ($this->monitoringPayload)())
        ->assertForbidden();
});

test('progress cannot be submitted for a proposal that is not approved', function () {
    $this->topic->update(['status' => 'pending', 'project_status' => null]);

    $this->actingAs($this->researcher)
        ->post(route('project-progress.store', $this->topic), ($this->monitoringPayload)())
        ->assertForbidden();
});

test('completed projects cannot preview or submit monitoring tools', function () {
    $this->topic->update(['project_status' => TopicProposal::PROJECT_STATUS_COMPLETED]);

    $this->actingAs($this->researcher)
        ->get(route('project-progress.create', $this->topic))
        ->assertNotFound();

    $this->actingAs($this->researcher)
        ->post(route('project-progress.preview', $this->topic), ($this->monitoringPayload)())
        ->assertForbidden();
    $this->actingAs($this->researcher)
        ->post(route('project-progress.store', $this->topic), ($this->monitoringPayload)())
        ->assertForbidden();

    expect(ProjectProgressReport::count())->toBe(0);
});

test('an accepted collaborator can access the same active project monitoring workflow', function () {
    $collaborator = User::factory()->create();
    $collaborator->assignRole('faculty_researcher');
    $this->topic->collaborators()->create([
        'user_id' => $collaborator->id,
        'name' => $collaborator->name,
        'email' => $collaborator->email,
        'accepted_at' => now(),
    ]);

    $this->actingAs($collaborator)
        ->get(route('research.index'))
        ->assertOk()
        ->assertSee('Approved Community Research');
    $this->actingAs($collaborator)
        ->get(route('research.show', $this->topic))
        ->assertOk()
        ->assertSee('Start report');
    $this->actingAs($collaborator)
        ->post(route('project-progress.store', $this->topic), ($this->monitoringPayload)())
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    $report = ProjectProgressReport::sole();
    expect($report->submitted_by)->toBe($collaborator->id)
        ->and(TopicProposal::count())->toBe(1);
    $this->actingAs($collaborator)
        ->get(route('project-progress.monitoring-tool', $report))
        ->assertOk();
});

test('a researcher can discard a prepared monitoring tool and its stored PDF', function () {
    $this->actingAs($this->researcher)
        ->post(route('project-progress.prepare', $this->topic), ($this->monitoringPayload)())
        ->assertSessionHasNoErrors();

    $report = ProjectProgressReport::firstOrFail();
    $pdfPath = $report->official_pdf_path;
    Storage::disk('local')->assertExists($pdfPath);

    $this->actingAs($this->researcher)
        ->get(route('project-progress.create', $this->topic))
        ->assertOk()
        ->assertSee('Monitoring Tool PDF prepared')
        ->assertSee('Submit to Research Head')
        ->assertSee('data-monitoring-action-dock-fixed', false);
    $this->actingAs($this->researcher)
        ->delete(route('project-progress.discard-prepared', [$this->topic, $report]))
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    $this->assertModelMissing($report);
    Storage::disk('local')->assertMissing($pdfPath);
});

test('a research head can review a report and update project status', function () {
    $report = ProjectProgressReport::create([
        'topic_id' => $this->topic->id,
        'submitted_by' => $this->researcher->id,
        'reporting_date' => now(),
        'progress_percentage' => 60,
        'accomplishments' => 'Draft report completed.',
    ]);
    $notification = $this->head->notifications()->create([
        'id' => (string) Str::uuid(),
        'type' => ProposalActivityNotification::class,
        'data' => [
            'title' => 'Monitoring tool submitted',
            'message' => 'A monitoring tool is ready for review.',
            'url' => route('research_head.projects.index'),
            'topic_id' => $this->topic->id,
            'workspace' => User::WORKSPACE_RESEARCH_HEAD,
            'sidebar_area' => ProposalActivityNotification::SIDEBAR_AREA_PROJECT_MONITORING,
        ],
    ]);
    $otherNotification = $this->head->notifications()->create([
        'id' => (string) Str::uuid(),
        'type' => ProposalActivityNotification::class,
        'data' => [
            'title' => 'Progress report submitted',
            'message' => 'Another project report is ready for review.',
            'url' => route('research_head.projects.index'),
            'topic_id' => $this->topic->id + 1000,
            'workspace' => User::WORKSPACE_RESEARCH_HEAD,
            'sidebar_area' => ProposalActivityNotification::SIDEBAR_AREA_PROJECT_MONITORING,
        ],
    ]);

    $this->actingAs($this->head)
        ->patch(route('research_head.progress-reports.review', $report), [
            'review_status' => 'reviewed',
            'research_head_remarks' => 'Progress is acceptable.',
        ])
        ->assertRedirect();

    $this->actingAs($this->head)
        ->patch(route('research_head.projects.update-status', $this->topic), [
            'project_status' => 'delayed',
        ])
        ->assertRedirect();

    expect($report->fresh()->review_status)->toBe('reviewed')
        ->and($report->fresh()->reviewed_by)->toBe($this->head->id)
        ->and($this->topic->fresh()->project_status)->toBe('delayed')
        ->and($notification->fresh()->read_at)->not->toBeNull()
        ->and($otherNotification->fresh()->read_at)->toBeNull();
});

test('revision requests require Research Head remarks', function () {
    $report = ProjectProgressReport::create([
        'topic_id' => $this->topic->id,
        'submitted_by' => $this->researcher->id,
        'reporting_date' => now(),
        'progress_percentage' => 60,
        'accomplishments' => 'Draft report completed.',
    ]);

    $this->actingAs($this->head)
        ->patch(route('research_head.progress-reports.review', $report), [
            'review_status' => 'revision_requested',
        ])
        ->assertSessionHasErrors('research_head_remarks');

    expect($report->fresh()->review_status)->toBe('pending');
});

test('a revised Monitoring Tool remains in its original quarter with a retained PDF history', function () {
    $this->actingAs($this->researcher)
        ->post(route('project-progress.prepare', $this->topic), ($this->monitoringPayload)())
        ->assertSessionHasNoErrors();

    $original = ProjectProgressReport::firstOrFail();
    $originalPdfPath = $original->official_pdf_path;

    $this->actingAs($this->researcher)
        ->post(route('project-progress.submit-prepared', [$this->topic, $original]))
        ->assertSessionHasNoErrors();

    $this->actingAs($this->head)
        ->patch(route('research_head.progress-reports.review', $original), [
            'review_status' => 'revision_requested',
            'research_head_remarks' => 'Please correct the accomplishment data.',
        ])
        ->assertSessionHasNoErrors();

    Notification::assertSentTo(
        $this->researcher,
        ProposalActivityNotification::class,
        fn (ProposalActivityNotification $notification): bool => $notification->title === $original->quarter_label.' Monitoring Tool Corrections Requested',
    );

    $this->actingAs($this->researcher)
        ->get(route('project-progress.create', ['topic' => $this->topic, 'revise_monitoring_report' => $original->id]))
        ->assertOk()
        ->assertSee('Correct '.$original->quarter_label.' Monitoring Tool')
        ->assertSee('Please correct the accomplishment data.');

    $this->actingAs($this->researcher)
        ->post(route('project-progress.prepare', $this->topic), ($this->monitoringPayload)([
            'source_report_id' => $original->id,
            'tracking_number' => 'REC-2026-001-R2',
        ]))
        ->assertSessionHasNoErrors();

    $replacement = ProjectProgressReport::query()
        ->where('supersedes_report_id', $original->id)
        ->sole();

    expect($replacement->reporting_year)->toBe($original->fresh()->reporting_year)
        ->and($replacement->reporting_quarter)->toBe($original->fresh()->reporting_quarter)
        ->and($replacement->version_number)->toBe(2)
        ->and($replacement->isPrepared())->toBeTrue()
        ->and($original->fresh()->review_status)->toBe('revision_requested')
        ->and($original->fresh()->nextVersion->is($replacement))->toBeTrue();
    Storage::disk('local')->assertExists($originalPdfPath);
    Storage::disk('local')->assertExists($replacement->official_pdf_path);

    $this->actingAs($this->researcher)
        ->post(route('project-progress.submit-prepared', [$this->topic, $replacement]))
        ->assertSessionHasNoErrors();

    Notification::assertSentTo(
        $this->head,
        ProposalActivityNotification::class,
        fn (ProposalActivityNotification $notification): bool => $notification->title === $replacement->quarter_label.' Monitoring Tool Resubmitted'
            && $notification->url === route('topics.show', $this->topic).'#monitoring-tool-'.$replacement->id,
    );

    $this->actingAs($this->head)
        ->get(route('topics.show', $this->topic))
        ->assertOk()
        ->assertSee('Quarterly reporting schedule')
        ->assertSee($replacement->quarter_label.' Monitoring Tool · Version 2')
        ->assertSee('Corrections requested')
        ->assertSee('Historical version')
        ->assertSee('Current submission');
});

test('project status accepts only supported execution states', function () {
    $this->actingAs($this->head)
        ->patch(route('research_head.projects.update-status', $this->topic), [
            'project_status' => 'cancelled',
        ])
        ->assertSessionHasErrors('project_status');

    expect($this->topic->fresh()->project_status)->toBe('ongoing');
});

test('a project cannot be marked completed below 100 percent progress', function () {
    ProjectProgressReport::create([
        'topic_id' => $this->topic->id,
        'submitted_by' => $this->researcher->id,
        'reporting_date' => now(),
        'progress_percentage' => 95,
        'accomplishments' => 'Final validation remains in progress.',
        'review_status' => 'reviewed',
    ]);

    $this->actingAs($this->head)
        ->patch(route('research_head.projects.update-status', $this->topic), [
            'project_status' => 'completed',
        ])
        ->assertSessionHasErrors([
            'project_status' => 'A project can only be completed when its latest monitoring tool shows 100% progress.',
        ]);

    expect($this->topic->fresh()->project_status)->toBe('ongoing');
});

test('a project requires the reviewed terminal reports signed PDF before completion', function () {
    $this->withoutVite();
    $this->topic->update(['notice_to_proceed_issued_at' => now()->subMonths(13)]);
    ProjectProgressReport::create([
        'topic_id' => $this->topic->id,
        'submitted_by' => $this->researcher->id,
        'reporting_date' => now()->subDay(),
        'progress_percentage' => 100,
        'accomplishments' => 'All approved activities were completed.',
        'review_status' => 'reviewed',
    ]);
    $terminalReport = ProjectNarrativeReport::create([
        'topic_id' => $this->topic->id,
        'submitted_by' => $this->researcher->id,
        'report_type' => 'terminal',
        'submission_date' => now()->subDay(),
        'researchers' => $this->researcher->name,
        'implementation_start' => now()->subYear(),
        'implementation_end' => now()->subDay(),
        'budget' => 50000,
        'funding_agency' => 'Batangas State University',
        'accomplishment_summary' => 'All project objectives were achieved.',
        'introduction' => 'Final introduction.',
        'objectives' => 'Complete the approved research objectives.',
        'methodology' => 'Approved methodology was completed.',
        'results_discussion' => 'Final results were validated.',
        'photos' => [],
        'terminal_data' => [],
        'submission_status' => ProjectNarrativeReport::SUBMISSION_STATUS_SUBMITTED,
        'submitted_at' => now()->subDay(),
        'review_status' => ProjectNarrativeReport::STATUS_REVIEWED,
        'reviewed_by' => $this->head->id,
        'reviewed_at' => now(),
    ]);

    $this->actingAs($this->head)
        ->patch(route('research_head.projects.update-status', $this->topic), [
            'project_status' => TopicProposal::PROJECT_STATUS_COMPLETED,
        ])
        ->assertSessionHasErrors([
            'project_status' => 'Upload the fully signed Terminal Report PDF before marking the project completed.',
        ]);

    expect($this->topic->fresh()->project_status)->toBe(TopicProposal::PROJECT_STATUS_ONGOING)
        ->and($terminalReport->fresh()->hasSignedCopy())->toBeFalse();

    $this->actingAs($this->head)
        ->post(route('research_head.narrative-progress-reports.signed-copy.store', $terminalReport), [
            'signed_report' => UploadedFile::fake()->create('signed-terminal-report.pdf', 120, 'application/pdf'),
        ])
        ->assertSessionHasNoErrors()
        ->assertSessionHas('success', 'Signed Terminal Report recorded. The project may now be completed when every other completion requirement is satisfied.');

    $signedCopy = $terminalReport->fresh()->signedCopy();
    expect($signedCopy)->not->toBeNull()
        ->and($signedCopy['original_filename'])->toBe('signed-terminal-report.pdf');
    Storage::disk('local')->assertExists($signedCopy['path']);

    $this->get(route('project-narrative-reports.signed-copy.download', $terminalReport))
        ->assertOk()
        ->assertDownload('signed-terminal-report.pdf');

    $this->get(route('topics.show', $this->topic))
        ->assertOk()
        ->assertSee('Signed copy recorded')
        ->assertSee('Download signed Terminal Report');

    $this->patch(route('research_head.narrative-progress-reports.review', $terminalReport), [
        'review_status' => ProjectNarrativeReport::STATUS_REVISION_REQUESTED,
        'research_head_remarks' => 'Correct the final results before collecting signatures again.',
    ])->assertSessionHasNoErrors();

    expect($terminalReport->fresh()->hasSignedCopy())->toBeFalse();
    Storage::disk('local')->assertMissing($signedCopy['path']);

    $this->patch(route('research_head.narrative-progress-reports.review', $terminalReport), [
        'review_status' => ProjectNarrativeReport::STATUS_REVIEWED,
    ])->assertSessionHasNoErrors();
    $this->post(route('research_head.narrative-progress-reports.signed-copy.store', $terminalReport), [
        'signed_report' => UploadedFile::fake()->create('corrected-signed-terminal-report.pdf', 120, 'application/pdf'),
    ])->assertSessionHasNoErrors();

    $this->patch(route('research_head.projects.update-status', $this->topic), [
        'project_status' => TopicProposal::PROJECT_STATUS_COMPLETED,
    ])->assertSessionHasNoErrors();

    expect($this->topic->fresh()->project_status)->toBe(TopicProposal::PROJECT_STATUS_COMPLETED);
});

test('the owner and Research Head can download a progress attachment', function () {
    Storage::fake('local');
    Storage::disk('local')->put('progress-reports/report.pdf', 'progress report');
    $report = ProjectProgressReport::create([
        'topic_id' => $this->topic->id,
        'submitted_by' => $this->researcher->id,
        'reporting_date' => now(),
        'progress_percentage' => 70,
        'accomplishments' => 'Analysis completed.',
        'attachment_path' => 'progress-reports/report.pdf',
    ]);

    $this->actingAs($this->researcher)->get(route('project-progress.download', $report))->assertOk();
    $this->actingAs($this->head)->get(route('project-progress.download', $report))->assertOk();
});

test('an unrelated user cannot download a progress attachment', function () {
    Storage::fake('local');
    Storage::disk('local')->put('progress-reports/private.pdf', 'private report');
    $report = ProjectProgressReport::create([
        'topic_id' => $this->topic->id,
        'submitted_by' => $this->researcher->id,
        'reporting_date' => now(),
        'progress_percentage' => 70,
        'accomplishments' => 'Analysis completed.',
        'attachment_path' => 'progress-reports/private.pdf',
    ]);
    $other = User::factory()->create();
    $other->assignRole('faculty_researcher');

    $this->actingAs($other)
        ->get(route('project-progress.download', $report))
        ->assertForbidden();
});

test('the owner and Research Head can download the filled official monitoring tool as a PDF', function () {
    $this->actingAs($this->researcher)
        ->post(route('project-progress.store', $this->topic), ($this->monitoringPayload)())
        ->assertSessionHasNoErrors();

    $report = ProjectProgressReport::firstOrFail();
    $ownerResponse = $this->actingAs($this->researcher)
        ->get(route('project-progress.monitoring-tool', $report))
        ->assertOk()
        ->assertDownload('approved-community-research-'.$report->quarter_label.'-v1-monitoring-tool.pdf');
    expect($ownerResponse->streamedContent())->toStartWith('%PDF-');
    $sourceDocument = $this->pdfConverter->sourceDocument;
    expect($this->pdfConverter->conversionCount)->toBe(1);
    $this->actingAs($this->head)
        ->get(route('project-progress.monitoring-tool', $report))
        ->assertForbidden();

    $this->actingAs($this->researcher)
        ->post(route('project-progress.submit-prepared', [$this->topic, $report]))
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    $this->actingAs($this->head)
        ->get(route('project-progress.monitoring-tool', $report))
        ->assertOk();
    expect($this->pdfConverter->conversionCount)->toBe(1);

    $generatedPath = tempnam(sys_get_temp_dir(), 'monitoring-test-');
    expect($generatedPath)->not->toBeFalse();
    file_put_contents($generatedPath, $sourceDocument);

    $template = new ZipArchive;
    $generated = new ZipArchive;

    try {
        expect($template->open(resource_path('documents/BatStateU-REC-RES-03-Monitoring-Tool.docx')))->toBeTrue()
            ->and($generated->open($generatedPath))->toBeTrue();

        $documentXml = $generated->getFromName('word/document.xml');
        $footerXml = $generated->getFromName('word/footer1.xml');
        expect($documentXml)->toContain('Approved Community Research')
            ->and($documentXml)->toContain('Conduct field interviews')
            ->and($documentXml)->toContain('PHP 50,000.00')
            ->and($documentXml)->not->toContain('Development of an Online College Research Journal Management System')
            ->and($documentXml)->not->toContain('Implement different level of access for different types of users')
            ->and($footerXml)->toContain('REC-2026-001')
            ->and($footerXml)->toContain('PAGE')
            ->and($footerXml)->toContain('NUMPAGES');

        for ($index = 0; $index < $template->numFiles; $index++) {
            $name = $template->getNameIndex($index);

            if (in_array($name, ['word/document.xml', 'word/footer1.xml'], true)) {
                continue;
            }

            expect(hash('sha256', $generated->getFromName($name)))
                ->toBe(hash('sha256', $template->getFromName($name)));
        }
    } finally {
        $template->close();
        $generated->close();
        unlink($generatedPath);
    }
});

test('an unrelated user cannot download the official monitoring tool', function () {
    $this->actingAs($this->researcher)
        ->post(route('project-progress.store', $this->topic), ($this->monitoringPayload)())
        ->assertSessionHasNoErrors();

    $other = User::factory()->create();
    $other->assignRole('faculty_researcher');

    $this->actingAs($other)
        ->get(route('project-progress.monitoring-tool', ProjectProgressReport::firstOrFail()))
        ->assertForbidden();
});

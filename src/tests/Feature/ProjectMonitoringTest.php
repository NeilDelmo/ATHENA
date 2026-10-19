<?php

use App\Contracts\DocumentPdfConverter;
use App\Models\ProjectMonitoringDraft;
use App\Models\ProjectNarrativeReport;
use App\Models\ProjectNarrativeReportDraft;
use App\Models\ProjectProgressReport;
use App\Models\ProposalVersionFile;
use App\Models\ResearchCall;
use App\Models\TopicProposal;
use App\Models\User;
use App\Notifications\ProposalActivityNotification;
use App\Services\ApprovedWorkPlanMonitoringService;
use App\Services\MonitoringQuarterService;
use App\Services\MonitoringToolDocumentService;
use App\Services\ProgressReportDocumentService;
use App\Support\ProgressReportData;
use App\Support\TerminalReportData;
use App\Support\TerminalReportRules;
use Carbon\CarbonImmutable;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
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

    $this->researcher = User::factory()->create(['college' => 'College of Engineering']);
    $this->researcher->assignRole('faculty_researcher');
    $this->head = User::factory()->create();
    $this->head->assignRole('research_head');
    $this->secretary = User::factory()->create([
        'name' => 'Marina Budget Secretary',
        'email' => 'marina.secretary@g.batstate-u.edu.ph',
        'avatar' => 'https://example.com/marina-secretary.jpg',
    ]);
    $this->secretary->assignRole('research_secretary');
    $this->officeSecretary = User::factory()->create(['college' => $this->researcher->college]);
    $this->officeSecretary->assignRole('research_secretary');
    $this->forwardReport = function (ProjectProgressReport|ProjectNarrativeReport $report): void {
        $route = $report instanceof ProjectProgressReport ? 'research_secretary.monitoring-reports.review' : 'research_secretary.narrative-reports.review';
        $this->actingAs($this->officeSecretary)->withSession([User::ACTIVE_WORKSPACE_SESSION_KEY => User::WORKSPACE_RESEARCH_SECRETARY])
            ->patch(route($route, $report), ['decision' => 'forward'])->assertRedirect()->assertSessionHasNoErrors();
    };
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

    $this->attachSubmissionProof = function (ProjectProgressReport|ProjectNarrativeReport $report): void {
        $upload = UploadedFile::fake()->image('accomplishment-proof.png');
        $prefix = $report instanceof ProjectProgressReport ? 'progress-reports' : 'narrative-progress-reports';
        $path = $upload->store($prefix.'/'.$report->topic_id.'/evidence', 'local');
        $file = ['id' => (string) Str::uuid(), 'path' => $path, 'name' => 'accomplishment-proof.png',
            'mime_type' => $upload->getMimeType(), 'size' => $upload->getSize(), 'checksum' => hash_file('sha256', $upload->getRealPath())];
        $field = $report instanceof ProjectProgressReport ? 'work_plan' : 'accomplishments';
        $report->update([$field => array_map(fn (array $row): array => [...$row, 'evidence' => [$file]], $report->$field)]);
    };

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

test('the monitoring workspace has one floating back link to the correct project list', function (string $viewer, bool $completed, string $backRoute) {
    $this->withoutVite();
    if ($completed) {
        $this->topic->update(['project_status' => 'completed']);
    }

    $response = $this->actingAs($this->{$viewer})
        ->get(route('topics.show', $this->topic))
        ->assertSuccessful()
        ->assertSee('data-topic-workspace', false)
        ->assertSee('id="project-monitoring-tab"', false);

    $document = new DOMDocument;
    @$document->loadHTML($response->getContent());
    $xpath = new DOMXPath($document);
    $links = $xpath->query('//*[@data-topic-workspace]//a[@data-fixed-back-link]');

    expect($links->length)->toBe(1)
        ->and($links->item(0)->getAttribute('href'))->toBe(route($backRoute))
        ->and(trim($links->item(0)->textContent))->toBe('Back to projects')
        ->and($xpath->query('ancestor::*[@role="tabpanel"]', $links->item(0))->length)->toBe(0);
})->with([
    'researcher' => ['researcher', false, 'research.index'],
    'research head' => ['head', false, 'research_head.projects.index'],
    'completed researcher project' => ['researcher', true, 'research.index'],
    'completed research head project' => ['head', true, 'research_head.completed-projects.index'],
]);

test('monitoring and progress signatories use the current Research Head and VCRDES with the official form roles', function () {
    $monitoring = new ProjectProgressReport([
        ...($this->monitoringPayload)(), 'progress_percentage' => 25,
    ]);
    $monitoring->setRelation('topic', $this->topic->loadMissing('user'));
    $monitoring->setRelation('submitter', $this->researcher);
    $monitoring->setRelation('reviewer', null);
    $progress = new ProjectNarrativeReport([
        'report_type' => 'progress', 'submission_date' => now(),
        'implementation_start' => now()->subMonths(3), 'implementation_end' => now()->addMonths(9),
        'researchers' => $this->researcher->name, 'budget' => 50000,
        'funding_agency' => 'Batangas State University', 'accomplishments' => [],
        'introduction' => 'Introduction', 'rationale' => 'Rationale', 'objectives' => 'Objectives',
        'methodology' => 'Methods', 'results_discussion' => 'Results', 'photos' => [],
    ]);
    $progress->setRelation('topic', $this->topic);
    $progress->setRelation('submitter', $this->researcher);
    $this->withoutVite();
    foreach ([
        [$monitoring, MonitoringToolDocumentService::class, 'faculty.monitoring-tools.preview'],
        [$progress, ProgressReportDocumentService::class, 'faculty.progress-reports.preview'],
    ] as [$report, $service, $view]) {
        $preview = $this->view($view, ['report' => $report]);
        $preview->assertSee('Asst. Prof. DJOANNA MARIE V. SALAC')->assertSee('Dr. FROILAN G. DESTREZA');
        $path = tempnam(sys_get_temp_dir(), 'report-signatories-');
        try {
            file_put_contents($path, app($service)->generate($report));
            $zip = new ZipArchive;
            expect($zip->open($path))->toBeTrue();
            $document = new DOMDocument;
            $document->loadXML($zip->getFromName('word/document.xml'));
            $zip->close();
            $xpath = new DOMXPath($document);
            $xpath->registerNamespace('w', 'http://schemas.openxmlformats.org/wordprocessingml/2006/main');
            foreach ([
                'Asst. Prof. DJOANNA MARIE V. SALAC' => 'Head, Research',
                'Dr. FROILAN G. DESTREZA' => 'Vice Chancellor for Research, Development',
            ] as $name => $role) {
                $nameParagraphs = $xpath->query('//w:p[normalize-space(.)="'.$name.'"]');
                expect($nameParagraphs->length)->toBe(1)
                    ->and($xpath->evaluate('string(following-sibling::w:p[1])', $nameParagraphs->item(0)))->toContain($role);
            }
        } finally {
            unlink($path);
        }
    }
});

test('three quarterly report pairs preserve approved values and editable results through terminal signing', function () {
    $this->withoutVite();
    $this->travelTo(CarbonImmutable::parse('2026-04-15'));
    $this->topic->update(['notice_to_proceed_issued_at' => '2026-01-15', 'estimated_duration_months' => 9]);
    ($this->attachApprovedWorkPlan)([
        ['objective' => 'Assess coastal habitats.', 'activity' => 'Survey and validate coastal habitats', 'expected_output' => 'A validated coastal dataset', 'months' => range(1, 9)],
    ], 9);
    $version = $this->topic->latestVersion()->firstOrFail();
    $version->files()->create([
        'document_type' => 'detailed_proposal', 'position' => 0, 'file_path' => 'proposal.pdf',
        'original_filename' => 'proposal.pdf', 'mime_type' => 'application/pdf', 'file_size' => 100,
        'checksum' => hash('sha256', 'proposal'), 'source_data' => [
            'introduction' => '<p>Approved coastal background.</p>', 'rationale' => '<p>Evidence guides coastal planning.</p>',
            'general_objective' => 'Assess coastal habitats.', 'specific_objectives' => [['description' => 'Document habitat conditions.']],
            'methodology' => ['research_design' => 'Community surveys and field observations.'],
        ],
    ]);
    $schedule = app(MonitoringQuarterService::class);
    $periods = $schedule->projectPeriods($this->topic->fresh());
    expect($periods)->toHaveCount(3)->and($schedule->narrativeProgressPeriods($this->topic))->toHaveCount(3);
    $progressDefaults = app(ProgressReportData::class)->defaults($this->topic->fresh());
    expect($progressDefaults['introduction'])->toBe('Approved coastal background.')
        ->and($progressDefaults['accomplishments'][0]['target'])->toBe('A validated coastal dataset');

    foreach ($periods as $index => $period) {
        $quarter = $index + 1;
        $this->actingAs($this->researcher)->withSession([User::ACTIVE_WORKSPACE_SESSION_KEY => User::WORKSPACE_FACULTY_RESEARCHER]);
        if ($index > 0) {
            $this->get(route('project-progress.create', ['topic' => $this->topic, 'reporting_date' => $period['end']->toDateString()]))
                ->assertOk()->assertSee('submissionOpen: false', false);
            $this->postJson(route('project-progress.draft', $this->topic), [
                'draft_version' => 0, 'reporting_date' => $period['end']->toDateString(), 'tracking_number' => 'Q'.$quarter.' draft',
            ])->assertOk();
            $this->postJson(route('project-narrative-reports.draft', $this->topic), [
                'draft_version' => 0, 'report_type' => 'progress', 'reporting_date' => $period['end']->toDateString(),
                'introduction' => 'Q'.$quarter.' editable introduction.',
            ])->assertOk();
            $this->get(route('project-narrative-reports.create', ['topic' => $this->topic, 'reporting_date' => $period['end']->toDateString()]))
                ->assertOk()->assertSee('Q'.$quarter.' editable introduction.')->assertSee('submissionOpen: false', false);
            expect($this->topic->progressReports()->submitted()->count())->toBe($index)
                ->and($this->topic->narrativeReports()->submitted()->count())->toBe($index);
        }
        $this->travelTo($period['opens_at']);
        $monitoringRows = app(ApprovedWorkPlanMonitoringService::class)->defaultsForDate($this->topic->fresh(), $period['end']);
        $monitoringRows[0] = [...$monitoringRows[0], 'objective' => 'Changed objective', 'physical_target' => 'Changed target',
            'actual_accomplishment' => 'Monitoring result '.$quarter, 'accomplished_percentage' => $quarter === 3 ? 100 : $quarter * 25,
            'findings' => 'Field observations '.$quarter];
        $this->post(route('project-progress.prepare', $this->topic), ($this->monitoringPayload)([
            'reporting_date' => $period['end']->toDateString(), 'work_plan' => $monitoringRows,
            'activity_evidence' => $quarter === 3 ? ['plan-0' => [UploadedFile::fake()->image('completed-coastal-dataset.png')]] : [],
        ]))->assertRedirect()->assertSessionHasNoErrors();
        $monitoring = ProjectProgressReport::where('topic_id', $this->topic->id)->latest('id')->firstOrFail();
        expect($monitoring->work_plan[0]['objective'])->toBe('Assess coastal habitats.')
            ->and($monitoring->progress_percentage)->toBe($quarter === 3 ? 100 : 0)
            ->and($monitoring->work_plan[0]['physical_target'])->toBe('A validated coastal dataset')
            ->and($monitoring->work_plan[0]['actual_accomplishment'])->toBe('Monitoring result '.$quarter);
        $this->get(route('project-progress.monitoring-tool', $monitoring))->assertOk();
        ($this->attachSubmissionProof)($monitoring);
        $this->post(route('project-progress.submit-prepared', [$this->topic, $monitoring]))->assertSessionHasNoErrors();
        $progressPayload = [
            ...$progressDefaults, 'report_type' => 'progress', 'reporting_date' => $period['end']->toDateString(),
            'submission_date' => now()->toDateString(), 'researchers' => $this->researcher->name,
            'introduction' => 'Q'.$quarter.' editable introduction.', 'results_discussion' => 'Quarter '.$quarter.' narrative findings.',
            'accomplishments' => [['objective' => 'Changed objective', 'target' => 'Changed target', 'actual' => 'Progress result '.$quarter]],
        ];
        $this->post(route('project-narrative-reports.preview', $this->topic), $progressPayload)
            ->assertOk()->assertSee('Progress result '.$quarter)->assertSee('Assess coastal habitats.')->assertDontSee('Changed objective');
        $this->post(route('project-narrative-reports.prepare', $this->topic), $progressPayload)->assertSessionHasNoErrors();
        $progress = ProjectNarrativeReport::where('topic_id', $this->topic->id)->where('report_type', 'progress')->latest('id')->firstOrFail();
        expect($progress->accomplishments[0]['objective'])->toBe('Assess coastal habitats.')
            ->and($progress->accomplishments[0]['target'])->toBe('A validated coastal dataset')
            ->and($progress->accomplishments[0]['actual'])->toBe('Progress result '.$quarter)
            ->and($progress->introduction)->toBe('Q'.$quarter.' editable introduction.');
        ($this->attachSubmissionProof)($progress);
        $this->post(route('project-narrative-reports.submit-prepared', [$this->topic, $progress]))->assertSessionHasNoErrors();
        ($this->forwardReport)($monitoring);
        ($this->forwardReport)($progress);
        $this->actingAs($this->head)->withSession([User::ACTIVE_WORKSPACE_SESSION_KEY => User::WORKSPACE_RESEARCH_HEAD]);
        $this->patch(route('research_head.progress-reports.review', $monitoring), ['review_status' => 'reviewed'])->assertRedirect()->assertSessionHasNoErrors();
        $this->patch(route('research_head.narrative-progress-reports.review', $progress), ['review_status' => 'reviewed'])->assertRedirect()->assertSessionHasNoErrors();
    }
    expect($this->topic->progressReports()->submitted()->count())->toBe(3)
        ->and($this->topic->narrativeReports()->submitted()->where('report_type', 'progress')->count())->toBe(3);
    $this->travelTo($schedule->terminalOpensAt($this->topic));
    $terminalDefaults = app(TerminalReportData::class)->defaults($this->topic->fresh());
    foreach (range(1, 3) as $quarter) {
        expect($terminalDefaults['accomplishments'][0]['actual'])->toContain('Monitoring result '.$quarter, 'Progress result '.$quarter)
            ->and($terminalDefaults['results_discussion'])->toContain('Quarter '.$quarter.' narrative findings.');
    }
    expect($terminalDefaults['terminal_data']['source_narrative_report_ids'])->toHaveCount(3);
    $terminalPayload = [
        ...$terminalDefaults, 'implementation_start' => '2026-01-15', 'implementation_end' => '2026-10-14',
        'accomplishments' => [['objective' => 'Changed objective', 'target' => 'Changed target', 'actual' => 'Final verified coastal results.']],
        'introduction' => '<p>Final editable introduction.</p>', 'results_discussion' => '<p>Final editable discussion.</p>',
        'terminal_data' => [...collect($terminalDefaults['terminal_data'])->only(['authors', 'signatories', 'tables', 'collaborating_agency'])->all(),
            'abstract' => implode(' ', array_fill(0, 210, 'research')), 'total_expenditure' => 35000,
            'literature_review' => '<p>Related coastal literature.</p>', 'conclusions' => '<p>Coastal objectives achieved.</p>',
            'recommendations' => '<p>Continue habitat monitoring.</p>', 'bibliography' => '<p>Researcher. (2026). Coastal study.</p>',
            'signatories' => collect(TerminalReportRules::SIGNATORY_ROLES)->map(fn () => ['name' => 'Reviewer', 'date_signed' => null])->all(),
        ],
    ];
    $lastProgress = $this->topic->narrativeReports()->where('report_type', 'progress')->latest('id')->firstOrFail();
    $lastProgress->update(['review_status' => 'revision_requested']);
    $this->actingAs($this->researcher)->withSession([User::ACTIVE_WORKSPACE_SESSION_KEY => User::WORKSPACE_FACULTY_RESEARCHER]);
    $this->post(route('project-narrative-reports.prepare', $this->topic), $terminalPayload)->assertSessionHasErrorsIn('narrativeProgress', 'preparation');
    $this->postJson(route('project-narrative-reports.draft', $this->topic), ['draft_version' => 0, ...$terminalPayload])->assertOk();
    $lastProgress->update(['review_status' => 'reviewed']);
    $this->flushSession();
    $this->post(route('project-narrative-reports.preview', $this->topic), $terminalPayload)->assertOk()->assertSee('Final verified coastal results.');
    $this->post(route('project-narrative-reports.prepare', $this->topic), $terminalPayload)->assertSessionHasNoErrors();
    $terminal = ProjectNarrativeReport::where('topic_id', $this->topic->id)->where('report_type', 'terminal')->sole();
    expect($terminal->accomplishments[0]['objective'])->toBe('Assess coastal habitats.')
        ->and($terminal->accomplishments[0]['actual'])->toBe('Final verified coastal results.')
        ->and($terminal->introduction)->toContain('Final editable introduction.');
    $this->post(route('project-narrative-reports.submit-prepared', [$this->topic, $terminal]))->assertSessionHasNoErrors();
    $this->actingAs($this->head)->withSession([User::ACTIVE_WORKSPACE_SESSION_KEY => User::WORKSPACE_RESEARCH_HEAD]);
    $this->patch(route('research_head.narrative-progress-reports.review', $terminal), ['review_status' => 'reviewed'])->assertSessionHasNoErrors();
    $this->post(route('research_head.narrative-progress-reports.signed-copy.store', $terminal), [
        'signed_report' => UploadedFile::fake()->create('signed-terminal.pdf', 100, 'application/pdf'),
    ])->assertSessionHasNoErrors();
    $this->patch(route('research_head.projects.update-status', $this->topic), ['project_status' => 'completed', 'completion_confirmed' => true])->assertSessionHasNoErrors();
    expect($this->topic->fresh()->project_status)->toBe('completed');
    $this->actingAs($this->researcher)->withSession([User::ACTIVE_WORKSPACE_SESSION_KEY => User::WORKSPACE_FACULTY_RESEARCHER]);
    $this->get(route('project-narrative-reports.signed-copy.download', $terminal))->assertOk();
    $this->get(route('research.dissemination.show', $this->topic))->assertOk()->assertSee('Journal Finder');
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

    expect($this->topic->fresh()->research_secretary_id)->toBe($projectMember->id)
        ->and($this->topic->collaborators()->where('user_id', $projectMember->id)->sole()->project_role)->toBe('secretary');

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

test('the project secretary has budget priority while other project members may complete it', function () {
    $this->topic->update(['research_secretary_id' => $this->secretary->id]);

    $this->actingAs($this->researcher)
        ->post(route('project-progress.prepare', $this->topic), ($this->monitoringPayload)())
        ->assertSessionHasNoErrors();

    $report = ProjectProgressReport::query()->sole();

    ($this->attachSubmissionProof)($report);
    $this->actingAs($this->researcher)
        ->post(route('project-progress.submit-prepared', [$this->topic, $report]))
        ->assertSessionHasErrors('preparation');

    expect($report->fresh()->isPrepared())->toBeTrue();

    $this->actingAs($this->researcher)
        ->get(route('project-progress.create', ['topic' => $this->topic, 'reporting_date' => $report->reporting_date->toDateString()]))
        ->assertOk()
        ->assertSee($this->secretary->name.' has priority for budget utilization')
        ->assertSee('Complete budget utilization')
        ->assertSee(route('project-budget.edit', [$this->topic, $report]), false)
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
        ->assertSee('data-workspace-header-banner', false)
        ->assertSee($this->topic->title)
        ->assertSee('Complete budget');

    $budgetResponse = $this->actingAs($this->secretary)
        ->withSession([User::ACTIVE_WORKSPACE_SESSION_KEY => User::WORKSPACE_RESEARCH_SECRETARY])
        ->get(route('research_secretary.projects.budget.edit', [$this->topic, $report]))
        ->assertOk()
        ->assertSee('data-budget-utilization-writing-toolbar', false)
        ->assertSee('data-writing-toolbar-close', false)
        ->assertSee('data-writing-toolbar-open', false)
        ->assertSee('Confirm budget utilization')
        ->assertSee('You are the priority project secretary')
        ->assertSee('budgetUtilizationForm', false);

    if (getenv('ATHENA_EXPORT_DOCUMENT_TOOLBARS') === '1') {
        File::ensureDirectoryExists(storage_path('framework/testing'));
        file_put_contents(storage_path('framework/testing/budget-utilization-form.html'), $budgetResponse->getContent());
    }

    $this->actingAs($this->researcher)
        ->withSession([User::ACTIVE_WORKSPACE_SESSION_KEY => User::WORKSPACE_FACULTY_RESEARCHER])
        ->get(route('project-budget.edit', [$this->topic, $report]))
        ->assertOk()
        ->assertSee($this->secretary->name.' has priority for this section')
        ->assertSee('you may complete it as an authorized project member');

    $this->actingAs($this->researcher)
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

    $report->refresh();
    expect($report->budget_prepared_by)->toBe($this->researcher->id)
        ->and($report->budget_prepared_at)->not->toBeNull()
        ->and($report->hasPreparedBudget())->toBeTrue()
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

test('monitoring and progress reports have matching quarter counts dates and form links', function () {
    $this->withoutVite();
    $this->topic->update(['notice_to_proceed_issued_at' => '2026-01-15', 'estimated_duration_months' => 9]);
    $this->travelTo(now()->setDate(2026, 10, 15)->startOfDay());
    $response = $this->actingAs($this->researcher)->get(route('research.show', $this->topic))->assertOk();
    $document = new DOMDocument;
    @$document->loadHTML($response->getContent());
    $xpath = new DOMXPath($document);
    $monitoring = $xpath->query('//*[@data-monitoring-schedule-table]//tr[@data-reporting-quarter]');
    $progress = $xpath->query('//*[@data-progress-schedule-table]//*[@data-progress-quarter]');
    expect($monitoring->length)->toBe(3)->and($progress->length)->toBe(3);
    foreach (['2026-04-14', '2026-07-14', '2026-10-14'] as $index => $date) {
        expect($monitoring->item($index)->getAttribute('data-reporting-quarter'))->toBe((string) ($index + 1))
            ->and($progress->item($index)->parentNode->isSameNode($monitoring->item($index)))->toBeTrue();
        $url = route('project-narrative-reports.create', ['topic' => $this->topic, 'report_type' => 'progress', 'reporting_date' => $date]);
        expect($xpath->query('.//a', $progress->item($index))->item(0)->getAttribute('href'))->toBe($url);
        $this->get($url)->assertOk()->assertViewHas('selectedReportingDate', $date);
    }
});

test('monitoring prioritizes paired quarterly reports and terminal closeout in completed projects', function (string $workspace) {
    $this->withoutVite();
    $this->travelTo('2026-10-06 10:00:00');
    $this->researcher->update(['name' => 'Djanisse Villaflor']);
    $this->topic->update([
        'notice_to_proceed_issued_at' => '2025-10-01',
        'estimated_duration_months' => 9,
        'project_status' => TopicProposal::PROJECT_STATUS_COMPLETED,
        'research_secretary_id' => $this->secretary->id,
    ]);
    $tool = $this->topic->progressReports()->create([
        'submitted_by' => $this->researcher->id, 'reporting_date' => '2025-12-31',
        'reporting_quarter' => 1, 'progress_percentage' => 25, 'submitted_at' => '2026-01-01',
        'accomplishments' => 'Pilot dataset validated.', 'review_status' => 'reviewed',
    ]);
    $progress = $this->topic->narrativeReports()->create([
        'submitted_by' => $this->researcher->id, 'report_type' => 'progress',
        'submission_date' => '2026-01-01', 'reporting_date' => '2025-12-31', 'reporting_quarter' => 1,
        'researchers' => $this->researcher->name, 'budget' => 50000,
        'funding_agency' => 'University', 'implementation_start' => '2025-10-01',
        'implementation_end' => '2026-06-30', 'accomplishment_summary' => 'Pilot complete.',
        'introduction' => 'Pilot implementation.', 'objectives' => 'Validate the dataset.',
        'methodology' => 'Pilot testing.', 'results_discussion' => 'Validated maintenance records.',
        'review_status' => 'reviewed', 'photos' => [],
    ]);
    $terminal = $progress->replicate();
    $terminal->fill([
        'report_type' => 'terminal', 'submission_date' => '2026-07-01', 'reporting_quarter' => null,
        'terminal_data' => ['signed_copy' => ['path' => 'signed-terminal.pdf', 'original_filename' => 'signed-terminal.pdf']],
    ])->save();
    $olderProgress = $progress->replicate();
    $olderProgress->fill(['reporting_quarter' => null, 'reporting_date' => null])->save();

    $response = $this->actingAs($workspace === 'research_head' ? $this->head : $this->researcher)
        ->withSession([User::ACTIVE_WORKSPACE_SESSION_KEY => $workspace])
        ->get(route('topics.show', $this->topic))->assertSuccessful()
        ->assertSee('Project archive')->assertSee('Archived reports');
    $document = new DOMDocument;
    @$document->loadHTML($response->getContent());
    $xpath = new DOMXPath($document);
    $tabs = $xpath->query('//*[@role="tablist"]//*[@role="tab"]');
    $quarter = $xpath->query('//*[@data-reporting-quarter="1"]')->item(0);
    $terminalSection = $xpath->query('//*[@id="terminal-reports"]')->item(0);

    expect($tabs->item(0)->getAttribute('id'))->toBe('project-monitoring-tab-button')
        ->and($quarter)->not->toBeNull()
        ->and($quarter->textContent)->toContain('Reviewed', 'View')
        ->and($xpath->query('./th | ./td', $quarter)->length)->toBe(5)
        ->and($xpath->query('.//*[@data-quarter-deadline]', $quarter)->length)->toBe(1)
        ->and($xpath->query('.//*[@data-quarter-targets]', $quarter)->length)->toBe(1)
        ->and($xpath->query('.//*[@data-monitoring-report-status]', $quarter)->item(0)->getAttribute('class'))->not->toContain('sr-only')
        ->and($xpath->query('.//button[@data-monitoring-action]/*[local-name()="svg"]', $quarter)->length)->toBe(1)
        ->and($xpath->query('.//button[@data-monitoring-action]', $quarter)->item(0)->getAttribute('title'))->toContain('Reviewed')
        ->and($xpath->query('//*[@id="quarterly-reports"]')->item(0)->textContent)->not->toContain('reporting periods')
        ->and($xpath->query('.//*[@data-progress-quarter="1"]//button', $quarter)->item(0)->getAttribute('aria-controls'))
        ->toBe('narrative-report-'.$progress->id)
        ->and($xpath->query('//*[@id="narrative-report-'.$progress->id.'"]')->item(0)->getAttribute('x-show'))
        ->toBe("openReportId === 'narrative-report-{$progress->id}'")
        ->and($xpath->query('//*[@id="monitoring-tool-'.$tool->id.'"]')->item(0)->getAttribute('x-show'))
        ->toBe("openReportId === 'monitoring-tool-{$tool->id}'")
        ->and($xpath->query('//*[@data-progress-report-detail]//*[@data-quarter-report-heading]')->item(0)->getAttribute('class'))
        ->toBe($xpath->query('//*[@data-monitoring-report-detail]//*[@data-quarter-report-heading]')->item(0)->getAttribute('class'))
        ->and($xpath->query('//*[@data-progress-report-detail]//*[@data-quarter-report-actions]')->item(0)->getAttribute('class'))
        ->toBe($xpath->query('//*[@data-monitoring-report-detail]//*[@data-quarter-report-actions]')->item(0)->getAttribute('class'))
        ->and($xpath->query('//*[@data-progress-report-reader-link]')->item(0)->getAttribute('href'))
        ->toBe(route('project-narrative-reports.show', $progress))
        ->and($xpath->query('//*[@data-quarter-pdf-preview-modal and @data-report-kind="progress"]')->length)->toBe(1)
        ->and($xpath->query('//*[@data-submitted-report-preview-modal]//*[@data-submitted-report-preview]')->length)->toBe(2)
        ->and($xpath->query('//*[@data-submitted-report-preview-modal]//*[@aria-label="Document zoom controls"]')->length)->toBe(2)
        ->and($xpath->query('//*[@data-submitted-report-preview-modal]//*[contains(@class, "proposal-preview-dock-heading")]')->length)->toBe(2)
        ->and($xpath->query('//*[@data-progress-report-detail]//*[@data-submitted-report-paper]')->length)->toBe(1)
        ->and($xpath->query('//*[@data-monitoring-report-detail]//*[@data-submitted-report-paper]')->length)->toBe(1)
        ->and($xpath->query('//*[@data-progress-report-detail]//*[@role="progressbar"]')->item(0)?->getAttribute('aria-valuenow'))->toBe('25')
        ->and($xpath->query('//*[@data-reporting-quarter="1"]//*[@role="progressbar"]')->length)->toBe(0)
        ->and($xpath->query('//*[@data-progress-report-detail]')->item(0)->textContent)->not->toContain('Pilot complete.', 'Validated maintenance records.')
        ->and($terminalSection)->not->toBeNull()
        ->and($terminalSection->textContent)->toContain('Reviewed', 'Signed terminal PDF uploaded')
        ->and($xpath->query('//*[@data-project-record-menu]//*[@role="tab"]')->length)->toBe($workspace === 'research_head' ? 4 : 3)
        ->and($xpath->query('//*[@data-project-record-menu]')->item(0)->hasAttribute('open'))->toBeFalse()
        ->and($xpath->query('//*[@id="terminal-reports"]//ol')->length)->toBe(0)
        ->and($xpath->query('//*[@id="terminal-reports"]//a')->length)->toBe(1)
        ->and($xpath->query('//*[@id="narrative-report-history"]//*[@id="narrative-report-'.$olderProgress->id.'"]')->length)->toBe(1)
        ->and($olderProgress->fresh()->reporting_quarter)->toBeNull()
        ->and($xpath->query('//*[@id="monitoring-tool-'.$tool->id.'"]')->length)->toBe(1)
        ->and($xpath->query('//*[@id="narrative-report-'.$progress->id.'"]')->length)->toBe(1)
        ->and($xpath->query('//*[@id="narrative-report-'.$terminal->id.'"]')->length)->toBe(1)
        ->and($xpath->query('//*[@id="project-monitoring"]//form')->length)->toBe(0)
        ->and($xpath->query('//*[@data-project-details]')->length)->toBe(0)
        ->and($xpath->query('//*[@data-monitoring-budget-section]')->length)->toBe($workspace === 'research_head' ? 0 : 1)
        ->and($xpath->query('//*[@data-monitoring-notice-link]')->item(0)->getAttribute('class'))->toContain('monitoring-secondary-action')
        ->and($xpath->query('//*[@id="narrative-report-history"]')->item(0)->getAttribute('class'))->toContain('monitoring-disclosure')
        ->and($xpath->query('//*[@id="project-monitoring"]//a[contains(@href, "/create")]')->length)->toBe(0);
    $response->assertDontSee('Earlier Progress Reports without a recorded quarter')
        ->assertDontSee('Report submitted')
        ->assertDontSee('Research Head reviewed');

    foreach ([['monitoring-tool', $tool->id], ['narrative-report', $progress->id]] as [$prefix, $id]) {
        $panel = $xpath->query('//*[@id="'.$prefix.'-'.$id.'"]')->item(0);
        expect($xpath->query('.//button[@data-close-quarter-report and @aria-label="Close report"]', $panel)->length)->toBe(1)
            ->and($xpath->query('.//*[@data-quarter-report-heading]//*[contains(@class, "report-review-status")]/*[local-name()="svg"]', $panel)->length)->toBe(1);
    }
    foreach (['data-monitoring-report-status', 'data-narrative-report-status'] as $attribute) {
        $badge = $xpath->query('.//*[@'.$attribute.']', $quarter)->item(0);
        expect($badge->getAttribute('class'))->toContain('report-review-status')
            ->and($xpath->query('./*[local-name()="svg"]', $badge)->length)->toBe(1)
            ->and($badge->getAttribute('title'))->toContain('Reviewed')
            ->and($xpath->query('./span[@aria-hidden="true"]', $badge)->item(0)->textContent)->toBe('Accepted');
    }
    expect($xpath->query('//*[@data-monitoring-report-detail]')->item(0)->textContent)->not->toContain('From this submitted Monitoring Tool.');

    foreach (['monitoring' => route('project-progress.monitoring-tool.view', $tool), 'progress' => route('project-narrative-reports.view', $progress)] as $kind => $url) {
        $paper = $xpath->query('//*[@data-'.$kind.'-report-detail]//*[@data-submitted-report-paper]')->item(0);
        $configuration = json_decode($paper->getAttribute('data-pdf-annotation-config'), true);
        expect($configuration['pdfUrl'])->toBe($url)
            ->and($configuration['canAnnotate'])->toBeFalse()
            ->and($configuration['loadWhenVisible'])->toBeTrue()
            ->and($configuration['firstPageOnly'] ?? false)->toBeTrue()
            ->and($configuration['wheelZoom'])->toBeFalse()
            ->and($xpath->query('.//*[@aria-label="Document zoom controls"]', $paper)->length)->toBe(0)
            ->and($xpath->query('.//button[@data-report-paper-maximize]', $paper)->length)->toBe(1);
    }

    foreach ($xpath->query('//*[@data-submitted-report-preview]') as $preview) {
        $configuration = json_decode($preview->getAttribute('data-pdf-annotation-config'), true);
        expect($preview->getAttribute('x-init'))->toBe("previewFit = 'width'")
            ->and($configuration['maxZoom'])->toBe(400);
    }

    if (getenv('ATHENA_EXPORT_MONITORING_LAYOUT') === '1') {
        file_put_contents(storage_path('framework/testing/monitoring-completed-'.$workspace.'.html'), $response->getContent());
    }

    $this->actingAs($this->secretary)->withSession([User::ACTIVE_WORKSPACE_SESSION_KEY => User::WORKSPACE_RESEARCH_SECRETARY])
        ->get(route('topics.show', $this->topic))->assertSuccessful()
        ->assertDontSee('data-progress-report-detail', false)->assertDontSee('Validated maintenance records.');
    $this->get(route('project-narrative-reports.show', $progress))->assertForbidden();
})->with(['faculty_researcher', 'research_head']);

test('historical progress stays inside its matching report including zero and missing values', function (string $workspace) {
    $this->withoutVite();
    $this->topic->update(['notice_to_proceed_issued_at' => '2026-01-15', 'estimated_duration_months' => 9]);
    $this->travelTo('2026-10-15 10:00:00');
    $reports = [];
    foreach ([1 => 0, 2 => 60, 3 => null] as $quarter => $percentage) {
        $date = [1 => '2026-04-14', 2 => '2026-07-14', 3 => '2026-10-14'][$quarter];
        $reports[$quarter] = $this->topic->narrativeReports()->create([
            'submitted_by' => $this->researcher->id, 'report_type' => 'progress',
            'submission_date' => now(), 'reporting_quarter' => $quarter, 'reporting_date' => $date,
            'researchers' => $this->researcher->name, 'budget' => 50000, 'funding_agency' => 'University',
            'implementation_start' => '2026-01-15', 'implementation_end' => '2026-10-14',
            'accomplishment_summary' => 'Quarterly activities documented.', 'introduction' => 'Implementation.',
            'objectives' => 'Complete fieldwork.', 'methodology' => 'Site visits.',
            'results_discussion' => 'Activities documented.', 'photos' => [],
        ]);
        if ($percentage !== null) {
            $this->topic->progressReports()->create([
                'submitted_by' => $this->researcher->id, 'reporting_date' => $date,
                'version_number' => 1, 'progress_percentage' => $quarter === 2 ? 25 : $percentage,
                'accomplishments' => 'Submitted activities.',
            ]);
            if ($quarter === 2) {
                $this->topic->progressReports()->create([
                    'submitted_by' => $this->researcher->id, 'reporting_date' => $date,
                    'version_number' => 2, 'progress_percentage' => $percentage,
                    'accomplishments' => 'Corrected activities.',
                ]);
            }
        }
        $this->topic->preparedProgressReports()->create([
            'submitted_by' => $this->researcher->id, 'reporting_date' => $date,
            'submission_status' => 'prepared', 'progress_percentage' => 95,
            'accomplishments' => 'Unsubmitted draft.',
        ]);
    }

    $response = $this->actingAs($workspace === 'research_head' ? $this->head : $this->researcher)
        ->withSession([User::ACTIVE_WORKSPACE_SESSION_KEY => $workspace])
        ->get(route('topics.show', $this->topic))->assertSuccessful();
    $document = new DOMDocument;
    @$document->loadHTML($response->getContent());
    $xpath = new DOMXPath($document);
    foreach ([1 => '0', 2 => '60'] as $quarter => $percentage) {
        expect($xpath->query('//*[@data-reporting-quarter="'.$quarter.'"]//*[@role="progressbar"]')->length)->toBe(0)
            ->and($xpath->query('//*[@id="narrative-report-'.$reports[$quarter]->id.'"]//*[@role="progressbar"]')->item(0)?->getAttribute('aria-valuenow'))->toBe($percentage)
            ->and($xpath->query('//*[@id="narrative-report-'.$reports[$quarter]->id.'"]')->item(0)->textContent)->toContain('Recorded project progress', 'From the Q'.$quarter.' Monitoring Tool');
    }
    $missing = $xpath->query('//*[@id="narrative-report-'.$reports[3]->id.'"]')->item(0);
    expect($xpath->query('//*[@data-reporting-quarter="3"]//*[@role="progressbar"]')->length)->toBe(0)
        ->and($xpath->query('.//*[@role="progressbar"]', $missing)->length)->toBe(0)
        ->and($missing->textContent)->toContain('No monitoring progress recorded for this quarter.');
})->with(['faculty_researcher', 'research_head']);

test('progress quarter rows keep reviewed reports and revision actions in their own period', function () {
    $this->withoutVite();
    $this->topic->update(['notice_to_proceed_issued_at' => '2026-01-15', 'estimated_duration_months' => 9]);
    $this->travelTo(now()->setDate(2026, 10, 15)->startOfDay());
    foreach ([1 => 'reviewed', 2 => 'revision_requested'] as $quarter => $status) {
        $this->topic->narrativeReports()->create([
            'submitted_by' => $this->researcher->id, 'report_type' => 'progress',
            'submission_date' => now(), 'reporting_quarter' => $quarter,
            'reporting_date' => $quarter === 1 ? '2026-04-14' : '2026-07-14',
            'review_status' => $status,
            'researchers' => $this->researcher->name,
            'implementation_start' => '2026-01-15', 'implementation_end' => '2026-10-14',
            'budget' => 50000, 'funding_agency' => 'Batangas State University',
            'accomplishment_summary' => 'Quarterly activities completed.',
            'introduction' => 'Quarterly implementation.', 'objectives' => 'Complete fieldwork.',
            'methodology' => 'Site visits.', 'results_discussion' => 'Activities documented.', 'photos' => [],
        ]);
    }
    $response = $this->actingAs($this->researcher)->get(route('topics.show', $this->topic))->assertOk();
    $document = new DOMDocument;
    @$document->loadHTML($response->getContent());
    $xpath = new DOMXPath($document);
    expect($xpath->query('//*[@data-progress-quarter="1"]//button')->length)->toBe(1)
        ->and($xpath->query('//*[@data-progress-quarter="1"]//a')->length)->toBe(0)
        ->and($xpath->query('//*[@data-progress-quarter="1"]')->item(0)->textContent)->toContain('Reviewed')
        ->and($xpath->query('//*[@data-progress-quarter="2"]')->item(0)->textContent)->toContain('Corrections requested', 'Revise report')
        ->and($xpath->query('//*[@data-progress-quarter="2"]//a[contains(., "Revise report")]')->item(0)->getAttribute('href'))
        ->toBe(route('project-narrative-reports.create', ['topic' => $this->topic, 'report_type' => 'progress', 'reporting_date' => '2026-07-14']))
        ->and($xpath->query('//*[@data-progress-quarter="3"]')->item(0)->textContent)->toContain('Overdue', 'Start');

    foreach ([1, 2, 3] as $quarter) {
        expect($xpath->query('//*[@data-reporting-quarter="'.$quarter.'"]//*[@data-quarter-overdue]')->length)->toBe(1);
    }
    expect($xpath->query('//*[@data-progress-quarter="3"]//*[@data-narrative-report-status]/span[@aria-hidden="true"]')->item(0)?->textContent)->toBe('Missing');

    if (getenv('ATHENA_EXPORT_MONITORING_LAYOUT') === '1') {
        file_put_contents(storage_path('framework/testing/monitoring-active.html'), $response->getContent());
    }

    $this->topic->progressReports()->create([
        'submitted_by' => $this->researcher->id, 'reporting_date' => '2026-04-14',
        'progress_percentage' => 25, 'accomplishments' => 'First-quarter activities.',
    ]);
    $completePair = $this->get(route('topics.show', $this->topic))->assertOk();
    @$document->loadHTML($completePair->getContent());
    $xpath = new DOMXPath($document);
    expect($xpath->query('//*[@data-reporting-quarter="1"]//*[@data-quarter-overdue]')->length)->toBe(0)
        ->and($xpath->query('//*[@data-reporting-quarter="3"]//*[@data-quarter-overdue]')->length)->toBe(1);

    $prepared = $this->topic->narrativeReports()->first()->replicate();
    $prepared->fill(['reporting_quarter' => 3, 'reporting_date' => '2026-10-14', 'submission_status' => 'prepared', 'review_status' => 'pending'])->save();
    $this->get(route('topics.show', $this->topic))->assertOk()->assertSee('PDF prepared')->assertSee('Review and submit');
    $this->actingAs($this->head)->withSession([User::ACTIVE_WORKSPACE_SESSION_KEY => User::WORKSPACE_RESEARCH_HEAD])
        ->get(route('topics.show', $this->topic))->assertOk()->assertDontSee('id="narrative-report-'.$prepared->id.'"', false);
});

test('prepared monitoring tools keep budget actions with their reporting quarter', function () {
    $this->withoutVite();
    $this->topic->update(['research_secretary_id' => $this->secretary->id]);
    $report = $this->topic->preparedProgressReports()->create([
        'submitted_by' => $this->researcher->id, 'submission_status' => 'prepared',
        'reporting_date' => now()->subDay(), 'progress_percentage' => 25,
        'accomplishments' => 'Budget confirmation pending.',
    ]);
    $response = $this->actingAs($this->researcher)->get(route('topics.show', $this->topic))->assertSuccessful();
    $document = new DOMDocument;
    @$document->loadHTML($response->getContent());
    $xpath = new DOMXPath($document);
    $quarter = $xpath->query('//*[@data-reporting-quarter="1"]')->item(0);

    expect($quarter)->not->toBeNull()
        ->and($quarter->textContent)->toContain('PDF prepared', 'Budget', 'Submit')
        ->and($xpath->query('.//*[@data-monitoring-report-status]/span[@aria-hidden="true"]', $quarter)->item(0)->textContent)->toBe('Prepared')
        ->and($xpath->query('.//a[@aria-label="Complete budget"]', $quarter)->item(0)->getAttribute('href'))
        ->toBe(route('project-budget.edit', [$this->topic, $report]))
        ->and($xpath->query('//*[@data-monitoring-budget-section]')->item(0)->textContent)->toContain('completes and confirms budget utilization')
        ->and($xpath->query('//*[@data-monitoring-budget-task="'.$report->id.'"]')->item(0)->getAttribute('href'))
        ->toBe(route('project-budget.edit', [$this->topic, $report]));
    $this->actingAs($this->secretary)->withSession([User::ACTIVE_WORKSPACE_SESSION_KEY => User::WORKSPACE_RESEARCH_SECRETARY])
        ->get(route('topics.show', $this->topic))->assertSuccessful()
        ->assertSee(route('research_secretary.projects.budget.edit', [$this->topic, $report]), false)
        ->assertDontSee('data-project-secretary-picker', false);
    $this->actingAs($this->head)->withSession([User::ACTIVE_WORKSPACE_SESSION_KEY => User::WORKSPACE_RESEARCH_HEAD])
        ->get(route('topics.show', $this->topic))->assertSuccessful()
        ->assertDontSee(route('project-budget.edit', [$this->topic, $report]), false);
});

test('report schedule blocks early submissions and opens terminal after project end', function () {
    $this->withoutVite();
    $this->topic->update(['notice_to_proceed_issued_at' => '2026-01-15', 'estimated_duration_months' => 6]);
    $this->travelTo(now()->setDate(2026, 4, 7)->endOfDay());
    $this->actingAs($this->researcher)->post(route('project-progress.prepare', $this->topic), ($this->monitoringPayload)(['reporting_date' => '2026-04-14']))
        ->assertSessionHasErrors('reporting_date');
    $this->get(route('project-narrative-reports.create', ['topic' => $this->topic, 'report_type' => 'terminal']))->assertOk()->assertSee('Save draft')->assertSee('PDF preparation and submission open Jul 15, 2026.');
    $this->get(route('topics.show', $this->topic))->assertOk()->assertSee('Monitoring Tool')->assertSee('Fill draft')->assertSee('Submission opens');
    $this->travelTo(now()->setDate(2026, 4, 8)->startOfDay());
    $this->actingAs($this->researcher)->post(route('project-progress.prepare', $this->topic), ($this->monitoringPayload)(['reporting_date' => '2026-04-14']))->assertSessionHasNoErrors()->assertRedirect(route('project-progress.create', ['topic' => $this->topic, 'reporting_date' => '2026-04-14']));
    $report = ProjectProgressReport::where('topic_id', $this->topic->id)->firstOrFail();
    expect($report->period_start->toDateString())->toBe('2026-01-15')
        ->and($report->period_end->toDateString())->toBe('2026-04-14');
    ($this->attachSubmissionProof)($report);
    $this->post(route('project-progress.submit-prepared', [$this->topic, $report]))->assertSessionHasNoErrors();
    expect($report->fresh()->isSubmitted())->toBeTrue();
    $this->travelTo(now()->setDate(2026, 7, 15)->startOfDay());
    $this->get(route('project-narrative-reports.create', ['topic' => $this->topic, 'report_type' => 'terminal']))->assertOk()->assertSee('Terminal report')->assertSee('value="terminal"', false);
});

test('quarterly report rows show the deadline and distinguish due reports from overdue reports', function (string $today, string $status, bool $prepared = false, bool $correction = false) {
    $this->withoutVite();
    $this->travelTo(CarbonImmutable::parse($today));
    $this->topic->update(['notice_to_proceed_issued_at' => '2026-01-01']);
    if ($prepared) {
        $monitoring = $this->topic->preparedProgressReports()->make([
            'submitted_by' => $this->researcher->id, 'reporting_date' => '2026-03-31',
            'reporting_quarter' => 1, 'period_start' => '2026-01-01', 'period_end' => '2026-03-31',
            'submission_status' => 'prepared', 'progress_percentage' => 25, 'accomplishments' => 'Prepared results.',
        ]);
        $progress = $this->topic->narrativeReports()->make([
            'submitted_by' => $this->researcher->id, 'report_type' => 'progress', 'reporting_date' => '2026-03-31',
            'reporting_quarter' => 1, 'period_start' => '2026-01-01', 'period_end' => '2026-03-31',
            'submission_status' => 'prepared', 'submission_date' => '2026-03-25',
            'researchers' => $this->researcher->name, 'implementation_start' => '2026-01-01',
            'implementation_end' => '2026-12-31', 'budget' => 50000, 'funding_agency' => 'University',
            'accomplishment_summary' => 'Prepared results.',
            'introduction' => 'Introduction', 'objectives' => 'Objectives', 'methodology' => 'Methods',
            'results_discussion' => 'Prepared results.', 'photos' => [],
        ]);
        if ($correction) {
            $original = $monitoring->replicate()->fill(['submission_status' => 'submitted', 'submitted_at' => '2026-03-31', 'review_status' => 'revision_requested']);
            $original->save();
            $monitoring->fill(['supersedes_report_id' => $original->id, 'version_number' => 2]);
            $progress->replicate()->fill(['submission_status' => 'submitted', 'submitted_at' => '2026-03-31', 'review_status' => 'revision_requested', 'version_number' => 1])->save();
            $progress->version_number = 2;
        }
        $monitoring->save();
        $progress->save();
    }
    $response = $this->actingAs($this->researcher)->get(route('topics.show', $this->topic))->assertOk();
    $document = new DOMDocument;
    @$document->loadHTML($response->getContent());
    $xpath = new DOMXPath($document);

    expect($xpath->query('//*[@data-reporting-quarter="1"]//*[@data-reporting-deadline]')->item(0)->textContent)->toBe('Deadline Mar 31, 2026');
    foreach (['data-monitoring-quarter', 'data-progress-quarter'] as $attribute) {
        expect($xpath->query('//*[@'.$attribute.'="1"]')->item(0)->textContent)->toContain($status);
        expect($xpath->query('//*[@'.$attribute.'="1"]//*[contains(@class, "report-review-status")]/span[@aria-hidden="true"]')->item(0)?->textContent)->toBe($prepared ? 'Prepared' : 'Missing');
        if ($correction) {
            expect($xpath->query('//*[@'.$attribute.'="1"]')->item(0)->textContent)->not->toContain('Overdue');
        }
    }
    expect($xpath->query('//*[@data-reporting-quarter="1"]//*[@data-quarter-overdue]')->length)->toBe(str_contains($status, 'Overdue') ? 1 : 0);
})->with([
    'submission week' => ['2026-03-25', 'Due Mar 31, 2026'],
    'deadline day' => ['2026-03-31 23:59:59', 'Due Mar 31, 2026'],
    'overdue' => ['2026-04-01', 'Overdue'],
    'prepared on the deadline' => ['2026-03-31 23:59:59', 'PDF prepared', true],
    'prepared but overdue' => ['2026-04-01', 'PDF prepared · Overdue', true],
    'on-time report prepared for correction' => ['2026-04-03', 'PDF prepared', true, true],
]);

test('researchers can fill and save all three report drafts before submission opens', function () {
    $this->withoutVite();
    $this->topic->update(['notice_to_proceed_issued_at' => '2026-01-15', 'estimated_duration_months' => 6]);
    $this->travelTo(now()->setDate(2026, 2, 10)->startOfDay());
    $this->actingAs($this->researcher);

    $this->get(route('research.show', $this->topic))->assertOk()->assertSee('Fill draft')
        ->assertSee('Draft now. Submission opens');
    $monitoring = $this->get(route('project-progress.create', $this->topic))->assertOk()
        ->assertSee('data-monitoring-tool-autosave-form', false)->assertSee('Save draft')->assertSee('Apr 8, 2026');
    $progress = $this->get(route('project-narrative-reports.create', $this->topic))->assertOk()
        ->assertSee('data-narrative-progress-autosave-form', false)->assertSee('Save draft')->assertSee('Apr 8, 2026');
    $terminal = $this->get(route('project-narrative-reports.create', ['topic' => $this->topic, 'report_type' => 'terminal']))->assertOk()
        ->assertSee('terminal_data[abstract]', false)->assertSee('Save draft')->assertSee('Jul 15, 2026');
    foreach ([$monitoring, $progress, $terminal] as $response) {
        $response->assertSee('submissionOpen: false', false)
            ->assertDontSee('Submit to Research Office Secretary')->assertDontSee('Submit to Research Head');
    }

    $this->postJson(route('project-progress.draft', $this->topic), ['draft_version' => 0, 'reporting_date' => '2026-04-14', 'tracking_number' => 'Early monitoring draft'])
        ->assertOk();
    foreach (['progress', 'terminal'] as $type) {
        $this->postJson(route('project-narrative-reports.draft', $this->topic), ['draft_version' => 0, 'report_type' => $type, 'reporting_date' => $type === 'progress' ? '2026-04-14' : null, 'introduction' => 'Saved '.$type.' draft'])->assertOk();
        $this->get(route('project-narrative-reports.create', ['topic' => $this->topic, 'report_type' => $type]))->assertOk()->assertSee('Saved '.$type.' draft');
    }
    $this->get(route('project-progress.create', $this->topic))->assertOk()->assertSee('Early monitoring draft');
    expect(ProjectMonitoringDraft::count())->toBe(1)
        ->and(ProjectNarrativeReportDraft::count())->toBe(2)
        ->and(ProjectProgressReport::count())->toBe(0)
        ->and(ProjectNarrativeReport::count())->toBe(0);
    $this->postJson(route('project-progress.draft', $this->topic), ['draft_version' => 1, 'reporting_date' => '2026-07-14'])->assertUnprocessable()->assertJsonValidationErrors('reporting_date');
    $this->postJson(route('project-narrative-reports.draft', $this->topic), ['draft_version' => 1, 'report_type' => 'progress', 'reporting_date' => '2026-07-14'])->assertUnprocessable()->assertJsonValidationErrors('reporting_date');
    $this->post(route('project-progress.prepare', $this->topic), ($this->monitoringPayload)(['reporting_date' => '2026-04-14']))->assertSessionHasErrors('reporting_date');
    $this->post(route('project-narrative-reports.prepare', $this->topic), ['report_type' => 'terminal'])->assertForbidden();
    Notification::assertNothingSent();
});

test('all report forms open and save before the approved project start without enabling submission', function () {
    $this->withoutVite();
    $this->travelTo(now()->setDate(2026, 1, 1)->startOfDay());
    $this->topic->update([
        'notice_to_proceed_issued_at' => '2026-01-01', 'estimated_duration_months' => 6,
        'notice_to_proceed_data' => ['approved_start_date' => '2026-02-01', 'approved_end_date' => '2026-07-31'],
    ]);
    $this->actingAs($this->researcher);
    $this->get(route('research.show', $this->topic))->assertOk()->assertSee('Fill draft');
    $date = '2026-07-31';
    $this->get(route('project-progress.create', ['topic' => $this->topic, 'reporting_date' => $date]))
        ->assertOk()->assertSee('Save draft')->assertSee('submissionOpen: false', false);
    $this->postJson(route('project-progress.draft', $this->topic), [
        'draft_version' => 0, 'reporting_date' => $date, 'tracking_number' => 'Future monitoring sample',
    ])->assertOk();
    foreach (['progress', 'terminal'] as $type) {
        $this->get(route('project-narrative-reports.create', ['topic' => $this->topic, 'report_type' => $type, 'reporting_date' => $date]))
            ->assertOk()->assertSee('Save draft')->assertSee('submissionOpen: false', false);
        $this->postJson(route('project-narrative-reports.draft', $this->topic), [
            'draft_version' => 0, 'report_type' => $type, 'reporting_date' => $type === 'progress' ? $date : null,
            'introduction' => 'Future '.$type.' sample',
        ])->assertOk();
        $this->get(route('project-narrative-reports.create', ['topic' => $this->topic, 'report_type' => $type]))
            ->assertOk()->assertSee('Future '.$type.' sample');
    }
    $this->get(route('project-progress.create', $this->topic))->assertOk()->assertSee('Future monitoring sample');
    $this->postJson(route('project-progress.draft', $this->topic), ['draft_version' => 1, 'reporting_date' => '2026-08-01'])
        ->assertUnprocessable()->assertJsonValidationErrors('reporting_date');
    $this->postJson(route('project-narrative-reports.draft', $this->topic), ['draft_version' => 1, 'report_type' => 'progress', 'reporting_date' => '2026-08-01'])
        ->assertUnprocessable()->assertJsonValidationErrors('reporting_date');
    $this->post(route('project-progress.prepare', $this->topic), ($this->monitoringPayload)(['reporting_date' => $date]))
        ->assertSessionHasErrors('reporting_date');
    $this->post(route('project-narrative-reports.prepare', $this->topic), ['report_type' => 'terminal'])->assertForbidden();
    expect(ProjectMonitoringDraft::count())->toBe(1)
        ->and(ProjectNarrativeReportDraft::count())->toBe(2)
        ->and(ProjectProgressReport::count())->toBe(0)
        ->and(ProjectNarrativeReport::count())->toBe(0);
    Notification::assertNothingSent();
});

test('unfinished quarterly drafts reopen and cannot be replaced with another quarter', function () {
    $this->withoutVite();
    $this->topic->update(['notice_to_proceed_issued_at' => '2026-01-15', 'estimated_duration_months' => 6]);
    $this->travelTo(now()->setDate(2026, 5, 10)->startOfDay());
    $this->actingAs($this->researcher);
    $this->postJson(route('project-progress.draft', $this->topic), ['draft_version' => 0, 'reporting_date' => '2026-04-14', 'tracking_number' => 'Keep monitoring'])->assertOk();
    $this->postJson(route('project-narrative-reports.draft', $this->topic), ['draft_version' => 0, 'report_type' => 'progress', 'reporting_date' => '2026-04-14', 'introduction' => 'Keep progress'])->assertOk();
    $this->get(route('project-progress.create', ['topic' => $this->topic, 'reporting_date' => '2026-07-14']))
        ->assertRedirect(route('project-progress.create', ['topic' => $this->topic, 'reporting_date' => '2026-04-14']));
    $this->get(route('project-narrative-reports.create', ['topic' => $this->topic, 'reporting_date' => '2026-07-14']))
        ->assertRedirect(route('project-narrative-reports.create', ['topic' => $this->topic, 'report_type' => 'progress', 'reporting_date' => '2026-04-14']));
    $this->postJson(route('project-progress.draft', $this->topic), ['draft_version' => 1, 'reporting_date' => '2026-07-14'])->assertUnprocessable()->assertJsonValidationErrors('reporting_date');
    $this->postJson(route('project-narrative-reports.draft', $this->topic), ['draft_version' => 1, 'report_type' => 'progress', 'reporting_date' => '2026-07-14'])->assertUnprocessable()->assertJsonValidationErrors('reporting_date');
    expect(ProjectMonitoringDraft::sole()->source_data['tracking_number'])->toBe('Keep monitoring')
        ->and(ProjectNarrativeReportDraft::sole()->source_data['introduction'])->toBe('Keep progress');
    $this->travelTo(now()->setDate(2026, 7, 15)->startOfDay());
    $this->post(route('project-progress.prepare', $this->topic), ($this->monitoringPayload)(['reporting_date' => '2026-07-14']))->assertSessionHasErrors('reporting_date');
    expect(ProjectMonitoringDraft::sole()->source_data['tracking_number'])->toBe('Keep monitoring')
        ->and(ProjectProgressReport::count())->toBe(0);
});

test('a researcher prepares an official monitoring PDF before submitting it to the Research Office Secretary', function () {
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

    ($this->attachSubmissionProof)($report);
    $this->actingAs($this->researcher)
        ->post(route('project-progress.submit-prepared', [$this->topic, $report]))
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    expect($report->fresh()->submission_status)->toBe(ProjectProgressReport::SUBMISSION_STATUS_SUBMITTED)
        ->and($this->pdfConverter->conversionCount)->toBe(1);
    Notification::assertSentTo(
        $this->officeSecretary,
        ProposalActivityNotification::class,
        fn (ProposalActivityNotification $notification): bool => $notification->workspace === User::WORKSPACE_RESEARCH_SECRETARY
            && $notification->url === route('topics.show', $this->topic).'#monitoring-tool-'.$report->id,
    );
    Notification::assertNotSentTo($this->head, ProposalActivityNotification::class);
});

test('the project header highlights journal search without repeating the monitoring status', function () {
    $response = $this->actingAs($this->researcher)
        ->get(route('research.show', $this->topic))
        ->assertOk()
        ->assertSee('data-find-journals', false);

    $document = new DOMDocument;
    @$document->loadHTML($response->getContent());
    $xpath = new DOMXPath($document);
    $journalLink = $xpath->query('//a[@data-find-journals]')->item(0);
    $actions = $xpath->query('//a[@data-find-journals]/parent::*')->item(0);

    expect($actions)->not->toBeNull();
    expect($journalLink->getAttribute('href'))->toBe(route('research-support.index', ['topic' => $this->topic->id]).'#journal-finder');
    expect($journalLink->hasAttribute('wire:navigate'))->toBeTrue();
    expect($actions->textContent)->toContain('Find journals')->not->toContain('Project monitoring');

    $this->topic->update(['notice_to_proceed_issued_at' => null]);

    $this->get(route('research.show', $this->topic))
        ->assertOk()
        ->assertSee('Final signing');
});

test('the faculty project page opens the monitoring tool in a focused form page', function () {
    $this->actingAs($this->researcher)
        ->get(route('research.show', $this->topic))
        ->assertOk()
        ->assertSeeInOrder([
            'data-project-monitoring-heading', 'Project monitoring',
            'data-project-monitoring-details', 'Monitoring starts', 'Project ends', 'Next submission',
        ], false)
        ->assertSee('Monitoring Tool')
        ->assertSee('Submit both documents each quarter.')
        ->assertSee('Progress Report')
        ->assertDontSee('Quarterly progress reports.')
        ->assertSee('Start tool')
        ->assertSee(route('project-progress.create', $this->topic), false)
        ->assertDontSee('data-monitoring-tool-autosave-form', false);

    $form = $this->actingAs($this->researcher)
        ->get(route('project-progress.create', $this->topic))
        ->assertOk()
        ->assertSee('Submit monitoring tool')
        ->assertSee('Prepare official PDF')
        ->assertSee('Preview paper')
        ->assertSee('Changes save automatically.')
        ->assertSee('Exit monitoring')
        ->assertSee('data-paper-cancel-exit', false)
        ->assertSee('data-monitoring-writing-toolbar', false)
        ->assertSee('data-proposal-workspace-toolbar', false)
        ->assertDontSee('data-monitoring-action-dock-fixed', false)
        ->assertSee('data-proposal-autosave-status', false)
        ->assertSee('data-monitoring-tool-autosave-form', false)
        ->assertSee('x-ref="previewFrame"', false)
        ->assertSee('Approved Activities')
        ->assertSee('Add Activity')
        ->assertSee('data-monitoring-workspace', false)
        ->assertSee('x-ref="activityList"', false)
        ->assertSee('data-monitoring-activity', false)
        ->assertSee('Progress this quarter')
        ->assertSee('Spending this quarter')
        ->assertSee('Purchase Request')
        ->assertSee('Request of Payment');

    $document = new DOMDocument;
    @$document->loadHTML($form->getContent());
    $xpath = new DOMXPath($document);
    expect($xpath->query('//*[@data-monitoring-workspace]')->item(0)->getAttribute('class'))->not->toContain('max-w-4xl');
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
        ->assertSee('From your approved work plan')
        ->assertSee('Review each planned activity and record what you accomplished this quarter.');

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

test('the monitoring preview contains the same filled paper as the official template', function () {
    $this->researcher->update(['name' => 'María Peña']);
    $response = $this->actingAs($this->researcher)
        ->post(route('project-progress.preview', $this->topic), ($this->monitoringPayload)())
        ->assertSuccessful();

    $preview = new DOMDocument;
    @$preview->loadHTML('<?xml encoding="UTF-8">'.$response->getContent());
    $previewXPath = new DOMXPath($preview);
    $documentPath = tempnam(sys_get_temp_dir(), 'monitoring-preview-test-');
    file_put_contents($documentPath, app(MonitoringToolDocumentService::class)->generate($response->viewData('report')));
    $archive = new ZipArchive;

    try {
        expect($archive->open($documentPath))->toBeTrue();
        $document = new DOMDocument;
        $document->loadXML($archive->getFromName('word/document.xml'));
        $xpath = new DOMXPath($document);
        $xpath->registerNamespace('w', 'http://schemas.openxmlformats.org/wordprocessingml/2006/main');
        $paperText = implode('', array_map(fn (DOMNode $node): string => $node->textContent, iterator_to_array($xpath->query('/w:document/w:body//w:t'))));
        $normalize = fn (string $text): string => preg_replace('/\s+/u', '', $text);

        expect($normalize($previewXPath->evaluate('string(//main)')))->toBe($normalize($paperText))
            ->and($previewXPath->evaluate('string(//table[contains(@class, "monitoring-budget-table")]/tbody/tr[1]/td[4])'))->toBe('16%')
            ->and($previewXPath->evaluate('string(//table[contains(@class, "monitoring-budget-table")]/tbody/tr[last()]/td[3])'))->toBe('25%');

        if ($fixtureDirectory = getenv('ATHENA_REPORT_TEMPLATE_FIXTURE_DIRECTORY')) {
            File::ensureDirectoryExists($fixtureDirectory);
            File::put($fixtureDirectory.'/monitoring-template-preview.html', $response->getContent());
        }
    } finally {
        $archive->close();
        unlink($documentPath);
    }
});

test('monitoring drafts can be previewed before PDF preparation opens', function (string $today) {
    $this->withoutVite();
    $this->travelTo(CarbonImmutable::parse($today));
    $this->topic->update([
        'notice_to_proceed_issued_at' => '2026-10-03',
        'estimated_duration_months' => 9,
        'notice_to_proceed_data' => ['approved_start_date' => '2026-10-17', 'approved_end_date' => '2027-07-16'],
    ]);
    $this->actingAs($this->researcher);
    $payload = ($this->monitoringPayload)(['reporting_date' => '2027-01-16']);
    $this->postJson(route('project-progress.draft', $this->topic), [...$payload, 'draft_version' => 0])->assertOk();
    $draft = ProjectMonitoringDraft::sole();
    $response = $this->get(route('project-progress.create', ['topic' => $this->topic, 'reporting_date' => '2027-01-16']))
        ->assertOk()->assertSee('submissionOpen: false', false)
        ->assertSee('You can fill, save, and preview this draft now.')
        ->assertSee('Jan 10, 2027')
        ->assertSee('Interviewed 12 participants');
    $document = new DOMDocument;
    @$document->loadHTML($response->getContent());
    $xpath = new DOMXPath($document);
    $previewButton = $xpath->query('//button[@data-proposal-preview-toggle]')->item(0);
    $prepareButton = $xpath->query('//button[span[normalize-space(.)="Prepare official PDF"]]')->item(0);
    expect($previewButton->getAttribute(':disabled'))->toBe('previewLoading || submitting')
        ->and($prepareButton->getAttribute(':disabled'))->toContain('!submissionOpen');

    $this->postJson(route('project-progress.preview', $this->topic), $payload)
        ->assertOk()->assertSee('MONITORING TOOL')->assertSee('Interviewed 12 participants');
    $prepareResponse = $this->post(route('project-progress.prepare', $this->topic), $payload);
    expect($prepareResponse->getStatusCode())->toBe(302);
    $prepareResponse->assertSessionHasErrors('reporting_date');
    $this->postJson(route('project-progress.preview', $this->topic), [...$payload, 'reporting_date' => '2027-07-17'])
        ->assertUnprocessable()->assertJsonValidationErrors('reporting_date');
    $payload['budget_utilization'][0]['actual_amount'] = 10001;
    $this->postJson(route('project-progress.preview', $this->topic), $payload)
        ->assertUnprocessable()->assertJsonValidationErrors('budget_utilization.0.actual_amount');

    expect(ProjectProgressReport::count())->toBe(0)
        ->and($draft->fresh()->source_data)->toBe($draft->source_data)
        ->and($draft->fresh()->lock_version)->toBe(1)
        ->and($this->pdfConverter->conversionCount)->toBe(0);
    Notification::assertNothingSent();
})->with(['before the project starts' => '2026-10-03', 'during the quarter' => '2026-11-20', 'before the final week' => '2027-01-09']);

test('the Research Head topic page shows monitoring in its own tab', function () {
    ProjectProgressReport::create([
        'topic_id' => $this->topic->id,
        'submitted_by' => $this->researcher->id,
        'reporting_date' => now()->subDay(),
        'progress_percentage' => 60,
        'accomplishments' => 'Monitoring interface review fixture.',
    ]);

    $response = $this->actingAs($this->head)
        ->get(route('topics.show', $this->topic))
        ->assertOk()
        ->assertSeeInOrder([
            'data-project-monitoring-heading', 'Project monitoring',
            'data-project-monitoring-details', 'Monitoring starts', 'Project ends', 'Next submission',
        ], false)
        ->assertSee('Review progress')
        ->assertSee('NEXT STEP')
        ->assertDontSee('NEXT ROUTING')
        ->assertSee('All five stages completed.')
        ->assertSee('data-workflow-stage-outcome="5"', false)
        ->assertSee('Notice to Proceed issued')
        ->assertSee('id="version-history-tab-button"', false)
        ->assertSee('id="project-monitoring-tab-button"', false)
        ->assertSee('@click="setTopicTab(\'monitoring\', \'project-monitoring\')"', false)
        ->assertSee('data-project-workspace-navigation', false)
        ->assertSee('data-project-record-menu', false)
        ->assertDontSee("activeTopicTab === 'monitoring' ? 'border-red-600 text-red-600'", false)
        ->assertSee('id="project-monitoring-tab"', false)
        ->assertSee('x-show="activeTopicTab === \'monitoring\'"', false)
        ->assertSee('data-project-status-manager', false)
        ->assertSee('Manage status')
        ->assertSee('x-show="statusManagerOpen"', false)
        ->assertDontSee('absolute bottom-full right-0 mb-3 w-full', false)
        ->assertDontSee('data-monitoring-budget-section', false)
        ->assertSee('x-ref="statusTrigger"', false)
        ->assertDontSee('absolute top-full right-0 mt-3', false)
        ->assertDontSee('sm:w-[22.5rem]', false)
        ->assertDontSee('<details class="rounded-xl border border-gray-200 bg-white p-5 dark:border-slate-700 dark:bg-slate-900">', false)
        ->assertSee('data-monitoring-schedule-table', false)
        ->assertSee('data-monitoring-action', false)
        ->assertSee('View');

    $document = new DOMDocument;
    @$document->loadHTML($response->getContent());
    $xpath = new DOMXPath($document);
    $manager = $xpath->query('//*[@data-project-status-manager]')->item(0);
    expect($manager->getAttribute('class'))->not->toContain('fixed', 'absolute')
        ->and($xpath->query('ancestor::*[@data-monitoring-overview]', $manager)->length)->toBe(1);
    $reviewTab = $xpath->query('//*[@id="proposal-review-tab"]')->item(0);

    expect($reviewTab->getAttribute('x-show'))->toBe("activeTopicTab === 'review'")
        ->and($reviewTab->hasAttribute('x-cloak'))->toBeTrue()
        ->and($xpath->query('//*[@id="proposal-review-tab"]//*[@data-visible-proposal-workflow]')->length)->toBe(1)
        ->and($xpath->query('//*[@data-visible-proposal-workflow][not(ancestor::*[@id="proposal-review-tab"])]')->length)->toBe(0);
});

test('the proposal review sequence remains visible before project monitoring begins', function () {
    $this->topic->update([
        'notice_to_proceed_issued_by' => null,
        'notice_to_proceed_issued_at' => null,
    ]);

    $this->actingAs($this->head)
        ->get(route('topics.show', $this->topic))
        ->assertOk()
        ->assertSee('Review progress')
        ->assertSee('data-proposal-routing-docket', false)
        ->assertSee('data-visible-proposal-workflow', false)
        ->assertDontSee('routingDocketOpen', false)
        ->assertDontSee('Show workflow')
        ->assertSee('data-horizontal-stepper', false)
        ->assertSee('grid-cols-5', false)
        ->assertDontSee('data-monitoring-schedule-table', false);
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
        ->assertSee('Start tool');
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
    $this->get(route('project-progress.create', $this->topic))->assertOk()
        ->assertSee('Only the project leader can submit this report.')->assertDontSee('Submit to Research Office Secretary');
    $this->post(route('project-progress.submit-prepared', [$this->topic, $report]))->assertForbidden();
    expect($report->fresh()->isPrepared())->toBeTrue();
    $this->actingAs($this->researcher)->get(route('project-progress.create', $this->topic))->assertOk()->assertSee('Submit to Research Office Secretary');
    $this->get(route('project-progress.monitoring-tool', $report))->assertOk();
    ($this->attachSubmissionProof)($report);
    $this->post(route('project-progress.submit-prepared', [$this->topic, $report]))->assertSessionHasNoErrors();
    expect($report->fresh()->isSubmitted())->toBeTrue();
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
        ->assertSee('Submit to Research Office Secretary')
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

    $this->actingAs($this->head)
        ->get(route('topics.show', $this->topic))
        ->assertOk()
        ->assertSee('role="progressbar"', false)
        ->assertSee('aria-valuenow="60"', false)
        ->assertSee('aria-label="Download monitoring tool PDF"', false)
        ->assertSee('openReportId:', false)
        ->assertSee('x-show="openReportId === \'monitoring-tool-'.$report->id.'\'"', false)
        ->assertSee('data-monitoring-schedule-table', false)
        ->assertSee('x-bind:aria-expanded="openReportId === \'monitoring-tool-'.$report->id.'\'"', false)
        ->assertSee('View')
        ->assertDontSee('name="research_head_remarks"', false)
        ->assertSee('Progress is acceptable.')
        ->assertDontSee('Monitoring tool versions')
        ->assertDontSee('<input name="research_head_remarks"', false);

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

    ($this->attachSubmissionProof)($original);
    $this->actingAs($this->researcher)
        ->post(route('project-progress.submit-prepared', [$this->topic, $original]))
        ->assertSessionHasNoErrors();
    ($this->forwardReport)($original);
    Notification::fake();
    $this->actingAs($this->head)
        ->patch(route('research_head.progress-reports.review', $original), [
            'review_status' => 'revision_requested',
            'research_head_remarks' => 'Please correct the accomplishment data.',
        ])
        ->assertSessionHasNoErrors();

    Notification::assertSentTo($this->officeSecretary, ProposalActivityNotification::class);
    Notification::assertNotSentTo($this->researcher, ProposalActivityNotification::class);
    expect($original->fresh()->review_status)->toBe('pending')->and($original->fresh()->review_stage)->toBe('secretary');
    $this->actingAs($this->officeSecretary)->withSession([User::ACTIVE_WORKSPACE_SESSION_KEY => User::WORKSPACE_RESEARCH_SECRETARY])
        ->patch(route('research_secretary.monitoring-reports.review', $original), [
            'decision' => 'revision_requested', 'secretary_remarks' => 'Please correct the accomplishment data.',
        ])->assertRedirect()->assertSessionHasNoErrors();
    Notification::assertSentTo($this->researcher, ProposalActivityNotification::class);
    $this->actingAs($this->researcher)->withSession([User::ACTIVE_WORKSPACE_SESSION_KEY => User::WORKSPACE_FACULTY_RESEARCHER])
        ->get(route('topics.show', $this->topic))
        ->assertSuccessful()
        ->assertSee('Corrections requested by Research Office Secretary')
        ->assertDontSee('Revision required');

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

    ($this->attachSubmissionProof)($replacement);
    $this->actingAs($this->researcher)
        ->post(route('project-progress.submit-prepared', [$this->topic, $replacement]))
        ->assertSessionHasNoErrors();

    Notification::assertSentTo(
        $this->officeSecretary,
        ProposalActivityNotification::class,
        fn (ProposalActivityNotification $notification): bool => $notification->workspace === User::WORKSPACE_RESEARCH_SECRETARY
            && $notification->url === route('topics.show', $this->topic).'#monitoring-tool-'.$replacement->id,
    );
    Notification::assertNotSentTo($this->head, ProposalActivityNotification::class);
    $historyResponse = $this->actingAs($this->researcher)
        ->get(route('topics.show', $this->topic))
        ->assertOk()
        ->assertSee('Monitoring Tool')
        ->assertSee($replacement->quarter_label.' Monitoring Tool · Version 2')
        ->assertSee('Corrections requested by Research Office Secretary')
        ->assertSee('Historical version')
        ->assertSee('Current submission');
    ($this->forwardReport)($replacement);
    $this->actingAs($this->head)->withSession([User::ACTIVE_WORKSPACE_SESSION_KEY => User::WORKSPACE_RESEARCH_HEAD])
        ->get(route('topics.show', $this->topic))->assertOk()->assertSee($replacement->quarter_label.' Monitoring Tool · Version 2');

    if (getenv('ATHENA_EXPORT_MONITORING_LAYOUT') === '1') {
        file_put_contents(storage_path('framework/testing/monitoring-history.html'), $historyResponse->getContent());
    }
});

test('project status accepts only supported execution states', function () {
    $this->actingAs($this->head)
        ->patch(route('research_head.projects.update-status', $this->topic), [
            'project_status' => 'cancelled',
        ])
        ->assertSessionHasErrors('project_status');

    expect($this->topic->fresh()->project_status)->toBe('ongoing');
});

test('completion requires acknowledgement of the final status warning', function () {
    $this->actingAs($this->head)->patch(route('research_head.projects.update-status', $this->topic), [
        'project_status' => 'completed',
    ])->assertSessionHasErrors('completion_confirmed');
    expect($this->topic->fresh()->project_status)->toBe('ongoing');
    Notification::assertNothingSent();
    $warningResponse = $this->get(route('topics.show', $this->topic))->assertSuccessful()
        ->assertSee('Completion is final. The status cannot be changed back, and monitoring reports become read-only.')
        ->assertSee('name="completion_confirmed"', false)
        ->assertSee('submitStatus($event)', false);
    if (getenv('ATHENA_EXPORT_COMPLETION_LAYOUT') === '1') {
        $directory = storage_path('framework/testing');
        if (! is_dir($directory)) {
            mkdir($directory, 0777, true);
        }
        file_put_contents($directory.'/completion-status.html', $warningResponse->getContent());
    }
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
            'completion_confirmed' => '1',
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
            'completion_confirmed' => '1',
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
        ->assertSee('Signed terminal PDF uploaded');
    $this->get(route('project-narrative-reports.show', $terminalReport))->assertSuccessful()
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
        'project_status' => TopicProposal::PROJECT_STATUS_COMPLETED, 'completion_confirmed' => '1',
    ])->assertSessionHasErrors('project_status');
    expect(session('errors')->first('project_status'))->toContain('Monitoring Tool Q1', 'Progress Report Q1');
    expect($this->topic->fresh()->project_status)->toBe(TopicProposal::PROJECT_STATUS_ONGOING);

    $periods = app(MonitoringQuarterService::class)->projectPeriods($this->topic);
    foreach ($periods as $period) {
        $this->topic->progressReports()->create([
            'submitted_by' => $this->researcher->id, 'reporting_date' => $period['end'],
            'reporting_quarter' => $period['quarter'], 'period_start' => $period['start'], 'period_end' => $period['end'],
            'progress_percentage' => 100, 'accomplishments' => 'Approved targets completed.', 'review_status' => 'reviewed',
        ]);
    }
    $this->patch(route('research_head.projects.update-status', $this->topic), [
        'project_status' => TopicProposal::PROJECT_STATUS_COMPLETED, 'completion_confirmed' => '1',
    ])->assertSessionHasErrors('project_status');
    expect(session('errors')->first('project_status'))->toContain('Progress Report Q1')->not->toContain('Monitoring Tool Q1');

    foreach ($periods as $period) {
        $terminalReport->replicate()->fill([
            'report_type' => 'progress', 'terminal_data' => [], 'reporting_date' => $period['end'],
            'reporting_quarter' => $period['quarter'], 'period_start' => $period['start'], 'period_end' => $period['end'],
            'submission_date' => $period['opens_at'],
        ])->save();
    }
    $this->patch(route('research_head.projects.update-status', $this->topic), [
        'project_status' => TopicProposal::PROJECT_STATUS_COMPLETED,
        'completion_confirmed' => '1',
    ])->assertSessionHasNoErrors();

    expect($this->topic->fresh()->project_status)->toBe(TopicProposal::PROJECT_STATUS_COMPLETED);
    $terminalBefore = $terminalReport->fresh()->getAttributes();
    foreach (['ongoing', 'delayed', 'completed'] as $status) {
        $this->patch(route('research_head.projects.update-status', $this->topic), [
            'project_status' => $status, 'completion_confirmed' => '1',
        ])->assertNotFound();
    }
    $monitoring = $this->topic->progressReports()->firstOrFail();
    $this->patch(route('research_head.progress-reports.review', $monitoring), [
        'review_status' => 'revision_requested', 'research_head_remarks' => 'Attempt to alter closed monitoring.',
    ])->assertNotFound();
    $this->patch(route('research_head.narrative-progress-reports.review', $terminalReport), [
        'review_status' => 'revision_requested', 'research_head_remarks' => 'Attempt to alter the closed terminal report.',
    ])->assertNotFound();
    $this->post(route('research_head.narrative-progress-reports.signed-copy.store', $terminalReport), [
        'signed_report' => UploadedFile::fake()->create('replacement.pdf', 120, 'application/pdf'),
    ])->assertForbidden();
    expect($this->topic->fresh()->project_status)->toBe('completed')
        ->and($terminalReport->fresh()->getAttributes())->toBe($terminalBefore)
        ->and($monitoring->fresh()->review_status)->toBe('reviewed');
    $closedProject = $this->get(route('topics.show', $this->topic))->assertSuccessful()
        ->assertSee('Reports are read-only.');
    $document = new DOMDocument;
    @$document->loadHTML($closedProject->getContent());
    expect((new DOMXPath($document))->query('//*[@data-project-status-manager]')->length)->toBe(0);
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
    $payload = ($this->monitoringPayload)();
    unset($payload['budget_utilization'][2]['details']);

    $this->actingAs($this->researcher)
        ->post(route('project-progress.preview', $this->topic), $payload)
        ->assertSuccessful()
        ->assertSee(Str::upper($this->researcher->name))
        ->assertSee('<td class="monitoring-request-details"></td>', false)
        ->assertDontSee($payload['tracking_number']);

    $this->actingAs($this->researcher)
        ->post(route('project-progress.store', $this->topic), $payload)
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

    ($this->attachSubmissionProof)($report);
    $this->actingAs($this->researcher)
        ->post(route('project-progress.submit-prepared', [$this->topic, $report]))
        ->assertRedirect()
        ->assertSessionHasNoErrors();
    $this->actingAs($this->head)->get(route('project-progress.monitoring-tool', $report))->assertForbidden();
    ($this->forwardReport)($report);
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
            ->and($documentXml)->toContain('Project Leader: '.Str::upper($this->researcher->name))
            ->and($documentXml)->not->toContain('Development of an Online College Research Journal Management System')
            ->and($documentXml)->not->toContain('Implement different level of access for different types of users')
            ->and($footerXml)->toContain('Tracking No. __________________________ ')
            ->and($footerXml)->not->toContain('REC-2026-001')
            ->and($footerXml)->toContain('PAGE')
            ->and($footerXml)->toContain('NUMPAGES');

        $document = new DOMDocument;
        $document->loadXML($documentXml);
        $xpath = new DOMXPath($document);
        $xpath->registerNamespace('w', 'http://schemas.openxmlformats.org/wordprocessingml/2006/main');
        $details = '/w:document/w:body/w:tbl[1]/w:tr[23]/w:tc[2]/w:p[1]/w:r[1]';
        expect($xpath->evaluate('string('.$details.'/w:t)'))->toBe('PR-2026-014, supplies')
            ->and($xpath->evaluate('string('.$details.'/w:rPr/w:sz/@w:val)'))->toBe('24')
            ->and($xpath->evaluate('string('.$details.'/w:rPr/w:szCs/@w:val)'))->toBe('24')
            ->and($xpath->evaluate('string(/w:document/w:body/w:tbl[1]/w:tr[24]/w:tc[2])'))->toBe('')
            ->and($xpath->evaluate('string(/w:document/w:body/w:tbl[1]/w:tr[25]/w:tc[2])'))->toBe('');

        $footer = new DOMDocument;
        $footer->loadXML($footerXml);
        $footerXPath = new DOMXPath($footer);
        $footerXPath->registerNamespace('w', 'http://schemas.openxmlformats.org/wordprocessingml/2006/main');
        expect($footerXPath->evaluate('string(/w:ftr/w:p/w:pPr/w:tabs/w:tab[@w:val="right"]/@w:pos)'))->toBe('11908')
            ->and($footerXPath->query('/w:ftr/w:p/w:r/w:tab')->length)->toBe(1);

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

test('monitoring PDF preview serves the exact submitted paper inline with the shared viewer', function () {
    $this->actingAs($this->researcher)
        ->post(route('project-progress.store', $this->topic), ($this->monitoringPayload)())
        ->assertSessionHasNoErrors();
    $report = ProjectProgressReport::firstOrFail();
    $pdf = Storage::disk('local')->get($report->official_pdf_path);

    $this->actingAs($this->head)->get(route('project-progress.monitoring-tool.view', $report))->assertForbidden();
    ($this->attachSubmissionProof)($report);
    $this->actingAs($this->researcher)
        ->post(route('project-progress.submit-prepared', [$this->topic, $report]))
        ->assertSessionHasNoErrors();
    $this->actingAs($this->head)->get(route('project-progress.monitoring-tool.view', $report))->assertForbidden();
    ($this->forwardReport)($report);
    foreach ([$this->head, $this->researcher] as $viewer) {
        $response = $this->actingAs($viewer)->get(route('project-progress.monitoring-tool.view', $report))
            ->assertSuccessful()->assertHeader('Content-Type', 'application/pdf')
            ->assertHeader('X-Content-Type-Options', 'nosniff');
        expect($response->headers->get('Content-Disposition'))->toStartWith('inline')
            ->and($response->streamedContent())->toBe($pdf);
    }
    expect($this->pdfConverter->conversionCount)->toBe(1);
    $this->actingAs($this->head)->get(route('topics.show', $this->topic))
        ->assertSuccessful()->assertSee('Preview submitted PDF')
        ->assertSee('data-quarter-pdf-preview-modal', false)
        ->assertSee('data-report-kind="monitoring"', false)
        ->assertSee('pdfAnnotationWorkspace', false)
        ->assertSee('Close preview')->assertSee('Download PDF')
        ->assertSee(str_replace('/', '\\/', route('project-progress.monitoring-tool.view', $report)), false);
});

test('monitoring PDF preview denies unrelated users and guests', function () {
    $this->actingAs($this->researcher)
        ->post(route('project-progress.store', $this->topic), ($this->monitoringPayload)())
        ->assertSessionHasNoErrors();
    $report = ProjectProgressReport::firstOrFail();
    ($this->attachSubmissionProof)($report);
    $this->post(route('project-progress.submit-prepared', [$this->topic, $report]))->assertSessionHasNoErrors();
    $other = User::factory()->create();
    $other->assignRole('faculty_researcher');
    $this->actingAs($other)->get(route('project-progress.monitoring-tool.view', $report))->assertForbidden();
    auth()->logout();
    $this->get(route('project-progress.monitoring-tool.view', $report))->assertRedirect(route('login'));
});

test('monitoring PDF preview supports legacy reports without a stored PDF', function () {
    $payload = ($this->monitoringPayload)();
    $report = ProjectProgressReport::create([
        'topic_id' => $this->topic->id, 'submitted_by' => $this->researcher->id,
        'reporting_date' => $payload['reporting_date'], 'tracking_number' => $payload['tracking_number'],
        'work_plan' => $payload['work_plan'], 'budget_utilization' => $payload['budget_utilization'],
        'progress_percentage' => 25, 'submission_status' => 'submitted', 'accomplishments' => 'Pilot interviews completed.',
    ]);
    $response = $this->actingAs($this->head)->get(route('project-progress.monitoring-tool.view', $report))
        ->assertSuccessful()->assertHeader('Content-Type', 'application/pdf')->assertHeader('Content-Disposition', 'inline');
    expect($response->streamedContent())->toStartWith('%PDF-')
        ->and($this->pdfConverter->conversionCount)->toBe(1);
});

test('older progress reports remain accessible in history while terminal reports follow their signing workflow', function () {
    $report = ProjectNarrativeReport::create([
        'topic_id' => $this->topic->id, 'submitted_by' => $this->researcher->id,
        'report_type' => 'progress', 'submission_date' => now(), 'researchers' => $this->researcher->name,
        'implementation_start' => now()->subMonths(3), 'implementation_end' => now(),
        'budget' => 50000, 'funding_agency' => 'Batangas State University',
        'accomplishment_summary' => 'Completed the pilot with documentation.',
        'introduction' => 'Pilot implementation.', 'objectives' => 'Evaluate the pilot.',
        'methodology' => 'Interviews and observation.', 'results_discussion' => 'Pilot results documented.',
        'review_status' => ProjectNarrativeReport::STATUS_PENDING,
        'research_head_remarks' => 'Explain the participant shortfall.',
        'photos' => [['path' => 'progress-reports/campus.jpg', 'caption' => 'Pilot coordination.', 'section' => 'results_discussion']],
    ]);
    $this->actingAs($this->head)->withSession([User::ACTIVE_WORKSPACE_SESSION_KEY => User::WORKSPACE_RESEARCH_HEAD]);
    $response = $this->get(route('topics.show', $this->topic))->assertSuccessful()
        ->assertSee('Open report')->assertSee(route('project-narrative-reports.show', $report), false)
        ->assertDontSee('data-narrative-report-content', false)->assertDontSee('data-narrative-pdf-preview-modal', false);
    $document = new DOMDocument;
    @$document->loadHTML('<?xml encoding="utf-8" ?>'.$response->getContent());
    $xpath = new DOMXPath($document);
    expect($xpath->query('//*[@id="narrative-report-history"]//*[@id="narrative-report-'.$report->id.'"]')->length)->toBe(1)
        ->and($xpath->query('//*[@id="narrative-report-'.$report->id.'"]')->length)->toBe(1)
        ->and($xpath->query('//*[@id="progress-reports"]//form')->length)->toBe(0);
    $this->get(route('project-narrative-reports.show', $report))->assertSuccessful()
        ->assertSee('Explain the participant shortfall.')->assertSee('Pilot coordination.')
        ->assertSee('data-narrative-report-review', false)->assertSee('Preview PDF');
    $this->from(route('project-narrative-reports.show', $report))
        ->patch(route('research_head.narrative-progress-reports.review', $report), [
            'review_status' => 'reviewed', 'research_head_remarks' => 'Evidence reviewed.',
        ])->assertSessionHasNoErrors()->assertRedirect(route('project-narrative-reports.show', $report));
    $this->get(route('project-narrative-reports.show', $report))->assertSuccessful()
        ->assertSee('Evidence reviewed.')->assertDontSee('data-narrative-report-review', false);

    $oldTerminal = $report->replicate();
    $oldTerminal->fill(['report_type' => 'terminal', 'review_status' => 'pending'])->save();
    $terminal = $oldTerminal->replicate();
    $terminal->save();
    $this->get(route('project-narrative-reports.show', $oldTerminal))->assertSuccessful()->assertDontSee('data-narrative-report-review', false);
    $this->get(route('project-narrative-reports.show', $terminal))->assertSuccessful()->assertSee('data-narrative-report-review', false);
    $terminal->update(['review_status' => 'reviewed']);
    $this->get(route('project-narrative-reports.show', $terminal))->assertSuccessful()
        ->assertSee('Signed terminal report')->assertDontSee('data-narrative-report-review', false);
    $terminal->update(['terminal_data' => ['signed_copy' => ['path' => 'signed.pdf', 'original_filename' => 'signed.pdf']]]);
    $this->get(route('topics.show', $this->topic))->assertSuccessful()->assertSee('Report history')->assertSee('Signed terminal PDF uploaded');
    $this->topic->update(['project_status' => TopicProposal::PROJECT_STATUS_COMPLETED]);
    $this->get(route('topics.show', $this->topic))->assertSuccessful()->assertSee('id="progress-reports"', false)
        ->assertSee('id="terminal-reports"', false);
    $this->get(route('project-narrative-reports.show', $terminal))->assertSuccessful()
        ->assertDontSee('data-narrative-report-review', false)->assertDontSee('name="signed_report"', false);
});
test('report reviews opens submitted quarterly reports and removes reviewed reports from the pending queue', function () {
    $report = ProjectProgressReport::create([
        'topic_id' => $this->topic->id, 'submitted_by' => $this->researcher->id,
        'reporting_date' => now(), 'progress_percentage' => 60, 'accomplishments' => 'Field work completed.',
    ]);
    $this->actingAs($this->head)->get(route('research_head.report-reviews.index'))
        ->assertOk()->assertSee('Reports awaiting review')->assertSee('Approved Community Research')
        ->assertSee(route('topics.show', $this->topic).'#monitoring-tool-'.$report->id, false)
        ->assertSee('Review report')->assertViewHas('reports', fn ($reports) => $reports->total() === 1);
    $this->actingAs($this->head)->patch(route('research_head.progress-reports.review', $report), ['review_status' => 'reviewed', 'research_head_remarks' => 'Accepted.'])->assertRedirect();
    $this->get(route('research_head.report-reviews.index'))->assertOk()->assertViewHas('reports', fn ($reports) => $reports->total() === 0);
    $this->get(route('research_head.report-reviews.index', ['status' => 'reviewed', 'type' => 'quarterly']))->assertOk()->assertSee('View report')->assertViewHas('reports', fn ($reports) => $reports->total() === 1);
});

test('completed project destination retains its scope when filters are submitted', function () {
    $this->actingAs($this->head)->get(route('research_head.completed-projects.index', ['status' => 'ongoing']))
        ->assertOk()->assertViewHas('status', 'completed')->assertViewHas('projects', fn ($projects) => $projects->total() === 0);
    $this->topic->forceFill(['project_status' => 'completed'])->save();
    $this->get(route('research_head.completed-projects.index'))->assertOk()->assertSee('Completed project records')
        ->assertViewHas('projects', fn ($projects) => $projects->total() === 1)
        ->assertSee('action="'.route('research_head.completed-projects.index').'"', false);
});

test('report review filters distinguish progress and latest terminal narratives and link to their project sections', function () {
    $data = [
        'topic_id' => $this->topic->id, 'submitted_by' => $this->researcher->id,
        'submission_date' => now(), 'researchers' => $this->researcher->name,
        'implementation_start' => now()->subMonths(3), 'implementation_end' => now(),
        'budget' => 50000, 'funding_agency' => 'Batangas State University',
        'accomplishment_summary' => 'Completed the planned field study.', 'introduction' => 'Project background.',
        'objectives' => 'Assess coastal conditions.', 'methodology' => 'Field survey.', 'results_discussion' => 'Study findings.', 'photos' => [],
    ];
    $progress = ProjectNarrativeReport::create([...$data, 'report_type' => 'progress']);
    ProjectNarrativeReport::create([...$data, 'report_type' => 'terminal']);
    $terminal = ProjectNarrativeReport::create([...$data, 'report_type' => 'terminal']);
    $this->actingAs($this->head)->get(route('research_head.report-reviews.index'))
        ->assertOk()->assertViewHas('reports', fn ($reports) => $reports->total() === 2)
        ->assertSee('2 reports awaiting review')
        ->assertSee(route('topics.show', $this->topic).'#narrative-report-'.$progress->id, false)
        ->assertSee(route('topics.show', $this->topic).'#narrative-report-'.$terminal->id, false);
    $this->get(route('research_head.report-reviews.index', ['type' => 'terminal']))->assertOk()
        ->assertViewHas('reports', fn ($reports) => $reports->total() === 1 && $reports->first()->id === $terminal->id);
    $this->get(route('topics.show', $this->topic).'#narrative-report-'.$terminal->id)->assertOk()
        ->assertSee('id="narrative-report-'.$terminal->id.'"', false)
        ->assertSee("window.location.hash.startsWith('#narrative-report-')", false);
});

test('report reader shows the accomplishment table and seven figures within their narrative sections without disclosures', function (string $reportType) {
    $this->topic->update([
        'title' => 'Predictive Maintenance Dashboard for University Laboratory Equipment',
        'notice_to_proceed_issued_at' => '2025-10-01', 'estimated_duration_months' => 9,
    ]);
    $photos = collect(range(1, 7))->map(fn ($number) => [
        'path' => 'report-figures/figure-'.$number.'.png', 'caption' => 'Research evidence '.$number,
        'section' => $number <= 4 ? 'methodology' : 'results_discussion',
        'after_paragraph' => $number <= 2 ? 1 : 0,
    ])->all();
    $report = ProjectNarrativeReport::create([
        'topic_id' => $this->topic->id, 'submitted_by' => $this->researcher->id, 'report_type' => $reportType,
        'reporting_quarter' => $reportType === 'progress' ? 1 : null,
        'reporting_date' => $reportType === 'progress' ? '2025-12-31' : null,
        'period_start' => $reportType === 'progress' ? '2025-10-01' : null,
        'period_end' => $reportType === 'progress' ? '2025-12-31' : null,
        'submission_date' => '2026-01-01', 'researchers' => $this->researcher->name, 'implementation_start' => '2025-10-01',
        'implementation_end' => '2026-06-30', 'budget' => 50000, 'funding_agency' => 'Batangas State University',
        'introduction' => 'Equipment inventory and maintenance-history preparation. The first quarter establishes the dataset and service workflow before dashboard development.',
        'rationale' => 'Laboratory equipment needs an accessible maintenance history and a clear service-priority queue. This sample study explores how a dashboard can support preventive maintenance planning.',
        'objectives' => 'Document laboratory equipment and validate the maintenance-history dataset.',
        'methodology' => "First methods paragraph. The team mapped equipment registration, fault reporting, and service scheduling using fictional laboratory records. Six illustrative staff walkthroughs identified the fields needed for maintenance planning.\n\nSecond methods paragraph. The team checked 240 sample entries for duplicate asset identifiers, missing service dates, and inconsistent equipment categories. Corrected entries were retained with a change log and a common data dictionary.",
        'results_discussion' => 'The sample inventory contains 48 assets across four laboratory areas. Twelve duplicate maintenance entries were consolidated and 18 incomplete service dates were flagged for confirmation. The resulting dataset supports equipment history and service-priority views.',
        'accomplishment_summary' => 'Prototype and pilot completed.',
        'accomplishments' => [['objective' => 'Validate the maintenance-history dataset', 'target' => 'Equipment inventory and validated maintenance records', 'actual' => 'Catalogued 48 fictional equipment assets, reviewed 240 maintenance entries, and prepared the initial dataset dictionary.']],
        'photos' => $photos, 'review_status' => ProjectNarrativeReport::STATUS_PENDING,
    ]);
    $response = $this->actingAs($this->head)->withSession([User::ACTIVE_WORKSPACE_SESSION_KEY => User::WORKSPACE_RESEARCH_HEAD])
        ->get(route('project-narrative-reports.show', $report))->assertSuccessful()->assertSee('Figure 7.')->assertSee('Research evidence 7')
        ->assertSee('Preview PDF')->assertSee('Preview figure')->assertDontSee('View full size')
        ->assertSee('data-narrative-pdf-preview-modal', false);
    $document = new DOMDocument;
    @$document->loadHTML('<?xml encoding="utf-8" ?>'.$response->getContent());
    $xpath = new DOMXPath($document);
    $card = $xpath->query('//*[@id="narrative-report-'.$report->id.'"]')->item(0);
    $backLinks = $xpath->query('//*[@data-fixed-back-link]');
    expect($backLinks->length)->toBe(1)
        ->and($backLinks->item(0)->getAttribute('href'))->toBe(route('topics.show', $this->topic).'#narrative-report-'.$report->id)
        ->and($xpath->query('//*[contains(@class, "report-page-header")]//*[@data-back-link]')->length)->toBe(0);
    expect($xpath->query('.//details | .//summary', $card)->length)->toBe(0)
        ->and($xpath->query('.//*[@data-report-accomplishments]', $card)->length)->toBe(1)
        ->and($xpath->query('.//*[@data-report-section]', $card)->length)->toBe(5)
        ->and($xpath->query('.//*[@data-report-section="methodology"]//figure', $card)->length)->toBe(4)
        ->and($xpath->query('.//*[@data-report-section="results_discussion"]//figure', $card)->length)->toBe(3)
        ->and($xpath->query('.//figure', $card)->length)->toBe(7);
    $numbers = [];
    foreach ($xpath->query('.//figure[@data-report-figure]', $card) as $figure) {
        $numbers[] = (int) $figure->getAttribute('data-report-figure');
    }
    expect($numbers)->toBe(range(1, 7));
    $listResponse = $this->get(route('topics.show', $this->topic))->assertSuccessful()->assertDontSee('Research evidence 7');
    $listDocument = new DOMDocument;
    @$listDocument->loadHTML('<?xml encoding="utf-8" ?>'.$listResponse->getContent());
    $listXpath = new DOMXPath($listDocument);
    $readerAction = $listXpath->query('//*[@data-narrative-report-summary or @data-progress-report-detail]//a[@href="'.route('project-narrative-reports.show', $report).'"]')->item(0);
    expect($readerAction)->not->toBeNull();
    if ($reportType === 'progress') {
        expect($readerAction->getAttribute('aria-label'))->toBe('Read full Q1 progress report')
            ->and($listXpath->query('//*[@data-progress-quarter="1"]//button')->item(0)->getAttribute('aria-controls'))->toBe('narrative-report-'.$report->id)
            ->and($listXpath->query('//*[@data-progress-report-detail]//*[@data-submitted-report-paper]')->length)->toBe(1)
            ->and($listXpath->query('//*[@data-quarter-pdf-preview-modal and @data-report-kind="progress"]')->length)->toBe(1);
    } else {
        expect($readerAction->getAttribute('class'))->toContain('border-slate-200', 'text-sm', 'min-h-11')->not->toContain('bg-brand', 'underline')
            ->and($readerAction->getAttribute('aria-label'))->toContain('Open '.strtolower($report->report_label).' submitted');
    }
    expect($xpath->query('.//figure//button[@aria-haspopup="dialog"]', $card)->length)->toBe(7)
        ->and($xpath->query('.//*[@data-report-reader-navigation]', $card)->length)->toBe(1)
        ->and($xpath->query('.//*[@data-report-reader-heading]', $card)->length)->toBe(1)
        ->and($xpath->query('.//*[@data-narrative-figure-preview-modal]', $card)->length)->toBe(7)
        ->and($xpath->query('.//header//button[@aria-haspopup="dialog"]/*[local-name()="svg"]', $card)->length)->toBe(1);
    expect($xpath->query('//*[@data-submitted-report-preview-modal]//*[@aria-label="Document zoom controls"]')->length)->toBe(1)
        ->and($xpath->query('//*[@data-submitted-report-preview-modal]//*[@aria-label="Zoom in"]')->length)->toBe(1);
    $content = $xpath->query('.//*[@data-report-section="methodology"]', $card)->item(0)->textContent;
    expect(strpos($content, 'First methods paragraph.'))->toBeLessThan(strpos($content, 'Figure 1.'))
        ->and(strpos($content, 'Figure 2.'))->toBeLessThan(strpos($content, 'Second methods paragraph.'));
    if (getenv('ATHENA_EXPORT_MONITORING_LAYOUT') === '1') {
        file_put_contents(storage_path('framework/testing/report-reader-'.$reportType.'-review.html'), $response->getContent());
        $this->topic->update(['project_status' => 'completed']);
        $report->update(['review_status' => 'reviewed', 'research_head_remarks' => 'Evidence reviewed. Retain the documented study limitations.']);
        $completed = $this->get(route('project-narrative-reports.show', $report))->assertSuccessful();
        file_put_contents(storage_path('framework/testing/report-reader-'.$reportType.'-completed.html'), $completed->getContent());
    }
})->with(['progress', 'terminal']);

test('reviewed progress remains visible beside a pending terminal report in both workspaces', function (string $workspace) {
    $report = ProjectNarrativeReport::create([
        'topic_id' => $this->topic->id,
        'submitted_by' => $this->researcher->id,
        'report_type' => 'progress',
        'reporting_quarter' => 1,
        'reporting_date' => now()->subDay(),
        'submission_date' => now()->subMonth(),
        'researchers' => $this->researcher->name,
        'implementation_start' => now()->subMonths(3),
        'implementation_end' => now(),
        'budget' => 50000,
        'funding_agency' => 'Batangas State University',
        'accomplishment_summary' => 'Quarterly fieldwork completed and reviewed.',
        'introduction' => 'Quarterly project implementation.',
        'rationale' => 'Track approved research activities.',
        'objectives' => 'Complete the scheduled fieldwork.',
        'methodology' => 'Interviews and site observations.',
        'results_discussion' => 'Planned activities completed.',
        'photos' => [],
        'review_status' => ProjectNarrativeReport::STATUS_REVIEWED,
        'research_head_remarks' => 'Quarterly accomplishments reviewed.',
    ]);
    $terminal = $report->replicate();
    $terminal->fill([
        'report_type' => 'terminal',
        'reporting_quarter' => null,
        'reporting_date' => null,
        'review_status' => ProjectNarrativeReport::STATUS_PENDING,
        'research_head_remarks' => null,
    ])->save();

    $viewer = $workspace === User::WORKSPACE_RESEARCH_HEAD ? $this->head : $this->researcher;
    $response = $this->actingAs($viewer)
        ->withSession([User::ACTIVE_WORKSPACE_SESSION_KEY => $workspace])
        ->get(route('topics.show', $this->topic))
        ->assertSuccessful()
        ->assertSee('Reviewed by Research Head')
        ->assertSee('Pending Research Head review')
        ->assertDontSee('No progress reports submitted yet.')
        ->assertDontSee('Signed copy required');

    $document = new DOMDocument;
    @$document->loadHTML('<?xml encoding="utf-8" ?>'.$response->getContent());
    $xpath = new DOMXPath($document);
    $progressCard = $xpath->query('//*[@id="progress-reports"]//*[@id="narrative-report-'.$report->id.'"]')->item(0);
    $terminalCard = $xpath->query('//*[@id="terminal-reports"]//*[@id="narrative-report-'.$terminal->id.'"]')->item(0);
    expect($progressCard)->not->toBeNull()
        ->and($terminalCard)->not->toBeNull()
        ->and($xpath->query('.//form', $progressCard)->length)->toBe(0)
        ->and($xpath->query('//*[@id="narrative-report-'.$report->id.'"]')->length)->toBe(1)
        ->and($xpath->query('//*[@data-narrative-history-entry="'.$report->id.'"]')->length)->toBe(1)
        ->and($xpath->query('.//*[@data-narrative-report-status]', $terminalCard)->item(0)->textContent)->toContain('Awaiting review')->not->toContain('Signed terminal PDF needed')
        ->and($xpath->query('.//*[@data-narrative-report-review]', $terminalCard)->length)->toBe(0);
    $detailResponse = $this->get(route('project-narrative-reports.show', $terminal))->assertSuccessful();
    expect(substr_count($detailResponse->getContent(), 'data-narrative-report-review'))->toBe($workspace === User::WORKSPACE_RESEARCH_HEAD ? 1 : 0);

    $terminal->update(['review_status' => ProjectNarrativeReport::STATUS_REVIEWED]);
    $this->get(route('topics.show', $this->topic))->assertSuccessful()->assertSee('Signed terminal PDF needed');
})->with([User::WORKSPACE_RESEARCH_HEAD, User::WORKSPACE_FACULTY_RESEARCHER]);

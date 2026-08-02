<?php

use App\Models\ProjectProgressReport;
use App\Models\ResearchCall;
use App\Models\TopicProposal;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    Notification::fake();
    foreach (['faculty_researcher', 'research_head'] as $role) {
        Role::firstOrCreate(['name' => $role]);
    }

    $this->researcher = User::factory()->create();
    $this->researcher->assignRole('faculty_researcher');
    $this->head = User::factory()->create();
    $this->head->assignRole('research_head');
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
        'notice_to_proceed_issued_at' => now(),
    ]);

    $this->monitoringPayload = fn (array $overrides = []): array => array_replace([
        'reporting_date' => now()->toDateString(),
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
});

test('a researcher with a Notice to Proceed can submit progress without an attachment', function () {
    $this->actingAs($this->researcher)
        ->post(route('project-progress.store', $this->topic), ($this->monitoringPayload)())
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    $this->assertDatabaseHas('project_progress_reports', [
        'topic_id' => $this->topic->id,
        'progress_percentage' => 25,
        'attachment_path' => null,
    ]);
});

test('the faculty project page shows the official monitoring tool fields', function () {
    $this->actingAs($this->researcher)
        ->get(route('research.show', $this->topic))
        ->assertOk()
        ->assertSee('Submit monitoring tool')
        ->assertSee('A. Work Plan')
        ->assertSee('Add up to eleven activities')
        ->assertSee('B. Budget Utilization')
        ->assertSee('Purchase Request')
        ->assertSee('Request of Payment');
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
        ->post(route('project-progress.store', $this->topic), ($this->monitoringPayload)())
        ->assertForbidden();
});

test('progress cannot be submitted for a proposal that is not approved', function () {
    $this->topic->update(['status' => 'pending', 'project_status' => null]);

    $this->actingAs($this->researcher)
        ->post(route('project-progress.store', $this->topic), ($this->monitoringPayload)())
        ->assertForbidden();
});

test('a research head can review a report and update project status', function () {
    $report = ProjectProgressReport::create([
        'topic_id' => $this->topic->id,
        'submitted_by' => $this->researcher->id,
        'reporting_date' => now(),
        'progress_percentage' => 60,
        'accomplishments' => 'Draft report completed.',
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
        ->and($this->topic->fresh()->project_status)->toBe('delayed');
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

test('project status accepts only supported execution states', function () {
    $this->actingAs($this->head)
        ->patch(route('research_head.projects.update-status', $this->topic), [
            'project_status' => 'cancelled',
        ])
        ->assertSessionHasErrors('project_status');

    expect($this->topic->fresh()->project_status)->toBe('ongoing');
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

test('the owner and Research Head can download the filled official monitoring tool', function () {
    $this->actingAs($this->researcher)
        ->post(route('project-progress.store', $this->topic), ($this->monitoringPayload)())
        ->assertSessionHasNoErrors();

    $report = ProjectProgressReport::firstOrFail();
    $ownerResponse = $this->actingAs($this->researcher)
        ->get(route('project-progress.monitoring-tool', $report))
        ->assertOk()
        ->assertDownload('approved-community-research-monitoring-tool.docx');
    $this->actingAs($this->head)
        ->get(route('project-progress.monitoring-tool', $report))
        ->assertOk();

    $generatedPath = tempnam(sys_get_temp_dir(), 'monitoring-test-');
    expect($generatedPath)->not->toBeFalse();
    file_put_contents($generatedPath, $ownerResponse->streamedContent());

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

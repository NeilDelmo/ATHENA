<?php

use App\Models\ProjectNarrativeReport;
use App\Models\ProjectProgressReport;
use App\Models\TopicProposal;
use App\Models\User;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    $this->withoutVite();
    $this->travelTo(now()->setDate(2026, 4, 15));
    foreach (['faculty_researcher', 'research_secretary', 'research_head'] as $role) {
        Role::firstOrCreate(['name' => $role]);
    }
    $this->owner = User::factory()->create(['college' => 'CICS']);
    $this->owner->assignRole('faculty_researcher');
    $this->secretary = User::factory()->create(['college' => 'CICS']);
    $this->secretary->assignRole('research_secretary');
    $this->otherSecretary = User::factory()->create(['college' => 'CTE']);
    $this->otherSecretary->assignRole('research_secretary');
    $this->teamSecretary = User::factory()->create(['college' => 'CICS']);
    $this->teamSecretary->assignRole('faculty_researcher');
    $this->head = User::factory()->create();
    $this->head->assignRole('research_head');
    $this->topic = TopicProposal::create([
        'user_id' => $this->owner->id, 'research_secretary_id' => $this->teamSecretary->id,
        'title' => 'College implementation report review', 'status' => 'approved',
        'project_status' => 'ongoing', 'notice_to_proceed_issued_at' => '2026-01-01',
        'estimated_duration_months' => 12, 'estimated_budget' => 50000,
    ]);
    $this->topic->collaborators()->create([
        'user_id' => $this->teamSecretary->id, 'name' => $this->teamSecretary->name,
        'email' => $this->teamSecretary->email, 'accepted_at' => now(), 'project_role' => 'secretary',
    ]);
    $this->reportData = [
        'topic_id' => $this->topic->id, 'submitted_by' => $this->owner->id,
        'reporting_date' => '2026-03-31', 'reporting_quarter' => 1,
        'period_start' => '2026-01-01', 'period_end' => '2026-03-31', 'version_number' => 1,
        'submission_status' => 'submitted', 'submitted_at' => now(),
        'review_stage' => 'secretary', 'review_status' => 'pending',
    ];
    $this->progressData = [
        ...$this->reportData, 'report_type' => 'progress', 'submission_date' => '2026-04-15',
        'researchers' => $this->owner->name, 'implementation_start' => '2026-01-01',
        'implementation_end' => '2026-12-31', 'budget' => 50000, 'funding_agency' => 'University',
        'accomplishment_summary' => 'Completed field observations.', 'accomplishments' => [], 'photos' => [],
        'introduction' => 'Research background.', 'objectives' => 'Assess outcomes.',
        'methodology' => 'Field observations.', 'results_discussion' => 'Implementation is ongoing.',
    ];
});

test('the college office dashboard and review forms stay separate from project team secretary duties', function () {
    $largeText = str_repeat('Full report details are not needed in the secretary queue. ', 900);
    $monitoring = ProjectProgressReport::create([
        ...$this->reportData, 'reporting_year' => 2026, 'progress_percentage' => 25,
        'accomplishments' => $largeText, 'issues' => $largeText, 'work_plan' => [], 'budget_utilization' => [],
    ]);
    $progress = ProjectNarrativeReport::create([
        ...$this->progressData, 'introduction' => $largeText, 'objectives' => $largeText,
        'methodology' => $largeText, 'results_discussion' => $largeText,
        'terminal_data' => ['abstract' => $largeText],
    ]);
    $outsideOwner = User::factory()->create(['college' => 'CTE']);
    $outside = TopicProposal::create([
        'user_id' => $outsideOwner->id, 'title' => 'Other college private report', 'status' => 'approved',
        'project_status' => 'ongoing', 'notice_to_proceed_issued_at' => '2026-01-01',
    ]);
    ProjectNarrativeReport::create([...$this->progressData, 'topic_id' => $outside->id, 'submitted_by' => $outsideOwner->id]);
    ProjectNarrativeReport::create([
        ...$this->progressData, 'reporting_quarter' => 2, 'reporting_date' => '2026-06-30',
        'period_start' => '2026-04-01', 'period_end' => '2026-06-30', 'review_stage' => 'head',
    ]);

    $response = $this->actingAs($this->secretary)->withSession([User::ACTIVE_WORKSPACE_SESSION_KEY => User::WORKSPACE_RESEARCH_SECRETARY])
        ->get(route('research_secretary.dashboard'))->assertOk()
        ->assertViewHas('reportReviewTasks', fn ($reports): bool => $reports->count() === 2)
        ->assertSee('Implementation reports to review')->assertSee($this->topic->title)
        ->assertSee('Jan 1, 2026 – Mar 31, 2026')->assertSee('Version 1')->assertSee($this->owner->name)
        ->assertDontSee($outside->title)
        ->assertSee(route('project-progress.monitoring-tool.view', $monitoring), false)
        ->assertSee(route('project-narrative-reports.view', $progress), false);
    $loadedReports = $response->viewData('reportReviewTasks')
        ->map(fn (ProjectProgressReport|ProjectNarrativeReport $report): array => $report->getAttributes());
    foreach ($loadedReports as $attributes) {
        expect(array_intersect(['accomplishments', 'issues', 'work_plan', 'budget_utilization', 'introduction', 'objectives', 'methodology', 'results_discussion', 'photos', 'terminal_data'], array_keys($attributes)))->toBe([]);
    }
    expect(strlen(json_encode($loadedReports->all(), JSON_THROW_ON_ERROR)))->toBeLessThan(3000);
    $this->get(route('topics.show', $this->topic))->assertOk()
        ->assertSee(route('research_secretary.monitoring-reports.review', $monitoring), false)
        ->assertSee(route('research_secretary.narrative-reports.review', $progress), false)
        ->assertSee('Forward to Research Head')->assertSee('Return to project leader for corrections');
    $this->get(route('project-narrative-reports.show', $progress))->assertOk()
        ->assertSee(route('research_secretary.narrative-reports.review', $progress), false);

    $this->actingAs($this->teamSecretary)->withSession([User::ACTIVE_WORKSPACE_SESSION_KEY => User::WORKSPACE_FACULTY_RESEARCHER])
        ->get(route('topics.show', $this->topic))->assertOk()
        ->assertDontSee(route('research_secretary.monitoring-reports.review', $monitoring), false)
        ->assertDontSee(route('research_secretary.narrative-reports.review', $progress), false);
});

test('Head concerns appear for office resolution while terminal review retains its existing decisions', function () {
    $progress = ProjectNarrativeReport::create([...$this->progressData, 'review_stage' => 'head']);
    $this->actingAs($this->head)->withSession([User::ACTIVE_WORKSPACE_SESSION_KEY => User::WORKSPACE_RESEARCH_HEAD])
        ->get(route('project-narrative-reports.show', $progress))->assertOk()
        ->assertSee('Accept report')->assertSee('Send concerns to Research Office Secretary')
        ->assertSee('data-head-report-review', false)->assertSee('Research Head decision');
    $progress->update(['review_stage' => 'secretary', 'research_head_remarks' => 'Verify the supporting evidence.', 'reviewed_by' => $this->head->id]);
    $this->actingAs($this->secretary)->withSession([User::ACTIVE_WORKSPACE_SESSION_KEY => User::WORKSPACE_RESEARCH_SECRETARY])
        ->get(route('research_secretary.dashboard'))->assertOk()
        ->assertSee('Returned by Research Head')->assertSee('resolve concerns before forwarding');
    $progress->update(['official_pdf_path' => 'missing/submitted-report.pdf', 'official_pdf_filename' => 'report.pdf']);
    $this->from(route('project-narrative-reports.show', $progress))
        ->patch(route('research_secretary.narrative-reports.review', $progress), ['decision' => 'forward'])
        ->assertRedirect();
    $this->get(route('project-narrative-reports.show', $progress))->assertOk()
        ->assertSee('Research Head concerns')->assertSee('Research Office Secretary to resolve')
        ->assertSee('Verify the supporting evidence.')->assertSee('The submitted report PDF is unavailable.')
        ->assertSee(route('research_secretary.narrative-reports.review', $progress), false);

    $terminal = ProjectNarrativeReport::create([...$this->progressData, 'report_type' => 'terminal', 'review_stage' => null]);
    $this->actingAs($this->head)->withSession([User::ACTIVE_WORKSPACE_SESSION_KEY => User::WORKSPACE_RESEARCH_HEAD])
        ->get(route('project-narrative-reports.show', $terminal))->assertOk()
        ->assertSee('Mark reviewed')->assertSee('Request corrections')
        ->assertDontSee('Send concerns to Research Office Secretary')
        ->assertDontSee(route('research_secretary.narrative-reports.review', $terminal), false);
});

test('Head monitoring rows retain submitted status while secretary report content stays hidden', function () {
    $this->travelTo(now()->setDate(2026, 7, 15)->setTime(12, 0));
    foreach ([1 => '2026-03-31', 2 => '2026-06-30'] as $quarter => $date) {
        $period = [
            'reporting_quarter' => $quarter, 'reporting_date' => $date,
            'period_start' => $quarter === 1 ? '2026-01-01' : '2026-04-01', 'period_end' => $date,
        ];
        ProjectProgressReport::create([
            ...$this->reportData, ...$period, 'progress_percentage' => 25,
            'accomplishments' => 'PRIVATE OFFICE TOOL NOTES', 'work_plan' => [], 'budget_utilization' => [],
        ]);
        ProjectNarrativeReport::create([
            ...$this->progressData, ...$period, 'accomplishment_summary' => 'PRIVATE OFFICE PROGRESS NOTES',
        ]);
    }

    $response = $this->actingAs($this->head)->withSession([User::ACTIVE_WORKSPACE_SESSION_KEY => User::WORKSPACE_RESEARCH_HEAD])
        ->get(route('topics.show', $this->topic))->assertOk()
        ->assertDontSee('PRIVATE OFFICE TOOL NOTES')->assertDontSee('PRIVATE OFFICE PROGRESS NOTES');
    foreach (['monitoringQuarterRows', 'progressQuarterRows'] as $key) {
        $rows = $response->viewData($key);
        expect($rows->take(2)->every(fn (array $row): bool => $row['report'] === null && $row['has_submitted_report']))->toBeTrue()
            ->and($rows->firstWhere('quarter', 3)['has_submitted_report'])->toBeFalse();
    }
    $document = new DOMDocument;
    @$document->loadHTML($response->getContent());
    $xpath = new DOMXPath($document);
    $overview = $xpath->query('//*[@data-monitoring-overview]')->item(0);

    expect($overview->textContent)->toContain('Office review')
        ->not->toContain('Q1 · Overdue', 'Q2 · Overdue')
        ->and($xpath->query('//*[@data-monitoring-review-group="Office review"]//a[@href="#progress-reports"]')->length)->toBe(4);
});

test('accepted monitoring tools and legacy progress correction requests do not offer inactive Head review forms', function () {
    $monitoring = ProjectProgressReport::create([
        ...$this->reportData, 'reporting_year' => 2026, 'progress_percentage' => 25,
        'accomplishments' => 'Quarter completed.', 'work_plan' => [], 'budget_utilization' => [],
        'review_stage' => 'head', 'review_status' => 'reviewed', 'research_head_remarks' => 'Accepted.',
    ]);
    $progress = ProjectNarrativeReport::create([
        ...$this->progressData, 'review_stage' => null, 'review_status' => 'revision_requested',
        'research_head_remarks' => 'Existing author correction request.',
    ]);
    $this->actingAs($this->head)->withSession([User::ACTIVE_WORKSPACE_SESSION_KEY => User::WORKSPACE_RESEARCH_HEAD])
        ->get(route('topics.show', $this->topic))->assertOk()
        ->assertDontSee(route('research_head.progress-reports.review', $monitoring), false);
    $this->get(route('project-narrative-reports.show', $progress))->assertOk()
        ->assertSee('Existing author correction request.')
        ->assertDontSee(route('research_head.narrative-progress-reports.review', $progress), false);
});

test('forwarded reports offer a distinct Head decision panel with notes and clearly named PDF previews', function () {
    $monitoring = ProjectProgressReport::create([
        ...$this->reportData, 'reporting_year' => 2026, 'progress_percentage' => 100,
        'accomplishments' => 'Quarter completed.', 'work_plan' => [], 'budget_utilization' => [],
        'review_stage' => 'head', 'forwarded_at' => now(),
    ]);
    ProjectNarrativeReport::create([...$this->progressData, 'review_stage' => 'head', 'forwarded_at' => now()]);
    $response = $this->actingAs($this->head)->withSession([User::ACTIVE_WORKSPACE_SESSION_KEY => User::WORKSPACE_RESEARCH_HEAD])
        ->get(route('topics.show', $this->topic))->assertOk()->assertSee('Research Head decision')->assertDontSee('Full screen');
    $document = new DOMDocument;
    @$document->loadHTML($response->getContent());
    $xpath = new DOMXPath($document);
    expect($xpath->query('//*[@data-head-report-review]')->length)->toBe(2)
        ->and($xpath->query('//*[@data-head-report-review]//input[@type="radio" and @name="review_status"]')->length)->toBe(4)
        ->and($xpath->query('//*[@data-head-report-review]//textarea[@rows="3" and @name="research_head_remarks"]')->length)->toBe(2)
        ->and($xpath->query('//*[@data-head-report-review]//select')->length)->toBe(0);
    if (getenv('ATHENA_EXPORT_HEAD_REVIEW') === '1') {
        file_put_contents(storage_path('framework/testing/monitoring-head-review.html'), $response->getContent());
    }
    $this->actingAs($this->secretary)->withSession([User::ACTIVE_WORKSPACE_SESSION_KEY => User::WORKSPACE_RESEARCH_SECRETARY])
        ->get(route('topics.show', $this->topic))->assertOk()->assertDontSee('data-head-report-review', false);
});

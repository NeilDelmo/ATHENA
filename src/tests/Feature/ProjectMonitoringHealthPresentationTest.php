<?php

use App\Models\ProjectProgressReport;
use App\Models\TopicProposal;
use App\Models\User;
use Illuminate\Support\Facades\File;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    $this->withoutVite();
    $this->travelTo(now()->setDate(2026, 10, 2)->setTime(12, 0));
    foreach (['faculty_researcher', 'research_head'] as $role) {
        Role::firstOrCreate(['name' => $role]);
    }
    $this->researcher = User::factory()->create();
    $this->researcher->assignRole('faculty_researcher');
    $this->head = User::factory()->create();
    $this->head->assignRole('research_head');
    $this->topic = TopicProposal::create([
        'user_id' => $this->researcher->id, 'title' => 'Computed monitoring health project',
        'status' => 'approved', 'project_status' => 'ongoing', 'estimated_duration_months' => 3,
        'notice_to_proceed_issued_at' => '2026-07-01',
        'notice_to_proceed_data' => ['approved_start_date' => '2026-07-01', 'approved_end_date' => '2026-09-30'],
    ]);
    $this->monitoringOverview = function (string $html): DOMXPath {
        $document = new DOMDocument;
        @$document->loadHTML($html);

        return new DOMXPath($document);
    };
});

test('an overdue project displays its computed delay while retaining its editable stored status', function () {
    $response = $this->actingAs($this->head)->get(route('topics.show', $this->topic))->assertOk();
    $xpath = ($this->monitoringOverview)($response->getContent());
    $overview = $xpath->query('//*[@data-monitoring-overview]')->item(0);

    expect($overview->textContent)->toContain('Delayed / overdue', 'Project deadline / reported milestone passed', '1 day overdue')
        ->and($xpath->query('//*[@data-project-status-form]//option[@value="ongoing" and @selected]')->length)->toBe(1)
        ->and($this->topic->fresh()->project_status)->toBe('ongoing');
});

test('reported full progress shows concrete missing reports before project closeout', function () {
    ProjectProgressReport::create([
        'topic_id' => $this->topic->id, 'submitted_by' => $this->researcher->id,
        'reporting_date' => '2026-09-30', 'progress_percentage' => 100,
        'accomplishments' => 'Implementation complete.', 'review_status' => 'reviewed',
    ]);
    $response = $this->actingAs($this->head)->get(route('topics.show', $this->topic))->assertOk();
    $xpath = ($this->monitoringOverview)($response->getContent());
    $overview = $xpath->query('//*[@data-monitoring-overview]')->item(0);

    expect($overview->textContent)->toContain('Reports overdue', 'Missing Progress Reports: Q1')
        ->not->toContain('Delayed / overdue', 'completion decision needed', 'Project closeout pending')
        ->and($this->topic->fresh()->project_status)->toBe('ongoing');
});

test('monitoring summary groups current reviews and identifies the overdue submission', function () {
    $this->topic->update([
        'estimated_duration_months' => 6, 'notice_to_proceed_issued_at' => '2026-04-01',
        'notice_to_proceed_data' => ['approved_start_date' => '2026-04-01', 'approved_end_date' => '2026-09-30'],
    ]);
    foreach ([1 => '2026-06-30', 2 => '2026-09-30'] as $quarter => $date) {
        $tool = $this->topic->progressReports()->create([
            'submitted_by' => $this->researcher->id, 'reporting_date' => $date, 'reporting_quarter' => $quarter,
            'progress_percentage' => 100, 'accomplishments' => 'Recorded accomplishments.',
            'review_stage' => 'head', 'review_status' => $quarter === 1 ? 'reviewed' : 'pending',
            'submitted_at' => $date, 'forwarded_at' => '2026-10-01 12:00:00',
        ]);
    }
    $progress = $this->topic->narrativeReports()->create([
        'submitted_by' => $this->researcher->id, 'report_type' => 'progress', 'reporting_quarter' => 1,
        'period_start' => '2026-04-01', 'period_end' => '2026-06-30', 'submission_date' => '2026-06-30',
        'researchers' => $this->researcher->name, 'implementation_start' => '2026-04-01', 'implementation_end' => '2026-09-30',
        'budget' => 10000, 'funding_agency' => 'University', 'accomplishment_summary' => 'Recorded accomplishments.',
        'introduction' => 'Introduction.', 'objectives' => 'Objectives.', 'methodology' => 'Methods.', 'results_discussion' => 'Results.', 'photos' => [],
        'review_stage' => 'head', 'review_status' => 'pending', 'submitted_at' => '2026-06-30', 'forwarded_at' => '2026-10-01 12:00:00',
    ]);
    $response = $this->actingAs($this->head)->get(route('topics.show', $this->topic))->assertOk();
    $xpath = ($this->monitoringOverview)($response->getContent());
    $overview = $xpath->query('//*[@data-monitoring-overview]')->item(0);
    $group = $xpath->query('//*[@data-monitoring-review-group="Head review"]')->item(0);

    expect($overview->textContent)->toContain('Reports overdue', 'Q2 · Overdue', 'Missing Progress Reports: Q2')
        ->not->toContain('Open now', 'Awaiting Research Head review', 'completion decision needed', 'Project closeout pending')
        ->and($group)->not->toBeNull()
        ->and($group->hasAttribute('open'))->toBeFalse()
        ->and($group->textContent)->toContain('Head review', '2 reports', 'Q2 Monitoring Tool', 'Q1 Progress Report', '1 day with Research Head')
        ->and($xpath->query('//*[@data-monitoring-task-link][contains(@href, "#monitoring-tool-'.$tool->id.'")]')->length)->toBe(1)
        ->and($xpath->query('//*[@data-monitoring-task-link][contains(@href, "#narrative-report-'.$progress->id.'")]')->length)->toBe(1)
        ->and($xpath->query('//*[@data-monitoring-actions]//*[@data-monitoring-notice-link]')->length)->toBe(1)
        ->and($xpath->query('//*[@data-monitoring-actions]//*[@data-project-status-manager]')->length)->toBe(1);

    if (getenv('ATHENA_EXPORT_MONITORING_LAYOUT') === '1') {
        File::put(storage_path('framework/testing/monitoring-summary-head.html'), $response->getContent());
    }

    $progress->replicate()->fill(['reporting_quarter' => 2, 'period_start' => '2026-07-01', 'period_end' => '2026-09-30'])->save();
    $terminal = $progress->replicate()->fill(['report_type' => 'terminal', 'reporting_quarter' => null, 'review_status' => 'reviewed']);
    $terminal->save();
    $response = $this->get(route('topics.show', $this->topic))->assertOk();
    $xpath = ($this->monitoringOverview)($response->getContent());
    expect($xpath->query('//*[@data-monitoring-overview]//dt[normalize-space(.)="Next submission"]/following-sibling::dd')->item(0)->textContent)->toBe('No submissions pending');

    $terminal->update(['review_status' => 'revision_requested']);
    $this->get(route('topics.show', $this->topic))->assertOk()->assertSee('Terminal · Corrections needed');
});

test('missing project schedules show unavailable dates rather than a default deadline', function () {
    $this->topic->update(['estimated_duration_months' => null, 'notice_to_proceed_data' => []]);
    $response = $this->actingAs($this->head)->get(route('topics.show', $this->topic))->assertOk();
    $xpath = ($this->monitoringOverview)($response->getContent());
    $overview = $xpath->query('//*[@data-monitoring-overview]')->item(0);
    $deadline = $xpath->query('//*[@data-monitoring-overview]//dt[normalize-space(.)="Project ends"]/following-sibling::dd')->item(0);

    expect(trim($deadline->textContent))->toBe('Not recorded')
        ->and($overview->textContent)->toContain('No project schedule recorded')
        ->not->toContain('Jun 30, 2027');
});

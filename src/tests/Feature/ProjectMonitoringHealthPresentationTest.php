<?php

use App\Models\ProjectProgressReport;
use App\Models\TopicProposal;
use App\Models\User;
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

test('reported full progress displays a completion decision rather than overdue implementation', function () {
    ProjectProgressReport::create([
        'topic_id' => $this->topic->id, 'submitted_by' => $this->researcher->id,
        'reporting_date' => '2026-09-30', 'progress_percentage' => 100,
        'accomplishments' => 'Implementation complete.', 'review_status' => 'reviewed',
    ]);
    $response = $this->actingAs($this->head)->get(route('topics.show', $this->topic))->assertOk();
    $xpath = ($this->monitoringOverview)($response->getContent());
    $overview = $xpath->query('//*[@data-monitoring-overview]')->item(0);

    expect($overview->textContent)->toContain('Awaiting required report / review', 'Reported 100% — completion decision needed')
        ->not->toContain('Delayed / overdue')
        ->and($this->topic->fresh()->project_status)->toBe('ongoing');
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

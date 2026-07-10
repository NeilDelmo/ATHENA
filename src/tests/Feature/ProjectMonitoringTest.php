<?php

use App\Models\ProjectProgressReport;
use App\Models\ResearchCall;
use App\Models\TopicProposal;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;

beforeEach(function () {
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
    ]);
});

test('an approved project researcher can submit a progress report', function () {
    Storage::fake('local');

    $this->actingAs($this->researcher)
        ->post(route('project-progress.store', $this->topic), [
            'reporting_date' => now()->toDateString(),
            'progress_percentage' => 40,
            'accomplishments' => 'Completed field interviews and initial coding.',
            'issues' => 'Two participants rescheduled.',
            'attachment' => UploadedFile::fake()->create('progress.pdf', 100, 'application/pdf'),
        ])
        ->assertRedirect()
        ->assertSessionHas('success');

    $report = ProjectProgressReport::firstOrFail();
    expect($report->topic_id)->toBe($this->topic->id)
        ->and($report->progress_percentage)->toBe(40)
        ->and($report->review_status)->toBe('pending');
    Storage::disk('local')->assertExists($report->attachment_path);
});

test('another researcher cannot report progress for a project they do not own', function () {
    $other = User::factory()->create();
    $other->assignRole('faculty_researcher');

    $this->actingAs($other)
        ->post(route('project-progress.store', $this->topic), [
            'reporting_date' => now()->toDateString(),
            'progress_percentage' => 20,
            'accomplishments' => 'Unauthorized report.',
        ])
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

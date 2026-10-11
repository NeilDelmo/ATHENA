<?php

use App\Models\ProjectJournalSubmission;
use App\Models\ProjectNarrativeReport;
use App\Models\ProjectProgressReport;
use App\Models\ResearchAnnualTarget;
use App\Models\ResearchCall;
use App\Models\TopicProposal;
use App\Models\User;
use App\Services\ResearchHeadAnalytics;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    $this->travelTo(now()->setDate(2026, 9, 30)->setTime(12, 0));
    Role::firstOrCreate(['name' => 'faculty']);
    $this->faculty = User::factory()->create(['name' => 'Faculty Lead', 'college' => 'Science']);
    $this->faculty->assignRole('faculty');
    $this->project = fn (array $attributes = []) => TopicProposal::create([
        'user_id' => $this->faculty->id, 'title' => fake()->unique()->sentence(),
        'status' => 'approved', 'project_status' => 'ongoing',
        'notice_to_proceed_issued_at' => '2026-08-01',
        'estimated_duration_months' => 12, 'estimated_budget' => 100000,
        ...$attributes,
    ]);
    $this->report = fn (TopicProposal $topic, array $attributes = []) => ProjectProgressReport::create([
        'topic_id' => $topic->id, 'submitted_by' => $topic->user_id,
        'reporting_date' => '2026-08-31', 'progress_percentage' => 25,
        'accomplishments' => 'Recorded progress', 'review_status' => 'reviewed',
        ...$attributes,
    ]);
    $this->narrative = fn (TopicProposal $topic, array $attributes = []) => ProjectNarrativeReport::create([
        'topic_id' => $topic->id, 'submitted_by' => $topic->user_id,
        'report_type' => 'progress', 'submission_date' => '2026-09-01',
        'researchers' => 'Faculty Lead', 'implementation_start' => '2026-08-01',
        'implementation_end' => '2027-07-31', 'budget' => 100000,
        'funding_agency' => 'Institution', 'accomplishment_summary' => 'Recorded progress',
        'introduction' => 'Background', 'objectives' => 'Objectives',
        'methodology' => 'Methodology', 'results_discussion' => 'Results', 'photos' => [],
        'review_status' => 'pending', 'submitted_at' => '2026-09-01',
        ...$attributes,
    ]);
});

test('reported utilization uses the approved funding and explains unavailable denominators', function ($approved, $estimate, $budget, $percentage, $source, $availability) {
    $project = ($this->project)([
        'estimated_budget' => $estimate, 'notice_to_proceed_data' => ['approved_budget' => $approved],
    ]);
    ($this->report)($project, ['budget_utilization' => [['actual_amount' => 40000]]]);
    ($this->report)($project, ['reporting_date' => '2026-09-15', 'submission_status' => 'prepared', 'budget_utilization' => [['actual_amount' => 99999]]]);

    $row = app(ResearchHeadAnalytics::class)->summarize()['projects']->sole();

    expect($row['budget'])->toBe($budget)
        ->and($row['utilized'])->toBe(40000.0)
        ->and($row['percentage'])->toBe($percentage)
        ->and($row['budget_source'])->toBe($source)
        ->and($row['budget_availability'])->toBe($availability)
        ->and($row['report_date'])->toBe('2026-08-31');
})->with([
    'approval supersedes estimate' => ['80000.00', 100000, 80000.0, 50.0, 'approved', 'available'],
    'approval fills missing estimate' => ['80000.00', null, 80000.0, 50.0, 'approved', 'available'],
    'intentional zero approval' => ['0.00', 100000, 0.0, null, 'approved', 'zero'],
    'missing approval falls back' => [null, 100000, 100000.0, 40.0, 'estimated', 'available'],
    'malformed approval falls back' => ['unknown', 100000, 100000.0, 40.0, 'estimated', 'available'],
    'negative approval falls back' => [-1, 100000, 100000.0, 40.0, 'estimated', 'available'],
    'missing both budgets' => [null, null, null, null, null, 'missing'],
    'zero estimate' => [null, 0, 0.0, null, 'estimated', 'zero'],
]);

test('reviewed narrative replacements resolve old corrections while unrelated legacy submissions remain actionable', function () {
    $project = ($this->project)();
    $period = ['reporting_quarter' => 1, 'period_start' => '2026-08-01', 'period_end' => '2026-10-31'];
    $old = ($this->narrative)($project, [...$period, 'version_number' => 1, 'review_status' => 'revision_requested']);
    ($this->narrative)($project, [...$period, 'version_number' => 2, 'review_status' => 'reviewed']);
    $pending = ($this->narrative)($project, ['reporting_quarter' => 2, 'version_number' => 1]);
    ($this->narrative)($project, ['reporting_quarter' => 2, 'version_number' => 2, 'submission_status' => 'prepared', 'review_status' => 'reviewed']);
    $legacy = ($this->narrative)($project, ['review_status' => 'revision_requested']);
    $otherLegacy = ($this->narrative)($project);

    $issues = app(ResearchHeadAnalytics::class)->summarize()['attention']->where('type', 'narrative_review');

    expect($issues->pluck('report_id')->all())->toEqualCanonicalizing([$pending->id, $legacy->id, $otherLegacy->id])
        ->and($issues->pluck('report_id'))->not->toContain($old->id);
});

test('annual events include prior year calls while current cohort and monthly trends remain scoped', function () {
    ResearchAnnualTarget::factory()->create(['academic_year' => '2026-2027', 'starts_on' => '2026-08-01', 'ends_on' => '2027-07-31']);
    $oldCall = ResearchCall::create(['title' => 'Prior call', 'academic_year' => '2025-2026', 'opens_at' => '2025-08-01', 'closes_at' => '2026-07-31', 'status' => 'closed']);
    $currentCall = ResearchCall::create(['title' => 'Current call', 'academic_year' => '2026-2027', 'opens_at' => '2026-08-01', 'closes_at' => '2027-07-31', 'status' => 'open']);
    $oldFaculty = User::factory()->create();
    $oldFaculty->assignRole('faculty');
    $oldProject = ($this->project)(['user_id' => $oldFaculty->id, 'research_call_id' => $oldCall->id]);
    ($this->project)(['research_call_id' => $currentCall->id]);
    $oldProject->versions()->create([
        'submitted_by' => $oldFaculty->id, 'version_number' => 2, 'submission_type' => 'revision',
        'file_path' => 'proposal.pdf', 'original_filename' => 'proposal.pdf', 'title' => $oldProject->title,
        'created_at' => '2026-09-01',
    ]);
    ProjectJournalSubmission::factory()->create([
        'topic_id' => $oldProject->id, 'added_by' => $oldFaculty->id,
        'status' => 'published', 'published_on' => '2026-09-01',
    ]);

    $data = app(ResearchHeadAnalytics::class)->summarize('2026-2027');

    expect($data['targets']->pluck('actual')->all())->toBe([2, 1, 2])
        ->and($data['kpis']['active'])->toBe(1)
        ->and($data['kpis']['faculty'])->toBe(1)
        ->and($data['trend']->sum('revision'))->toBe(0);
});

test('annual faculty participation requires an overlapping schedule for projects issued before the period', function () {
    ($this->project)([
        'notice_to_proceed_issued_at' => '2026-07-01',
        'notice_to_proceed_data' => ['approved_start_date' => '2026-10-01', 'approved_end_date' => '2026-12-31'],
    ]);

    expect(app(ResearchHeadAnalytics::class)->summarize('', '2026-08-01', '2026-09-30')['targets']->last()['actual'])->toBe(0);

    ($this->project)([
        'notice_to_proceed_issued_at' => '2026-07-01',
        'notice_to_proceed_data' => ['approved_start_date' => '2026-07-01', 'approved_end_date' => '2026-08-01'],
    ]);

    expect(app(ResearchHeadAnalytics::class)->summarize('', '2026-08-01', '2026-09-30')['targets']->last()['actual'])->toBe(1);
});

test('faculty drill down lists exactly the active eligible participants and their projects', function () {
    $member = User::factory()->create(['name' => 'Accepted Member', 'college' => 'Arts']);
    $member->assignRole('faculty');
    $secretary = User::factory()->create();
    $secretary->assignRole('faculty');
    $invited = User::factory()->create();
    $invited->assignRole('faculty');
    $unverified = User::factory()->unverified()->create();
    $unverified->assignRole('faculty');
    $nonFaculty = User::factory()->create();
    $project = ($this->project)(['title' => 'Active research']);
    foreach ([
        ['user_id' => $member->id, 'email' => 'linked-member@example.test', 'accepted_at' => now()],
        ['user_id' => null, 'email' => strtoupper($member->email), 'accepted_at' => now()],
        ['user_id' => $this->faculty->id, 'email' => $this->faculty->email, 'accepted_at' => now()],
        ['user_id' => $secretary->id, 'email' => $secretary->email, 'accepted_at' => now(), 'project_role' => 'secretary'],
        ['user_id' => $invited->id, 'email' => $invited->email],
        ['user_id' => null, 'email' => $unverified->email, 'accepted_at' => now()],
        ['user_id' => $nonFaculty->id, 'email' => $nonFaculty->email, 'accepted_at' => now()],
    ] as $attributes) {
        $project->collaborators()->create(['name' => 'Project staff', ...$attributes]);
    }
    ($this->project)(['user_id' => $invited->id, 'project_status' => 'completed']);

    $data = app(ResearchHeadAnalytics::class)->summarize();

    expect($data['kpis']['faculty'])->toBe(2)
        ->and($data['faculty']->pluck('id')->all())->toEqualCanonicalizing([$this->faculty->id, $member->id])
        ->and($data['faculty']->firstWhere('id', $this->faculty->id))->toMatchArray([
            'name' => 'Faculty Lead', 'college' => 'Science',
            'projects' => [['id' => $project->id, 'title' => 'Active research', 'role' => 'lead']],
        ])
        ->and($data['faculty']->firstWhere('id', $member->id))->toMatchArray([
            'name' => 'Accepted Member', 'college' => 'Arts',
            'projects' => [['id' => $project->id, 'title' => 'Active research', 'role' => 'member']],
        ]);
});

test('project drill down retains its delay evidence and latest report date', function () {
    $project = ($this->project)([
        'notice_to_proceed_data' => ['approved_start_date' => '2026-08-01', 'approved_end_date' => '2026-09-20'],
    ]);
    $report = ($this->report)($project, ['reporting_date' => '2026-09-15']);

    $row = app(ResearchHeadAnalytics::class)->summarize()['projects']->sole();

    expect($row['status'])->toBe('delayed')
        ->and($row['issues']->pluck('type'))->toContain('delayed')
        ->and($row['delay_reason'])->toBe('Project deadline / reported milestone passed')
        ->and($row['delay_days'])->toBe(9)
        ->and($row['report_id'])->toBe($report->id)
        ->and($row['report_date'])->toBe('2026-09-15');
});

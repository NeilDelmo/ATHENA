<?php

use App\Livewire\ResearchHeadDashboard;
use App\Models\ProjectJournalSubmission;
use App\Models\ProjectNarrativeReport;
use App\Models\ProjectProgressReport;
use App\Models\ResearchAnnualTarget;
use App\Models\ResearchCall;
use App\Models\TopicProposal;
use App\Models\User;
use App\Services\ResearchHeadAnalytics;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    $this->travelTo(now()->setDate(2026, 9, 30)->setTime(12, 0));
    foreach (['faculty', 'research_head', 'research_coordinator'] as $role) {
        Role::firstOrCreate(['name' => $role]);
    }
    $this->head = User::factory()->create();
    $this->head->assignRole('research_head');
    $this->faculty = User::factory()->create();
    $this->faculty->assignRole('faculty');
    $this->call = ResearchCall::create(['title' => 'Analytics verification', 'academic_year' => '2026-2027', 'opens_at' => '2026-08-01', 'closes_at' => '2026-10-31', 'status' => 'open']);
    $this->topic = fn (array $attributes = []) => TopicProposal::create([
        'user_id' => $this->faculty->id, 'research_call_id' => $this->call->id,
        'title' => fake()->unique()->sentence(), 'status' => 'pending', ...$attributes,
    ]);
    $this->project = fn (array $attributes = []) => ($this->topic)([
        'status' => 'approved', 'notice_to_proceed_issued_at' => '2026-08-01', 'project_status' => 'ongoing',
        'estimated_duration_months' => 12, 'estimated_budget' => 100000, ...$attributes,
    ]);
    $this->version = function (TopicProposal $topic, int $number, string $type, string $date): void {
        $version = $topic->versions()->make(['submitted_by' => $this->faculty->id, 'version_number' => $number,
            'submission_type' => $type, 'file_path' => 'analytics.pdf', 'original_filename' => 'analytics.pdf',
            'title' => $topic->title, 'estimated_budget' => 1000, 'estimated_duration_months' => 6]);
        $version->created_at = $date;
        $version->save();
    };
    $this->report = fn (TopicProposal $topic, array $attributes = []) => ProjectProgressReport::create([
        'topic_id' => $topic->id, 'submitted_by' => $this->faculty->id, 'reporting_date' => '2026-08-31',
        'progress_percentage' => 25, 'accomplishments' => 'Recorded progress', 'review_status' => 'reviewed', ...$attributes,
    ]);
});

test('the pipeline reuses workflow statuses and review KPIs exclude external review stages', function () {
    foreach (['pending', 'resubmitted', 'expert_review', 'for_final_decision', 'revision_requested', 'gad_review', 'lrec_queued', 'lrec_review', 'ready_for_signature', 'approved', 'rejected'] as $status) {
        ($this->topic)(['status' => $status]);
    }
    ($this->project)();
    $data = app(ResearchHeadAnalytics::class)->summarize();
    expect($data['kpis']['review'])->toBe(4)->and($data['kpis']['active'])->toBe(1)
        ->and($data['pipeline']->pluck('count', 'key')->all())->toBe([
            'submitted' => 1, 'head_review' => 3, 'revision_requested' => 1, 'gad_review' => 1, 'lrec' => 2, 'signing' => 2, 'approved' => 1,
        ]);
    Livewire::actingAs($this->head)->test(ResearchHeadDashboard::class)->call('setPipeline', 'lrec')
        ->assertViewHas('topics', fn ($topics): bool => $topics->total() === 2)
        ->call('setPipeline', 'signing')->assertViewHas('topics', fn ($topics): bool => $topics->total() === 2);
});

test('monthly trends count one new proposal separately from each revision and preserve first-submission cohorts', function () {
    $topic = ($this->topic)(['status' => 'resubmitted']);
    ($this->version)($topic, 1, 'initial', '2026-08-05');
    ($this->version)($topic, 2, 'revision', '2026-09-05');
    ($this->version)($topic, 3, 'revision', '2026-09-20');
    $data = app(ResearchHeadAnalytics::class)->summarize('', '2026-08-01', '2026-10-31');
    expect($data['trend'])->toHaveCount(3)->and($data['trend']->sum('new'))->toBe(1)
        ->and($data['trend']->sum('revision'))->toBe(2)->and($data['trend']->last()['new'])->toBe(0);
    expect(app(ResearchHeadAnalytics::class)->topics('', '2026-09-01', '2026-09-30')->count())->toBe(0);
    expect(app(ResearchHeadAnalytics::class)->summarize('', '2026-09-01', '2026-09-30')['trend']->sum('revision'))->toBe(2);
    Livewire::actingAs($this->head)->test(ResearchHeadDashboard::class)->set('submissionMonth', '2026-09')
        ->assertViewHas('topics', fn ($topics): bool => $topics->total() === 1);
});

test('project analytics derive delay and missing reports from recorded schedules without treating completion percentage as completion', function () {
    $ended = ($this->project)(['notice_to_proceed_data' => ['approved_start_date' => '2026-01-01', 'approved_end_date' => '2026-06-30'], 'notice_to_proceed_issued_at' => '2026-01-01']);
    $awaiting = ($this->project)();
    ($this->report)($awaiting, ['progress_percentage' => 100, 'review_status' => 'pending']);
    ($this->project)(['project_status' => 'completed']);
    ($this->project)(['estimated_duration_months' => null]);
    $data = app(ResearchHeadAnalytics::class)->summarize();
    expect($data['kpis']['active'])->toBe(3)->and($data['kpis']['completed'])->toBe(1)->and($data['kpis']['delayed'])->toBe(1)
        ->and($data['projectStatuses']->pluck('count', 'key')->all())->toBe(['ongoing' => 1, 'delayed' => 1, 'awaiting' => 1, 'completed' => 1])
        ->and($data['unknownSchedules'])->toBe(1);
    expect($data['attention']->where('id', $ended->id)->pluck('type')->all())->toContain('delayed', 'quarterly', 'terminal');
    expect($data['attention']->where('id', $awaiting->id)->pluck('type')->all())->toContain('completion', 'report_review');
});

test('reported approved-plan milestones can flag delay before the overall project end', function () {
    $project = ($this->project)();
    ($this->report)($project, ['work_plan' => [['source_work_plan_index' => 0, 'target_completion_date' => '2026-09-01', 'percent_weight' => 100, 'accomplished_percentage' => 25]]]);
    expect(app(ResearchHeadAnalytics::class)->summarize()['kpis']['delayed'])->toBe(1);
    ($this->report)($project, ['reporting_date' => '2026-09-20', 'work_plan' => [['source_work_plan_index' => 0, 'target_completion_date' => '2026-09-01', 'percent_weight' => 100, 'accomplished_percentage' => 100]]]);
    expect(app(ResearchHeadAnalytics::class)->summarize()['kpis']['delayed'])->toBe(0);
});

test('report replacements remove missing-report alerts and only the latest terminal report is actionable', function () {
    $project = ($this->project)(['notice_to_proceed_issued_at' => '2026-01-01', 'estimated_duration_months' => 3]);
    ($this->report)($project, ['reporting_date' => '2026-03-31']);
    ProjectNarrativeReport::create(['topic_id' => $project->id, 'submitted_by' => $this->faculty->id,
        'report_type' => 'terminal', 'submission_date' => '2026-04-01', 'review_status' => 'reviewed',
        'researchers' => $this->faculty->name, 'implementation_start' => '2026-01-01', 'implementation_end' => '2026-03-31',
        'budget' => 100000, 'funding_agency' => 'Institution', 'accomplishment_summary' => 'Complete',
        'introduction' => 'Introduction', 'rationale' => 'Rationale', 'objectives' => 'Objectives', 'methodology' => 'Methodology', 'results_discussion' => 'Results', 'photos' => []]);
    $types = app(ResearchHeadAnalytics::class)->summarize()['attention']->pluck('type');
    expect($types)->not->toContain('quarterly', 'terminal', 'terminal_review');
});

test('budget utilization uses only usable latest submitted snapshots and does not treat missing reports as zero spend', function () {
    $reported = ($this->project)(['project_status' => 'completed']);
    ($this->report)($reported, ['budget_utilization' => [['actual_amount' => 10000]]]);
    ($this->report)($reported, ['reporting_date' => '2026-09-20', 'budget_utilization' => [['actual_amount' => 30000]]]);
    ($this->report)($reported, ['reporting_date' => '2026-09-25', 'submission_status' => 'prepared', 'budget_utilization' => [['actual_amount' => 99999]]]);
    ($this->project)();
    $data = app(ResearchHeadAnalytics::class)->summarize();
    expect($data['budget']['percentage'])->toBe(30.0)->and($data['budget']['utilized'])->toBe(30000.0)
        ->and($data['budget']['budget'])->toBe(100000.0)->and($data['budget']['reported'])->toBe(1)->and($data['budget']['total'])->toBe(2)
        ->and($data['attention']->pluck('type'))->toContain('budget');
});

test('faculty participation deduplicates accepted faculty and excludes invitations and secretaries', function () {
    $member = User::factory()->create();
    $member->assignRole('faculty');
    $invited = User::factory()->create();
    $invited->assignRole('faculty');
    $secretary = User::factory()->create();
    $secretary->assignRole('faculty');
    foreach (range(1, 2) as $index) {
        $project = ($this->project)();
        $project->collaborators()->create(['user_id' => $member->id, 'name' => $member->name, 'email' => $member->email, 'accepted_at' => now()]);
        $project->collaborators()->create(['user_id' => $invited->id, 'name' => $invited->name, 'email' => $invited->email]);
        $project->collaborators()->create(['user_id' => $secretary->id, 'name' => $secretary->name, 'email' => $secretary->email, 'accepted_at' => now(), 'project_role' => 'secretary']);
    }
    expect(app(ResearchHeadAnalytics::class)->summarize()['kpis']['faculty'])->toBe(2);
});

test('academic year targets are persisted securely with nullable targets and exact dates', function () {
    Livewire::actingAs($this->head)->test(ResearchHeadDashboard::class)
        ->set('targetForm', ['academic_year' => '2026-2027', 'starts_on' => '2026-08-01', 'ends_on' => '2027-07-31', 'projects_target' => 10, 'publications_target' => '', 'faculty_target' => 0])
        ->call('saveTargets')->assertHasNoErrors()->assertSet('academicYear', '2026-2027')
        ->assertSet('fromDate', '2026-08-01')->assertSee('Academic-year targets saved.');
    $target = ResearchAnnualTarget::sole();
    expect($target->publications_target)->toBeNull()->and($target->faculty_target)->toBe(0)->and($target->updated_by)->toBe($this->head->id);
    Livewire::actingAs($this->faculty)->test(ResearchHeadDashboard::class)->assertForbidden();
    $office = User::factory()->create();
    $office->assignRole('research_coordinator');
    $this->actingAs($office)->get(route('research_head.dashboard'))->assertForbidden();
});

test('year targets count dated published outputs rather than acceptances or undated publications', function () {
    ResearchAnnualTarget::factory()->create(['academic_year' => '2026-2027', 'starts_on' => '2026-08-01', 'ends_on' => '2027-07-31']);
    $project = ($this->project)();
    foreach ([['published', '2026-09-01', 'One output'], ['published', '2026-09-02', ' One  output '], ['accepted', null, 'Accepted only'], ['published', null, 'Undated']] as [$status, $date, $title]) {
        ProjectJournalSubmission::factory()->create(['topic_id' => $project->id, 'added_by' => $this->faculty->id, 'manuscript_title' => $title, 'status' => $status, 'published_on' => $date]);
    }
    $data = app(ResearchHeadAnalytics::class)->summarize('2026-2027');
    expect($data['targets']->pluck('actual')->all())->toBe([1, 1, 1])->and($data['undatedPublications'])->toBe(1)->and($data['targetMatches'])->toBeTrue();
    expect(app(ResearchHeadAnalytics::class)->summarize('2026-2027', '2026-09-01', '2026-09-30')['targetMatches'])->toBeFalse();
});

test('unknown year dates and empty datasets have honest no-data states', function () {
    $data = app(ResearchHeadAnalytics::class)->summarize('2026-2027');
    expect($data['periodAvailable'])->toBeFalse()->and($data['trend'])->toBeEmpty()->and($data['targets']->pluck('actual')->all())->toBe([null, null, null])
        ->and($data['budget']['percentage'])->toBeNull();
    Livewire::actingAs($this->head)->test(ResearchHeadDashboard::class)->assertSee('No data yet')
        ->set('academicYear', '2026-2027')->assertSee('Set this academic year');
});

test('invalid dates and target values are rejected without writes', function () {
    Livewire::actingAs($this->head)->test(ResearchHeadDashboard::class)->set('fromDate', 'invalid')->assertHasErrors('fromDate');
    Livewire::actingAs($this->head)->test(ResearchHeadDashboard::class)->set('targetForm', [
        'academic_year' => '2026-2027', 'starts_on' => '2026-08-01', 'ends_on' => '2026-07-31',
        'projects_target' => -1, 'publications_target' => '', 'faculty_target' => '',
    ])->call('saveTargets')->assertHasErrors(['targetForm.ends_on', 'targetForm.projects_target']);
    expect(ResearchAnnualTarget::count())->toBe(0);
});

test('initial-screening deadlines do not incorrectly mark LREC revisions overdue', function () {
    $this->call->update(['paper_revisions_end_date' => '2026-09-01']);
    $initial = ($this->topic)(['status' => 'revision_requested', 'review_stage' => 'initial']);
    $lrec = ($this->topic)(['status' => 'revision_requested', 'review_stage' => 'lrec']);
    $items = app(ResearchHeadAnalytics::class)->summarize()['attention'];
    expect($items->firstWhere('id', $initial->id)['issue'])->toBe('Overdue faculty revision')
        ->and($items->firstWhere('id', $lrec->id)['basis'])->toBe('waiting, not an overdue calculation');
});

test('period achievements include projects whose proposal was submitted before the selected date range', function () {
    $project = ($this->project)();
    ($this->version)($project, 1, 'initial', '2026-06-01');
    ($this->version)($project, 2, 'revision', '2026-08-15');
    $data = app(ResearchHeadAnalytics::class)->summarize('', '2026-08-01', '2026-09-30');
    expect($data['kpis']['active'])->toBe(0)->and($data['targets']->first()['actual'])->toBe(1)
        ->and($data['targets']->last()['actual'])->toBe(1)->and($data['trend']->sum('revision'))->toBe(1);
    Livewire::actingAs($this->head)->test(ResearchHeadDashboard::class)->set('fromDate', '2026-08-01')->set('toDate', '2026-09-30')
        ->set('submissionMonth', '2026-08')->assertViewHas('topics', fn ($topics): bool => $topics->total() === 1)
        ->assertSee(route('topics.show', $project).'#project-monitoring', false);
});

test('analytics pagination and derived project drill-down reset properly', function () {
    foreach (range(1, 11) as $index) {
        ($this->project)(['project_status' => 'delayed']);
    }
    ($this->project)(['project_status' => 'completed']);
    Livewire::actingAs($this->head)->test(ResearchHeadDashboard::class)->call('showProjects', 'delayed')
        ->assertViewHas('projectItems', fn ($items): bool => $items->total() === 11 && $items->count() === 9)
        ->call('setPage', 2, 'projectPage')->assertViewHas('projectItems', fn ($items): bool => $items->count() === 2)
        ->call('showProjects', 'completed')->assertViewHas('projectItems', fn ($items): bool => $items->currentPage() === 1 && $items->total() === 1)
        ->call('resetAnalyticsFilters')->assertViewHas('projectItems', fn ($items): bool => $items->currentPage() === 1 && $items->total() === 12);
});

test('an old quarter revision cannot overwrite a newer milestone completion observation', function () {
    $project = ($this->project)();
    ($this->report)($project, ['reporting_date' => '2026-09-15', 'work_plan' => [['source_work_plan_index' => 0, 'target_completion_date' => '2026-09-01', 'percent_weight' => 100, 'accomplished_percentage' => 100]]]);
    ($this->report)($project, ['reporting_date' => '2026-08-31', 'version_number' => 2, 'work_plan' => [['source_work_plan_index' => 0, 'target_completion_date' => '2026-09-01', 'percent_weight' => 100, 'accomplished_percentage' => 25]]]);
    expect(app(ResearchHeadAnalytics::class)->summarize()['kpis']['delayed'])->toBe(0);
});

test('saved academic-year dates and target editor survive dashboard reloads', function () {
    ResearchAnnualTarget::factory()->create(['academic_year' => '2026-2027', 'starts_on' => '2026-08-01', 'ends_on' => '2027-07-31']);
    Livewire::actingAs($this->head)->withQueryParams(['academicYear' => '2026-2027'])->test(ResearchHeadDashboard::class)
        ->assertSet('fromDate', '2026-08-01')->assertSet('toDate', '2027-07-31')
        ->call('editTargets')->assertSet('editingTargets', true)->assertSet('targetForm.projects_target', 10)
        ->call('editTargets')->assertSet('editingTargets', false);
});

test('a project reported at 100 percent awaits completion review rather than being marked delayed implementation', function () {
    $project = ($this->project)(['notice_to_proceed_issued_at' => '2026-01-01', 'estimated_duration_months' => 3]);
    ($this->report)($project, ['reporting_date' => '2026-03-31', 'progress_percentage' => 100]);
    $data = app(ResearchHeadAnalytics::class)->summarize();
    expect($data['kpis']['delayed'])->toBe(0)->and($data['kpis']['completed'])->toBe(0)
        ->and($data['projectStatuses']->firstWhere('key', 'awaiting')['count'])->toBe(1);
});

test('a Research Head account using the faculty workspace cannot access analytics actions', function () {
    $this->actingAs($this->head)->withSession([User::ACTIVE_WORKSPACE_SESSION_KEY => User::WORKSPACE_FACULTY]);
    Livewire::test(ResearchHeadDashboard::class)->assertForbidden();
});

test('milestone completion uses its assigned project weight and unknown weights are not guessed', function () {
    $project = ($this->project)();
    ($this->report)($project, ['work_plan' => [['source_work_plan_index' => 0, 'target_completion_date' => '2026-09-01', 'percent_weight' => 20, 'accomplished_percentage' => 20]]]);
    expect(app(ResearchHeadAnalytics::class)->summarize()['kpis']['delayed'])->toBe(0);
    ($this->report)($project, ['reporting_date' => '2026-09-15', 'work_plan' => [['source_work_plan_index' => 0, 'target_completion_date' => '2026-09-01', 'percent_weight' => 20, 'accomplished_percentage' => 10]]]);
    expect(app(ResearchHeadAnalytics::class)->summarize()['kpis']['delayed'])->toBe(1);
    ($this->report)($project, ['reporting_date' => '2026-09-20', 'work_plan' => [['source_work_plan_index' => 0, 'target_completion_date' => '2026-09-01', 'accomplished_percentage' => 10]]]);
    expect(app(ResearchHeadAnalytics::class)->summarize()['kpis']['delayed'])->toBe(0);
});

test('manually delayed projects and completed projects without terminal reports do not invent waiting dates', function () {
    $delayed = ($this->project)(['project_status' => 'delayed', 'status_started_at' => '2026-08-01']);
    $completed = ($this->project)(['project_status' => 'completed']);
    $data = app(ResearchHeadAnalytics::class)->summarize();
    expect($data['attention']->where('id', $delayed->id)->firstWhere('type', 'delayed')['days'])->toBeNull()
        ->and($data['attention']->where('id', $completed->id)->firstWhere('type', 'terminal')['days'])->toBeNull()
        ->and($data['projectStatuses']->firstWhere('key', 'completed')['count'])->toBe(1);
});

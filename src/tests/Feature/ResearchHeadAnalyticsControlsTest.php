<?php

use App\Livewire\ResearchHeadDashboard;
use App\Models\ResearchAnnualTarget;
use App\Models\ResearchCall;
use App\Models\TopicProposal;
use App\Models\User;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    $this->travelTo(now()->setDate(2026, 9, 30)->setTime(12, 0));
    foreach (['faculty', 'research_head'] as $role) {
        Role::firstOrCreate(['name' => $role]);
    }
    $this->head = User::factory()->create();
    $this->head->assignRole('research_head');
    $this->faculty = User::factory()->create();
    $this->faculty->assignRole('faculty');
    $this->call = ResearchCall::create(['title' => 'Analytics controls', 'academic_year' => '2026-2027', 'opens_at' => '2026-08-01', 'closes_at' => '2026-10-31', 'status' => 'open']);
    ResearchAnnualTarget::factory()->create(['academic_year' => '2026-2027', 'starts_on' => '2026-08-01', 'ends_on' => '2027-07-31']);
    ResearchAnnualTarget::factory()->create(['academic_year' => '2025-2026', 'starts_on' => '2025-08-01', 'ends_on' => '2026-07-31']);
    $this->topic = fn (array $attributes = []) => TopicProposal::create([
        'user_id' => $this->faculty->id, 'research_call_id' => $this->call->id,
        'title' => fake()->unique()->sentence(), 'status' => 'pending', ...$attributes,
    ]);
});

test('review KPI opens the whole selected cohort queue despite local inbox filters', function () {
    ($this->topic)(['title' => 'Pending coastal study']);
    ($this->topic)(['title' => 'Resubmitted energy study', 'status' => 'resubmitted']);
    ($this->topic)(['status' => 'revision_requested']);

    Livewire::actingAs($this->head)->withQueryParams([
        'academicYear' => '2026-2027', 'fromDate' => '2026-08-01', 'toDate' => '2027-07-31',
        'search' => 'no match', 'status' => 'revision_requested', 'attention' => 'repeat', 'submissionMonth' => '2026-08',
        'projectStatus' => 'delayed', 'showFaculty' => true,
    ])->test(ResearchHeadDashboard::class)->call('setPage', 3)
        ->call('showReviewQueue')->assertSet('pipeline', 'awaiting_review')
        ->assertSet('search', '')->assertSet('status', '')->assertSet('attention', '')->assertSet('submissionMonth', '')
        ->assertSet('projectStatus', '')->assertSet('showFaculty', false)
        ->assertSet('academicYear', '2026-2027')->assertSet('fromDate', '2026-08-01')->assertSet('toDate', '2027-07-31')
        ->assertViewHas('topics', fn ($items): bool => $items->currentPage() === 1 && $items->total() === 2)
        ->assertDispatched('analytics-drilldown', target: 'received-proposals')
        ->call('showReviewQueue')->assertSet('pipeline', 'awaiting_review');
});

test('pipeline drill downs replace conflicting filters and retain stage toggle behavior', function () {
    ($this->topic)(['title' => 'Unopened study']);
    ($this->topic)(['title' => 'Faculty revision', 'status' => 'revision_requested']);

    Livewire::actingAs($this->head)->withQueryParams([
        'academicYear' => '2026-2027', 'search' => 'no match', 'status' => 'revision_requested',
        'attention' => 'repeat', 'submissionMonth' => '2026-08', 'projectStatus' => 'completed', 'showFaculty' => true,
    ])->test(ResearchHeadDashboard::class)->call('setPipeline', 'submitted')
        ->assertSet('pipeline', 'submitted')->assertSet('search', '')->assertSet('status', '')
        ->assertSet('attention', '')->assertSet('submissionMonth', '')->assertSet('projectStatus', '')->assertSet('showFaculty', false)
        ->assertViewHas('topics', fn ($items): bool => $items->total() === 1 && $items->first()->title === 'Unopened study')
        ->assertDispatched('analytics-drilldown', target: 'received-proposals')
        ->call('setPipeline', 'submitted')->assertSet('pipeline', '')
        ->assertViewHas('topics', fn ($items): bool => $items->total() === 2)
        ->call('setPipeline', 'unknown-stage')->assertSet('pipeline', '');
});

test('month drill downs clear local restrictions and toggle the selected chart month', function () {
    $topic = ($this->topic)(['title' => 'Revised coastal study', 'status' => 'resubmitted']);
    foreach ([[1, 'initial', '2026-08-05'], [2, 'revision', '2026-09-05']] as [$number, $type, $date]) {
        $version = $topic->versions()->make(['submitted_by' => $this->faculty->id, 'version_number' => $number,
            'submission_type' => $type, 'file_path' => 'analytics.pdf', 'original_filename' => 'analytics.pdf', 'title' => $topic->title]);
        $version->created_at = $date;
        $version->save();
    }

    Livewire::actingAs($this->head)->withQueryParams([
        'academicYear' => '2026-2027', 'pipeline' => 'submitted', 'status' => 'pending', 'search' => 'no match', 'attention' => 'repeat',
    ])->test(ResearchHeadDashboard::class)->call('showSubmissionMonth', '2026-09')
        ->assertSet('submissionMonth', '2026-09')->assertSet('pipeline', '')->assertSet('status', '')
        ->assertSet('search', '')->assertSet('attention', '')
        ->assertViewHas('topics', fn ($items): bool => $items->total() === 1 && $items->first()->id === $topic->id)
        ->assertDispatched('analytics-drilldown', target: 'received-proposals')
        ->call('showSubmissionMonth', '2026-09')->assertSet('submissionMonth', '')
        ->call('showSubmissionMonth', '2026-13')->assertHasErrors('submissionMonth')->assertSet('submissionMonth', '')
        ->call('showSubmissionMonth', '2025-09')->assertHasErrors('submissionMonth')->assertSet('submissionMonth', '');
});

test('project KPI drill downs retain their selection on repeated clicks and expose the matching list', function () {
    foreach (['delayed', 'delayed', 'completed'] as $status) {
        ($this->topic)(['status' => 'approved', 'notice_to_proceed_issued_at' => '2026-08-01',
            'project_status' => $status, 'estimated_duration_months' => 12]);
    }

    Livewire::actingAs($this->head)->withQueryParams(['showFaculty' => true])->test(ResearchHeadDashboard::class)
        ->call('showProjects', 'delayed')->assertSet('showFaculty', false)->assertSet('projectStatus', 'delayed')
        ->assertViewHas('projectItems', fn ($items): bool => $items->total() === 2 && $items->currentPage() === 1)
        ->assertDispatched('analytics-drilldown', target: 'active-projects')
        ->call('showProjects', 'delayed')->assertSet('projectStatus', 'delayed')
        ->call('showProjects', 'completed')->assertViewHas('projectItems', fn ($items): bool => $items->total() === 1);
});

test('faculty KPI opens independently paginated participants in the selected cohort', function () {
    foreach (range(1, 7) as $number) {
        $faculty = $number === 1 ? $this->faculty : User::factory()->create();
        $faculty->assignRole('faculty');
        ($this->topic)(['user_id' => $faculty->id, 'status' => 'approved', 'notice_to_proceed_issued_at' => '2026-08-01',
            'project_status' => 'ongoing', 'estimated_duration_months' => 12]);
    }

    Livewire::actingAs($this->head)->withQueryParams(['academicYear' => '2026-2027', 'projectStatus' => 'delayed'])->test(ResearchHeadDashboard::class)
        ->call('showFacultyResearchers')->assertSet('showFaculty', true)->assertSet('projectStatus', '')
        ->assertViewHas('facultyItems', fn ($items): bool => $items->total() === 7 && $items->count() === 6)
        ->assertDispatched('analytics-drilldown', target: 'faculty-researchers')
        ->call('nextPage', 'facultyPage')->assertViewHas('facultyItems', fn ($items): bool => $items->currentPage() === 2 && $items->count() === 1)
        ->assertViewHas('projectItems', fn ($items): bool => $items->currentPage() === 1)
        ->call('showFacultyResearchers')->assertViewHas('facultyItems', fn ($items): bool => $items->currentPage() === 1);
});

test('cohort changes close stale target editing and clear scoped drill downs', function (string $property, string $value) {
    Livewire::actingAs($this->head)->withQueryParams([
        'academicYear' => '2026-2027', 'submissionMonth' => '2026-09', 'projectStatus' => 'delayed', 'showFaculty' => true,
    ])->test(ResearchHeadDashboard::class)->call('editTargets')->set('targetForm.projects_target', 99)
        ->call('setPage', 2, 'facultyPage')->set($property, $value)
        ->assertSet('editingTargets', false)->assertSet('targetForm.academic_year', '')
        ->assertSet('submissionMonth', '')->assertSet('projectStatus', '')->assertSet('showFaculty', false)
        ->assertViewHas('facultyItems', fn ($items): bool => $items->currentPage() === 1);

    expect(ResearchAnnualTarget::where('academic_year', '2026-2027')->first()->projects_target)->toBe(10);
})->with([
    ['academicYear', '2025-2026'], ['fromDate', '2026-09-01'], ['toDate', '2027-06-30'],
]);

test('saving a different target year clears incompatible scoped selections', function () {
    Livewire::actingAs($this->head)->withQueryParams([
        'academicYear' => '2026-2027', 'submissionMonth' => '2026-09', 'projectStatus' => 'delayed', 'showFaculty' => true,
    ])->test(ResearchHeadDashboard::class)->call('editTargets')->set('targetForm', [
        'academic_year' => '2025-2026', 'starts_on' => '2025-08-01', 'ends_on' => '2026-07-31',
        'projects_target' => 4, 'publications_target' => '', 'faculty_target' => 0,
    ])->call('saveTargets')->assertHasNoErrors()->assertSet('academicYear', '2025-2026')
        ->assertSet('fromDate', '2025-08-01')->assertSet('toDate', '2026-07-31')
        ->assertSet('submissionMonth', '')->assertSet('projectStatus', '')->assertSet('showFaculty', false);

    expect(ResearchAnnualTarget::where('academic_year', '2025-2026')->first()->projects_target)->toBe(4)
        ->and(ResearchAnnualTarget::where('academic_year', '2026-2027')->first()->projects_target)->toBe(10);
});

test('reset closes the editor and clears every analytics selection', function () {
    Livewire::actingAs($this->head)->withQueryParams([
        'academicYear' => '2026-2027', 'fromDate' => '2026-08-01', 'toDate' => '2027-07-31',
        'submissionMonth' => '2026-09', 'projectStatus' => 'delayed', 'showFaculty' => true, 'attention' => 'repeat', 'search' => 'coastal',
    ])->test(ResearchHeadDashboard::class)->call('editTargets')->call('resetAnalyticsFilters')
        ->assertSet('academicYear', '')->assertSet('fromDate', '')->assertSet('toDate', '')
        ->assertSet('submissionMonth', '')->assertSet('projectStatus', '')->assertSet('showFaculty', false)
        ->assertSet('attention', '')->assertSet('search', '')->assertSet('editingTargets', false)->assertSet('targetForm.academic_year', '');
});

test('clearing proposal filters removes the hidden repeat restriction and retains the cohort', function () {
    ($this->topic)(['title' => 'Visible pending study']);

    Livewire::actingAs($this->head)->withQueryParams([
        'academicYear' => '2026-2027', 'search' => 'no match', 'attention' => 'repeat', 'pipeline' => 'submitted',
        'status' => 'pending', 'submissionMonth' => '2026-08',
    ])->test(ResearchHeadDashboard::class)->call('clearProposalFilters')
        ->assertSet('academicYear', '2026-2027')->assertSet('fromDate', '2026-08-01')->assertSet('toDate', '2027-07-31')
        ->assertSet('search', '')->assertSet('attention', '')->assertSet('pipeline', '')->assertSet('status', '')->assertSet('submissionMonth', '')
        ->assertViewHas('topics', fn ($items): bool => $items->total() === 1);
});

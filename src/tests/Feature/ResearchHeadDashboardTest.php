<?php

use App\Livewire\ResearchHeadDashboard;
use App\Models\ProjectProgressReport;
use App\Models\ResearchCall;
use App\Models\TopicProposal;
use App\Models\User;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    foreach (['faculty', 'faculty_researcher', 'research_head'] as $role) {
        Role::firstOrCreate(['name' => $role]);
    }

    $this->head = User::factory()->create();
    $this->head->assignRole('research_head');
    $this->researcher = User::factory()->create(['name' => 'Monitoring Researcher']);
    $this->researcher->assignRole('faculty_researcher');
    $this->call = ResearchCall::create([
        'title' => 'Dashboard Research Call',
        'academic_year' => '2026-2027',
        'opens_at' => now()->subMonth(),
        'closes_at' => now()->addMonth(),
        'status' => 'open',
    ]);
});

function createDashboardTopic(User $researcher, ResearchCall $call, array $overrides = []): TopicProposal
{
    $attributes = array_merge([
        'user_id' => $researcher->id,
        'research_call_id' => $call->id,
        'title' => 'Community Health Research',
        'description' => 'A dashboard filtering test proposal.',
        'estimated_budget' => 50000,
        'estimated_duration_months' => 12,
        'status' => 'pending',
    ], $overrides);

    if ($attributes['status'] === 'approved' && ! array_key_exists('notice_to_proceed_issued_at', $overrides)) {
        $attributes['notice_to_proceed_issued_at'] = now();
    }

    return TopicProposal::create($attributes);
}

test('Research Head pages are protected by role', function () {
    $faculty = User::factory()->create();
    $faculty->assignRole('faculty');

    $this->actingAs($faculty)->get(route('research_head.dashboard'))->assertForbidden();
    $this->actingAs($faculty)->get(route('research_head.projects.index'))->assertForbidden();
});

test('proposal dashboard supports search and status filters', function () {
    createDashboardTopic($this->researcher, $this->call, ['title' => 'Mangrove Restoration', 'status' => 'pending']);
    createDashboardTopic($this->researcher, $this->call, ['title' => 'Solar Irrigation', 'status' => 'rejected']);

    Livewire::actingAs($this->head)->withQueryParams(['search' => 'Mangrove', 'status' => 'pending'])
        ->test(ResearchHeadDashboard::class)
        ->assertSet('status', 'pending')
        ->assertViewHas('topics', fn ($topics) => $topics->total() === 1 && $topics->first()->title === 'Mangrove Restoration');
});

test('proposal dashboard presents a focused research head workspace', function () {
    $this->actingAs($this->head)
        ->get(route('research_head.dashboard'))
        ->assertOk()
        ->assertSee('data-dashboard-palette="maroon-slate-white"', false)
        ->assertSee('Research Operations Dashboard')
        ->assertSee('data-research-operations-status', false)
        ->assertSee('self-end', false)
        ->assertSee('Research operations active')
        ->assertDontSee('Project monitoring')
        ->assertSee('Proposal pipeline')
        ->assertSee('data-dashboard-section-navigation', false)
        ->assertSee('sticky top-[128px] z-20', false)
        ->assertSee('href="#research-calendar"', false)
        ->assertSee('href="#needs-attention"', false)
        ->assertSee('href="#active-projects"', false)
        ->assertSee('href="#received-proposals"', false)
        ->assertSee('scroll-mt-64', false)
        ->assertSee('Inbox controls')
        ->assertSee('Received proposal inbox')
        ->assertSee('table-fixed', false)
        ->assertDontSee('overflow-x-auto', false)
        ->assertDontSee('All research calls')
        ->assertSee('Research calendar');
});

test('dashboard shows completion percentages for every active project', function () {
    $reportedProject = createDashboardTopic($this->researcher, $this->call, [
        'title' => 'Reported Active Project',
        'status' => 'approved',
        'project_status' => 'ongoing',
    ]);
    createDashboardTopic($this->researcher, $this->call, [
        'title' => 'Unreported Active Project',
        'status' => 'approved',
        'project_status' => 'delayed',
    ]);
    createDashboardTopic($this->researcher, $this->call, [
        'title' => 'Completed Project',
        'status' => 'approved',
        'project_status' => 'completed',
    ]);
    ProjectProgressReport::create([
        'topic_id' => $reportedProject->id,
        'submitted_by' => $this->researcher->id,
        'reporting_date' => now(),
        'progress_percentage' => 64,
        'accomplishments' => 'Completed the scheduled field activities.',
        'review_status' => 'reviewed',
    ]);

    Livewire::actingAs($this->head)
        ->test(ResearchHeadDashboard::class)
        ->assertViewHas('activeProjects', fn ($projects): bool => $projects->pluck('title')->all() === [
            'Reported Active Project',
            'Unreported Active Project',
        ])
        ->assertSee('Project completion')
        ->assertSee('Reported Active Project')
        ->assertSee('64%')
        ->assertSeeHtml('aria-valuenow="64"')
        ->assertSee('Unreported Active Project')
        ->assertSee('No monitoring tool submitted')
        ->assertSeeHtml('aria-valuenow="0"');
});

test('proposal dashboard shows received files and opens the submitted package', function () {
    Storage::fake('local');

    $topic = createDashboardTopic($this->researcher, $this->call);
    $version = $topic->versions()->create([
        'submitted_by' => $this->researcher->id,
        'version_number' => 1,
        'submission_type' => 'initial',
        'file_path' => 'packages/proposal.pdf',
        'original_filename' => 'proposal.pdf',
        'mime_type' => 'application/pdf',
        'file_size' => 100,
        'checksum' => str_repeat('a', 64),
        'title' => $topic->title,
        'description' => $topic->description,
        'estimated_budget' => $topic->estimated_budget,
        'estimated_duration_months' => $topic->estimated_duration_months,
    ]);

    foreach ([
        'detailed_proposal',
        'work_plan',
        'line_item_budget',
        'expense_breakdown',
        'curriculum_vitae',
        'gad_checklist',
        'initial_screening_form',
    ] as $position => $documentType) {
        $path = "packages/{$documentType}.pdf";
        Storage::disk('local')->put($path, $documentType);
        $version->files()->create([
            'document_type' => $documentType,
            'position' => $position,
            'file_path' => $path,
            'original_filename' => "{$documentType}.pdf",
            'mime_type' => 'application/pdf',
            'file_size' => strlen($documentType),
            'checksum' => hash('sha256', $documentType),
            'is_carried_forward' => false,
        ]);
    }

    $this->actingAs($this->head)
        ->get(route('research_head.dashboard'))
        ->assertOk()
        ->assertSee('Received proposal inbox')
        ->assertSee('v1 · 7 files')
        ->assertSee('Review proposal')
        ->assertSee(route('topics.show', $topic).'#proposal-review', false);
});

test('proposal dashboard paginates and preserves search', function () {
    foreach (range(1, 16) as $number) {
        createDashboardTopic($this->researcher, $this->call, ['title' => "Filtered Proposal {$number}"]);
    }

    Livewire::actingAs($this->head)->withQueryParams(['search' => 'Filtered'])
        ->test(ResearchHeadDashboard::class)
        ->assertViewHas('topics', fn ($topics) => $topics->total() === 16 && $topics->hasMorePages())
        ->call('setPage', 2)->assertSet('search', 'Filtered')
        ->assertViewHas('topics', fn ($topics) => $topics->currentPage() === 2 && $topics->count() === 1);
});

test('monitoring page shows approved projects only with latest progress and counts', function () {
    $project = createDashboardTopic($this->researcher, $this->call, ['title' => 'Approved Monitoring Project', 'status' => 'approved', 'project_status' => 'ongoing']);
    createDashboardTopic($this->researcher, $this->call, ['title' => 'Unapproved Proposal', 'status' => 'pending']);
    ProjectProgressReport::create([
        'topic_id' => $project->id,
        'submitted_by' => $this->researcher->id,
        'reporting_date' => now()->subDay(),
        'progress_percentage' => 45,
        'accomplishments' => 'Completed field work.',
    ]);

    $this->actingAs($this->head)
        ->get(route('research_head.projects.index'))
        ->assertOk()
        ->assertSee('Research Projects Under Monitoring')
        ->assertDontSee('Projects with a Notice to Proceed')
        ->assertSee('Approved Monitoring Project')
        ->assertSee('45%')
        ->assertSee('1 awaiting review')
        ->assertDontSee('text-sm font-black text-gray-900">Unapproved Proposal', false);
});

test('monitoring page presents active projects at 100 percent as completion pending', function () {
    $project = createDashboardTopic($this->researcher, $this->call, [
        'title' => 'Implementation Finished Project',
        'status' => 'approved',
        'project_status' => 'ongoing',
    ]);
    ProjectProgressReport::create([
        'topic_id' => $project->id,
        'submitted_by' => $this->researcher->id,
        'reporting_date' => now(),
        'progress_percentage' => 100,
        'accomplishments' => 'All implementation activities are complete.',
        'review_status' => 'reviewed',
    ]);

    $this->actingAs($this->head)
        ->get(route('research_head.projects.index', ['status' => 'completion_pending']))
        ->assertOk()
        ->assertViewHas('projects', fn ($projects): bool => $projects->contains('id', $project->id))
        ->assertSee('Completion pending')
        ->assertSee('100%');

    $this->actingAs($this->head)
        ->get(route('research_head.projects.index', ['status' => 'ongoing']))
        ->assertOk()
        ->assertViewHas('projects', fn ($projects): bool => ! $projects->contains('id', $project->id));
});

test('monitoring page filters by project status and attention', function () {
    createDashboardTopic($this->researcher, $this->call, ['title' => 'Delayed Priority Project', 'status' => 'approved', 'project_status' => 'delayed']);
    createDashboardTopic($this->researcher, $this->call, ['title' => 'Completed Stable Project', 'status' => 'approved', 'project_status' => 'completed']);

    $this->actingAs($this->head)
        ->get(route('research_head.projects.index', ['status' => 'delayed', 'attention' => 'needs_attention']))
        ->assertOk()
        ->assertViewHas('projects', fn ($projects): bool => $projects->contains('title', 'Delayed Priority Project')
            && ! $projects->contains('title', 'Completed Stable Project'));
});

test('monitoring page filters projects with reports awaiting review', function () {
    $pending = createDashboardTopic($this->researcher, $this->call, ['title' => 'Pending Report Project', 'status' => 'approved', 'project_status' => 'ongoing']);
    $reviewed = createDashboardTopic($this->researcher, $this->call, ['title' => 'Reviewed Report Project', 'status' => 'approved', 'project_status' => 'ongoing']);
    foreach ([[$pending, 'pending'], [$reviewed, 'reviewed']] as [$project, $reviewStatus]) {
        ProjectProgressReport::create([
            'topic_id' => $project->id,
            'submitted_by' => $this->researcher->id,
            'reporting_date' => now(),
            'progress_percentage' => 50,
            'accomplishments' => 'Progress submitted.',
            'review_status' => $reviewStatus,
        ]);
    }

    $this->actingAs($this->head)
        ->get(route('research_head.projects.index', ['attention' => 'pending_reports']))
        ->assertOk()
        ->assertViewHas('projects', fn ($projects): bool => $projects->contains('id', $pending->id)
            && ! $projects->contains('id', $reviewed->id));
});

test('monitoring search is paginated and preserves all filters', function () {
    foreach (range(1, 16) as $number) {
        createDashboardTopic($this->researcher, $this->call, [
            'title' => "Tracked Coastal Project {$number}",
            'status' => 'approved',
            'project_status' => 'delayed',
        ]);
    }

    $this->actingAs($this->head)
        ->get(route('research_head.projects.index', [
            'search' => 'Tracked Coastal',
            'status' => 'delayed',
            'attention' => 'needs_attention',
        ]))
        ->assertOk()
        ->assertSee('page=2', false)
        ->assertSee('search=Tracked%20Coastal', false)
        ->assertSee('status=delayed', false)
        ->assertSee('attention=needs_attention', false);
});

test('both Research Head pages have useful empty states', function () {
    $this->actingAs($this->head)->get(route('research_head.dashboard'))->assertSee('No proposals found');
    $this->actingAs($this->head)->get(route('research_head.projects.index'))->assertSee('No projects found');
});

test('dashboard labels submission trends as weekly', function () {
    createDashboardTopic($this->researcher, $this->call);

    $this->actingAs($this->head)
        ->get(route('research_head.dashboard'))
        ->assertOk()
        ->assertSee('Submissions by week')
        ->assertDontSee('Average review time')
        ->assertSee('New submissions received each week · last 8 weeks')
        ->assertSee('Week of')
        ->assertSee('in the last 4 weeks, compared with')
        ->assertDontSee('Submissions volume');

});

test('dashboard shows the proposal status overview and budget utilization analytics', function () {
    createDashboardTopic($this->researcher, $this->call, ['status' => 'pending']);
    createDashboardTopic($this->researcher, $this->call, ['status' => 'expert_review']);
    createDashboardTopic($this->researcher, $this->call, ['status' => 'revision_requested']);
    $project = createDashboardTopic($this->researcher, $this->call, [
        'title' => 'Budgeted Approved Project',
        'status' => 'approved',
        'project_status' => 'ongoing',
        'estimated_budget' => 100000,
    ]);
    ProjectProgressReport::create([
        'topic_id' => $project->id,
        'submitted_by' => $this->researcher->id,
        'reporting_date' => now(),
        'progress_percentage' => 40,
        'accomplishments' => 'Implementation is underway.',
        'budget_utilization' => [['type' => 'Purchase Request', 'actual_amount' => 30000]],
    ]);

    $this->actingAs($this->head)
        ->get(route('research_head.dashboard'))
        ->assertOk()
        ->assertSee('Proposal status overview')
        ->assertSee('Current proposals grouped by status.')
        ->assertSee('4 total')
        ->assertSee('2 proposals')
        ->assertSee('Faculty revision')
        ->assertSee('Budget utilization')
        ->assertSee('₱30,000.00')
        ->assertSee('₱100,000.00')
        ->assertSee('Budgeted Approved Project')
        ->assertSee('30.0%')
        ->assertSee('Latest report');
});
test('the proposal pipeline shows the four actionable counts', function () {
    $this->actingAs($this->head)
        ->get(route('research_head.dashboard'))
        ->assertOk()
        ->assertSee('Awaiting your review')
        ->assertSee('Awaiting faculty revision')
        ->assertSee('Deadlines in 14 days')
        ->assertSee('Active projects')
        ->assertDontSee('In-progress drafts')
        ->assertDontSee('Live workload');
});

test('the proposal pipeline can be filtered by stage without a page refresh', function () {
    createDashboardTopic($this->researcher, $this->call, ['title' => 'Mangrove Restoration', 'status' => 'pending']);
    createDashboardTopic($this->researcher, $this->call, ['title' => 'Solar Irrigation', 'status' => 'rejected']);

    Livewire::actingAs($this->head)
        ->test(ResearchHeadDashboard::class)
        ->assertSet('pipeline', '')
        ->call('setPipeline', 'awaiting_review')
        ->assertSet('pipeline', 'awaiting_review')
        ->assertSee('Mangrove Restoration')
        ->assertDontSee('Solar Irrigation')
        ->call('clearPipeline')
        ->assertSet('pipeline', '');
});

test('the dashboard search box filters proposals via Livewire', function () {
    createDashboardTopic($this->researcher, $this->call, ['title' => 'Mangrove Restoration', 'status' => 'pending']);
    createDashboardTopic($this->researcher, $this->call, ['title' => 'Solar Irrigation', 'status' => 'pending']);

    Livewire::actingAs($this->head)
        ->test(ResearchHeadDashboard::class)
        ->set('search', 'Mangrove')
        ->assertViewHas('topics', fn ($topics) => $topics->total() === 1 && $topics->first()->title === 'Mangrove Restoration');
});

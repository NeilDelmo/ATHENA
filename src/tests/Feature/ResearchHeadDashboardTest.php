<?php

use App\Models\ProjectProgressReport;
use App\Models\ResearchCall;
use App\Models\TopicProposal;
use App\Models\User;
use Illuminate\Support\Facades\Storage;
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
    return TopicProposal::create(array_merge([
        'user_id' => $researcher->id,
        'research_call_id' => $call->id,
        'title' => 'Community Health Research',
        'description' => 'A dashboard filtering test proposal.',
        'estimated_budget' => 50000,
        'estimated_duration_months' => 12,
        'status' => 'pending',
    ], $overrides));
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

    $this->actingAs($this->head)
        ->get(route('research_head.dashboard', ['search' => 'Mangrove', 'status' => 'pending']))
        ->assertOk()
        ->assertSee('Mangrove Restoration')
        ->assertDontSee('Solar Irrigation');
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
        ->assertSee('7 PDFs received')
        ->assertSee('Open submitted package')
        ->assertSee(route('topics.show', $topic).'#submitted-files', false);
});

test('proposal dashboard paginates and preserves filters', function () {
    foreach (range(1, 16) as $number) {
        createDashboardTopic($this->researcher, $this->call, ['title' => "Filtered Proposal {$number}"]);
    }

    $this->actingAs($this->head)
        ->get(route('research_head.dashboard', ['search' => 'Filtered', 'status' => 'pending']))
        ->assertOk()
        ->assertSee('page=2', false)
        ->assertSee('search=Filtered', false)
        ->assertSee('status=pending', false);
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
        ->assertSee('Approved Monitoring Project')
        ->assertSee('45%')
        ->assertSee('1 awaiting review')
        ->assertDontSee('Unapproved Proposal');
});

test('monitoring page filters by project status and attention', function () {
    createDashboardTopic($this->researcher, $this->call, ['title' => 'Delayed Priority Project', 'status' => 'approved', 'project_status' => 'delayed']);
    createDashboardTopic($this->researcher, $this->call, ['title' => 'Completed Stable Project', 'status' => 'approved', 'project_status' => 'completed']);

    $this->actingAs($this->head)
        ->get(route('research_head.projects.index', ['status' => 'delayed', 'attention' => 'needs_attention']))
        ->assertOk()
        ->assertSee('Delayed Priority Project')
        ->assertDontSee('Completed Stable Project');
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
        ->assertSee('Pending Report Project')
        ->assertDontSee('Reviewed Report Project');
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

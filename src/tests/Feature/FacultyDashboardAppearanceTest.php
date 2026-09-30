<?php

use App\Models\ProjectProgressReport;
use App\Models\ProposalDraft;
use App\Models\ResearchCall;
use App\Models\TopicProposal;
use App\Models\User;
use Spatie\Permission\Models\Role;

test('faculty dashboards show workspace-specific data in the shared restrained layout', function (string $workspace) {
    $this->withoutVite();

    foreach (['faculty', 'faculty_researcher', 'research_head'] as $role) {
        Role::firstOrCreate(['name' => $role]);
    }

    $head = User::factory()->create();
    $head->assignRole('research_head');
    $faculty = User::factory()->create(['name' => 'Alexandra Reyes']);
    $faculty->assignRole(['faculty', 'faculty_researcher']);
    $otherFaculty = User::factory()->create();
    $otherFaculty->assignRole('faculty');
    $call = ResearchCall::create([
        'title' => 'Institutional Research 2026',
        'academic_year' => '2026-2027',
        'opens_at' => now()->subWeek(),
        'closes_at' => now()->addWeeks(2),
        'status' => 'open',
        'created_by' => $head->id,
    ]);

    foreach (['Community-based disaster preparedness', 'Digital learning in local schools'] as $title) {
        ProposalDraft::create([
            'user_id' => $faculty->id,
            'research_call_id' => $call->id,
            'project_title' => $title,
        ]);
    }

    $revision = TopicProposal::create([
        'user_id' => $faculty->id,
        'research_call_id' => $call->id,
        'title' => 'Sustainable coastal livelihoods',
        'status' => 'revision_requested',
    ]);
    $revision->reviews()->create([
        'reviewer_id' => $head->id,
        'decision' => 'revision_requested',
        'comment' => 'Please clarify the sampling plan and update the budget.',
    ]);
    $active = TopicProposal::create([
        'user_id' => $faculty->id,
        'research_call_id' => $call->id,
        'title' => 'Smart campus energy monitoring',
        'status' => 'approved',
        'project_status' => TopicProposal::PROJECT_STATUS_ONGOING,
        'notice_to_proceed_issued_by' => $head->id,
        'notice_to_proceed_issued_at' => now(),
    ]);
    ProjectProgressReport::create([
        'topic_id' => $active->id,
        'submitted_by' => $faculty->id,
        'reporting_date' => now(),
        'progress_percentage' => 64,
        'accomplishments' => 'Field data collection completed.',
    ]);
    TopicProposal::create([
        'user_id' => $faculty->id,
        'research_call_id' => $call->id,
        'title' => 'Coastal water quality assessment',
        'status' => 'approved',
        'project_status' => TopicProposal::PROJECT_STATUS_DELAYED,
        'notice_to_proceed_issued_by' => $head->id,
        'notice_to_proceed_issued_at' => now(),
    ]);
    TopicProposal::create([
        'user_id' => $faculty->id,
        'research_call_id' => $call->id,
        'title' => 'Community health research',
        'status' => 'approved',
    ]);
    TopicProposal::create([
        'user_id' => $otherFaculty->id,
        'research_call_id' => $call->id,
        'title' => 'Private project outside this portfolio',
        'status' => 'approved',
    ]);

    $response = $this->withSession([User::ACTIVE_WORKSPACE_SESSION_KEY => $workspace])
        ->actingAs($faculty)
        ->get(route('faculty.dashboard'));

    $response->assertOk()
        ->assertSee('data-faculty-shell', false)
        ->assertSee('data-dashboard-columns', false)
        ->assertDontSee('data-calendar-compact', false)
        ->assertSee('data-sidebar-account', false)
        ->assertSee('data-dashboard-palette="red-black-white"', false)
        ->assertSee('Research tools')
        ->assertSee('Account Profile')
        ->assertDontSee('Private project outside this portfolio')
        ->assertSee('athena-header-hexagons', false)
        ->assertSee('images/front.jpg', false)
        ->assertSee('id="manila-system-time"', false)
        ->assertSee('data-timezone="Asia/Manila"', false)
        ->assertSee('Philippine Time:')
        ->assertSee('h-[120px] items-end', false)
        ->assertSee('border-l-4 border-[#800000]', false)
        ->assertSee('aria-label="Open account menu"', false)
        ->assertSee('aria-label="Toggle light and dark theme"', false)
        ->assertDontSee('athena-sidebar-hexagons', false);

    if ($workspace === User::WORKSPACE_FACULTY) {
        $response->assertSee('data-dashboard-layout="faculty-overview"', false)
            ->assertSee(route('faculty.submissions'), false)
            ->assertDontSee('submitted-proposals-heading', false)
            ->assertSee('Community-based disaster preparedness')
            ->assertSee(route('faculty.topics.revision', $revision), false)
            ->assertDontSee('Project distribution');
    } else {
        $response->assertSee('data-dashboard-layout="research-overview"', false)
            ->assertSee('Project distribution')
            ->assertViewHas('activeProjects', fn ($projects): bool => $projects->count() === 2)
            ->assertViewHas('averageProgress', 32)
            ->assertSee('32%')
            ->assertSee('Coastal water quality assessment')
            ->assertSee(route('research.index'), false)
            ->assertDontSee('Sustainable coastal livelihoods')
            ->assertDontSee('New proposal');

        $dashboardContent = str($response->getContent())
            ->after('data-dashboard-layout="research-overview"')
            ->before('</main>');

        expect((string) $dashboardContent)
            ->not->toContain('bg-amber-50', 'bg-sky-50', 'bg-emerald-50');
    }

})->with([User::WORKSPACE_FACULTY, User::WORKSPACE_FACULTY_RESEARCHER]);

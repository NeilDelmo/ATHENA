<?php

use App\Models\ProjectProgressReport;
use App\Models\ProposalDraft;
use App\Models\ResearchCall;
use App\Models\TopicProposal;
use App\Models\User;
use App\Support\ProposalDraftReadiness;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\ViewErrorBag;
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

test('dashboard announcements use compact previews and retain proposal and full poster actions', function () {
    $items = collect([
        [
            'url' => '/test-call-poster.png',
            'alt' => 'Institutional research proposals',
            'isResearchCall' => true,
            'canSubmitProposal' => true,
        ],
        [
            'url' => '/test-announcement.png',
            'alt' => 'Research Office workshop',
            'isResearchCall' => false,
            'canSubmitProposal' => false,
        ],
    ]);

    $html = view('faculty.partials.research-call-carousel', [
        'researchCallCarouselItems' => $items,
    ])->render();

    $document = new DOMDocument;
    @$document->loadHTML($html);
    $xpath = new DOMXPath($document);
    $slides = $xpath->query('//*[@data-research-call-slide]');
    $poster = $xpath->query('//*[@data-research-call-poster-trigger]')->item(0);

    expect($slides)->toHaveCount(2)
        ->and($slides->item(0)->getAttribute('data-announcement-layout'))->toBe('compact')
        ->and($slides->item(1)->hasAttribute('hidden'))->toBeTrue()
        ->and($slides->item(1)->hasAttribute('inert'))->toBeTrue()
        ->and($poster->getAttribute('class'))->toContain('h-40', 'sm:h-44', 'object-contain')
        ->and($xpath->query('//a')->length)->toBe(1)
        ->and($xpath->query('//a')->item(0)->getAttribute('href'))->toBe(route('faculty.proposal-drafts.create'))
        ->and($xpath->query('//*[@data-research-call-preview]')->length)->toBe(4)
        ->and($xpath->query('//*[@data-research-call-lightbox]')->length)->toBe(1)
        ->and($html)->toContain('View full poster', 'Show next announcement')
        ->not->toContain('max-h-[38rem]', 'text-2xl');
});

test('announcement section is omitted when there are no posters', function () {
    expect(trim(view('faculty.partials.research-call-carousel', [
        'researchCallCarouselItems' => collect(),
    ])->render()))->toBe('');
});

test('draft list keeps resume aligned for owners and collaborators with clear paper readiness', function () {
    $this->withoutVite();
    Role::firstOrCreate(['name' => 'faculty']);
    $faculty = User::factory()->create();
    $faculty->assignRole('faculty');
    $owner = User::factory()->make(['id' => 98765]);
    $this->actingAs($faculty);

    $drafts = collect([
        new ProposalDraft(['user_id' => $faculty->id, 'project_title' => 'Owned draft', 'status' => 'draft']),
        new ProposalDraft(['user_id' => $owner->id, 'project_title' => 'Shared draft', 'status' => 'draft']),
    ]);
    foreach ($drafts as $index => $draft) {
        $draft->id = 900 + $index;
        $draft->updated_at = now();
        $draft->setRelation('owner', $index === 0 ? $faculty : $owner);
        $draft->setRelation('researchCall', null);
    }
    $this->mock(ProposalDraftReadiness::class)
        ->shouldReceive('checklist')->twice()->andReturn(
            collect(array_fill(0, 7, ['complete' => true, 'needs_attention' => false])),
            collect(array_fill(0, 7, ['complete' => false, 'needs_attention' => false])),
        );

    view()->share('errors', new ViewErrorBag);
    $html = view('faculty.proposal-drafts.index', [
        'proposalDrafts' => new LengthAwarePaginator($drafts, 2, 12),
    ])->render();
    $document = new DOMDocument;
    @$document->loadHTML($html);
    $xpath = new DOMXPath($document);
    $actions = $xpath->query('//*[@data-draft-actions]');

    expect($actions)->toHaveCount(2)
        ->and($actions->item(0)->getAttribute('class'))->toContain('grid-cols-2')
        ->and($actions->item(1)->getAttribute('class'))->toContain('grid-cols-2')
        ->and($xpath->query('.//a', $actions->item(0))->item(0)->getAttribute('class'))->toContain('col-start-1')
        ->and($xpath->query('.//a', $actions->item(1))->item(0)->getAttribute('class'))->toContain('col-start-1')
        ->and($xpath->query('.//form', $actions->item(0))->length)->toBe(1)
        ->and($xpath->query('.//form', $actions->item(1))->length)->toBe(0)
        ->and($html)->toContain('7 of 7 papers ready', '0 of 7 papers ready', 'data-proposal-confirm')
        ->not->toContain('Package progress', '<progress');
});

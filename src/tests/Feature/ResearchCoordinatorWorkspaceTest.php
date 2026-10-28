<?php

use App\Models\TopicProposal;
use App\Models\User;
use Illuminate\Support\Facades\File;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    $this->withoutVite();
    $this->travelTo(now()->setDate(2026, 10, 27)->setTime(9, 0));
    foreach (['research_coordinator', 'faculty'] as $role) {
        Role::firstOrCreate(['name' => $role]);
    }
    $this->coordinator = User::factory()->create(['name' => 'Andrea Reyes', 'college' => 'CICS']);
    $this->coordinator->assignRole('research_coordinator');
    $this->owner = User::factory()->create(['name' => 'Gabriel Santos', 'college' => 'CICS']);
    foreach ([
        ['Community health information systems', TopicProposal::STATUS_LREC_REVIEW],
        ['Coastal resource monitoring', TopicProposal::STATUS_LREC_QUEUED],
        ['Community resilience mapping', 'revision_requested'],
    ] as $index => [$title, $status]) {
        $proposal = TopicProposal::create(['user_id' => $this->owner->id, 'title' => $title, 'status' => $status, 'review_stage' => 'lrec']);
        $proposal->forceFill(['status_started_at' => now()->subDays(5 - $index)])->saveQuietly();
    }
    $release = TopicProposal::create(['user_id' => $this->owner->id, 'title' => 'Sustainable campus water systems', 'status' => TopicProposal::STATUS_READY_FOR_SIGNATURE]);
    $release->forceFill(['status_started_at' => now()->subDays(2)])->saveQuietly();
    $this->actingAs($this->coordinator)->withSession([User::ACTIVE_WORKSPACE_SESSION_KEY => User::WORKSPACE_RESEARCH_OFFICE]);
});

test('coordinator dashboard and focused work pages share the office design and navigation', function () {
    foreach (['dashboard', 'proposals.index', 'targets.index', 'members.index'] as $page) {
        $response = $this->get(route('research_coordinator.'.$page))->assertOk()
            ->assertSee('data-coordinator-workspace', false)->assertSee('data-research-office-navigation', false)
            ->assertDontSee(route('research_coordinator.notices.index'), false)
            ->assertDontSee('Prepare Notice to Proceed')->assertDontSee('Document releases')
            ->assertDontSee('secretary-page-index')->assertDontSee('secretary-page-intro');
        foreach (['dashboard', 'proposals.index', 'targets.index', 'members.index', 'reports.index'] as $destination) {
            $response->assertSee(route('research_coordinator.'.$destination), false);
        }
        if (getenv('ATHENA_EXPORT_COORDINATOR_WORKSPACE') === '1') {
            File::ensureDirectoryExists(storage_path('framework/testing'));
            File::put(storage_path('framework/testing/coordinator-'.str_replace('.index', '', $page).'.html'), $response->getContent());
        }
    }
    $this->get(route('research_coordinator.dashboard'))->assertOk()->assertSeeText('Coordinator dashboard')
        ->assertViewHas('lrecReviewCount', 1)->assertViewHas('memberCount', 1)
        ->assertDontSee('data-research-target-board', false)->assertDontSee('data-notice-to-proceed-queue', false);
    $this->get(route('research_coordinator.proposals.index'))->assertOk()->assertSeeText('LREC proposals')
        ->assertSeeText('Record comments')->assertSeeText('View review status')->assertDontSee('data-notice-to-proceed-queue', false);
    $this->get(route('research_coordinator.notices.index'))->assertRedirect(route('research_coordinator.dashboard'));
});

test('coordinator searches and review filters retain college access and pagination state', function () {
    $outsider = User::factory()->create(['name' => 'Private outside faculty', 'college' => 'CTE']);
    TopicProposal::create(['user_id' => $outsider->id, 'title' => 'Private outside proposal', 'status' => TopicProposal::STATUS_LREC_REVIEW, 'review_stage' => 'lrec']);
    $this->get(route('research_coordinator.proposals.index', ['status' => 'review', 'q' => 'Community']))->assertOk()
        ->assertViewHas('lrecProposals', fn ($proposals): bool => $proposals->total() === 1)
        ->assertSeeText('Community health information systems')->assertDontSeeText('Private outside proposal')
        ->assertDontSeeText('Community resilience mapping');
    $this->get(route('research_coordinator.members.index', ['q' => 'Gabriel']))->assertOk()->assertSeeText('Gabriel Santos')->assertDontSeeText($outsider->name);
    $this->get(route('research_coordinator.members.index', ['q' => 'No matching faculty']))->assertOk()->assertSeeText('No members match your search.');
    $this->getJson(route('research_coordinator.proposals.index', ['status' => 'invalid']))->assertUnprocessable();
    $this->getJson(route('research_coordinator.members.index', ['q' => ['invalid']]))->assertUnprocessable();
});

test('coordinator priority counts cover every page while dashboard lists remain short', function () {
    foreach (range(1, 14) as $index) {
        TopicProposal::create(['user_id' => $this->owner->id, 'title' => 'Additional college review '.$index,
            'status' => TopicProposal::STATUS_LREC_REVIEW, 'review_stage' => 'lrec']);
    }
    $response = $this->get(route('research_coordinator.dashboard'))->assertOk()->assertViewHas('lrecReviewCount', 15);
    $document = new DOMDocument;
    @$document->loadHTML($response->getContent());
    expect((new DOMXPath($document))->query('//*[@data-coordinator-lrec-queue]//article')->length)->toBe(4);
    $proposals = $this->get(route('research_coordinator.proposals.index', ['status' => 'review', 'q' => 'Additional']))
        ->assertOk()->viewData('lrecProposals');
    expect($proposals->total())->toBe(14)
        ->and($proposals->nextPageUrl())->toContain('q=Additional', 'status=review', 'page=2');
});

test('coordinator pages stay private and missing colleges show empty queues', function () {
    $this->coordinator->update(['college' => null]);
    $this->get(route('research_coordinator.proposals.index'))->assertOk()->assertDontSeeText('Community health information systems')
        ->assertSeeText('Your account needs an assigned college');
    $faculty = User::factory()->create();
    $faculty->assignRole('faculty');
    $this->actingAs($faculty)->withSession([User::ACTIVE_WORKSPACE_SESSION_KEY => User::WORKSPACE_FACULTY]);
    foreach (['dashboard', 'proposals.index', 'notices.index', 'targets.index', 'members.index'] as $page) {
        $this->getJson(route('research_coordinator.'.$page))->assertForbidden();
    }
});

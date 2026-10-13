<?php

use App\Livewire\ResearchHeadDashboard;
use App\Models\ProposalDraft;
use App\Models\TopicProposal;
use App\Models\User;
use App\Support\DashboardAssistantSuggestions;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    $this->withoutVite();
    Http::preventStrayRequests();

    foreach (['faculty', 'faculty_researcher', 'research_head'] as $role) {
        Role::firstOrCreate(['name' => $role]);
    }
});

/** @return list<array<string, mixed>> */
function dashboardReviewSuggestions(string $html): array
{
    $document = new DOMDocument;
    @$document->loadHTML($html);
    $xpath = new DOMXPath($document);
    $items = [];

    foreach ($xpath->query('//button[@data-review-suggestion]') as $button) {
        $items[] = json_decode($button->getAttribute('data-review-suggestion'), true, flags: JSON_THROW_ON_ERROR);
    }

    return $items;
}

test('faculty AI checks appear alongside accessible revisions and recent drafts without a duplicate list', function () {
    $faculty = User::factory()->create();
    $faculty->assignRole('faculty');
    $outside = User::factory()->create();
    $revision = TopicProposal::create(['user_id' => $faculty->id, 'title' => 'Coastal revision', 'status' => 'revision_requested']);
    $pending = TopicProposal::create(['user_id' => $faculty->id, 'title' => 'Pending watershed study', 'status' => 'pending']);
    $draft = ProposalDraft::create(['user_id' => $faculty->id, 'project_title' => 'Draft "sampling" plan']);
    ProposalDraft::create(['user_id' => $outside->id, 'project_title' => 'Private other draft']);
    TopicProposal::create(['user_id' => $outside->id, 'title' => 'Private other revision', 'status' => 'revision_requested']);
    TopicProposal::create(['user_id' => $faculty->id, 'title' => 'Closed project', 'status' => 'rejected']);

    $response = $this->actingAs($faculty)
        ->withSession([User::ACTIVE_WORKSPACE_SESSION_KEY => User::WORKSPACE_FACULTY])
        ->get(route('faculty.dashboard'))
        ->assertOk()
        ->assertDontSee('Review with Athena')
        ->assertDontSee('data-dashboard-assistant-suggestions', false)
        ->assertSee('Get revision help')
        ->assertSee('Improve this draft')
        ->assertDontSee('AI revision check')
        ->assertDontSee('The Research Office requested changes to this proposal.')
        ->assertSee('Open the revision page to see the requested changes.')
        ->assertSee('data-proposal-help', false)
        ->assertSee('$store.researchAssistant.openProposalHelp', false)
        ->assertSee('aria-controls="athena-assistant-workspace"', false)
        ->assertSee(':disabled="$store.researchAssistant.reviewLaunchBusy()"', false);

    $items = dashboardReviewSuggestions($response->getContent());
    expect($items)->toHaveCount(2)
        ->and(array_column($items, 'target_label'))->toBe([$revision->title, $draft->project_title])
        ->and($items[0]['context'])->toBe(['topic_id' => $revision->id, 'workflow_scope' => 'review'])
        ->and($items[0]['action'])->toBe(['type' => 'review_proposal', 'review_focus' => 'revision_response'])
        ->and($items[0]['evidence'])->toBe('Revision requested.')
        ->and($items[1]['context'])->toBe(['proposal_draft_id' => $draft->id, 'workflow_scope' => 'review'])
        ->and($items[1]['action'])->toBe(['type' => 'review_proposal', 'review_focus' => 'consistency'])
        ->and($items[1]['evidence'])->toContain('papers ready');

    $document = new DOMDocument;
    @$document->loadHTML($response->getContent());
    $xpath = new DOMXPath($document);
    expect($xpath->query('//*[@data-revision-task]//button[@data-review-suggestion]'))->toHaveCount(1)
        ->and($xpath->query('//*[@data-dashboard-draft]//button[@data-review-suggestion]'))->toHaveCount(1);

    Http::assertNothingSent();
});

test('faculty review checks cover all revisions and complete recent drafts without a three item cap', function () {
    $faculty = User::factory()->create();
    $faculty->assignRole('faculty');
    $revisions = collect(range(1, 4))->map(fn (int $number): TopicProposal => TopicProposal::create([
        'user_id' => $faculty->id,
        'title' => 'Revision '.$number,
        'status' => 'revision_requested',
    ]));
    $drafts = collect(range(1, 2))->map(fn (int $number): ProposalDraft => ProposalDraft::create([
        'user_id' => $faculty->id,
        'project_title' => 'Complete draft '.$number,
    ]));
    $progress = $drafts->mapWithKeys(fn (ProposalDraft $draft): array => [
        $draft->id => ['completed' => 7, 'total' => 7, 'percentage' => 100],
    ]);

    $items = app(DashboardAssistantSuggestions::class)->forFaculty($revisions, $drafts, $progress);

    expect($items)->toHaveCount(6)
        ->and($items->pluck('context.topic_id')->filter()->values()->all())->toBe($revisions->pluck('id')->all())
        ->and($items->pluck('context.proposal_draft_id')->filter()->values()->all())->toBe($drafts->pluck('id')->all());
});

test('research head dashboard caps focused review suggestions and follows the visible review filter', function () {
    $head = User::factory()->create();
    $head->assignRole('research_head');
    $faculty = User::factory()->create();
    $revision = TopicProposal::create(['user_id' => $faculty->id, 'title' => 'Resubmitted coastal study', 'status' => 'resubmitted']);
    foreach (range(1, 4) as $number) {
        TopicProposal::create(['user_id' => $faculty->id, 'title' => 'Pending study '.$number, 'status' => 'pending']);
    }
    TopicProposal::create(['user_id' => $faculty->id, 'title' => 'Closed other study', 'status' => 'rejected']);

    $component = Livewire::actingAs($head)->test(ResearchHeadDashboard::class, ['overview' => true])
        ->assertSee('Review with Athena');
    $items = dashboardReviewSuggestions($component->html());

    expect($items)->toHaveCount(3)
        ->and($items[0]['target_label'])->toBe($revision->title)
        ->and($items[0]['context'])->toBe(['topic_id' => $revision->id, 'workflow_scope' => 'review'])
        ->and($items[0]['action'])->toBe(['type' => 'review_proposal', 'review_focus' => 'revision_response'])
        ->and($items[0]['evidence'])->toBe('Resubmitted proposal awaiting review.');

    $component->set('pipeline', 'awaiting_review')->set('search', 'Pending study 4');
    $filtered = dashboardReviewSuggestions($component->html());
    expect($filtered)->toHaveCount(1)
        ->and($filtered[0]['target_label'])->toBe('Pending study 4')
        ->and($filtered[0]['action']['review_focus'])->toBe('consistency');

    Http::assertNothingSent();
});

test('faculty monitoring workspace does not expose preparation review suggestions', function () {
    $faculty = User::factory()->create();
    $faculty->assignRole(['faculty', 'faculty_researcher']);
    TopicProposal::create(['user_id' => $faculty->id, 'title' => 'Private preparation revision', 'status' => 'revision_requested']);
    ProposalDraft::create(['user_id' => $faculty->id, 'project_title' => 'Private preparation draft']);

    $response = $this->actingAs($faculty)
        ->withSession([User::ACTIVE_WORKSPACE_SESSION_KEY => User::WORKSPACE_FACULTY_RESEARCHER])
        ->get(route('faculty.dashboard'))->assertOk();

    expect(dashboardReviewSuggestions($response->getContent()))->toBeEmpty();
    Http::assertNothingSent();
});

test('empty faculty dashboard offers no invented review targets', function () {
    $faculty = User::factory()->create();
    $faculty->assignRole('faculty');

    $response = $this->actingAs($faculty)
        ->withSession([User::ACTIVE_WORKSPACE_SESSION_KEY => User::WORKSPACE_FACULTY])
        ->get(route('faculty.dashboard'))->assertOk()
        ->assertDontSee('Review with Athena')
        ->assertSee('No proposal drafts yet');

    expect(dashboardReviewSuggestions($response->getContent()))->toBeEmpty();
    Http::assertNothingSent();
});

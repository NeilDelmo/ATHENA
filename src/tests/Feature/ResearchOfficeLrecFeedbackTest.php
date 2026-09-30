<?php

use App\Contracts\DocumentPdfConverter;
use App\Models\ResearchCall;
use App\Models\ResearchCategory;
use App\Models\TopicProposal;
use App\Models\User;
use App\Notifications\ProposalActivityNotification;
use App\Services\CommentResponseFeedback;
use Illuminate\Support\Facades\Notification;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    $this->withoutVite();

    foreach (['faculty', 'research_coordinator', 'research_head'] as $role) {
        Role::firstOrCreate(['name' => $role]);
    }

    $this->office = User::factory()->create(['college' => User::COLLEGES['CICS']]);
    $this->office->assignRole(['faculty', 'research_coordinator']);
    $this->faculty = User::factory()->create(['college' => User::COLLEGES['CICS']]);
    $this->faculty->assignRole('faculty');

    $category = ResearchCategory::create(['name' => 'Research Office LREC']);
    $call = ResearchCall::create([
        'title' => 'Research Office LREC Call',
        'academic_year' => '2026-2027',
        'opens_at' => now()->subDay(),
        'closes_at' => now()->addMonth(),
        'max_active_research_per_faculty' => 2,
        'status' => 'open',
    ]);
    $call->categories()->attach($category);

    $this->topic = TopicProposal::create([
        'user_id' => $this->faculty->id,
        'research_call_id' => $call->id,
        'research_category_id' => $category->id,
        'title' => 'LREC office proposal',
        'estimated_budget' => 10000,
        'estimated_duration_months' => 12,
        'initial_file_path' => 'proposals/lrec-office.pdf',
        'status' => TopicProposal::STATUS_LREC_REVIEW,
        'review_stage' => 'lrec',
    ]);
    $this->topic->versions()->create([
        'submitted_by' => $this->faculty->id,
        'version_number' => 1,
        'submission_type' => 'initial',
        'file_path' => 'proposals/lrec-office.pdf',
        'original_filename' => 'lrec-office.pdf',
        'mime_type' => 'application/pdf',
        'file_size' => 18,
        'checksum' => hash('sha256', 'lrec office'),
        'title' => $this->topic->title,
        'estimated_budget' => 10000,
        'estimated_duration_months' => 12,
    ]);
});

test('Research Office records LREC comments into a faculty revision and Comment Response paper', function (?string $panelist) {
    Notification::fake();

    $this->actingAs($this->office)
        ->withSession(['active_role' => 'research_coordinator', 'active_workspace' => User::WORKSPACE_RESEARCH_OFFICE])
        ->get(route('research_coordinator.dashboard'))
        ->assertOk()
        ->assertSee('LREC office proposal')
        ->assertSee('Record comments');

    $this->actingAs($this->office)
        ->withSession(['active_role' => 'research_coordinator', 'active_workspace' => User::WORKSPACE_RESEARCH_OFFICE])
        ->get(route('topics.show', $this->topic))
        ->assertOk()
        ->assertSee('data-lrec-office-feedback', false)
        ->assertDontSee('LREC reviewer / panelist name')->assertSee('Send LREC comments to faculty');

    $this->actingAs($this->office)
        ->withSession(['active_role' => 'research_coordinator', 'active_workspace' => User::WORKSPACE_RESEARCH_OFFICE])
        ->post(route('research_coordinator.topics.lrec-feedback.store', $this->topic), [
            'committee_comments' => [[
                'reviewer' => $panelist,
                'location' => 'Detailed Proposal, Methodology',
                'comment' => 'Clarify the sampling procedure.',
            ]],
        ])
        ->assertRedirect(route('topics.show', $this->topic).'#proposal-review');

    expect($this->topic->fresh()->status)->toBe('revision_requested');
    $review = $this->topic->reviews()->latest('id')->firstOrFail();
    expect(app(CommentResponseFeedback::class)->rows($review)[0]['reviewer'])->toBe('');
    expect($review->review_stage)->toBe('lrec');
    expect($review->reviewer_id)->toBe($this->office->id);
    expect($review->committee_comments[0]['comment'])->toBe('Clarify the sampling procedure.');
    expect(app(CommentResponseFeedback::class)->rows($review)[0]['stage'])->toBe('lrec');
    expect(app(CommentResponseFeedback::class)->rows($review)[0]['comment'])->toBe('Clarify the sampling procedure.');
    Notification::assertSentTo($this->faculty, ProposalActivityNotification::class);

    $this->actingAs($this->office)
        ->withSession(['active_role' => 'research_coordinator', 'active_workspace' => User::WORKSPACE_RESEARCH_OFFICE])
        ->get(route('topics.show', $this->topic))
        ->assertOk()
        ->assertSee('LREC Comment Response Paper');

    $this->actingAs($this->faculty)
        ->withSession(['active_workspace' => User::WORKSPACE_FACULTY])
        ->get(route('faculty.topics.revision', $this->topic))
        ->assertOk()
        ->assertSee('Clarify the sampling procedure.');
})->with(['named panelist' => ['Dr. External Panelist'], 'unnamed committee' => [null]]);

test('Research Office can preview its saved LREC Comment Response paper', function () {
    $review = $this->topic->reviews()->create([
        'reviewer_id' => $this->office->id,
        'decision' => 'revision_requested',
        'review_stage' => 'lrec',
        'committee_comments' => [['reviewer' => 'LREC committee', 'comment' => 'Clarify the method.']],
    ]);
    $this->topic->update(['status' => 'revision_requested']);

    app()->instance(DocumentPdfConverter::class, new class implements DocumentPdfConverter
    {
        public function convertDocx(string $contents): string
        {
            return "%PDF-1.7\nLREC comment response";
        }

        public function convertXlsx(string $contents): string
        {
            throw new LogicException('An XLSX conversion was not expected.');
        }
    });

    $this->actingAs($this->office)
        ->withSession(['active_role' => 'research_coordinator', 'active_workspace' => User::WORKSPACE_RESEARCH_OFFICE])
        ->get(route('research_coordinator.topics.comment-response-form.pdf', ['topic' => $this->topic, 'review' => $review->id]))
        ->assertOk()
        ->assertHeader('content-type', 'application/pdf')
        ->assertContent("%PDF-1.7\nLREC comment response");
});

test('Research Office can open LREC review after the presentation but only once', function () {
    Notification::fake();
    $this->topic->update(['status' => TopicProposal::STATUS_LREC_QUEUED]);
    $url = route('research_coordinator.topics.lrec-review.start', $this->topic);

    $this->actingAs($this->office)
        ->withSession(['active_role' => 'research_coordinator', 'active_workspace' => User::WORKSPACE_RESEARCH_OFFICE])
        ->get(route('topics.show', $this->topic))
        ->assertOk()
        ->assertSee('Presentation complete — record comments');

    $this->actingAs($this->office)
        ->withSession(['active_role' => 'research_coordinator', 'active_workspace' => User::WORKSPACE_RESEARCH_OFFICE])
        ->post($url)
        ->assertRedirect(route('topics.show', $this->topic).'#proposal-review');

    expect($this->topic->fresh()->status)->toBe(TopicProposal::STATUS_LREC_REVIEW);
    expect($this->topic->reviews()->where('decision', TopicProposal::STATUS_LREC_REVIEW)->count())->toBe(1);
    Notification::assertSentTo($this->faculty, ProposalActivityNotification::class);

    $this->actingAs($this->office)
        ->withSession(['active_role' => 'research_coordinator', 'active_workspace' => User::WORKSPACE_RESEARCH_OFFICE])
        ->post($url)
        ->assertForbidden();

    expect($this->topic->reviews()->where('decision', TopicProposal::STATUS_LREC_REVIEW)->count())->toBe(1);
});

test('Research Office LREC access is limited to its college, stage, and selected workspace', function () {
    $url = route('research_coordinator.topics.lrec-feedback.store', $this->topic);
    $payload = ['committee_comments' => [['reviewer' => 'LREC', 'comment' => 'Revise this section.']]];

    $this->actingAs($this->office)
        ->withSession(['active_role' => 'faculty', 'active_workspace' => User::WORKSPACE_FACULTY])
        ->post($url, $payload)
        ->assertForbidden();

    $this->office->update(['college' => User::COLLEGES['CTE']]);
    $this->actingAs($this->office)
        ->withSession(['active_role' => 'research_coordinator', 'active_workspace' => User::WORKSPACE_RESEARCH_OFFICE])
        ->get(route('topics.show', $this->topic))
        ->assertForbidden();
    $this->actingAs($this->office)
        ->withSession(['active_role' => 'research_coordinator', 'active_workspace' => User::WORKSPACE_RESEARCH_OFFICE])
        ->post($url, $payload)
        ->assertForbidden();

    $this->office->update(['college' => User::COLLEGES['CICS']]);
    $this->topic->update(['status' => TopicProposal::STATUS_LREC_QUEUED]);
    $this->actingAs($this->office)
        ->withSession(['active_role' => 'research_coordinator', 'active_workspace' => User::WORKSPACE_RESEARCH_OFFICE])
        ->post($url, $payload)
        ->assertForbidden();

    $this->topic->update(['status' => TopicProposal::STATUS_LREC_REVIEW, 'review_stage' => 'initial']);
    $this->actingAs($this->office)
        ->withSession(['active_role' => 'research_coordinator', 'active_workspace' => User::WORKSPACE_RESEARCH_OFFICE])
        ->post($url, $payload)
        ->assertForbidden();

    expect($this->topic->reviews()->count())->toBe(0);
});

test('Research Office cannot submit empty comments or a Research Head decision', function () {
    $this->actingAs($this->office)
        ->withSession(['active_role' => 'research_coordinator', 'active_workspace' => User::WORKSPACE_RESEARCH_OFFICE])
        ->post(route('research_coordinator.topics.lrec-feedback.store', $this->topic), ['committee_comments' => []])
        ->assertSessionHasErrors('committee_comments');

    $this->actingAs($this->office)
        ->withSession(['active_role' => 'research_coordinator', 'active_workspace' => User::WORKSPACE_RESEARCH_OFFICE])
        ->post(route('research_coordinator.topics.lrec-feedback.store', $this->topic), [
            'status' => 'ready_for_signature',
            'committee_comments' => [['reviewer' => 'LREC', 'comment' => 'Clarify the method.']],
        ])
        ->assertSessionHasErrors('status');

    $this->actingAs($this->office)
        ->withSession(['active_role' => 'research_coordinator', 'active_workspace' => User::WORKSPACE_RESEARCH_OFFICE])
        ->patch(route('research_head.topics.updateStatus', $this->topic), ['status' => 'ready_for_signature'])
        ->assertForbidden();

    expect($this->topic->fresh()->status)->toBe(TopicProposal::STATUS_LREC_REVIEW);
});

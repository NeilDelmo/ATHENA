<?php

use App\Models\ProposalVersion;
use App\Models\ResearchCall;
use App\Models\TopicProposal;
use App\Models\User;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    $this->withoutVite();

    Role::firstOrCreate(['name' => 'faculty']);
    Role::firstOrCreate(['name' => 'research_head']);

    $this->researchHead = User::factory()->create();
    $this->researchHead->assignRole('research_head');
    $this->faculty = User::factory()->create([
        'name' => 'Dr. Elena Santos',
        'email' => 'elena.santos@g.batstate-u.edu.ph',
    ]);
    $this->faculty->assignRole('faculty');
    $this->researchCall = ResearchCall::create([
        'title' => 'Sustainable Communities Research Call',
        'academic_year' => '2026-2027',
        'opens_at' => now()->subMonth(),
        'closes_at' => now()->addMonth(),
        'status' => 'open',
    ]);
});

test('research heads can view every initial proposal submission and revision', function () {
    $topic = TopicProposal::create([
        'user_id' => $this->faculty->id,
        'research_call_id' => $this->researchCall->id,
        'title' => 'Community Flood Resilience',
        'estimated_budget' => 35000,
        'estimated_duration_months' => 12,
        'status' => 'resubmitted',
    ]);

    $initialSubmission = createProposalSubmission($topic, $this->faculty, [
        'version_number' => 1,
        'submission_type' => 'initial',
        'title' => 'Community Flood Resilience',
    ]);
    $revision = createProposalSubmission($topic, $this->faculty, [
        'version_number' => 2,
        'submission_type' => 'revision',
        'title' => 'Community Flood Resilience',
        'change_summary' => 'Expanded the implementation schedule and revised the budget.',
    ]);

    $initialSubmission->files()->create([
        'document_type' => 'detailed_proposal',
        'position' => 0,
        'file_path' => 'packages/proposal-v1.pdf',
        'original_filename' => 'proposal-v1.pdf',
        'file_size' => 100,
        'is_carried_forward' => false,
    ]);
    foreach (['detailed_proposal', 'work_plan'] as $position => $documentType) {
        $revision->files()->create([
            'document_type' => $documentType,
            'position' => $position,
            'file_path' => "packages/{$documentType}-v2.pdf",
            'original_filename' => "{$documentType}-v2.pdf",
            'file_size' => 100,
            'is_carried_forward' => false,
        ]);
    }

    $this->actingAs($this->researchHead)
        ->get(route('research_head.proposal-submissions.index'))
        ->assertOk()
        ->assertSee('Proposal Submissions')
        ->assertSee('Initial submission')
        ->assertSee('Revision')
        ->assertSee('Version 1')
        ->assertSee('Version 2')
        ->assertSee('Expanded the implementation schedule and revised the budget.')
        ->assertSee('Dr. Elena Santos')
        ->assertSee('Sustainable Communities Research Call')
        ->assertSee('1 package file')
        ->assertSee('2 package files')
        ->assertSee(route('topics.show', $topic).'#version-history', false)
        ->assertSeeInOrder(['Faculty Directory', 'Proposal Submissions', 'Project Monitoring']);
});

test('proposal submissions can be searched and filtered by type and status', function () {
    $initialTopic = TopicProposal::create([
        'user_id' => $this->faculty->id,
        'research_call_id' => $this->researchCall->id,
        'title' => 'Initial Coastal Survey',
        'status' => 'pending',
    ]);
    createProposalSubmission($initialTopic, $this->faculty, [
        'version_number' => 1,
        'submission_type' => 'initial',
        'title' => 'Initial Coastal Survey',
    ]);

    $revisedTopic = TopicProposal::create([
        'user_id' => $this->faculty->id,
        'research_call_id' => $this->researchCall->id,
        'title' => 'Revised Mangrove Mapping',
        'status' => 'resubmitted',
    ]);
    createProposalSubmission($revisedTopic, $this->faculty, [
        'version_number' => 2,
        'submission_type' => 'revision',
        'title' => 'Revised Mangrove Mapping',
    ]);

    $this->actingAs($this->researchHead)
        ->get(route('research_head.proposal-submissions.index', [
            'search' => 'Mangrove',
            'type' => 'revision',
            'status' => 'resubmitted',
        ]))
        ->assertOk()
        ->assertSee('Revised Mangrove Mapping')
        ->assertSee('Revision')
        ->assertDontSee('Initial Coastal Survey');
});

test('proposal submissions are restricted to research heads', function () {
    $faculty = User::factory()->create();
    $faculty->assignRole('faculty');

    $this->get(route('research_head.proposal-submissions.index'))
        ->assertRedirect(route('login'));

    $this->actingAs($faculty)
        ->get(route('research_head.proposal-submissions.index'))
        ->assertForbidden();
});

function createProposalSubmission(TopicProposal $topic, User $submitter, array $overrides = []): ProposalVersion
{
    return $topic->versions()->create(array_merge([
        'submitted_by' => $submitter->id,
        'version_number' => 1,
        'submission_type' => 'initial',
        'file_path' => 'packages/proposal.pdf',
        'original_filename' => 'proposal.pdf',
        'mime_type' => 'application/pdf',
        'file_size' => 100,
        'checksum' => str_repeat('a', 64),
        'title' => $topic->title,
        'estimated_budget' => 10000,
        'estimated_duration_months' => 12,
    ], $overrides));
}

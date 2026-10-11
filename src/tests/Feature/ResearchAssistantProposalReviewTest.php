<?php

use App\Models\ProposalDraft;
use App\Models\ProposalVersionFile;
use App\Models\ResearchCategory;
use App\Models\TopicProposal;
use App\Models\User;
use App\Services\ResearchAssistantProposalReviewService;
use Illuminate\Support\Facades\Http;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    Role::firstOrCreate(['name' => 'faculty']);
    Role::firstOrCreate(['name' => 'research_head']);
    config([
        'services.openrouter.key' => '',
        'services.gemini.key' => 'test-key',
        'services.gemini.base_url' => 'https://generativelanguage.googleapis.com/v1beta/openai',
    ]);
    Http::preventStrayRequests();
    Http::fake(['*/chat/completions' => Http::response([
        'choices' => [['message' => ['content' => 'Check objective 2 against the work plan. [Work Plan]']]],
    ])]);
});

function proposalReviewTopic(User $owner): TopicProposal
{
    return TopicProposal::create([
        'user_id' => $owner->id,
        'research_category_id' => ResearchCategory::create(['name' => 'Review '.uniqid()])->id,
        'title' => 'Coastal habitat study',
        'description' => 'Compare shoreline observations.',
        'estimated_budget' => 12000,
        'estimated_duration_months' => 6,
        'status' => 'pending',
    ]);
}

function proposalReviewFile(TopicProposal $topic, User $owner, int $number, string $type, ?array $data): ProposalVersionFile
{
    $version = $topic->versions()->firstOrCreate(['version_number' => $number], [
        'submitted_by' => $owner->id,
        'submission_type' => $number === 1 ? 'initial' : 'revision',
        'title' => $topic->title,
        'description' => $topic->description,
        'estimated_budget' => 12000,
        'estimated_duration_months' => 6,
        'file_path' => 'proposals/review.pdf',
        'original_filename' => 'review.pdf',
        'mime_type' => 'application/pdf',
        'file_size' => 100,
        'checksum' => str_repeat('a', 64),
    ]);

    return $version->files()->create([
        'document_type' => $type,
        'position' => 0,
        'file_path' => 'proposals/'.$type.'.pdf',
        'original_filename' => $type.'.pdf',
        'mime_type' => 'application/pdf',
        'file_size' => 100,
        'checksum' => str_repeat('b', 64),
        'source_data' => $data,
    ]);
}

function proposalReviewRequest(array $context, string $focus = 'consistency'): array
{
    return [
        'messages' => [['role' => 'user', 'content' => 'Check my proposal and show the evidence for each concern.']],
        'action' => ['type' => 'review_proposal', 'review_focus' => $focus],
        'context' => $context,
    ];
}

test('proposal review compares saved draft papers without sending private profile or browser values', function () {
    $owner = User::factory()->create();
    $owner->assignRole('faculty');
    $draft = ProposalDraft::create(['user_id' => $owner->id, 'project_title' => 'Coastal habitat study']);
    $draft->documents()->create([
        'paper_key' => 'detailed-proposal',
        'document_type' => ProposalVersionFile::TYPE_DETAILED_PROPOSAL,
        'source_data' => [
            'specific_objectives' => [['description' => 'Measure shoreline recovery'], ['description' => 'Compare habitat diversity']],
            'methodology' => ['research_design' => '<p>Quarterly transect sampling.</p>'],
            'leader_email' => 'private-leader@example.com',
            'methodology_images' => [['stored_path' => 'private/image.png']],
        ],
    ]);
    $draft->documents()->create([
        'paper_key' => 'work-plan',
        'document_type' => ProposalVersionFile::TYPE_WORK_PLAN,
        'source_data' => ['entries' => [['objective' => 'Measure shoreline recovery', 'activity' => 'Quarterly transects', 'months' => [1, 3, 6]]]],
    ]);
    $draft->documents()->create([
        'paper_key' => 'line-item-budget',
        'document_type' => ProposalVersionFile::TYPE_LINE_ITEM_BUDGET,
        'source_data' => ['amounts' => ['travel' => 12000], 'project_total_override' => 14000],
    ]);
    $payload = proposalReviewRequest(['proposal_draft_id' => $draft->id, 'workflow_scope' => 'review']);
    $payload['context']['form'] = ['values' => [['field' => 'methodology', 'label' => 'Methodology', 'value' => 'UNSAVED-BROWSER-TEXT']]];

    $this->actingAs($owner)->postJson(route('research-support.chat'), $payload)
        ->assertOk()->assertJsonPath('sources.0.reference', 'Detailed Research Proposal');

    Http::assertSent(function ($request) {
        $content = collect($request['messages'])->pluck('content')->join("\n");

        return str_contains($content, 'proposal_review_evidence')
            && str_contains($content, 'Compare habitat diversity')
            && str_contains($content, 'Quarterly transect sampling.')
            && str_contains($content, 'entries.0.activity')
            && str_contains($content, 'project_total_override')
            && str_contains($content, 'expense_breakdown')
            && ! str_contains($content, 'private-leader@example.com')
            && ! str_contains($content, 'private/image.png')
            && ! str_contains($content, 'UNSAVED-BROWSER-TEXT');
    });
});

test('revision checks compare reviewed and current fields and do not equate resolution flags with proof', function () {
    $owner = User::factory()->create();
    $owner->assignRole('faculty');
    $head = User::factory()->create();
    $head->assignRole('research_head');
    $topic = proposalReviewTopic($owner);
    $old = proposalReviewFile($topic, $owner, 1, 'detailed_proposal', ['methodology' => ['research_design' => 'Convenience sample of 20']]);
    $current = proposalReviewFile($topic, $owner, 2, 'detailed_proposal', ['methodology' => ['research_design' => 'Stratified sample of 100']]);
    proposalReviewFile($topic, $owner, 2, 'curriculum_vitae', ['private_notes' => 'PRIVATE-CV']);
    $review = $topic->reviews()->create(['reviewer_id' => $head->id, 'decision' => 'revision_requested', 'comment' => 'Justify sample size.']);
    $revision = $review->fileRevisions()->create([
        'proposal_version_file_id' => $old->id,
        'document_type' => 'detailed_proposal',
        'original_filename' => 'detailed_proposal.pdf',
        'revision_note' => 'Use stratified sampling and justify 100 participants.',
        'resolution_type' => 'replaced',
        'faculty_response' => 'Sampling has been revised.',
        'resolved_by_version_file_id' => $current->id,
        'resolved_at' => now(),
    ]);
    $annotation = $old->annotations()->create([
        'reviewer_id' => $head->id,
        'topic_review_file_revision_id' => $revision->id,
        'annotation_type' => 'text',
        'page_number' => 3,
        'selected_text' => '20 participants',
        'rectangles' => [],
        'comment' => 'Explain how the sample size was determined.',
    ]);
    $review->update([
        'committee_comments' => [['comment' => 'Explain allocation across strata.', 'location' => 'Methodology']],
        'feedback_responses' => [
            'annotation_'.$annotation->id => ['response' => 'Power analysis is now described in section 3.', 'page' => 3, 'paragraph' => 2],
            'committee_0' => ['response' => 'Allocation uses proportional stratum weights.'],
        ],
    ]);
    foreach (range(1, 3) as $upload) {
        $topic->reviews()->create(['reviewer_id' => $head->id, 'decision' => 'head_upload', 'comment' => 'Uploaded supplemental file '.$upload]);
    }

    $this->actingAs($head)->postJson(route('research-support.chat'), proposalReviewRequest(['topic_id' => $topic->id], 'revision_response'))
        ->assertOk()->assertJsonFragment(['reference' => 'Detailed Research Proposal']);

    Http::assertSent(function ($request) {
        $content = collect($request['messages'])->pluck('content')->join("\n");

        return str_contains($content, 'Convenience sample of 20')
            && str_contains($content, 'Stratified sample of 100')
            && str_contains($content, 'Explain how the sample size was determined.')
            && str_contains($content, 'Sampling has been revised.')
            && str_contains($content, 'Power analysis is now described in section 3.')
            && str_contains($content, 'Allocation uses proportional stratum weights.')
            && str_contains($content, 'resolution flags are not proof')
            && ! str_contains($content, 'PRIVATE-CV');
    });
});

test('draft review uses the same objective and budget calculations as generated papers and discloses truncation', function () {
    $owner = User::factory()->create();
    $owner->assignRole('faculty');
    $draft = ProposalDraft::create(['user_id' => $owner->id, 'project_title' => 'Current title', 'duration_months' => 6]);
    $draft->documents()->create(['document_type' => 'detailed_proposal', 'position' => 0, 'source_data' => [
        'specific_objectives' => [['description' => 'Current objective']],
        'methodology' => ['research_design' => str_repeat('Long methods description. ', 400)],
    ]]);
    $draft->documents()->create(['document_type' => 'work_plan', 'position' => 0, 'source_data' => [
        'entries' => [['objective' => 'OUTDATED-OBJECTIVE', 'activity' => 'Transect observations', 'months' => [1, 3, 6]]],
    ]]);
    $draft->documents()->create(['document_type' => 'line_item_budget', 'position' => 0, 'source_data' => [
        'amounts' => ['travelling_local' => 999],
        'custom_mooe_items' => [['particular' => 'Custom field equipment', 'amount' => 100]],
    ]]);
    $draft->documents()->create(['document_type' => 'expense_breakdown', 'position' => 0, 'source_data' => [
        'items' => [['category' => 'mooe', 'account' => 'Travelling Expenses', 'sub_account' => 'Local', 'particulars' => 'Coastal visits', 'quantity' => 2, 'unit_cost' => 500]],
    ]]);

    $evidence = app(ResearchAssistantProposalReviewService::class)->evidence($draft, null, 'consistency');
    expect($evidence['prompt'])->toContain('Current objective', 'Custom field equipment', 'application_budget_check', 'truncated_fields', 'methodology.research_design', '"line_item_budget":1100')
        ->not->toContain('OUTDATED-OBJECTIVE', str_repeat('Long methods description. ', 400));
});

test('review targets remain authorized and must identify a single related project', function () {
    $owner = User::factory()->create();
    $owner->assignRole('faculty');
    $other = User::factory()->create();
    $other->assignRole('faculty');
    $topic = proposalReviewTopic($owner);
    $draft = ProposalDraft::create(['user_id' => $owner->id, 'project_title' => 'Private draft']);

    $this->actingAs($other)->postJson(route('research-support.chat'), proposalReviewRequest(['topic_id' => $topic->id]))->assertForbidden();
    $this->postJson(route('research-support.chat'), proposalReviewRequest(['proposal_draft_id' => $draft->id]))->assertForbidden();
    $this->actingAs($owner)->postJson(route('research-support.chat'), proposalReviewRequest([]))->assertUnprocessable();
    $this->postJson(route('research-support.chat'), proposalReviewRequest(['topic_id' => $topic->id, 'proposal_draft_id' => $draft->id]))->assertUnprocessable();
    $this->postJson(route('research-support.chat'), proposalReviewRequest(['topic_id' => $topic->id], 'invalid'))->assertUnprocessable();
    Http::assertNothingSent();
});

test('review followups and restored history keep their focus and disclose unavailable paper content', function () {
    $owner = User::factory()->create();
    $owner->assignRole('faculty');
    $topic = proposalReviewTopic($owner);
    proposalReviewFile($topic, $owner, 1, 'detailed_proposal', null);
    $payload = proposalReviewRequest(['topic_id' => $topic->id, 'workflow_scope' => 'review', 'review_focus' => 'consistency']);
    unset($payload['action']);
    $payload['messages'][0]['content'] = 'Which parts still need evidence?';

    $this->actingAs($owner)->postJson(route('research-support.chat'), $payload)->assertOk();
    Http::assertSent(fn ($request) => collect($request['messages'])->contains(fn ($message) => str_contains($message['content'], 'uploaded_content_not_read')));
    $this->postJson(route('research-support.history.save'), ['messages' => $payload['messages'], 'context' => $payload['context']])
        ->assertOk()->assertJsonPath('conversation.context.review_focus', 'consistency');
});

test('typed review requests use evidence only with a selected target and preserve other actions', function () {
    $review = app(ResearchAssistantProposalReviewService::class);
    $context = ['topic_id' => 1];
    expect($review->focus([], $context, 'Check this proposal for consistency.'))->toBe('consistency')
        ->and($review->focus([], $context, 'Check whether I addressed the comments.'))->toBe('revision_response')
        ->and($review->focus([], [], 'Check my proposal.'))->toBeNull()
        ->and($review->focus([], $context, 'What does proposal consistency mean?'))->toBeNull()
        ->and($review->focus(['type' => 'analyze_document'], $context, 'Check this proposal.'))->toBeNull();
});

test('revision draft metadata does not present previous submitted totals as current draft values', function () {
    $owner = User::factory()->create();
    $topic = proposalReviewTopic($owner);
    $draft = ProposalDraft::create(['user_id' => $owner->id, 'topic_id' => $topic->id, 'project_title' => 'Changed draft']);
    $evidence = app(ResearchAssistantProposalReviewService::class)->evidence($draft, $topic, 'consistency');
    preg_match('/\{.*\}/s', $evidence['prompt'], $matches);
    $packet = json_decode($matches[0], true, flags: JSON_THROW_ON_ERROR)['proposal_review_evidence'];

    expect($packet['estimated_budget'])->toBeNull()
        ->and($packet['duration_months'])->toBeNull();
});

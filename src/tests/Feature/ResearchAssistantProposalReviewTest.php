<?php

use App\Models\ProposalDraft;
use App\Models\ProposalVersionFile;
use App\Models\ResearchCategory;
use App\Models\TopicProposal;
use App\Models\User;
use App\Services\ResearchAssistantProposalReviewService;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
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
    $this->proposalReviewReply = json_encode(['summary' => 'Check objective 2 against the work plan.', 'findings' => []]);
    $this->proposalReviewResponses = [];
    Http::fake(['*/chat/completions' => fn () => Http::response(array_shift($this->proposalReviewResponses) ?? [
        'choices' => [['message' => ['content' => $this->proposalReviewReply], 'finish_reason' => 'stop']],
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
        ->assertOk()->assertJsonPath('review.papers.0.reference', 'Detailed Research Proposal');

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

test('proposal reviews include RRL text and verify citations to explicit manual requirements and calculated coverage', function () {
    $indexPath = storage_path('framework/testing/review-requirements-'.uniqid().'.json');
    File::ensureDirectoryExists(dirname($indexPath));
    File::put($indexPath, json_encode([
        'schema_version' => 1, 'version' => hash('sha256', 'review-requirement'), 'title' => 'Fixture Research Manual',
        'chunks' => [['id' => 'rrl', 'section' => 'DETAILED RESEARCH PROPOSAL', 'source_kind' => 'appendix', 'page_start' => 92, 'page_end' => 92,
            'content' => 'Review of Related Literature: minimum of ten literature/studies reviewed.']],
    ], JSON_THROW_ON_ERROR));
    config(['research_assistant.manual.enabled' => true, 'research_assistant.manual.index_path' => $indexPath]);
    try {
        $owner = User::factory()->create();
        $owner->assignRole('faculty');
        $draft = ProposalDraft::create(['user_id' => $owner->id, 'project_title' => 'RRL review fixture']);
        $draft->documents()->create(['document_type' => 'detailed_proposal', 'position' => 0,
            'source_data' => ['related_literature' => '<p>Untracked discussion of community monitoring studies.</p>', 'references' => '<p>Legacy references.</p>']]);
        $service = app(ResearchAssistantProposalReviewService::class);
        $evidence = $service->evidence($draft, null, 'consistency');
        $source = collect($evidence['sources'])->firstWhere('type', 'Official Research Manual');
        expect($source['reference'])->toStartWith('Manual requirement ')
            ->and($evidence['prompt'])->toContain('Untracked discussion', 'manual_requirements', '"count":null', '"minimum":10', 'never count paragraphs');
        $this->proposalReviewReply = json_encode(['summary' => 'Check the untracked RRL against the manual.', 'findings' => [[
            'title' => 'Verify the literature coverage', 'status' => 'needs_attention',
            'explanation' => 'The manual requires ten studies, but untracked writing prevents a complete count.',
            'next_edit' => 'Review and link the cited studies before checking the minimum.',
            'evidence' => [
                ['reference' => $source['reference'], 'field' => 'requirement', 'quote' => 'minimum of ten literature/studies reviewed'],
                ['reference' => 'Saved RRL coverage', 'field' => 'citation_count', 'quote' => '0 distinct linked studies used'],
            ],
        ]]], JSON_THROW_ON_ERROR);
        $response = $this->actingAs($owner)->postJson(route('research-support.chat'), proposalReviewRequest(['proposal_draft_id' => $draft->id]))->assertOk();
        expect($response->json('review.findings.0.evidence'))->toHaveCount(2)
            ->and(collect($response->json('sources'))->firstWhere('type', 'Official Research Manual')['page_start'])->toBe(92);
    } finally {
        File::delete($indexPath);
    }
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

test('legacy review replies retain deterministic saved draft coverage without structured findings', function () {
    $owner = User::factory()->create();
    $owner->assignRole('faculty');
    $draft = ProposalDraft::create(['user_id' => $owner->id, 'project_title' => 'Saved review target']);
    $draft->documents()->create(['document_type' => 'detailed_proposal', 'source_data' => [
        'methodology' => ['research_design' => str_repeat('Bounded evidence. ', 200)],
    ]]);
    $draft->documents()->create(['document_type' => 'work_plan', 'source_data' => null]);

    $service = app(ResearchAssistantProposalReviewService::class);
    $response = $service->response('Check objective 2 against the work plan. [Work Plan]', $service->evidence($draft, null, 'consistency'));

    expect($response['reply'])->toBe('Check objective 2 against the work plan. [Work Plan]')
        ->and($response['review']['record_basis'])->toBe('private_saved_draft')
        ->and($response['review']['version_number'])->toBeNull()
        ->and($response['review']['findings'])->toBe([])
        ->and($response['review']['papers'][0]['coverage'])->toBe('saved_structured_fields')
        ->and($response['review']['papers'][1]['coverage'])->toBe('uploaded_content_not_read')
        ->and($response['review']['papers'][2]['coverage'])->toBe('paper_not_available')
        ->and($response['review']['papers'][0]['truncated_fields'][0])->toBe('Research design')
        ->and($response['review']['reviewed_at'])->toBeString()
        ->and(implode(' ', $response['review']['limitations']))->toContain('saved structured fields', 'Uploaded PDF/DOCX', 'truncated', 'not independently verified');
});

test('structured reviews cite the current submitted version with matched quotes and application links', function () {
    $owner = User::factory()->create();
    $owner->assignRole('faculty');
    $topic = proposalReviewTopic($owner);
    proposalReviewFile($topic, $owner, 1, 'work_plan', ['entries' => [['activity' => 'Old activity']]]);
    $current = proposalReviewFile($topic, $owner, 2, 'work_plan', ['entries' => [['activity' => 'Quarterly coastal transects']]]);
    $reply = ['summary' => 'Check the observation schedule.', 'findings' => [[
        'title' => 'Observation schedule', 'status' => 'needs_attention',
        'explanation' => 'Make the schedule explicit.', 'next_edit' => 'Add the observation months.',
        'evidence' => [['reference' => 'Attachment A: Work Plan', 'field' => 'entries.0.activity', 'quote' => 'Quarterly coastal transects', 'url' => 'https://untrusted.example/']]],
    ]];
    $this->proposalReviewReply = json_encode($reply);

    $response = $this->actingAs($owner)->postJson(route('research-support.chat'), proposalReviewRequest(['topic_id' => $topic->id]))
        ->assertOk()
        ->assertJsonPath('review.record_basis', 'latest_submitted_version')
        ->assertJsonPath('review.version_number', 2)
        ->assertJsonPath('review.findings.0.status', 'needs_attention')
        ->assertJsonPath('review.findings.0.evidence.0.location', 'Activity 1')
        ->assertJsonPath('review.findings.0.evidence.0.quote', 'Quarterly coastal transects')
        ->assertJsonPath('review.findings.0.evidence.0.url', route('topics.versions.files.view', [$topic, $current->version, $current]));

    expect($response->json('reply'))->toContain('Observation schedule', 'Activity 1', 'Quarterly coastal transects', 'Add the observation months.')
        ->not->toContain('untrusted.example', 'entries.0.activity', '"findings"');
});

test('review findings with unknown or mismatched evidence cannot claim verified support', function (array $citation) {
    $owner = User::factory()->create();
    $owner->assignRole('faculty');
    $topic = proposalReviewTopic($owner);
    proposalReviewFile($topic, $owner, 1, 'detailed_proposal', ['methodology' => ['research_design' => 'Stratified sample of 100']]);
    $this->proposalReviewReply = json_encode([
        'summary' => 'Revision assessment.', 'findings' => [[
            'title' => 'Sampling request', 'status' => 'addressed',
            'explanation' => 'The request is addressed.', 'next_edit' => 'No edit needed.',
            'evidence' => [$citation],
        ]],
    ]);

    $response = $this->actingAs($owner)->postJson(route('research-support.chat'), proposalReviewRequest(['topic_id' => $topic->id], 'revision_response'))
        ->assertOk()->assertJsonPath('review.findings.0.status', 'cannot_verify')->assertJsonPath('review.findings.0.evidence', []);

    expect(implode(' ', $response->json('review.limitations')))->toContain('could not be matched')
        ->and($response->json('review.findings.0.explanation'))->not->toBe('The request is addressed.');
})->with([
    'unknown paper' => [['reference' => 'Imaginary attachment', 'field' => 'methodology.research_design', 'quote' => 'Stratified sample of 100']],
    'unknown field' => [['reference' => 'Detailed Research Proposal', 'field' => 'methodology.secret', 'quote' => 'Stratified sample of 100']],
    'invented quote' => [['reference' => 'Detailed Research Proposal', 'field' => 'methodology.research_design', 'quote' => 'Randomized sample of 500']],
]);

test('review evidence cannot cite text omitted from the bounded prompt packet', function () {
    $owner = User::factory()->create();
    $owner->assignRole('faculty');
    $topic = proposalReviewTopic($owner);
    proposalReviewFile($topic, $owner, 1, 'detailed_proposal', ['methodology' => ['research_design' => str_repeat('Included context. ', 200).'OUTSIDE-PACKET']]);
    $this->proposalReviewReply = json_encode([
        'summary' => 'Methodology check.', 'findings' => [[
            'title' => 'Methodology', 'status' => 'observed_mismatch', 'explanation' => 'Conflicting evidence.', 'next_edit' => 'Check the design.',
            'evidence' => [['reference' => 'Detailed Research Proposal', 'field' => 'methodology.research_design', 'quote' => 'OUTSIDE-PACKET']],
        ]],
    ]);

    $this->actingAs($owner)->postJson(route('research-support.chat'), proposalReviewRequest(['topic_id' => $topic->id]))
        ->assertOk()->assertJsonPath('review.findings.0.status', 'cannot_verify')->assertJsonPath('review.findings.0.evidence', []);
});

test('review metadata counts fields omitted from large papers', function () {
    $owner = User::factory()->create();
    $draft = ProposalDraft::create(['user_id' => $owner->id, 'project_title' => 'Large expense plan']);
    $draft->documents()->create(['document_type' => 'expense_breakdown', 'source_data' => [
        'items' => array_fill(0, 80, ['purpose' => 'Coastal field observations']),
    ]]);

    $evidence = app(ResearchAssistantProposalReviewService::class)->evidence($draft, null, 'consistency');

    expect($evidence['review']['papers'][3]['omitted_fields'])->toBeGreaterThan(0)
        ->and(implode(' ', $evidence['review']['limitations']))->toContain('omitted or truncated');
});

test('reviewer comment quotations resolve to known feedback and cannot use model links', function () {
    $owner = User::factory()->create();
    $head = User::factory()->create();
    $owner->assignRole('faculty');
    $head->assignRole('research_head');
    $topic = proposalReviewTopic($owner);
    $topic->reviews()->create(['reviewer_id' => $head->id, 'decision' => 'revision_requested', 'comment' => 'Justify the number of participants.']);
    $this->proposalReviewReply = json_encode([
        'summary' => 'A saved reviewer request needs evidence.', 'findings' => [[
            'title' => 'Participant count', 'status' => 'not_evidenced', 'explanation' => 'The saved papers do not provide the justification.', 'next_edit' => 'Add a sample size justification.',
            'evidence' => [['reference' => 'Reviewer feedback', 'field' => 'reviewer_feedback.0.comment', 'quote' => 'Justify the number of participants.']],
        ]],
    ]);

    $this->actingAs($owner)->postJson(route('research-support.chat'), proposalReviewRequest(['topic_id' => $topic->id], 'revision_response'))
        ->assertOk()->assertJsonPath('review.findings.0.status', 'not_evidenced')
        ->assertJsonPath('review.findings.0.evidence.0.location', 'Reviewer comment 1')
        ->assertJsonPath('review.findings.0.evidence.0.url', route('topics.show', $topic).'#project-details');
});

test('saved chat history round trips bounded review provenance and source categories safely', function () {
    $this->withoutExceptionHandling();
    $owner = User::factory()->create();
    $owner->assignRole('faculty');
    $review = [
        'summary' => '<b>Saved review</b>', 'record_basis' => 'latest_submitted_version', 'version_number' => 2,
        'reviewed_at' => now()->toIso8601String(),
        'papers' => [['reference' => 'Detailed Research Proposal', 'title' => 'Submitted proposal v2', 'url' => 'javascript:alert(1)', 'coverage' => 'saved_structured_fields', 'omitted_fields' => 0, 'truncated_fields' => []]],
        'findings' => [['title' => 'Sampling', 'status' => 'needs_attention', 'explanation' => 'Clarify sampling.', 'next_edit' => 'Explain the strata.',
            'evidence' => [['reference' => 'Detailed Research Proposal', 'location' => 'Research design', 'quote' => 'Stratified sample', 'url' => route('faculty.dashboard')]]]],
        'limitations' => ['Saved structured fields only.'],
    ];
    $saved = $this->actingAs($owner)->postJson(route('research-support.history.save'), ['messages' => [
        ['role' => 'user', 'content' => 'Review my proposal.'],
        ['role' => 'assistant', 'content' => 'Clarify sampling.', 'review' => $review,
            'sources' => [['reference' => 'Detailed Research Proposal', 'title' => 'Submission v2', 'type' => 'proposal_paper', 'category' => 'Saved ATHENA paper fields', 'url' => route('faculty.dashboard')]]],
    ]])->assertOk()->assertJsonPath('conversation.messages.1.review.summary', 'Saved review')
        ->assertJsonPath('conversation.messages.1.review.papers.0.url', null)
        ->assertJsonPath('conversation.messages.1.sources.0.type', 'proposal_paper')
        ->assertJsonPath('conversation.messages.1.sources.0.category', 'Saved ATHENA paper fields');

    $this->getJson(route('research-support.history.show', $saved->json('conversation.id')))->assertOk()
        ->assertJsonPath('conversation.messages.1.review.version_number', 2)
        ->assertJsonPath('conversation.messages.1.review.findings.0.evidence.0.location', 'Research design');

    $this->expectException(ValidationException::class);
    $review['findings'] = array_fill(0, 6, $review['findings'][0]);
    $this->postJson(route('research-support.history.save'), ['messages' => [
        ['role' => 'user', 'content' => 'Review.'], ['role' => 'assistant', 'content' => 'Review.', 'review' => $review],
    ]]);
});

test('malformed structured review responses never leak raw json into the chat', function (string $reply) {
    $owner = User::factory()->create();
    $owner->assignRole('faculty');
    $draft = ProposalDraft::create(['user_id' => $owner->id, 'project_title' => 'Incomplete response']);
    $this->proposalReviewReply = $reply;

    $response = $this->actingAs($owner)->postJson(route('research-support.chat'), proposalReviewRequest(['proposal_draft_id' => $draft->id]))
        ->assertStatus(502)->assertJsonMissingPath('review')->assertJsonMissingPath('sources');

    expect($response->json('message'))->toContain('complete review')->not->toContain('{', '```', 'RAW-PAYLOAD');
    Http::assertSentCount(2);
})->with([
    'truncated json' => ['{"summary":"RAW-PAYLOAD","findings": ['],
    'missing findings' => ['{"summary":"RAW-PAYLOAD"}'],
    'missing summary' => ["```json\n{\"findings\":[]}\n```"],
    'unlabeled malformed fence' => ["```\n{\"summary\":\"RAW-PAYLOAD\",\"findings\": [\n```"],
    'wrong root shape' => ['[{"summary":"RAW-PAYLOAD","findings":[]}]'],
]);

test('an unsupported addressed verdict loses its positive summary and no edit instruction', function () {
    $owner = User::factory()->create();
    $owner->assignRole('faculty');
    $topic = proposalReviewTopic($owner);
    $this->proposalReviewReply = json_encode(['summary' => 'All reviewer requests are addressed.', 'findings' => [[
        'title' => 'Reviewer request', 'status' => 'addressed', 'explanation' => 'All changes are complete.', 'next_edit' => 'No edit needed.', 'evidence' => [],
    ]]]);

    $response = $this->actingAs($owner)->postJson(route('research-support.chat'), proposalReviewRequest(['topic_id' => $topic->id], 'revision_response'))
        ->assertOk()->assertJsonPath('review.findings.0.status', 'cannot_verify');

    expect($response->json('review.summary'))->not->toBe('All reviewer requests are addressed.')
        ->and($response->json('review.findings.0.next_edit'))->not->toBe('No edit needed.');
});

test('reviews request enforced json schema with a bounded reasoning and completion budget', function () {
    $owner = User::factory()->create();
    $owner->assignRole('faculty');
    $draft = ProposalDraft::create(['user_id' => $owner->id, 'project_title' => 'Schema review']);

    $this->actingAs($owner)->postJson(route('research-support.chat'), proposalReviewRequest(['proposal_draft_id' => $draft->id]))->assertOk();

    Http::assertSent(function ($request): bool {
        $schema = $request['response_format']['json_schema']['schema'] ?? [];

        return ($request['response_format']['type'] ?? null) === 'json_schema'
            && ($request['response_format']['json_schema']['strict'] ?? null) === true
            && ($schema['required'] ?? null) === ['summary', 'findings']
            && ($schema['additionalProperties'] ?? null) === false
            && ($schema['properties']['findings']['items']['required'] ?? null) === ['title', 'status', 'explanation', 'next_edit', 'evidence']
            && $request['reasoning_effort'] === 'low'
            && $request['max_completion_tokens'] === 4096;
    });
});

test('reviews recover one incomplete provider response with a larger bounded budget', function (string $content, string $finishReason) {
    $owner = User::factory()->create();
    $owner->assignRole('faculty');
    $draft = ProposalDraft::create(['user_id' => $owner->id, 'project_title' => 'Recovered review']);
    $this->proposalReviewResponses = [
        ['choices' => [['message' => ['content' => $content], 'finish_reason' => $finishReason]], 'usage' => ['completion_tokens' => 4096, 'completion_tokens_details' => ['reasoning_tokens' => 3000]]],
        ['choices' => [['message' => ['content' => json_encode(['summary' => 'No supported mismatch was found in the available fields.', 'findings' => []])], 'finish_reason' => 'stop']]],
    ];

    $this->actingAs($owner)->postJson(route('research-support.chat'), proposalReviewRequest(['proposal_draft_id' => $draft->id]))
        ->assertOk()->assertJsonPath('review.summary', 'No supported mismatch was found in the available fields.')->assertJsonPath('sources', []);

    $requests = Http::recorded()->map(fn (array $record): mixed => $record[0]);
    expect($requests)->toHaveCount(2)
        ->and($requests[0]['max_completion_tokens'])->toBe(4096)
        ->and($requests[1]['max_completion_tokens'])->toBe(8192)
        ->and($requests[1]['reasoning_effort'])->toBe('low');
})->with([
    'truncated json' => ['{"summary":"Incomplete', 'length'],
    'invalid json' => ['{"summary":"Incomplete', 'stop'],
    'empty completion' => ['', 'length'],
    'blank summary' => ['{"summary":"","findings":[]}', 'stop'],
    'whitespace summary' => ['{"summary":"   ","findings":[]}', 'stop'],
]);

test('a token limited response cannot be treated as a completed review even with valid json', function () {
    $owner = User::factory()->create();
    $owner->assignRole('faculty');
    $draft = ProposalDraft::create(['user_id' => $owner->id, 'project_title' => 'Limited review']);
    $limited = ['choices' => [['message' => ['content' => json_encode(['summary' => 'Partial assessment.', 'findings' => []])], 'finish_reason' => 'length']]];
    $this->proposalReviewResponses = [$limited, $limited];

    $this->actingAs($owner)->postJson(route('research-support.chat'), proposalReviewRequest(['proposal_draft_id' => $draft->id]))
        ->assertStatus(502)->assertJsonMissingPath('review')->assertJsonMissingPath('sources');
    Http::assertSentCount(2);
});

test('review source lists contain only references used by validated finding evidence', function () {
    $owner = User::factory()->create();
    $owner->assignRole('faculty');
    $topic = proposalReviewTopic($owner);
    proposalReviewFile($topic, $owner, 1, 'detailed_proposal', ['methodology' => ['research_design' => 'Quarterly observations']]);
    proposalReviewFile($topic, $owner, 1, 'work_plan', ['entries' => [['activity' => 'Coastal transects']]]);
    $this->proposalReviewReply = json_encode(['summary' => 'Clarify the activity schedule.', 'findings' => [[
        'title' => 'Activity schedule', 'status' => 'needs_attention', 'explanation' => 'Specify the observation frequency.', 'next_edit' => 'Add observation months.',
        'evidence' => [['reference' => 'Attachment A: Work Plan', 'field' => 'entries.0.activity', 'quote' => 'Coastal transects']],
    ]]]);

    $this->actingAs($owner)->postJson(route('research-support.chat'), proposalReviewRequest(['topic_id' => $topic->id]))
        ->assertOk()->assertJsonCount(1, 'sources')->assertJsonPath('sources.0.reference', 'Attachment A: Work Plan');
});

test('blank summaries returned twice do not become a successful review', function () {
    $owner = User::factory()->create();
    $owner->assignRole('faculty');
    $draft = ProposalDraft::create(['user_id' => $owner->id, 'project_title' => 'Blank assessment']);
    $this->proposalReviewReply = json_encode(['summary' => '   ', 'findings' => []]);

    $this->actingAs($owner)->postJson(route('research-support.chat'), proposalReviewRequest(['proposal_draft_id' => $draft->id]))
        ->assertStatus(502)->assertJsonMissingPath('review');
    Http::assertSentCount(2);
});

test('dual role faculty assistants cannot read unrelated projects through chat documents or saved head history', function () {
    $dual = User::factory()->create();
    $dual->assignRole(['faculty', 'research_head']);
    $owner = User::factory()->create();
    $topic = proposalReviewTopic($owner);
    $file = proposalReviewFile($topic, $owner, 1, 'detailed_proposal', ['methodology' => ['research_design' => 'OTHER-ACCOUNT-PAPER']]);
    $headHistory = $dual->researchAssistantConversations()->create(['title' => 'Head review', 'context' => ['topic_id' => $topic->id, 'workspace' => User::WORKSPACE_RESEARCH_HEAD], 'messages' => [['role' => 'user', 'content' => 'HEAD-PRIVATE-CHAT']]]);
    $token = Crypt::encryptString(json_encode(['type' => 'proposal_version_file', 'id' => $file->id]));
    $this->actingAs($dual)->withSession([User::ACTIVE_WORKSPACE_SESSION_KEY => User::WORKSPACE_FACULTY]);

    $this->postJson(route('research-support.chat'), proposalReviewRequest(['topic_id' => $topic->id]))->assertForbidden();
    $this->getJson(route('research-support.documents', ['topic_id' => $topic->id]))->assertForbidden();
    $this->postJson(route('research-support.chat'), ['messages' => [['role' => 'user', 'content' => 'Analyze this document.']], 'action' => ['type' => 'analyze_document', 'document_token' => $token]])->assertForbidden();
    $this->getJson(route('research-support.history.show', $headHistory))->assertNotFound();
    $this->getJson(route('research-support.history'))->assertOk()->assertJsonCount(0, 'conversations');
    $this->postJson(route('research-support.chat'), ['messages' => [['role' => 'user', 'content' => 'Summarize my previous conversation.']], 'conversation_id' => $headHistory->id])->assertForbidden();
    Http::assertNothingSent();

    $this->withSession([User::ACTIVE_WORKSPACE_SESSION_KEY => User::WORKSPACE_RESEARCH_HEAD])
        ->postJson(route('research-support.chat'), proposalReviewRequest(['topic_id' => $topic->id]))->assertOk();
});

test('accepted faculty collaborators can use project feedback while pending invitations cannot', function () {
    $owner = User::factory()->create();
    $shared = User::factory()->create();
    $shared->assignRole(['faculty', 'research_head']);
    $topic = proposalReviewTopic($owner);
    $collaborator = $topic->collaborators()->create(['user_id' => $shared->id, 'name' => $shared->name, 'email' => $shared->email]);
    $this->actingAs($shared)->withSession([User::ACTIVE_WORKSPACE_SESSION_KEY => User::WORKSPACE_FACULTY]);
    $this->postJson(route('research-support.chat'), proposalReviewRequest(['topic_id' => $topic->id]))->assertForbidden();
    Http::assertNothingSent();
    $collaborator->update(['accepted_at' => now()]);
    $this->postJson(route('research-support.chat'), proposalReviewRequest(['topic_id' => $topic->id]))->assertOk();
});

test('faculty document analysis excludes unreleased research head documents and rechecks old tokens', function () {
    Storage::fake('local');
    $dual = User::factory()->create();
    $dual->assignRole(['faculty', 'research_head']);
    $topic = proposalReviewTopic($dual);
    $submitted = proposalReviewFile($topic, $dual, 1, 'detailed_proposal', ['methodology' => ['research_design' => 'Saved design']]);
    $signed = proposalReviewFile($topic, $dual, 1, 'head_upload', ['purpose' => ProposalVersionFile::HEAD_UPLOAD_PURPOSE_SIGNED]);
    Storage::disk('local')->put($submitted->file_path, 'submitted proposal');
    Storage::disk('local')->put($signed->file_path, 'UNRELEASED-SIGNED-COPY');
    $token = Crypt::encryptString(json_encode(['type' => 'proposal_version_file', 'id' => $signed->id]));
    $this->actingAs($dual)->withSession([User::ACTIVE_WORKSPACE_SESSION_KEY => User::WORKSPACE_FACULTY]);

    $listed = $this->getJson(route('research-support.documents', ['topic_id' => $topic->id]))->assertOk();
    expect(collect($listed->json('documents'))->pluck('label')->join(' '))->not->toContain('Signed');
    $this->postJson(route('research-support.chat'), ['messages' => [['role' => 'user', 'content' => 'Analyze this document.']], 'action' => ['type' => 'analyze_document', 'document_token' => $token]])->assertForbidden();
    Http::assertNothingSent();

    $this->withSession([User::ACTIVE_WORKSPACE_SESSION_KEY => User::WORKSPACE_RESEARCH_HEAD]);
    expect(count($this->getJson(route('research-support.documents', ['topic_id' => $topic->id]))->assertOk()->json('documents')))->toBeGreaterThan(count($listed->json('documents')));
});

test('faculty workflow help excludes head upload notes and limits identity to the active workspace', function () {
    $dual = User::factory()->create();
    $dual->assignRole(['faculty', 'research_head']);
    $topic = proposalReviewTopic($dual);
    $topic->reviews()->create(['reviewer_id' => $dual->id, 'decision' => 'head_upload', 'comment' => 'HEAD-UPLOAD-PRIVATE-NOTE']);
    $topic->reviews()->create(['reviewer_id' => $dual->id, 'decision' => 'revision_requested', 'comment' => 'Explain the sample size.']);
    $this->proposalReviewReply = 'Explain the sample size using your actual research assumptions. [Project record]';
    $this->actingAs($dual)->withSession([User::ACTIVE_WORKSPACE_SESSION_KEY => User::WORKSPACE_FACULTY])
        ->postJson(route('research-support.chat'), ['messages' => [['role' => 'user', 'content' => 'Explain my reviewer feedback.']], 'context' => ['topic_id' => $topic->id, 'workflow_scope' => 'review']])->assertOk();

    Http::assertSent(function ($request): bool {
        $prompt = collect($request['messages'])->pluck('content')->join("\n");

        return str_contains($prompt, 'Explain the sample size.') && ! str_contains($prompt, 'HEAD-UPLOAD-PRIVATE-NOTE')
            && ! str_contains($request['messages'][0]['content'], 'Athena role(s): research head');
    });
});

test('review help modes remain conversational with saved feedback instead of forcing a new structured assessment', function (string $mode) {
    $owner = User::factory()->create();
    $owner->assignRole('faculty');
    $topic = proposalReviewTopic($owner);
    proposalReviewFile($topic, $owner, 1, 'detailed_proposal', ['methodology' => ['research_design' => 'Quarterly transects']]);
    $topic->reviews()->create(['reviewer_id' => $owner->id, 'decision' => 'revision_requested', 'comment' => 'Explain how sites were chosen.']);
    $this->proposalReviewReply = 'Start with the site selection rationale. [Detailed Research Proposal]';
    $this->actingAs($owner)->postJson(route('research-support.chat'), [
        'messages' => [['role' => 'user', 'content' => 'Help me write a revision response.']],
        'context' => ['topic_id' => $topic->id, 'workflow_scope' => 'review', 'review_focus' => 'revision_response', 'assistance_mode' => $mode],
    ])->assertOk()->assertJsonMissingPath('review');

    Http::assertSent(function ($request): bool {
        $prompt = collect($request['messages'])->pluck('content')->join("\n");

        return ! isset($request['response_format']) && $request['max_completion_tokens'] === 4096 && $request['reasoning_effort'] === 'low'
            && str_contains($prompt, 'Quarterly transects') && str_contains($prompt, 'Explain how sites were chosen.')
            && ! str_contains($prompt, 'Return only a JSON object');
    });
})->with(['revision_plan', 'feedback_explanation', 'response_draft', 'draft_guidance', 'discussion']);

test('saved histories stamp the active workspace and reject foreign selected proposal contexts', function () {
    $dual = User::factory()->create();
    $dual->assignRole(['faculty', 'research_head']);
    $other = User::factory()->create();
    $topic = proposalReviewTopic($other);
    $this->actingAs($dual)->withSession([User::ACTIVE_WORKSPACE_SESSION_KEY => User::WORKSPACE_FACULTY]);
    $payload = ['messages' => [['role' => 'user', 'content' => 'Private discussion.']], 'context' => ['assistance_mode' => 'discussion']];
    $this->postJson(route('research-support.history.save'), $payload)->assertOk()->assertJsonPath('conversation.context.workspace', User::WORKSPACE_FACULTY);
    $payload['context']['topic_id'] = $topic->id;
    $this->postJson(route('research-support.history.save'), $payload)->assertForbidden();
    Http::assertNothingSent();
});

test('ambiguous legacy chats remain hidden in faculty even when the selected project is owned', function () {
    $dual = User::factory()->create();
    $dual->assignRole(['faculty', 'research_head']);
    $topic = proposalReviewTopic($dual);
    $legacy = $dual->researchAssistantConversations()->create(['title' => 'Legacy private head discussion', 'context' => ['topic_id' => $topic->id], 'messages' => [['role' => 'user', 'content' => 'Legacy reviewer notes.']]]);
    $this->actingAs($dual)->withSession([User::ACTIVE_WORKSPACE_SESSION_KEY => User::WORKSPACE_FACULTY]);

    $this->getJson(route('research-support.history'))->assertOk()->assertJsonCount(0, 'conversations');
    $this->getJson(route('research-support.history.show', $legacy))->assertNotFound();
    $this->postJson(route('research-support.chat'), ['messages' => [['role' => 'user', 'content' => 'What did the earlier chat say?']], 'conversation_id' => $legacy->id])->assertForbidden();
    Http::assertNothingSent();
});

test('only accepted draft members can use saved draft help and no head role grants private draft access', function () {
    $owner = User::factory()->create();
    $member = User::factory()->create();
    $member->assignRole(['faculty', 'research_head']);
    $draft = ProposalDraft::create(['user_id' => $owner->id, 'project_title' => 'Explicitly shared draft']);
    $membership = $draft->members()->create(['user_id' => $member->id, 'name' => $member->name, 'email' => $member->email, 'accepted_at' => null]);
    $this->proposalReviewReply = 'Clarify the objective before drafting the methods.';
    $payload = ['messages' => [['role' => 'user', 'content' => 'Help with this draft.']], 'context' => ['proposal_draft_id' => $draft->id, 'assistance_mode' => 'draft_guidance']];
    $this->actingAs($member)->withSession([User::ACTIVE_WORKSPACE_SESSION_KEY => User::WORKSPACE_FACULTY]);
    expect($membership->accepted_at)->toBeNull();
    expect(ProposalDraft::query()->accessibleTo($member)->whereKey($draft->id)->exists())->toBeFalse();
    $pending = $this->postJson(route('research-support.chat'), $payload);
    expect($pending->status())->toBe(403, 'Pending draft invitation must be denied');
    Http::assertNothingSent();
    $membership->update(['accepted_at' => now()]);
    $this->postJson(route('research-support.chat'), $payload)->assertOk()->assertJsonMissingPath('review');
    $headRequest = $this->withSession([User::ACTIVE_WORKSPACE_SESSION_KEY => User::WORKSPACE_RESEARCH_HEAD])->postJson(route('research-support.chat'), $payload);
    expect($headRequest->status())->toBe(403, 'Head workspace must not read a private faculty draft');
    Http::assertSentCount(1);
});

test('revoked project sharing prevents stored conversation memory from being reused', function () {
    $owner = User::factory()->create();
    $participant = User::factory()->create();
    $participant->assignRole('faculty');
    $topic = proposalReviewTopic($owner);
    $sharing = $topic->collaborators()->create(['user_id' => $participant->id, 'name' => $participant->name, 'email' => $participant->email, 'accepted_at' => now()]);
    $this->actingAs($participant);
    $saved = $this->postJson(route('research-support.history.save'), ['messages' => [['role' => 'user', 'content' => 'Explain this project feedback.']], 'context' => ['topic_id' => $topic->id, 'assistance_mode' => 'feedback_explanation']])->assertOk();
    $sharing->delete();
    $this->getJson(route('research-support.history.show', $saved->json('conversation.id')))->assertNotFound();
    $this->postJson(route('research-support.chat'), ['messages' => [['role' => 'user', 'content' => 'Continue explaining the feedback.']], 'conversation_id' => $saved->json('conversation.id')])->assertForbidden();
    Http::assertNothingSent();
});

test('faculty assistant packets omit unsigned notice details until they are issued', function () {
    $dual = User::factory()->create();
    $dual->assignRole(['faculty', 'research_head']);
    $topic = proposalReviewTopic($dual);
    $topic->update(['status' => 'approved', 'notice_to_proceed_data' => [
        'resolution_number' => 'UNRELEASED-OFFICE-RESOLUTION', 'approved_end_date' => '2099-11-30',
    ]]);
    $this->proposalReviewReply = 'The signed Notice to Proceed has not been issued.';
    $this->actingAs($dual)->withSession([User::ACTIVE_WORKSPACE_SESSION_KEY => User::WORKSPACE_FACULTY]);
    foreach (['notice', 'monitoring'] as $scope) {
        $this->postJson(route('research-support.chat'), ['messages' => [['role' => 'user', 'content' => 'Explain the available project dates.']],
            'context' => ['topic_id' => $topic->id, 'workflow_scope' => $scope]])->assertOk();
        $request = Http::recorded()->last()[0];
        $prompt = collect($request['messages'])->pluck('content')->join("\n");
        expect($prompt)->not->toContain('UNRELEASED-OFFICE-RESOLUTION', '2099-11-30');
    }
    $this->withSession([User::ACTIVE_WORKSPACE_SESSION_KEY => User::WORKSPACE_RESEARCH_HEAD])
        ->postJson(route('research-support.chat'), ['messages' => [['role' => 'user', 'content' => 'Explain the prepared notice.']],
            'context' => ['topic_id' => $topic->id, 'workflow_scope' => 'notice']])->assertOk();
    expect(collect(Http::recorded()->last()[0]['messages'])->pluck('content')->join("\n"))->toContain('UNRELEASED-OFFICE-RESOLUTION');
    $topic->update(['notice_to_proceed_issued_at' => now()]);
    $this->withSession([User::ACTIVE_WORKSPACE_SESSION_KEY => User::WORKSPACE_FACULTY])
        ->postJson(route('research-support.chat'), ['messages' => [['role' => 'user', 'content' => 'Explain the issued notice.']],
            'context' => ['topic_id' => $topic->id, 'workflow_scope' => 'notice']])->assertOk();
    expect(collect(Http::recorded()->last()[0]['messages'])->pluck('content')->join("\n"))->toContain('UNRELEASED-OFFICE-RESOLUTION', '2099-11-30');
});

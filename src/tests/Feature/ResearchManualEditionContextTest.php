<?php

use App\Actions\CreateProposalRevisionDraft;
use App\Models\ProposalDraft;
use App\Models\ResearchManual;
use App\Models\TopicProposal;
use App\Models\User;
use App\Services\ResearchAssistantProposalAssistanceService;
use App\Services\ResearchAssistantProposalReviewService;
use App\Services\ResearchKnowledgeService;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;
use Symfony\Component\HttpKernel\Exception\HttpException;

beforeEach(function () {
    Storage::fake('local');
    Role::firstOrCreate(['name' => 'faculty']);
    $this->faculty = User::factory()->create();
    $this->faculty->assignRole('faculty');
    $this->withoutVite();
    $this->writeEdition = function (ResearchManual $manual, string $minimum): void {
        File::ensureDirectoryExists(dirname($manual->indexPath()));
        File::put($manual->indexPath(), json_encode([
            'schema_version' => 1, 'version' => $manual->version, 'title' => $manual->title, 'page_count' => 92, 'unindexed_pages' => [],
            'chunks' => [['id' => 'rrl', 'section' => 'DETAILED RESEARCH PROPOSAL', 'source_kind' => 'appendix',
                'page_start' => 92, 'page_end' => 92,
                'content' => 'Review of Related Literature: minimum of '.$minimum.' literature/studies reviewed for proposal approval.']],
        ], JSON_THROW_ON_ERROR));
    };
    $this->previous = ResearchManual::factory()->create(['title' => 'Research Manual 2024', 'edition' => '2024', 'version' => hash('sha256', '2024'), 'directory' => 'research-manual/editions/previous']);
    $this->current = ResearchManual::factory()->create(['title' => 'Research Manual 2026', 'edition' => '2026', 'version' => hash('sha256', '2026'), 'directory' => 'research-manual/editions/current']);
    ($this->writeEdition)($this->previous, 'ten');
    ($this->writeEdition)($this->current, 'twelve');
    $this->activePath = Storage::disk('local')->path('research-manual/current.json');
    config(['research_assistant.manual.enabled' => true, 'research_assistant.manual.index_path' => $this->activePath,
        'research_assistant.manual.minimum_score' => 0.1, 'cache.default' => 'array',
        'services.openrouter.key' => null, 'services.gemini.key' => 'test-key',
        'services.gemini.base_url' => 'https://generativelanguage.googleapis.com/v1beta/openai']);
    File::copy($this->previous->indexPath(), $this->activePath);
    $this->draft = ProposalDraft::create(['user_id' => $this->faculty->id, 'project_title' => 'Coastal monitoring',
        'research_manual_id' => $this->previous->id]);
    $this->document = $this->draft->documents()->create(['document_type' => 'detailed_proposal', 'position' => 0,
        'source_data' => ['rationale' => '<p>Assess community monitoring needs.</p>', 'related_literature' => ''], 'lock_version' => 1]);
    Http::preventStrayRequests();
    $this->completion = ['choices' => [['message' => ['content' => 'Use the applicable manual.'], 'finish_reason' => 'stop']]];
    Http::fake(fn () => Http::response($this->completion));
    $this->actingAs($this->faculty);
});

test('new proposals bind the active edition while existing records and revision drafts retain theirs', function () {
    $olderDraft = ProposalDraft::create(['user_id' => $this->faculty->id, 'project_title' => 'Older draft']);
    $topic = TopicProposal::create(['user_id' => $this->faculty->id, 'title' => 'Older proposal', 'status' => 'revision_requested']);
    File::copy($this->current->indexPath(), $this->activePath);
    $newDraft = ProposalDraft::create(['user_id' => $this->faculty->id, 'project_title' => 'New draft']);
    $newTopic = TopicProposal::create(['user_id' => $this->faculty->id, 'title' => 'New proposal']);
    $revision = app(CreateProposalRevisionDraft::class)->handle($topic, $this->faculty);

    expect($olderDraft->fresh()->research_manual_id)->toBe($this->previous->id)
        ->and($topic->fresh()->research_manual_id)->toBe($this->previous->id)
        ->and($revision->research_manual_id)->toBe($this->previous->id)
        ->and($newDraft->researchManual->is($this->current))->toBeTrue()
        ->and($newTopic->researchManual->is($this->current))->toBeTrue();
    $this->get(route('faculty.proposal-drafts.show', $olderDraft))->assertSuccessful()->assertSee('2024');
});

test('proposal assessment and review use the pinned edition and fail closed when its index is unavailable', function () {
    File::copy($this->current->indexPath(), $this->activePath);
    $assessment = app(ResearchAssistantProposalAssistanceService::class)->assessment($this->draft);
    $review = app(ResearchAssistantProposalReviewService::class)->evidence($this->draft, null, 'consistency');

    expect($assessment['rrl']['minimum'])->toBe(10)
        ->and($assessment['rrl']['sources'][0]['document_version'])->toBe($this->previous->version)
        ->and($review['prompt'])->toContain('minimum of ten')->not->toContain('minimum of twelve');
    File::delete($this->previous->indexPath());
    $assessment = app(ResearchAssistantProposalAssistanceService::class)->assessment($this->draft);
    expect($assessment['rrl']['minimum'])->toBeNull()
        ->and($assessment['rrl']['sources'])->toBe([])
        ->and(collect(app(ResearchKnowledgeService::class)->retrieve('What are the RRL requirements in the Research Manual?'))->where('type', 'Official Research Manual')->first()['document_version'])->toBe($this->current->version);
});

test('selected proposal chat uses its edition while general chat uses the current edition', function (string $selection) {
    File::copy($this->current->indexPath(), $this->activePath);
    $context = $selection === 'draft' ? ['proposal_draft_id' => $this->draft->id]
        : ['topic_id' => TopicProposal::create(['user_id' => $this->faculty->id, 'title' => 'Pinned topic', 'research_manual_id' => $this->previous->id])->id];
    $request = ['messages' => [['role' => 'user', 'content' => 'What are the RRL requirements in the Research Manual?']]];
    $selected = $this->postJson(route('research-support.chat'), [...$request, 'context' => $context])->assertSuccessful();
    $general = $this->postJson(route('research-support.chat'), $request)->assertSuccessful();

    expect(collect($selected->json('sources'))->where('type', 'Official Research Manual')->pluck('document_version')->unique()->all())->toBe([$this->previous->version])
        ->and(collect($general->json('sources'))->where('type', 'Official Research Manual')->pluck('document_version')->unique()->all())->toBe([$this->current->version]);
})->with(['draft', 'topic']);

test('submitted proposal reviews and guidance can cite their pinned manual requirements', function () {
    File::copy($this->current->indexPath(), $this->activePath);
    $topic = TopicProposal::create(['user_id' => $this->faculty->id, 'title' => 'Pinned submitted proposal', 'research_manual_id' => $this->previous->id]);
    $evidence = app(ResearchAssistantProposalReviewService::class)->evidence(null, $topic, 'consistency');

    expect($evidence['evidence_fields']['Manual requirement 1']['requirement']['value'] ?? '')->toContain('minimum of ten')
        ->and(collect($evidence['sources'])->where('type', 'Official Research Manual')->first()['document_version'] ?? null)->toBe($this->previous->version)
        ->and($evidence['prompt'])->not->toContain('minimum of twelve');
});

test('selected saved paper analysis resolves the edition from its authorized document token', function () {
    File::copy($this->current->indexPath(), $this->activePath);
    $options = $this->getJson(route('research-support.documents', ['proposal_draft_id' => $this->draft->id]))->assertSuccessful()->json('documents');
    $selection = collect($options)->first(fn (array $option): bool => $option['format'] === 'SAVED' && str_contains($option['label'], 'Detailed'));
    $response = $this->postJson(route('research-support.chat'), [
        'messages' => [['role' => 'user', 'content' => 'Compare this paper with the Research Manual RRL requirements.']],
        'action' => ['type' => 'analyze_document', 'document_token' => $selection['token']],
    ])->assertSuccessful();

    expect(collect($response->json('sources'))->where('type', 'Official Research Manual')->pluck('document_version')->unique()->all())->toBe([$this->previous->version]);
});

test('assistant edition context cannot be selected with a raw manual id or another users proposal', function () {
    $private = ProposalDraft::create(['user_id' => User::factory()->create()->id, 'project_title' => 'Private', 'research_manual_id' => $this->previous->id]);
    $request = ['messages' => [['role' => 'user', 'content' => 'What are the Research Manual RRL requirements?']]];
    $this->postJson(route('research-support.chat'), [...$request, 'context' => ['proposal_draft_id' => $private->id]])->assertForbidden();
    $this->postJson(route('research-support.chat'), [...$request, 'context' => ['research_manual_id' => $this->previous->id]])->assertUnprocessable();
    Http::assertNothingSent();
});

test('suggestions survive current edition activation but reject changes to the pinned index', function () {
    $this->completion = ['choices' => [['message' => ['content' => json_encode([
        'summary' => 'Review this edit.', 'suggestions' => [['field' => 'rationale', 'before' => 'Assess community monitoring needs.',
            'value' => 'Assess the needs for community monitoring.', 'reason' => 'Clarifies the wording.']],
    ], JSON_THROW_ON_ERROR)], 'finish_reason' => 'stop']]];
    $assistance = app(ResearchAssistantProposalAssistanceService::class);
    $suggestion = $assistance->respond($this->faculty, ['proposal_draft_id' => $this->draft->id], 'Improve my rationale')['proposal_changes']['suggestions'][0];
    File::copy($this->current->indexPath(), $this->activePath);

    expect($assistance->resolve($this->faculty, $this->draft, $suggestion['token'], $suggestion['id'])['value'])->toBe($suggestion['value']);
    ($this->writeEdition)($this->previous, 'eleven');
    expect(fn () => $assistance->resolve($this->faculty, $this->draft, $suggestion['token'], $suggestion['id']))->toThrow(HttpException::class);
});

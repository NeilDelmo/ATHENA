<?php

use App\Models\ProposalDraft;
use App\Models\ResearchAssistantConversation;
use App\Models\TopicProposal;
use App\Models\User;
use App\Services\ResearchAssistantDocumentService;
use App\Services\ResearchAssistantPaperReader;
use App\Services\ResearchAssistantProposalAssistanceService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    Storage::fake('local');
    Http::preventStrayRequests();
    Process::preventStrayProcesses();
    Role::firstOrCreate(['name' => 'faculty']);
    $this->figureUser = User::factory()->create();
    $this->figureUser->assignRole('faculty');
    $this->actingAs($this->figureUser);
    $this->figureDraft = ProposalDraft::create(['user_id' => $this->figureUser->id,
        'project_title' => 'Coastal diagram fixture', 'project_leader' => $this->figureUser->name]);
    $this->figurePng = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+jB2sAAAAASUVORK5CYII=');
    config(['services.openrouter.key' => null, 'services.gemini.key' => 'test-key',
        'services.gemini.base_url' => 'https://generativelanguage.googleapis.com/v1beta/openai',
        'services.gemini.model' => 'text-model', 'services.gemini.vision_model' => 'vision-model']);
    Http::fake(['*/chat/completions' => Http::response(['choices' => [['message' => [
        'content' => 'Only the visible labels support this description. [Detailed Research Proposal · Figure 2 · Research Design]',
    ], 'finish_reason' => 'stop']]])]);
    $this->saveFigures = function (array $sections): array {
        $images = collect($sections)->map(function (string $section, int $index): array {
            $path = $this->figureDraft->storageDirectory().'/detailed-proposal/methodology-images/figure-'.$index.'.png';
            Storage::disk('local')->put($path, $this->figurePng);

            return ['id' => (string) Str::uuid(), 'section' => $section, 'path' => $path,
                'caption' => 'An unsupported caption claim', 'mime_type' => 'image/png', 'alignment' => 'center', 'size' => 'medium'];
        })->all();
        $this->figureDraft->documents()->updateOrCreate(['document_type' => 'detailed_proposal', 'position' => 0],
            ['source_data' => ['methodology_images' => $images, 'methodology' => ['research_design' => 'Saved narrative is not proof of diagram content.']]]);
        $this->figureDraft->unsetRelation('documents');

        return $images;
    };
    $this->askFigure = fn (string $question, array $extra = []) => $this->postJson(route('research-support.chat'), [
        'messages' => [['role' => 'user', 'content' => $question]],
        ...$extra, 'context' => ['proposal_draft_id' => $this->figureDraft->id, ...($extra['context'] ?? [])],
    ]);
});

test('ordinary saved diagram descriptions send the actual selected figure once to vision with exact references', function (string $question, array $extra) {
    $images = ($this->saveFigures)(['research_design', 'rationale']);
    $response = ($this->askFigure)($question, $extra)->assertSuccessful()->assertJsonMissingPath('proposal_changes.suggestions.0');

    expect(collect($response->json('sources'))->pluck('reference')->all())->toContain('Detailed Research Proposal · Figure 2 · Research Design')
        ->and($response->getContent())->not->toContain('data:image', base64_encode($this->figurePng), $images[0]['path']);
    Http::assertSentCount(1);
    Http::assertSent(function ($request) use ($question, $images): bool {
        $messages = $request['messages'];
        $last = end($messages);
        $prompt = collect($messages)->where('role', 'system')->pluck('content')->join("\n");

        return $request['model'] === 'vision-model'
            && $last['content'] === [['type' => 'text', 'text' => $question],
                ['type' => 'image_url', 'image_url' => ['url' => 'data:image/png;base64,'.base64_encode($this->figurePng)]]]
            && str_contains($prompt, 'Figure 2') && str_contains($prompt, 'Research Design')
            && str_contains($prompt, 'captions are not visual evidence')
            && str_contains($prompt, 'untrusted source data')
            && str_contains($prompt, 'when a detail cannot be read')
            && ! str_contains($prompt, $images[0]['path']);
    });
    Process::assertNothingRan();
})->with([
    ['Describe the diagram in my methodology.', []],
    ['Write a description of my methodology diagram.', []],
    ['Generate a description of my methodology diagram.', []],
    ['Write a caption for Figure 2.', []],
    ['What does my methodology diagram mean?', []],
    ['What is this methodology diagram about?', []],
    ['Help me understand the diagram in my methodology.', []],
    ['Review my proposal diagram in the methodology.', []],
    ['Check my proposal diagram in the methodology for correct labels.', []],
    ['Describe the diagram in my methodology.', ['context' => ['paper_slug' => 'work-plan']]],
    ['Draft a paragraph describing my methodology diagram.', ['action' => ['type' => 'suggest_proposal_changes']]],
    ['Explain my methodology diagram.', ['context' => ['assistance_mode' => 'draft_guidance']]],
]);

test('ambiguous saved methodology figures ask for a figure number without asking a text model to guess', function () {
    ($this->saveFigures)(['rationale', 'research_design', 'specific_methods']);
    $response = ($this->askFigure)('Describe the diagram in my methodology.')->assertSuccessful()
        ->assertJsonPath('model', 'athena-clarification');
    expect($response->json('reply'))->toContain('Figure 2', 'Research Design', 'Figure 3', 'Specific Methods');
    Http::assertNothingSent();
});

test('an explicit figure request uses document order and can compare two saved figures', function () {
    ($this->saveFigures)(['specific_methods', 'research_design', 'rationale']);
    $result = app(ResearchAssistantPaperReader::class)->read($this->figureUser, $this->figureDraft, null, 'Compare Figures 2 and 3 in my methodology.');
    expect($result['images'] ?? [])->toHaveCount(2)
        ->and(array_column($result['sources'], 'reference'))->toBe([
            'Detailed Research Proposal · Figure 2 · Research Design',
            'Detailed Research Proposal · Figure 3 · Specific Methods',
        ]);
});

test('missing corrupt oversized and foreign saved diagram files produce a clear limitation without AI', function (string $failure) {
    $images = ($this->saveFigures)(['research_design']);
    $path = $images[0]['path'];
    if ($failure === 'missing') {
        Storage::disk('local')->delete($path);
    } elseif ($failure === 'corrupt') {
        Storage::disk('local')->put($path, 'not an image');
    } elseif ($failure === 'oversized') {
        Storage::disk('local')->put($path, $this->figurePng.str_repeat('x', 4 * 1024 * 1024));
    } elseif ($failure === 'foreign') {
        $images[0]['path'] = 'proposal-drafts/999/999/detailed-proposal/methodology-images/private.png';
        Storage::disk('local')->put($images[0]['path'], $this->figurePng);
        $this->figureDraft->documents()->first()->update(['source_data' => ['methodology_images' => $images]]);
    }
    $response = ($this->askFigure)('Describe my methodology diagram.')->assertSuccessful()
        ->assertJsonPath('model', 'athena-clarification');
    expect($response->json('reply'))->toContain('Figure 1', 'cannot inspect')
        ->and($response->getContent())->not->toContain($path, $images[0]['path'], 'unsupported caption claim');
    Http::assertNothingSent();
})->with(['missing', 'corrupt', 'oversized', 'foreign']);

test('no saved diagram and invalid figure selections clarify without inventing visual evidence', function (string $question, array $sections) {
    ($this->saveFigures)($sections);
    ($this->askFigure)($question)->assertSuccessful()->assertJsonPath('model', 'athena-clarification');
    Http::assertNothingSent();
})->with([
    ['Describe my methodology diagram.', []],
    ['Describe Figure 9 in my methodology.', ['research_design']],
    ['Describe Figures 1, 2 and 3 in my methodology.', ['research_design', 'specific_methods', 'data_analysis']],
    ['Describe Figure 1.5 in my methodology.', ['research_design']],
]);

test('a saved paper selection carries diagram images through document analysis and saves only public references', function () {
    $images = ($this->saveFigures)(['rationale', 'research_design']);
    $option = collect(app(ResearchAssistantDocumentService::class)->availableDocuments($this->figureUser, null, $this->figureDraft->id))
        ->first(fn (array $option): bool => $option['format'] === 'SAVED' && $option['paper_slug'] === 'detailed-proposal');
    $question = 'Describe Figure 2 in my methodology.';
    $response = ($this->askFigure)($question, ['action' => ['type' => 'analyze_document', 'document_token' => $option['token']]])
        ->assertSuccessful()->assertJsonPath('document_reading.basis', 'saved_draft_projection');
    Http::assertSent(function ($request): bool {
        $messages = $request['messages'];

        return $request['model'] === 'vision-model' && is_array(end($messages)['content']);
    });
    $this->postJson(route('research-support.history.save'), [
        'context' => ['proposal_draft_id' => $this->figureDraft->id],
        'messages' => [['role' => 'user', 'content' => $question],
            ['role' => 'assistant', 'content' => $response->json('reply'), 'sources' => $response->json('sources'), 'document_reading' => $response->json('document_reading')]],
    ])->assertSuccessful();
    $history = ResearchAssistantConversation::query()->where('user_id', $this->figureUser->id)->firstOrFail()->messages;
    expect(json_encode($history, JSON_THROW_ON_ERROR))->toContain('Figure 2', 'Research Design')
        ->not->toContain($images[0]['path'], $images[1]['path'], 'data:image', base64_encode($this->figurePng), $option['token']);
});

test('revoked saved diagram access is rechecked before images leave the application', function () {
    ($this->saveFigures)(['research_design']);
    $other = User::factory()->create();
    $other->assignRole('faculty');
    $membership = $this->figureDraft->members()->create(['user_id' => $other->id, 'name' => $other->name,
        'email' => $other->email, 'accepted_at' => now()]);
    $this->actingAs($other);
    ($this->askFigure)('Describe my methodology diagram.')->assertSuccessful();
    Http::assertSentCount(1);
    $membership->update(['accepted_at' => null]);
    Http::fake();
    ($this->askFigure)('Describe my methodology diagram.')->assertForbidden();
    Http::assertNothingSent();
});

test('selected historical diagram snapshots never read newer proposal or current draft images', function () {
    ($this->saveFigures)(['research_design']);
    $topic = TopicProposal::create(['user_id' => $this->figureUser->id, 'title' => 'Historical diagram fixture', 'status' => 'pending']);
    $file = null;
    foreach ([1, 2] as $number) {
        $version = $topic->versions()->create(['version_number' => $number, 'submitted_by' => $this->figureUser->id,
            'submission_type' => 'revision', 'title' => $topic->title, 'file_path' => 'version-'.$number.'.pdf',
            'original_filename' => 'version.pdf', 'mime_type' => 'application/pdf', 'file_size' => 1]);
        $directory = 'proposal-packages/'.$this->figureUser->id.'/snapshot-'.$number;
        $path = $directory.'/figures/diagram.png';
        Storage::disk('local')->put($path, $this->figurePng.'VERSION_'.$number);
        $snapshot = $version->files()->create(['document_type' => 'detailed_proposal', 'position' => 0,
            'file_path' => $directory.'/detailed-proposal/file.pdf', 'original_filename' => 'file.pdf',
            'source_data' => ['methodology_images' => [['id' => (string) Str::uuid(), 'section' => 'research_design', 'path' => $path]]]]);
        $file ??= $snapshot;
    }
    $result = app(ResearchAssistantPaperReader::class)->readVersionPaper($this->figureUser, $file, 'Describe my methodology diagram.');
    expect($result['images'] ?? [])->toHaveCount(1)
        ->and($result['images'][0]['image_url']['url'])->toBe('data:image/png;base64,'.base64_encode($this->figurePng.'VERSION_1'))
        ->and($result['prompt'])->toContain('selected_submitted_version', '"version_number":1')
        ->not->toContain('VERSION_2');
    expect($file->fresh()->source_data)->toEqual($file->source_data);
});

test('ordinary numerical figures and requests excluding images do not attach saved diagrams', function (string $question) {
    ($this->saveFigures)(['research_design']);
    ($this->askFigure)($question)->assertSuccessful();
    Http::assertSent(function ($request): bool {
        $messages = $request['messages'];

        return $request['model'] === 'text-model' && is_string(end($messages)['content']);
    });
})->with([
    'What budget figures are saved?',
    'Explain the budget figures.',
    'Explain the figures in my budget.',
    'Summarize my methodology text; do not read images.',
    'How do I add an image to my methodology?',
    'Where can I upload my diagram?',
    'Create a diagram for my methodology.',
]);

test('a diagram description followup resends the same saved figure rather than using prior prose as visual evidence', function (string $followup) {
    ($this->saveFigures)(['research_design', 'specific_methods']);
    $this->postJson(route('research-support.chat'), [
        'context' => ['proposal_draft_id' => $this->figureDraft->id, 'paper_slug' => 'detailed-proposal'],
        'messages' => [
            ['role' => 'user', 'content' => 'Explain Figure 2 in my methodology.'],
            ['role' => 'assistant', 'content' => 'The caption suggests a process, but the labels were unclear.'],
            ['role' => 'user', 'content' => $followup],
        ],
    ])->assertSuccessful()->assertJsonMissingPath('proposal_changes.suggestions.0');
    Http::assertSent(function ($request): bool {
        $messages = $request['messages'];
        $prompt = collect($messages)->where('role', 'system')->pluck('content')->join("\n");

        return $request['model'] === 'vision-model' && count(end($messages)['content']) === 2
            && str_contains($prompt, 'Figure 2 · Specific Methods');
    });
})->with(['Write a description of it for this section.', 'Now describe it in more detail.', 'Rewrite it into a methodology paragraph.']);

test('saved paper choices without image originals clarify instead of substituting another paper figure', function () {
    ($this->saveFigures)(['research_design']);
    $this->figureDraft->documents()->create(['document_type' => 'work_plan', 'position' => 0,
        'source_data' => ['entries' => [['activity' => 'Saved table text is not diagram evidence.']]]]);
    $option = collect(app(ResearchAssistantDocumentService::class)->availableDocuments($this->figureUser, null, $this->figureDraft->id))
        ->first(fn (array $option): bool => $option['format'] === 'SAVED' && $option['paper_slug'] === 'work-plan');
    ($this->askFigure)('Describe the diagram in this paper.', ['action' => ['type' => 'analyze_document', 'document_token' => $option['token']]])
        ->assertSuccessful()->assertJsonPath('model', 'athena-clarification');
    Http::assertNothingSent();
});

test('two saved figures respect the aggregate image byte budget without partially describing the selection', function () {
    $images = ($this->saveFigures)(['research_design', 'specific_methods']);
    foreach ($images as $image) {
        Storage::disk('local')->put($image['path'], $this->figurePng.str_repeat('x', 3 * 1024 * 1024 + 1));
    }
    ($this->askFigure)('Compare Figures 1 and 2 in my methodology.')->assertSuccessful()
        ->assertJsonPath('model', 'athena-clarification');
    Http::assertNothingSent();
});

test('a focused diagram question selects its saved section with dotted field keys and DOM IDs', function (string $field) {
    ($this->saveFigures)(['rationale', 'research_design', 'specific_methods']);
    $result = app(ResearchAssistantPaperReader::class)->read($this->figureUser, $this->figureDraft, null, 'Describe this diagram.',
        ['paper_slug' => 'detailed-proposal', 'field' => $field]);
    expect($result['images'] ?? [])->toHaveCount(1)
        ->and(array_column($result['sources'], 'reference'))->toBe(['Detailed Research Proposal · Figure 3 · Specific Methods']);
})->with(['methodology.specific_methods', 'methodology-specific-methods']);

test('an explicit figure number overrides the current field when selecting image evidence', function () {
    ($this->saveFigures)(['research_design', 'specific_methods']);
    $result = app(ResearchAssistantPaperReader::class)->read($this->figureUser, $this->figureDraft, null, 'Describe Figure 2.',
        ['paper_slug' => 'detailed-proposal', 'field' => 'methodology.research_design']);
    expect($result['images'] ?? [])->toHaveCount(1)
        ->and(array_column($result['sources'], 'reference'))->toBe(['Detailed Research Proposal · Figure 2 · Specific Methods']);
});

test('an explicit new text target after a diagram question keeps proposal revision routing', function (string $question) {
    $context = ['proposal_draft_id' => $this->figureDraft->id, 'paper_slug' => 'detailed-proposal'];
    expect(app(ResearchAssistantPaperReader::class)->wantsFigureRead($question, [['role' => 'user', 'content' => 'Describe Figure 2.']]))->toBeFalse()
        ->and(app(ResearchAssistantProposalAssistanceService::class)->wantsSuggestions([], $context, $question))->toBeTrue();
    Http::assertNothingSent();
})->with(['Revise my rationale.', 'Revise this rationale.', 'Revise this introduction.', 'Revise this expected outputs section.']);

test('consecutive short diagram followups retain the original figure number and actual image evidence', function () {
    ($this->saveFigures)(['research_design', 'specific_methods']);
    $response = $this->postJson(route('research-support.chat'), [
        'context' => ['proposal_draft_id' => $this->figureDraft->id, 'paper_slug' => 'detailed-proposal'],
        'messages' => [
            ['role' => 'user', 'content' => 'Describe Figure 2.'],
            ['role' => 'assistant', 'content' => 'Some visible labels are unclear.'],
            ['role' => 'user', 'content' => 'Explain it in more detail.'],
            ['role' => 'assistant', 'content' => 'I can only describe the visible parts.'],
            ['role' => 'user', 'content' => 'Rewrite it into one paragraph.'],
        ],
    ])->assertSuccessful()->assertJsonMissingPath('proposal_changes.suggestions.0');
    expect(array_column($response->json('sources'), 'reference'))->toContain('Detailed Research Proposal · Figure 2 · Specific Methods');
    Http::assertSent(function ($request): bool {
        $messages = $request['messages'];

        return $request['model'] === 'vision-model' && count(end($messages)['content']) === 2;
    });
});

test('a figure-number answer resolves a saved-diagram clarification using image evidence', function (string $answer) {
    ($this->saveFigures)(['research_design', 'specific_methods']);
    $question = 'Describe my methodology diagram.';
    $clarification = ($this->askFigure)($question)->assertSuccessful()->assertJsonPath('model', 'athena-clarification')->json('reply');
    $response = $this->postJson(route('research-support.chat'), [
        'context' => ['proposal_draft_id' => $this->figureDraft->id],
        'messages' => [['role' => 'user', 'content' => $question], ['role' => 'assistant', 'content' => $clarification], ['role' => 'user', 'content' => $answer]],
    ])->assertSuccessful();
    expect(array_column($response->json('sources'), 'reference'))->toContain('Detailed Research Proposal · Figure 2 · Specific Methods');
    Http::assertSent(function ($request): bool {
        $messages = $request['messages'];

        return $request['model'] === 'vision-model' && count(end($messages)['content']) === 2;
    });
})->with(['Figure 2.', '2']);

test('ordinary vague paper diagram questions respect the active paper instead of borrowing detailed proposal images', function (string $question, string $slug) {
    ($this->saveFigures)(['research_design']);
    ($this->askFigure)($question, ['context' => ['paper_slug' => $slug]])->assertSuccessful()->assertJsonPath('model', 'athena-clarification');
    Http::assertNothingSent();
})->with([
    ['Describe the diagram in this paper.', 'work-plan'],
    ['Describe the diagram in my work plan.', 'detailed-proposal'],
]);

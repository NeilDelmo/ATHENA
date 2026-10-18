<?php

use App\Services\ResearchAssistantRrlRevisionService;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

uses(TestCase::class);

test('RRL revision protects grammatical number around masked narrative citations', function () {
    config(['services.openrouter.key' => '', 'services.gemini.key' => 'fixture-key', 'services.gemini.model' => 'fixture-model',
        'services.gemini.base_url' => 'https://generativelanguage.googleapis.com/v1beta/openai']);
    Http::preventStrayRequests();
    Http::fake(['generativelanguage.googleapis.com/*' => function (Request $request) {
        $input = json_decode($request['messages'][1]['content'], true, flags: JSON_THROW_ON_ERROR);

        return Http::response(['choices' => [['message' => ['content' => json_encode(['summary' => 'Preserved the narrative attribution.', 'blocks' => $input['blocks']])], 'finish_reason' => 'stop']]]);
    }]);
    $html = '<p>Vaswani et al. (2017) describe an attention-based model.</p>';

    expect(app(ResearchAssistantRrlRevisionService::class)->revise($html, [], 'Polish the wording.')['value'])->toBe($html);
    Http::assertSent(function (Request $request): bool {
        $system = $request['messages'][0]['content'];
        $input = json_decode($request['messages'][1]['content'], true, flags: JSON_THROW_ON_ERROR);

        return str_contains($system, 'A citation placeholder may hide a full narrative author phrase, including plural authors.')
            && str_contains($system, 'Preserve the existing attribution verb and grammatical number beside it')
            && ! str_contains($input['blocks'][0]['text'], 'Vaswani')
            && preg_match('/^\[\[ATHENA_CITATION_[a-f0-9]{32}\]\] describe an attention-based model\.$/', $input['blocks'][0]['text']) === 1;
    });
    Http::assertSentCount(1);
});

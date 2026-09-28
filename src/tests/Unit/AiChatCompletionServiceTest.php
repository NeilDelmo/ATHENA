<?php

use App\Services\AiChatCompletionService;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

uses(TestCase::class);

test('falls back to direct Gemini when OpenRouter is temporarily unavailable', function () {
    config([
        'services.openrouter.key' => 'openrouter-test-key',
        'services.openrouter.model' => 'google/gemini-3.8-flash',
        'services.openrouter.base_url' => 'https://openrouter.ai/api/v1',
        'services.gemini.key' => 'gemini-test-key',
        'services.gemini.model' => 'gemini-3.5-flash',
        'services.gemini.base_url' => 'https://generativelanguage.googleapis.com/v1beta/openai',
    ]);

    Http::preventStrayRequests();
    Http::fake([
        'openrouter.ai/api/v1/chat/completions' => Http::response(['error' => ['message' => 'Busy']], 503),
        'generativelanguage.googleapis.com/v1beta/openai/chat/completions' => Http::response([
            'model' => 'gemini-3.5-flash',
            'choices' => [[
                'message' => ['content' => 'Fallback response'],
            ]],
        ]),
    ]);

    $response = app(AiChatCompletionService::class)->complete(
        payload: [
            'messages' => [['role' => 'user', 'content' => 'Hello']],
            'stream' => false,
        ],
        usesVision: false,
        connectTimeout: 5,
        timeout: 10,
    );

    expect($response->json('choices.0.message.content'))->toBe('Fallback response');

    Http::assertSent(fn (Request $request): bool => $request->url() === 'https://openrouter.ai/api/v1/chat/completions'
        && $request['model'] === 'google/gemini-3.8-flash');
    Http::assertSent(fn (Request $request): bool => $request->url() === 'https://generativelanguage.googleapis.com/v1beta/openai/chat/completions'
        && $request['model'] === 'gemini-3.5-flash');
});

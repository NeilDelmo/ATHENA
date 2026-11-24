<?php

use App\Services\AiChatCompletionService;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

uses(TestCase::class);

beforeEach(function () {
    config([
        'services.ai.provider' => 'openrouter',
        'services.openrouter.key' => 'openrouter-test-key',
        'services.openrouter.model' => 'openrouter-model',
        'services.openrouter.vision_model' => 'openrouter-vision-model',
        'services.openrouter.base_url' => 'https://openrouter.ai/api/v1',
        'services.gemini.key' => 'gemini-test-key',
        'services.gemini.model' => 'gemini-model',
        'services.gemini.vision_model' => 'gemini-vision-model',
        'services.gemini.base_url' => 'https://generativelanguage.googleapis.com/v1beta/openai',
    ]);
    Http::preventStrayRequests();
});

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

test('uses the selected AI provider for text and vision requests', function (string $provider, bool $vision, string $host) {
    config(['services.ai.provider' => $provider]);
    Http::fake(['*/chat/completions' => Http::response(['choices' => [['message' => ['content' => 'Reply']]]])]);

    app(AiChatCompletionService::class)->complete(['messages' => [['role' => 'user', 'content' => 'Hello']]], $vision, 1, 1);

    Http::assertSentCount(1);
    Http::assertSent(fn (Request $request): bool => parse_url($request->url(), PHP_URL_HOST) === $host
        && $request['model'] === $provider.($vision ? '-vision-model' : '-model'));
})->with([
    ['openrouter', false, 'openrouter.ai'],
    ['gemini', false, 'generativelanguage.googleapis.com'],
    ['gemini', true, 'generativelanguage.googleapis.com'],
]);

test('uses the other configured provider when the preferred provider has no key', function () {
    config(['services.ai.provider' => 'gemini', 'services.gemini.key' => null]);
    Http::fake(['*/chat/completions' => Http::response(['choices' => [['message' => ['content' => 'Reply']]]])]);

    app(AiChatCompletionService::class)->complete(['messages' => []], false, 1, 1);

    Http::assertSentCount(1);
    Http::assertSent(fn (Request $request): bool => parse_url($request->url(), PHP_URL_HOST) === 'openrouter.ai');
});

test('falls back to the other provider when the selected provider rejects the spending budget', function (string $provider, string $firstHost, string $secondHost) {
    config(['services.ai.provider' => $provider]);
    Http::fake([
        $firstHost.'/*' => Http::response(['error' => ['code' => 402, 'message' => 'Insufficient credits']], 402),
        $secondHost.'/*' => Http::response(['choices' => [['message' => ['content' => 'Fallback reply']]]]),
    ]);

    $response = app(AiChatCompletionService::class)->complete(['messages' => []], false, 1, 1);

    expect($response->json('choices.0.message.content'))->toBe('Fallback reply');
    Http::assertSentCount(2);
})->with([
    ['openrouter', 'openrouter.ai', 'generativelanguage.googleapis.com'],
    ['gemini', 'generativelanguage.googleapis.com', 'openrouter.ai'],
]);

test('does not send an invalid request to a second provider', function () {
    Http::fake(['*/chat/completions' => Http::response(['error' => ['message' => 'Invalid payload']], 400)]);

    $response = app(AiChatCompletionService::class)->complete(['messages' => []], false, 1, 1);

    expect($response->status())->toBe(400);
    Http::assertSentCount(1);
});

test('explains credit failures without exposing the provider error body', function (string $source, string $expected) {
    config(['services.gemini.key' => null]);
    Http::fake(['*/chat/completions' => Http::response([
        'error' => ['message' => 'PRIVATE_PROVIDER_DETAILS', 'metadata' => ['limit_source' => $source]],
    ], 402)]);

    $service = app(AiChatCompletionService::class);
    $response = $service->complete(['messages' => []], false, 1, 1);

    expect($service->failureMessage($response, 'Generic error'))->toContain($expected)
        ->not->toContain('PRIVATE_PROVIDER_DETAILS');
})->with([
    ['openrouter_credits', 'insufficient credits'],
    ['openrouter_key_limit', 'spending limit'],
    ['openrouter_in_flight_budget', 'other requests'],
]);

<?php

namespace App\Http\Controllers;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class ResearchAssistantController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'messages' => ['required', 'array', 'min:1', 'max:8'],
            'messages.*.role' => ['required', 'string', 'in:user,assistant'],
            'messages.*.content' => ['required', 'string', 'max:2000'],
        ]);

        $messages = collect($validated['messages'])
            ->map(fn (array $message) => [
                'role' => $message['role'],
                'content' => trim($message['content']),
            ])
            ->filter(fn (array $message) => $message['content'] !== '')
            ->values();

        if ($messages->isEmpty() || $messages->last()['role'] !== 'user') {
            throw ValidationException::withMessages([
                'messages' => 'The conversation must end with a user message.',
            ]);
        }

        $apiKey = (string) config('services.groq.key');
        $model = (string) config('services.groq.model');
        $baseUrl = (string) config('services.groq.base_url');

        if ($apiKey === '' || $model === '' || $baseUrl === '') {
            return response()->json([
                'message' => 'The research assistant is not configured yet.',
            ], 503);
        }

        try {
            $response = Http::baseUrl($baseUrl)
                ->withToken($apiKey)
                ->acceptJson()
                ->asJson()
                ->connectTimeout(10)
                ->timeout(45)
                ->post('chat/completions', [
                    'model' => $model,
                    'messages' => [
                        [
                            'role' => 'system',
                            'content' => $this->systemPrompt(),
                        ],
                        ...$messages->all(),
                    ],
                    'temperature' => 0.25,
                    'max_completion_tokens' => 700,
                    'stream' => false,
                ]);
        } catch (ConnectionException $exception) {
            Log::warning('Groq research assistant connection failed.', [
                'exception' => $exception::class,
            ]);

            return response()->json([
                'message' => 'The research assistant could not be reached. Please try again.',
            ], 503);
        }

        if ($response->status() === 429) {
            return response()->json([
                'message' => 'The assistant is receiving too many requests. Please wait a moment and retry.',
                'retry_after' => max(1, (int) $response->header('retry-after', 10)),
            ], 429);
        }

        if ($response->failed()) {
            Log::warning('Groq research assistant request failed.', [
                'status' => $response->status(),
                'model' => $model,
            ]);

            return response()->json([
                'message' => match ($response->status()) {
                    401, 403 => 'The research assistant credentials need attention.',
                    404 => 'The configured research model is unavailable.',
                    default => 'The research assistant could not answer right now. Please try again.',
                },
            ], 502);
        }

        $reply = trim((string) $response->json('choices.0.message.content'));

        if ($reply === '') {
            return response()->json([
                'message' => 'The assistant returned an empty response. Please try rephrasing your question.',
            ], 502);
        }

        return response()->json([
            'reply' => $reply,
            'model' => $model,
            'usage' => [
                'prompt_tokens' => $response->json('usage.prompt_tokens'),
                'completion_tokens' => $response->json('usage.completion_tokens'),
            ],
        ]);
    }

    private function systemPrompt(): string
    {
        return <<<'PROMPT'
You are Athena, a concise and supportive research assistant for university faculty researchers.

Help with research questions, objectives, methodology, proposal organization, academic writing, and general research planning. Ask a focused clarifying question when essential information is missing. Prefer practical steps, short examples, and clear headings when useful.

Important boundaries:
- Do not claim to have read uploaded papers, Athena records, university policies, or private data unless their contents are explicitly included in the conversation.
- Do not invent citations, sources, institutional rules, statistics, or research findings.
- Clearly label uncertainty and recommend verification with an adviser, ethics board, statistician, or official university material when appropriate.
- Do not make proposal approval, ethics, authorship, or grading decisions.
- Protect personal and confidential research information; encourage anonymization when sensitive data appears.
- Keep ordinary answers under 350 words unless the user explicitly asks for more detail.
PROMPT;
    }
}

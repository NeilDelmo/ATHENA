<?php

namespace App\Services;

use App\Models\ResearchManual;
use Illuminate\Http\Client\ConnectionException;
use RuntimeException;
use Throwable;

class ResearchManualGuidanceCheck
{
    public function __construct(
        private ResearchManualLibrary $library,
        private ResearchManualRetriever $retriever,
        private ResearchKnowledgeService $knowledge,
        private AiChatCompletionService $ai,
    ) {}

    /** @return array{status: int, payload: array<string, mixed>} */
    public function answer(ResearchManual $manual, string $question, int $timeout = 45): array
    {
        try {
            $index = $this->library->index($manual);
        } catch (RuntimeException) {
            return ['status' => 503, 'payload' => ['message' => 'This edition is unavailable. Restore its PDF and index before checking guidance.']];
        }

        $sources = $this->retriever->retrieve($question, indexPath: $manual->indexPath());
        $chunks = array_column($index['chunks'], null, 'id');
        foreach ($sources as $source) {
            $chunk = $chunks[$source['chunk_id'] ?? ''] ?? null;
            if ($chunk === null || ($source['document_version'] ?? null) !== $manual->version
                || ($source['page_start'] ?? null) !== $chunk['page_start'] || ($source['page_end'] ?? null) !== $chunk['page_end']
                || ($source['content'] ?? null) !== $chunk['content'] || ($source['section'] ?? null) !== $chunk['section']
                || ($source['url'] ?? null) !== route('research-manuals.read', $manual->version).'#page='.$chunk['page_start']) {
                return ['status' => 503, 'payload' => ['message' => 'Retrieved references do not match this edition and its indexed pages.', 'check_type' => 'invalid_evidence']];
            }
        }
        $sources = array_map(fn (array $source, int $position): array => [
            ...$source, 'title' => $source['title'].' · Edition '.$manual->edition, 'reference' => 'ATHENA '.($position + 1),
        ], $sources, array_keys($sources));

        $context = null;
        while ($sources !== []) {
            $context = $this->knowledge->promptContext($sources, $question);
            if ($context !== null && str_contains($context, 'BEGIN REFERENCE DATA')) {
                break;
            }
            array_pop($sources);
        }

        if ($sources === []) {
            return ['status' => 200, 'payload' => [
                'reply' => 'I could not find enough matching evidence in this edition to verify that guidance. Review the PDF or ask the research office; some pages may not have indexed text.',
                'sources' => [], 'edition' => $manual->edition, 'version' => $manual->version, 'check_type' => 'no_evidence',
            ]];
        }

        if (! $this->ai->isConfigured()) {
            return ['status' => 503, 'payload' => ['message' => 'Guidance checking is unavailable right now. Please try again later.', 'sources' => $sources]];
        }

        try {
            $response = $this->ai->complete([
                'messages' => [
                    ['role' => 'system', 'content' => 'You are checking guidance for the selected research manual edition '.$manual->edition.'. Use only the supplied excerpts from this edition. Do not use another edition or outside knowledge. If evidence is insufficient, say so. Cite supported claims with the supplied [ATHENA N] references.'],
                    ['role' => 'system', 'content' => $context],
                    ['role' => 'user', 'content' => $question],
                ],
                'temperature' => 0.25, 'max_completion_tokens' => 1400, 'stream' => false,
            ], usesVision: false, connectTimeout: min(10, $timeout), timeout: $timeout);
        } catch (ConnectionException) {
            return ['status' => 503, 'payload' => ['message' => 'Guidance checking could not be reached. Please try again.', 'sources' => $sources]];
        } catch (Throwable $exception) {
            report($exception);

            return ['status' => 503, 'payload' => ['message' => 'Guidance checking is unavailable right now. Please try again.', 'sources' => $sources]];
        }

        $reply = $response->json('choices.0.message.content');
        if ($response->failed() || ! is_string($reply) || trim($reply) === '') {
            return ['status' => 502, 'payload' => ['message' => 'Guidance checking could not answer right now. Please try again.', 'sources' => $sources]];
        }

        return ['status' => 200, 'payload' => [
            'reply' => trim($reply), 'sources' => $sources, 'edition' => $manual->edition,
            'version' => $manual->version, 'check_type' => 'ai_response',
        ]];
    }
}

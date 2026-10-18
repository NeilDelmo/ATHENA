<?php

namespace App\Http\Controllers;

use App\Http\Requests\CheckResearchManualGuidanceRequest;
use App\Models\ResearchManual;
use App\Models\User;
use App\Services\AiChatCompletionService;
use App\Services\ResearchKnowledgeService;
use App\Services\ResearchManualLibrary;
use App\Services\ResearchManualRetriever;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;
use RuntimeException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Throwable;

class ResearchManualReaderController extends Controller
{
    public function __construct(
        private ResearchManualLibrary $library,
        private ResearchManualRetriever $retriever,
        private ResearchKnowledgeService $knowledge,
        private AiChatCompletionService $ai,
    ) {}

    public function show(Request $request, string $version): View
    {
        $resolved = $this->readableManual($request, $version);

        return view('research-manuals.reader', [
            'manualReader' => $this->library->readerConfig($resolved['index'], $resolved['manual'], $this->isManager($request)),
        ]);
    }

    public function file(Request $request, string $version): BinaryFileResponse|Response
    {
        $resolved = $this->readableManual($request, $version);
        $headers = ['Content-Type' => 'application/pdf', 'X-Content-Type-Options' => 'nosniff', 'Cache-Control' => 'private, no-store'];
        $filename = 'research-manual-'.$version.'.pdf';

        if (isset($resolved['pdf_content'])) {
            return response($resolved['pdf_content'], 200, [
                ...$headers,
                'Content-Disposition' => ($request->boolean('download') ? 'attachment' : 'inline').'; filename="'.$filename.'"',
            ])->setPrivate();
        }

        $response = $request->boolean('download')
            ? response()->download($resolved['pdf_path'], $filename, $headers)
            : response()->file($resolved['pdf_path'], $headers)->setContentDisposition('inline', $filename);

        return $response->setPrivate();
    }

    public function check(CheckResearchManualGuidanceRequest $request, ResearchManual $researchManual): JsonResponse
    {
        try {
            $this->library->index($researchManual);
        } catch (RuntimeException) {
            return response()->json(['message' => 'This edition is unavailable. Restore its PDF and index before checking guidance.'], 503);
        }

        $question = $request->validated('question');
        $sources = collect($this->retriever->retrieve($question, indexPath: $researchManual->indexPath()))->map(function (array $source, int $index) use ($researchManual): array {
            return [...$source, 'title' => $source['title'].' · Edition '.$researchManual->edition, 'reference' => 'ATHENA '.($index + 1)];
        })->all();

        $context = null;
        while ($sources !== []) {
            $context = $this->knowledge->promptContext($sources, $question);
            if ($context !== null && str_contains($context, 'BEGIN REFERENCE DATA')) {
                break;
            }
            array_pop($sources);
        }

        if ($sources === []) {
            return response()->json([
                'reply' => 'I could not find enough matching evidence in this edition to verify that guidance. Review the PDF or ask the research office; some pages may not have indexed text.',
                'sources' => [], 'edition' => $researchManual->edition,
            ]);
        }

        if (! $this->ai->isConfigured()) {
            return response()->json(['message' => 'Guidance checking is unavailable right now. Please try again later.'], 503);
        }

        try {
            $response = $this->ai->complete([
                'messages' => [
                    ['role' => 'system', 'content' => 'You are checking guidance for the selected research manual edition '.$researchManual->edition.'. Use only the supplied excerpts from this edition. Do not use another edition or outside knowledge. If evidence is insufficient, say so. Cite supported claims with the supplied [ATHENA N] references.'],
                    ['role' => 'system', 'content' => $context],
                    ['role' => 'user', 'content' => $question],
                ],
                'temperature' => 0.25, 'max_completion_tokens' => 1400, 'stream' => false,
            ], usesVision: false, connectTimeout: 10, timeout: 45);
        } catch (ConnectionException) {
            return response()->json(['message' => 'Guidance checking could not be reached. Please try again.'], 503);
        } catch (Throwable $exception) {
            report($exception);

            return response()->json(['message' => 'Guidance checking is unavailable right now. Please try again.'], 503);
        }

        $reply = $response->json('choices.0.message.content');
        if ($response->failed() || ! is_string($reply) || trim($reply) === '') {
            return response()->json(['message' => 'Guidance checking could not answer right now. Please try again.'], 502);
        }

        return response()->json(['reply' => trim($reply), 'sources' => $sources, 'edition' => $researchManual->edition]);
    }

    /** @return array{manual: ?ResearchManual, index: array<string, mixed>, pdf_path: string, pdf_content?: string} */
    private function readableManual(Request $request, string $version): array
    {
        abort_unless($request->user() !== null, 403);

        try {
            $resolved = $this->library->resolveVersion($version);
        } catch (RuntimeException) {
            abort(503, 'This research manual is unavailable. Please contact the research office or try again later.');
        }

        abort_if($resolved === null, 404, 'This research manual edition could not be found.');
        abort_unless($resolved['manual'] === null || $resolved['manual']->activated_at !== null || $this->isManager($request), 403);

        return $resolved;
    }

    private function isManager(Request $request): bool
    {
        return $request->user()?->hasRole('research_head')
            && $request->user()->isUsingWorkspace(User::WORKSPACE_RESEARCH_HEAD);
    }
}

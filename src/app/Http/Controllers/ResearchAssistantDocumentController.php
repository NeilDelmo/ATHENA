<?php

namespace App\Http\Controllers;

use App\Exceptions\ResearchAssistantDocumentException;
use App\Http\Requests\ListResearchAssistantDocumentsRequest;
use App\Services\ResearchAssistantDocumentService;
use Illuminate\Http\JsonResponse;

class ResearchAssistantDocumentController extends Controller
{
    public function __invoke(
        ListResearchAssistantDocumentsRequest $request,
        ResearchAssistantDocumentService $documents,
    ): JsonResponse {
        try {
            return response()->json([
                'documents' => $documents->availableDocuments(
                    $request->user(),
                    $request->integer('topic_id') ?: null,
                    $request->integer('proposal_draft_id') ?: null,
                ),
            ]);
        } catch (ResearchAssistantDocumentException $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
            ], $exception->status);
        }
    }
}

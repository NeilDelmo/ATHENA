<?php

namespace App\Http\Controllers;

use App\Exceptions\LiteratureFullTextPreviewException;
use App\Http\Requests\PreviewLiteratureFullTextRequest;
use App\Services\LiteratureFullTextPreviewService;
use Illuminate\Http\JsonResponse;

class LiteratureFullTextPreviewController extends Controller
{
    public function __invoke(
        PreviewLiteratureFullTextRequest $request,
        LiteratureFullTextPreviewService $previewService,
    ): JsonResponse {
        try {
            return response()->json($previewService->preview($request->validated('source_token')));
        } catch (LiteratureFullTextPreviewException $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
                'failure_reason' => $exception->getMessage(),
                'evidence_basis' => 'abstract',
            ], $exception->status);
        }
    }
}

<?php

namespace App\Http\Controllers;

use App\Contracts\DocumentPdfConverter;
use App\Http\Requests\PreviewRevisionPaperRequest;
use App\Models\TopicProposal;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use RuntimeException;

class RevisionPaperPreviewController extends Controller
{
    public function __invoke(PreviewRevisionPaperRequest $request, TopicProposal $topic, DocumentPdfConverter $converter): Response|JsonResponse
    {
        try {
            $contents = $converter->convertDocx($request->file('paper')->getContent());
        } catch (RuntimeException $exception) {
            report($exception);

            return response()->json(['message' => 'The Word preview could not be created. Try a PDF replacement, or return to your revision.'], 503);
        }

        return response($contents, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="revision-preview.pdf"',
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}

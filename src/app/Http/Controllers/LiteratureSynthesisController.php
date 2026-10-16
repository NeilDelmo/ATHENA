<?php

namespace App\Http\Controllers;

use App\Exceptions\LiteratureSynthesisException;
use App\Http\Requests\SynthesizeLiteratureRequest;
use App\Models\ProposalDraft;
use App\Services\LiteratureSynthesisEvidenceResolver;
use App\Services\LiteratureSynthesisService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class LiteratureSynthesisController extends Controller
{
    public function __invoke(
        SynthesizeLiteratureRequest $request,
        LiteratureSynthesisService $literatureSynthesis,
        LiteratureSynthesisEvidenceResolver $evidenceResolver,
    ): JsonResponse {
        $paper = $request->validated();
        $linked = null;
        if (isset($paper['source_link_id'])) {
            $draft = ProposalDraft::query()->accessibleTo($request->user())->findOrFail($paper['proposal_draft_id']);
            Gate::authorize('update', $draft);
            $linked = $draft->literatureSources()->findOrFail($paper['source_link_id']);
            $source = $linked->toLibraryArray();
            $paper = [...$paper, 'title' => $source['title'], 'authors' => $source['authors'], 'year' => $source['year'],
                'abstract' => $source['description'], 'full_text_token' => $source['full_text_token'], 'proposal_title' => $draft->project_title];
        } else {
            $source = $paper;
        }
        $evidence = $evidenceResolver->resolve($source, $paper['evidence_basis'], $linked);
        try {
            $payload = $literatureSynthesis->synthesize([...$paper, ...$evidence]);
        } catch (LiteratureSynthesisException $exception) {
            return response()->json(['message' => $exception->getMessage()], $exception->status);
        }

        return response()->json([...$payload, 'notice' => $evidence['notice'], 'coverage' => $evidence['coverage']]);
    }
}

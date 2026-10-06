<?php

namespace App\Http\Controllers;

use App\Exceptions\LiteratureSynthesisException;
use App\Http\Requests\AssistProposalLiteratureEvidenceRequest;
use App\Http\Requests\StoreProposalLiteraturePassageRequest;
use App\Http\Requests\UploadProposalLiteratureDocumentRequest;
use App\Models\ProposalDraft;
use App\Models\ProposalDraftLiteratureSource;
use App\Services\ProposalLiteratureEvidenceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ProposalLiteratureEvidenceController extends Controller
{
    public function __construct(private ProposalLiteratureEvidenceService $evidence) {}

    public function show(ProposalDraft $proposalDraft, ProposalDraftLiteratureSource $proposalDraftLiteratureSource): JsonResponse
    {
        $this->authorizeSource('view', $proposalDraft, $proposalDraftLiteratureSource);

        return response()->json($this->evidence->payload($proposalDraftLiteratureSource));
    }

    public function storeDocument(UploadProposalLiteratureDocumentRequest $request, ProposalDraft $proposalDraft, ProposalDraftLiteratureSource $proposalDraftLiteratureSource): JsonResponse
    {
        return response()->json($this->evidence->upload($proposalDraft, $proposalDraftLiteratureSource, $request->file('file'), $request->user()), 201);
    }

    public function document(Request $request, ProposalDraft $proposalDraft, ProposalDraftLiteratureSource $proposalDraftLiteratureSource): BinaryFileResponse
    {
        $this->authorizeSource('view', $proposalDraft, $proposalDraftLiteratureSource);
        $response = response()->file($this->evidence->privateDocumentPath($proposalDraftLiteratureSource), [
            'Content-Type' => 'application/pdf', 'Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff',
        ]);
        $response->setPrivate();
        $response->setContentDisposition($request->boolean('download') ? 'attachment' : 'inline', $proposalDraftLiteratureSource->evidence_document['name'] ?? 'source.pdf', 'source.pdf');

        return $response;
    }

    public function storePassage(StoreProposalLiteraturePassageRequest $request, ProposalDraft $proposalDraft, ProposalDraftLiteratureSource $proposalDraftLiteratureSource): JsonResponse
    {
        return response()->json($this->evidence->savePassage($proposalDraft, $proposalDraftLiteratureSource, $request->validated(), $request->user()), 201);
    }

    public function destroyPassage(ProposalDraft $proposalDraft, ProposalDraftLiteratureSource $proposalDraftLiteratureSource, string $passageId): JsonResponse
    {
        $this->authorizeSource('update', $proposalDraft, $proposalDraftLiteratureSource);

        return response()->json($this->evidence->deletePassage($proposalDraft, $proposalDraftLiteratureSource, $passageId));
    }

    public function assist(AssistProposalLiteratureEvidenceRequest $request, ProposalDraft $proposalDraft): JsonResponse
    {
        try {
            return response()->json($this->evidence->assist($proposalDraft, $request->validated()));
        } catch (LiteratureSynthesisException $exception) {
            return response()->json(['message' => $exception->getMessage()], $exception->status);
        }
    }

    private function authorizeSource(string $ability, ProposalDraft $draft, ProposalDraftLiteratureSource $source): void
    {
        Gate::authorize($ability, $draft);
        abort_unless($source->proposal_draft_id === $draft->getKey(), 404);
    }
}

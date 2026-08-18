<?php

namespace App\Http\Controllers;

use App\Actions\LinkLiteratureSourceToProposal;
use App\Http\Requests\AttachLiteratureSourceToProposalRequest;
use App\Http\Requests\UpdateProposalLiteratureDraftRequest;
use App\Models\LiteratureSource;
use App\Models\ProposalDraft;
use App\Models\ProposalDraftLiteratureSource;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

class ProposalDraftLiteratureSourceController extends Controller
{
    public function store(
        AttachLiteratureSourceToProposalRequest $request,
        ProposalDraft $proposalDraft,
        LiteratureSource $literatureSource,
        LinkLiteratureSourceToProposal $linkLiteratureSource,
    ): JsonResponse {
        $result = $linkLiteratureSource->handle(
            $proposalDraft,
            $literatureSource,
            $request->user(),
            $request->validated('rrl_note'),
            $request->validated('rrl_evidence_basis'),
            is_array($request->validated('research_context')) ? $request->validated('research_context') : [],
        );

        return response()->json([
            'message' => $result['already_linked']
                ? 'This shared paper is already linked to the selected proposal.'
                : 'Shared paper linked to the selected proposal.',
            'already_linked' => $result['already_linked'],
            'source' => $result['link']->toLibraryArray(),
        ], $result['already_linked'] ? 200 : 201);
    }

    public function updateDraft(
        UpdateProposalLiteratureDraftRequest $request,
        ProposalDraft $proposalDraft,
        ProposalDraftLiteratureSource $proposalDraftLiteratureSource,
    ): JsonResponse {
        $validated = $request->validated();
        $proposalDraftLiteratureSource->update([
            'rrl_note' => trim($validated['rrl_note']),
            'rrl_draft_status' => $validated['rrl_draft_status'],
            'rrl_evidence_basis' => $validated['rrl_evidence_basis'],
            'rrl_word_count' => Str::wordCount($validated['rrl_note']),
            'rrl_generated_at' => now(),
        ]);

        return response()->json([
            'message' => $validated['rrl_draft_status'] === ProposalDraftLiteratureSource::DRAFT_CONFIRMED
                ? 'RRL paragraph confirmed and ready to insert into Section XI.'
                : 'RRL draft saved to this proposal.',
            'source' => $this->sourcePayload($proposalDraftLiteratureSource->fresh()),
        ]);
    }

    public function discardDraft(
        ProposalDraft $proposalDraft,
        ProposalDraftLiteratureSource $proposalDraftLiteratureSource,
    ): JsonResponse {
        Gate::authorize('update', $proposalDraft);
        abort_unless($proposalDraftLiteratureSource->proposal_draft_id === $proposalDraft->getKey(), 404);

        $proposalDraftLiteratureSource->update([
            'rrl_note' => null,
            'rrl_draft_status' => ProposalDraftLiteratureSource::DRAFT_NONE,
            'rrl_evidence_basis' => null,
            'rrl_word_count' => null,
            'rrl_generated_at' => null,
        ]);

        return response()->json([
            'message' => 'The saved RRL draft was discarded. The literature source remains linked to the proposal.',
            'source' => $this->sourcePayload($proposalDraftLiteratureSource->fresh()),
        ]);
    }

    /** @return array<string, mixed> */
    private function sourcePayload(
        ProposalDraftLiteratureSource $literatureSource,
    ): array {
        $literatureSource->load(['literatureSource.collections:id,name,slug', 'literatureSource.addedBy:id,name']);

        return $literatureSource->toLibraryArray();
    }
}

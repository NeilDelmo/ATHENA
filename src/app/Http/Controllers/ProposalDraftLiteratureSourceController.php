<?php

namespace App\Http\Controllers;

use App\Actions\LinkLiteratureSourceToProposal;
use App\Http\Requests\AttachLiteratureSourceToProposalRequest;
use App\Models\LiteratureSource;
use App\Models\ProposalDraft;
use Illuminate\Http\JsonResponse;

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
        );

        return response()->json([
            'message' => $result['already_linked']
                ? 'This shared paper is already linked to the selected proposal.'
                : 'Shared paper linked to the selected proposal.',
            'already_linked' => $result['already_linked'],
            'source' => $result['link']->toLibraryArray(),
        ], $result['already_linked'] ? 200 : 201);
    }
}

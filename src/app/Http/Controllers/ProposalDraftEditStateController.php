<?php

namespace App\Http\Controllers;

use App\Models\ProposalDraft;
use App\Support\ProposalPaperCatalog;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class ProposalDraftEditStateController extends Controller
{
    public function __invoke(
        ProposalDraft $proposalDraft,
        string $scope,
        int $position,
        ProposalPaperCatalog $catalog,
    ): JsonResponse {
        Gate::authorize('update', $proposalDraft);

        if ($scope === 'details') {
            $proposalDraft->refresh();

            return response()->json([
                'version' => $proposalDraft->lock_version,
                'updated_at' => $proposalDraft->updated_at?->toIso8601String(),
                'updated_by' => null,
            ]);
        }

        $paper = $catalog->forDocumentType($scope);
        abort_unless(is_array($paper) && $paper['mode'] !== 'automatic', 404);

        $document = $proposalDraft->documents()
            ->where('document_type', $scope)
            ->where('position', $position)
            ->select(['id', 'proposal_draft_id', 'lock_version', 'updated_at'])
            ->first();
        $latestVersion = $proposalDraft->documentVersions()
            ->reorder()
            ->where('document_type', $scope)
            ->where('position', $position)
            ->with('creator:id,name')
            ->latest('version_number')
            ->first();

        return response()->json([
            'version' => max(
                $document?->lock_version ?? 0,
                $latestVersion?->version_number ?? 0,
            ),
            'is_removed' => $document === null && $latestVersion?->action === 'removed',
            'updated_at' => $latestVersion?->created_at?->toIso8601String()
                ?? $document?->updated_at?->toIso8601String(),
            'updated_by' => $latestVersion?->creator?->name,
        ]);
    }
}

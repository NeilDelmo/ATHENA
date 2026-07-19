<?php

namespace App\Http\Controllers;

use App\Models\ProposalDraft;
use App\Models\ProposalDraftDocumentVersion;
use App\Support\ProposalPaperCatalog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ProposalDraftDocumentVersionController extends Controller
{
    public function index(
        Request $request,
        ProposalDraft $proposalDraft,
        ProposalPaperCatalog $catalog,
    ): View {
        Gate::authorize('view', $proposalDraft);

        $selectedPaper = null;
        $paperSlug = $request->string('paper')->toString();

        if (filled($paperSlug)) {
            $selectedPaper = $catalog->find($paperSlug);
            abort_unless(is_array($selectedPaper), 404);
        }

        $versions = ProposalDraftDocumentVersion::query()
            ->whereBelongsTo($proposalDraft, 'draft')
            ->when(
                $selectedPaper,
                fn ($query) => $query->where('document_type', $selectedPaper['document_type']),
            )
            ->with([
                'creator:id,name',
                'document:id,lock_version',
            ])
            ->latest('id')
            ->paginate(20)
            ->withQueryString();

        return view('faculty.proposal-drafts.history', [
            'proposalDraft' => $proposalDraft,
            'versions' => $versions,
            'papers' => $catalog->all(),
            'selectedPaper' => $selectedPaper,
        ]);
    }

    public function download(
        ProposalDraft $proposalDraft,
        int $documentVersion,
    ): StreamedResponse {
        Gate::authorize('download', $proposalDraft);

        $version = ProposalDraftDocumentVersion::query()
            ->whereBelongsTo($proposalDraft, 'draft')
            ->findOrFail($documentVersion);

        abort_unless(
            filled($version->file_path)
                && str($version->file_path)->startsWith($proposalDraft->storageDirectory().'/')
                && Storage::disk('local')->exists($version->file_path),
            404,
        );

        return Storage::disk('local')->download(
            $version->file_path,
            $version->original_filename,
            ['Content-Type' => $version->mime_type],
        );
    }
}

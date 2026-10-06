<?php

namespace App\Http\Controllers;

use App\Actions\RestoreProposalDraftDocumentVersion;
use App\Http\Requests\RestoreProposalDraftDocumentVersionRequest;
use App\Models\ProposalDraft;
use App\Models\ProposalDraftDocumentVersion;
use App\Models\TopicProposal;
use App\Support\ProposalPaperCatalog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ProposalDraftDocumentVersionController extends Controller
{
    public function index(ProposalDraft $proposalDraft): RedirectResponse
    {
        Gate::authorize('view', $proposalDraft);

        return redirect(route('faculty.proposal-drafts.show', $proposalDraft).'#required-pdf-attachments');
    }

    public function restore(
        RestoreProposalDraftDocumentVersionRequest $request,
        ProposalDraft $proposalDraft,
        int $documentVersion,
        RestoreProposalDraftDocumentVersion $restoreVersion,
    ): RedirectResponse {
        $version = ProposalDraftDocumentVersion::query()
            ->whereBelongsTo($proposalDraft, 'draft')
            ->findOrFail($documentVersion);
        $result = $restoreVersion->handle(
            $proposalDraft,
            $version,
            $request->user(),
            $request->integer('document_version'),
            $request->string('change_note')->toString(),
        );

        return redirect(route('faculty.proposal-drafts.show', $proposalDraft).'#required-pdf-attachments')
            ->with(
                $result['version_created'] ? 'success' : 'warning',
                $result['version_created']
                    ? 'The earlier paper version was restored. Your previous working draft was preserved.'
                    : 'That paper version already matches the current working draft, so nothing needed to be restored.',
            );
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

    public function archived(
        Request $request,
        TopicProposal $topic,
        ProposalPaperCatalog $catalog,
    ): View {
        Gate::authorize('view', $topic);

        $selectedPaper = null;
        $paperSlug = $request->string('paper')->toString();

        if (filled($paperSlug)) {
            $selectedPaper = $catalog->find($paperSlug);
            abort_unless(is_array($selectedPaper), 404);
        }

        $versions = ProposalDraftDocumentVersion::query()
            ->whereBelongsTo($topic, 'topic')
            ->when(
                $selectedPaper,
                fn ($query) => $query->where('document_type', $selectedPaper['document_type']),
            )
            ->with([
                'creator:id,name',
                'restoredFrom:id,version_number',
            ])
            ->latest('id')
            ->paginate(20)
            ->withQueryString();

        return view('topics.draft-history', [
            'topic' => $topic,
            'versions' => $versions,
            'papers' => $catalog->all(),
            'selectedPaper' => $selectedPaper,
        ]);
    }

    public function downloadArchived(
        TopicProposal $topic,
        int $documentVersion,
    ): StreamedResponse {
        Gate::authorize('view', $topic);

        $version = ProposalDraftDocumentVersion::query()
            ->whereBelongsTo($topic, 'topic')
            ->findOrFail($documentVersion);

        abort_unless(
            filled($version->file_path)
                && Str::startsWith($version->file_path, 'proposal-packages/'.$topic->user_id.'/')
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

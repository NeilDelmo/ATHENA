<?php

namespace App\Http\Controllers;

use App\Actions\RestoreProposalDraftDocumentVersion;
use App\Http\Requests\RestoreProposalDraftDocumentVersionRequest;
use App\Models\ProposalDraft;
use App\Models\ProposalDraftDocument;
use App\Models\ProposalDraftDocumentVersion;
use App\Models\TopicProposal;
use App\Support\ProposalPaperCatalog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
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
                'restoredFrom:id,version_number',
            ])
            ->latest('id')
            ->paginate(20)
            ->withQueryString();
        $currentDocuments = $proposalDraft->documents()
            ->select(['id', 'document_type', 'position', 'lock_version'])
            ->get()
            ->keyBy(fn ($document): string => $document->document_type.':'.$document->position);
        $currentVersions = $this->currentDocumentVersions($proposalDraft, $currentDocuments);

        return view('faculty.proposal-drafts.history', [
            'proposalDraft' => $proposalDraft,
            'topic' => null,
            'versions' => $versions,
            'papers' => $catalog->all(),
            'selectedPaper' => $selectedPaper,
            'currentDocuments' => $currentDocuments,
            'currentVersions' => $currentVersions,
            'archived' => false,
        ]);
    }

    public function restore(
        RestoreProposalDraftDocumentVersionRequest $request,
        ProposalDraft $proposalDraft,
        int $documentVersion,
        RestoreProposalDraftDocumentVersion $restoreVersion,
        ProposalPaperCatalog $catalog,
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
        $paper = $catalog->forDocumentType($version->document_type);

        return redirect()
            ->route('faculty.proposal-drafts.history.index', [
                $proposalDraft,
                'paper' => $paper['slug'] ?? null,
            ])
            ->with(
                $result['version_created'] ? 'success' : 'warning',
                $result['version_created']
                    ? 'Version '.$version->version_number.' was restored as a new current version.'
                    : 'That version already matches the current paper, so no duplicate version was created.',
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

        return view('faculty.proposal-drafts.history', [
            'proposalDraft' => null,
            'topic' => $topic,
            'versions' => $versions,
            'papers' => $catalog->all(),
            'selectedPaper' => $selectedPaper,
            'currentDocuments' => collect(),
            'currentVersions' => collect(),
            'archived' => true,
        ]);
    }

    /**
     * @param  Collection<string, ProposalDraftDocument>  $currentDocuments
     * @return Collection<string, int>
     */
    private function currentDocumentVersions(
        ProposalDraft $proposalDraft,
        Collection $currentDocuments,
    ): Collection {
        $currentVersions = $proposalDraft->documentVersions()
            ->reorder()
            ->selectRaw('document_type, position, MAX(version_number) as current_version')
            ->groupBy('document_type', 'position')
            ->get()
            ->mapWithKeys(fn (ProposalDraftDocumentVersion $version): array => [
                $version->document_type.':'.$version->position => (int) $version->current_version,
            ]);

        foreach ($currentDocuments as $key => $document) {
            $currentVersions->put(
                $key,
                max($document->lock_version, $currentVersions->get($key, 0)),
            );
        }

        return $currentVersions;
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

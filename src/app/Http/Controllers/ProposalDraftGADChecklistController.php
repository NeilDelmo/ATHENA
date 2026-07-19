<?php

namespace App\Http\Controllers;

use App\Actions\SaveProposalDraftDocument;
use App\Http\Requests\UpdateProposalDraftGADChecklistRequest;
use App\Models\ProposalDraft;
use App\Models\ProposalDraftDocument;
use App\Services\GADChecklistDocumentService;
use App\Support\GADChecklistData;
use App\Support\ProposalPaperCatalog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ProposalDraftGADChecklistController extends Controller
{
    public function edit(
        ProposalDraft $proposalDraft,
        ProposalPaperCatalog $catalog,
    ): View {
        Gate::authorize('update', $proposalDraft);

        $proposalDraft->load('researchCall');
        $paper = $catalog->get('gad-checklist');
        $gadDocument = $this->document($proposalDraft);

        return view('faculty.proposal-drafts.gad-checklist.edit', compact(
            'proposalDraft',
            'paper',
            'gadDocument',
        ));
    }

    public function update(
        UpdateProposalDraftGADChecklistRequest $request,
        ProposalDraft $proposalDraft,
        ProposalPaperCatalog $catalog,
        SaveProposalDraftDocument $saveProposalDraftDocument,
    ): RedirectResponse {
        Gate::authorize('update', $proposalDraft);

        $paper = $catalog->get('gad-checklist');
        $saveProposalDraftDocument->handle(
            $proposalDraft,
            $request->user(),
            $paper['document_type'],
            0,
            $request->integer('document_version'),
            [
                'source_data' => Arr::only($request->validated(), ['project_title', 'project_leader']),
                'file_path' => null,
                'original_filename' => null,
                'mime_type' => null,
                'file_size' => null,
                'checksum' => null,
                'completed_at' => now(),
            ],
        );

        return redirect()
            ->route(
                $request->boolean('exit_after_save')
                    ? 'faculty.proposal-drafts.show'
                    : 'faculty.proposal-drafts.gad-checklist.edit',
                $proposalDraft,
            )
            ->with('success', 'GAD Generic Checklist saved.');
    }

    public function preview(
        UpdateProposalDraftGADChecklistRequest $request,
        ProposalDraft $proposalDraft,
    ): View {
        Gate::authorize('update', $proposalDraft);

        $gadChecklist = GADChecklistData::fromValidated($request->validated());

        return view('faculty.gad-checklist.preview', compact('gadChecklist'));
    }

    public function download(
        UpdateProposalDraftGADChecklistRequest $request,
        ProposalDraft $proposalDraft,
        GADChecklistDocumentService $documentService,
    ): StreamedResponse {
        Gate::authorize('download', $proposalDraft);

        $gadChecklist = GADChecklistData::fromValidated($request->validated());
        $contents = $documentService->generate($gadChecklist);
        $filenameBase = Str::slug($proposalDraft->project_title) ?: 'research-project';

        return response()->streamDownload(
            static function () use ($contents): void {
                echo $contents;
            },
            $filenameBase.'-gad-checklist.docx',
            ['Content-Type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'],
        );
    }

    private function document(ProposalDraft $proposalDraft): ?ProposalDraftDocument
    {
        return $proposalDraft->documents()
            ->where('document_type', config('proposal_papers.gad-checklist.document_type'))
            ->where('position', 0)
            ->first();
    }
}

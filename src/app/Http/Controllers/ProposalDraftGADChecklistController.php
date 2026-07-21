<?php

namespace App\Http\Controllers;

use App\Models\ProposalDraft;
use App\Services\GADChecklistDocumentService;
use App\Support\GADChecklistData;
use App\Support\ProposalDraftReadiness;
use App\Support\ProposalPaperCatalog;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ProposalDraftGADChecklistController extends Controller
{
    public function show(
        ProposalDraft $proposalDraft,
        ProposalPaperCatalog $catalog,
        ProposalDraftReadiness $readiness,
    ): View {
        Gate::authorize('view', $proposalDraft);

        $paper = $catalog->get('gad-checklist');
        $projectDetailsComplete = $readiness->projectDetailsAreComplete($proposalDraft);

        return view('faculty.proposal-drafts.gad-checklist.show', compact(
            'proposalDraft',
            'paper',
            'projectDetailsComplete',
        ));
    }

    public function preview(ProposalDraft $proposalDraft): View
    {
        Gate::authorize('view', $proposalDraft);

        $gadChecklist = GADChecklistData::fromValidated($this->gadChecklistData($proposalDraft));

        return view('faculty.gad-checklist.preview', compact('gadChecklist'));
    }

    public function download(
        ProposalDraft $proposalDraft,
        GADChecklistDocumentService $documentService,
    ): StreamedResponse {
        Gate::authorize('download', $proposalDraft);

        $gadChecklist = GADChecklistData::fromValidated($this->gadChecklistData($proposalDraft));
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

    /** @return array{project_title: string, project_leader: string} */
    private function gadChecklistData(ProposalDraft $proposalDraft): array
    {
        return [
            'project_title' => (string) $proposalDraft->project_title,
            'project_leader' => (string) $proposalDraft->project_leader,
        ];
    }
}

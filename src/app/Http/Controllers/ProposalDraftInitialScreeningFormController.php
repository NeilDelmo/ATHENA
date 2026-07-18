<?php

namespace App\Http\Controllers;

use App\Models\ProposalDraft;
use App\Services\InitialScreeningFormDocumentService;
use App\Support\ProposalDraftReadiness;
use App\Support\ProposalPaperCatalog;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ProposalDraftInitialScreeningFormController extends Controller
{
    public function show(
        ProposalDraft $proposalDraft,
        ProposalPaperCatalog $catalog,
        ProposalDraftReadiness $readiness,
    ): View {
        Gate::authorize('view', $proposalDraft);

        $paper = $catalog->get('initial-screening-form');
        $projectDetailsComplete = $readiness->projectDetailsAreComplete($proposalDraft);

        return view('faculty.proposal-drafts.initial-screening-form.show', compact(
            'proposalDraft',
            'paper',
            'projectDetailsComplete',
        ));
    }

    public function preview(ProposalDraft $proposalDraft): View
    {
        Gate::authorize('view', $proposalDraft);

        $screeningForm = $this->screeningFormData($proposalDraft);

        return view('faculty.initial-screening-form.preview', compact('screeningForm'));
    }

    public function download(
        ProposalDraft $proposalDraft,
        InitialScreeningFormDocumentService $documentService,
    ): StreamedResponse {
        Gate::authorize('download', $proposalDraft);

        $contents = $documentService->generate($this->screeningFormData($proposalDraft));
        $filenameBase = Str::slug($proposalDraft->project_title) ?: 'research-project';

        return response()->streamDownload(
            static function () use ($contents): void {
                echo $contents;
            },
            $filenameBase.'-initial-screening-form.docx',
            ['Content-Type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'],
        );
    }

    /** @return array{project_title: string, project_leader: string} */
    private function screeningFormData(ProposalDraft $proposalDraft): array
    {
        return [
            'project_title' => (string) $proposalDraft->project_title,
            'project_leader' => (string) $proposalDraft->project_leader,
        ];
    }
}

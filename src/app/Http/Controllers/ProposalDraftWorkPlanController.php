<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateProposalDraftWorkPlanRequest;
use App\Models\ProposalDraft;
use App\Models\ProposalDraftDocument;
use App\Services\WorkPlanDocumentService;
use App\Support\ProposalPaperCatalog;
use App\Support\WorkPlanData;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ProposalDraftWorkPlanController extends Controller
{
    public function edit(
        ProposalDraft $proposalDraft,
        ProposalPaperCatalog $catalog,
    ): View {
        Gate::authorize('update', $proposalDraft);

        $proposalDraft->load('researchCall');
        $paper = $catalog->get('work-plan');
        $workPlanDocument = $this->workPlanDocument($proposalDraft);
        $sourceData = $workPlanDocument?->source_data ?? [
            'entries' => [],
        ];

        return view('faculty.proposal-drafts.work-plan.edit', compact(
            'proposalDraft',
            'paper',
            'workPlanDocument',
            'sourceData',
        ));
    }

    public function update(
        UpdateProposalDraftWorkPlanRequest $request,
        ProposalDraft $proposalDraft,
        ProposalPaperCatalog $catalog,
    ): RedirectResponse {
        Gate::authorize('update', $proposalDraft);

        $paper = $catalog->get('work-plan');
        $proposalDraft->documents()->updateOrCreate(
            [
                'document_type' => $paper['document_type'],
                'position' => 0,
            ],
            [
                'source_data' => Arr::only($request->validated(), [
                    'entries',
                ]),
                'file_path' => null,
                'original_filename' => null,
                'mime_type' => null,
                'file_size' => null,
                'checksum' => null,
                'completed_at' => now(),
            ],
        );

        return redirect()
            ->route('faculty.proposal-drafts.work-plan.edit', $proposalDraft)
            ->with('success', 'Attachment A: Work Plan saved.');
    }

    public function preview(
        UpdateProposalDraftWorkPlanRequest $request,
        ProposalDraft $proposalDraft,
    ): View {
        Gate::authorize('update', $proposalDraft);

        $workPlan = WorkPlanData::fromValidated($request->validated());

        return view('faculty.work-plans.preview', compact('workPlan'));
    }

    public function download(
        UpdateProposalDraftWorkPlanRequest $request,
        ProposalDraft $proposalDraft,
        WorkPlanDocumentService $documentService,
    ): StreamedResponse {
        Gate::authorize('download', $proposalDraft);

        $workPlan = WorkPlanData::fromValidated($request->validated());
        $contents = $documentService->generate($workPlan);
        $filenameBase = Str::slug($proposalDraft->project_title) ?: 'research-project';

        return response()->streamDownload(
            static function () use ($contents): void {
                echo $contents;
            },
            $filenameBase.'-work-plan.docx',
            ['Content-Type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'],
        );
    }

    private function workPlanDocument(ProposalDraft $proposalDraft): ?ProposalDraftDocument
    {
        return $proposalDraft->documents()
            ->where('document_type', config('proposal_papers.work-plan.document_type'))
            ->where('position', 0)
            ->first();
    }
}

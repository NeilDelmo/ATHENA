<?php

namespace App\Http\Controllers;

use App\Actions\SaveProposalDraftDocument;
use App\Contracts\DocumentPdfConverter;
use App\Http\Requests\UpdateProposalDraftWorkPlanRequest;
use App\Models\ProposalDraft;
use App\Models\ProposalDraftDocument;
use App\Services\WorkPlanDocumentService;
use App\Support\ProposalDraftReadiness;
use App\Support\ProposalPaperCatalog;
use App\Support\WorkPlanData;
use App\Support\WorkPlanProposalObjectives;
use Illuminate\Http\JsonResponse;
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
        WorkPlanProposalObjectives $proposalObjectives,
        ProposalDraftReadiness $readiness,
    ): View {
        Gate::authorize('update', $proposalDraft);

        $proposalDraft->load('researchCall');
        $paper = $catalog->get('work-plan');
        $workPlanDocument = $this->workPlanDocument($proposalDraft);
        $sourceData = $workPlanDocument?->source_data ?? [
            'entries' => [],
        ];
        $linkedObjectives = $proposalObjectives->forDraft($proposalDraft);
        $projectDetailsComplete = $readiness->projectDetailsAreComplete($proposalDraft);
        $workPlanComplete = $readiness->workPlanIsComplete($proposalDraft, $workPlanDocument);
        $proposal = $proposalObjectives->proposalForDraft($proposalDraft);
        $proposalActivities = $proposalObjectives->activities($proposal);
        $proposalOutputs = collect($proposal['expected_outputs'])->filter()->all();
        $initialEntries = old('entries', $sourceData['entries'] ?? []);
        $sourceData['entries'] = $proposalObjectives->entries(
            is_array($initialEntries) ? $initialEntries : [],
            $linkedObjectives,
        );
        foreach ($sourceData['entries'] as &$entry) {
            if (blank($entry['activity'])) {
                $entry['activity'] = $proposalActivities[$entry['objective']] ?? '';
            }
        }
        unset($entry);

        return view('faculty.proposal-drafts.work-plan.edit', compact(
            'proposalDraft',
            'paper',
            'workPlanDocument',
            'workPlanComplete',
            'projectDetailsComplete',
            'sourceData',
            'linkedObjectives',
            'proposalActivities',
            'proposalOutputs',
        ));
    }

    public function update(
        UpdateProposalDraftWorkPlanRequest $request,
        ProposalDraft $proposalDraft,
        ProposalPaperCatalog $catalog,
        SaveProposalDraftDocument $saveProposalDraftDocument,
        ProposalDraftReadiness $readiness,
    ): JsonResponse|RedirectResponse {
        Gate::authorize('update', $proposalDraft);

        $paper = $catalog->get('work-plan');
        $sourceData = Arr::only($request->validated(), ['entries']);
        $saveAsDraft = $request->boolean('save_as_draft')
            && ! $readiness->workPlanSourceIsComplete($proposalDraft, $sourceData);
        $savedDocument = $saveProposalDraftDocument->handle(
            $proposalDraft,
            $request->user(),
            $paper['document_type'],
            0,
            $request->integer('document_version'),
            [
                'source_data' => $sourceData,
                'completed_at' => $saveAsDraft ? null : now(),
            ],
            changeNote: $request->string('change_note')->toString(),
        );

        $savedAsDraft = $savedDocument->completed_at === null;
        $message = $savedAsDraft
            ? 'Attachment A: Work Plan saved as a draft.'
            : 'Attachment A: Work Plan saved.';

        if ($request->expectsJson()) {
            return response()->json([
                'message' => $message,
                'document_version' => $savedDocument->lock_version,
                'draft_version' => $proposalDraft->fresh()->lock_version,
                'saved_as_draft' => $savedAsDraft,
            ]);
        }

        return redirect()
            ->route(
                $request->boolean('exit_after_save')
                    ? 'faculty.proposal-drafts.show'
                    : 'faculty.proposal-drafts.work-plan.edit',
                $proposalDraft,
            )
            ->with('proposal_tab', $request->boolean('exit_after_save') ? 'attachments' : null)
            ->with('success', $message);
    }

    public function preview(
        UpdateProposalDraftWorkPlanRequest $request,
        ProposalDraft $proposalDraft,
    ): View {
        Gate::authorize('update', $proposalDraft);

        $workPlan = WorkPlanData::fromValidated($request->validated(), includeUnscheduledEntries: true);

        return view('faculty.work-plans.preview', compact('workPlan'));
    }

    public function download(
        UpdateProposalDraftWorkPlanRequest $request,
        ProposalDraft $proposalDraft,
        WorkPlanDocumentService $documentService,
        DocumentPdfConverter $pdfConverter,
    ): StreamedResponse {
        Gate::authorize('download', $proposalDraft);

        $workPlan = WorkPlanData::fromValidated($request->validated());
        $contents = $documentService->generate($workPlan);
        $filenameBase = Str::slug($proposalDraft->project_title) ?: 'research-project';
        $filename = $filenameBase.'-work-plan.docx';
        $contentType = 'application/vnd.openxmlformats-officedocument.wordprocessingml.document';

        if ($request->header('X-Revision-PDF') === '1') {
            $contents = $pdfConverter->convertDocx($contents);
            $filename = $filenameBase.'-work-plan.pdf';
            $contentType = 'application/pdf';
        }

        return response()->streamDownload(
            static function () use ($contents): void {
                echo $contents;
            },
            $filename,
            ['Content-Type' => $contentType],
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

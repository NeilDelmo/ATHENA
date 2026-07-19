<?php

namespace App\Http\Controllers;

use App\Actions\SaveProposalDraftDocument;
use App\Http\Requests\UpdateProposalDraftLineItemBudgetRequest;
use App\Models\ProposalDraft;
use App\Models\ProposalDraftDocument;
use App\Services\LineItemBudgetDocumentService;
use App\Support\LineItemBudgetData;
use App\Support\ProposalPaperCatalog;
use App\Support\ProposalWorkspacePeople;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ProposalDraftLineItemBudgetController extends Controller
{
    /** @var list<string> */
    private const SOURCE_FIELDS = [
        'leader_campus',
        'leader_college',
        'staff',
        'amounts',
        'custom_mooe_items',
        'custom_co_items',
        'mooe_total_override',
        'co_total_override',
        'project_total_override',
        'level_of_call',
        'approval_body',
        'resolution_number',
        'resolution_year',
    ];

    public function edit(
        ProposalDraft $proposalDraft,
        ProposalPaperCatalog $catalog,
        ProposalWorkspacePeople $proposalWorkspacePeople,
    ): View {
        Gate::authorize('update', $proposalDraft);
        $proposalDraft->load('researchCall');
        $paper = $catalog->get('line-item-budget');
        $lineItemBudgetDocument = $this->document($proposalDraft);
        $sourceData = $lineItemBudgetDocument?->source_data ?? [];
        $workspacePeople = $proposalWorkspacePeople->forDraft($proposalDraft);

        return view('faculty.proposal-drafts.line-item-budget.edit', compact(
            'proposalDraft',
            'paper',
            'lineItemBudgetDocument',
            'sourceData',
            'workspacePeople',
        ));
    }

    public function update(
        UpdateProposalDraftLineItemBudgetRequest $request,
        ProposalDraft $proposalDraft,
        ProposalPaperCatalog $catalog,
        SaveProposalDraftDocument $saveProposalDraftDocument,
    ): RedirectResponse {
        Gate::authorize('update', $proposalDraft);
        $paper = $catalog->get('line-item-budget');
        $saveProposalDraftDocument->handle(
            $proposalDraft,
            $request->user(),
            $paper['document_type'],
            0,
            $request->integer('document_version'),
            [
                'source_data' => Arr::only($request->validated(), self::SOURCE_FIELDS),
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
                    : 'faculty.proposal-drafts.line-item-budget.edit',
                $proposalDraft,
            )
            ->with('success', 'Attachment B: Line-Item Budget saved.');
    }

    public function preview(UpdateProposalDraftLineItemBudgetRequest $request, ProposalDraft $proposalDraft): View
    {
        Gate::authorize('update', $proposalDraft);
        $lineItemBudget = LineItemBudgetData::fromValidated($request->validated());

        return view('faculty.line-item-budgets.preview', compact('lineItemBudget'));
    }

    public function download(
        UpdateProposalDraftLineItemBudgetRequest $request,
        ProposalDraft $proposalDraft,
        LineItemBudgetDocumentService $documentService,
    ): StreamedResponse {
        Gate::authorize('download', $proposalDraft);
        $lineItemBudget = LineItemBudgetData::fromValidated($request->validated());
        $contents = $documentService->generate($lineItemBudget);
        $filenameBase = Str::slug($proposalDraft->project_title) ?: 'research-project';

        return response()->streamDownload(
            static function () use ($contents): void {
                echo $contents;
            },
            $filenameBase.'-line-item-budget.docx',
            ['Content-Type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'],
        );
    }

    private function document(ProposalDraft $proposalDraft): ?ProposalDraftDocument
    {
        return $proposalDraft->documents()
            ->where('document_type', config('proposal_papers.line-item-budget.document_type'))
            ->where('position', 0)
            ->first();
    }
}

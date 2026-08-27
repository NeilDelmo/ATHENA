<?php

namespace App\Http\Controllers;

use App\Actions\SaveProposalDraftDocument;
use App\Contracts\DocumentPdfConverter;
use App\Http\Requests\UpdateProposalDraftExpenseBreakdownRequest;
use App\Models\ProposalDraft;
use App\Models\ProposalDraftDocument;
use App\Models\ResearchCall;
use App\Services\ExpenseBreakdownDocumentService;
use App\Support\ExpenseBreakdownData;
use App\Support\LineItemBudgetData;
use App\Support\ProposalBudgetConsistency;
use App\Support\ProposalPaperCatalog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ProposalDraftExpenseBreakdownController extends Controller
{
    public function edit(
        ProposalDraft $proposalDraft,
        ProposalPaperCatalog $catalog,
        ProposalBudgetConsistency $proposalBudgetConsistency,
    ): View {
        Gate::authorize('update', $proposalDraft);
        $proposalDraft->loadMissing('researchCall');
        $paper = $catalog->get('expense-breakdown');
        $expenseBreakdownDocument = $this->document($proposalDraft);
        $sourceData = $expenseBreakdownDocument?->source_data ?? ['items' => []];
        $budgetConsistency = $proposalBudgetConsistency->compare($proposalDraft);
        $budgetCeiling = $proposalDraft->researchCall?->budgetCeiling() ?? ResearchCall::MAXIMUM_BUDGET;

        return view('faculty.proposal-drafts.expense-breakdown.edit', compact(
            'proposalDraft',
            'paper',
            'expenseBreakdownDocument',
            'sourceData',
            'budgetConsistency',
            'budgetCeiling',
        ));
    }

    public function update(
        UpdateProposalDraftExpenseBreakdownRequest $request,
        ProposalDraft $proposalDraft,
        ProposalPaperCatalog $catalog,
        SaveProposalDraftDocument $saveProposalDraftDocument,
    ): JsonResponse|RedirectResponse {
        Gate::authorize('update', $proposalDraft);
        $paper = $catalog->get('expense-breakdown');
        $savedDocument = $saveProposalDraftDocument->handle(
            $proposalDraft,
            $request->user(),
            $paper['document_type'],
            0,
            $request->integer('document_version'),
            [
                'source_data' => Arr::only($request->validated(), ['items']),
                'file_path' => null,
                'original_filename' => null,
                'mime_type' => null,
                'file_size' => null,
                'checksum' => null,
                'completed_at' => $request->boolean('save_as_draft') ? null : now(),
            ],
            changeNote: $request->string('change_note')->toString(),
        );
        $lineItemBudgetDocument = $this->lineItemBudgetDocument($proposalDraft);
        $expenseItems = $savedDocument->source_data['items'] ?? [];

        $saveProposalDraftDocument->handle(
            $proposalDraft,
            $request->user(),
            config('proposal_papers.line-item-budget.document_type'),
            0,
            $proposalDraft->currentDocumentVersion(
                config('proposal_papers.line-item-budget.document_type'),
                0,
                $lineItemBudgetDocument,
            ),
            [
                'source_data' => LineItemBudgetData::synchronizeSourceWithExpenseBreakdown(
                    is_array($lineItemBudgetDocument?->source_data) ? $lineItemBudgetDocument->source_data : [],
                    is_array($expenseItems) ? $expenseItems : [],
                ),
                'completed_at' => $savedDocument->completed_at,
            ],
        );

        if ($request->expectsJson()) {
            return response()->json([
                'message' => $request->boolean('save_as_draft')
                    ? 'Estimated Expense Breakdown saved as a draft.'
                    : 'Estimated Expense Breakdown saved.',
                'document_version' => $savedDocument->lock_version,
                'draft_version' => $proposalDraft->fresh()->lock_version,
                'saved_as_draft' => $request->boolean('save_as_draft'),
            ]);
        }

        return redirect()
            ->route(
                $request->boolean('exit_after_save')
                    ? 'faculty.proposal-drafts.show'
                    : 'faculty.proposal-drafts.expense-breakdown.edit',
                $proposalDraft,
            )
            ->with('proposal_tab', $request->boolean('exit_after_save') ? 'attachments' : null)
            ->with('success', $request->boolean('save_as_draft')
                ? 'Estimated Expense Breakdown saved as a draft.'
                : 'Estimated Expense Breakdown saved.');
    }

    public function preview(
        UpdateProposalDraftExpenseBreakdownRequest $request,
        ProposalDraft $proposalDraft,
    ): View {
        Gate::authorize('update', $proposalDraft);
        $expenseBreakdown = ExpenseBreakdownData::fromValidated($request->validated());

        return view('faculty.expense-breakdowns.preview', compact('expenseBreakdown'));
    }

    public function download(
        UpdateProposalDraftExpenseBreakdownRequest $request,
        ProposalDraft $proposalDraft,
        ExpenseBreakdownDocumentService $documentService,
        DocumentPdfConverter $pdfConverter,
    ): StreamedResponse {
        Gate::authorize('download', $proposalDraft);
        $expenseBreakdown = ExpenseBreakdownData::fromValidated($request->validated());
        $contents = $pdfConverter->convertXlsx(
            $documentService->generate($expenseBreakdown),
        );
        $filenameBase = Str::slug($proposalDraft->project_title) ?: 'research-project';

        return response()->streamDownload(
            static function () use ($contents): void {
                echo $contents;
            },
            $filenameBase.'-estimated-expense-breakdown.pdf',
            ['Content-Type' => 'application/pdf'],
        );
    }

    private function document(ProposalDraft $proposalDraft): ?ProposalDraftDocument
    {
        return $proposalDraft->documents()
            ->where('document_type', config('proposal_papers.expense-breakdown.document_type'))
            ->where('position', 0)
            ->first();
    }

    private function lineItemBudgetDocument(ProposalDraft $proposalDraft): ?ProposalDraftDocument
    {
        return $proposalDraft->documents()
            ->where('document_type', config('proposal_papers.line-item-budget.document_type'))
            ->where('position', 0)
            ->first();
    }
}

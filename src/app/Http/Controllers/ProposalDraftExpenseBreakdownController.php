<?php

namespace App\Http\Controllers;

use App\Actions\SaveProposalDraftDocument;
use App\Http\Requests\UpdateProposalDraftExpenseBreakdownRequest;
use App\Models\ProposalDraft;
use App\Models\ProposalDraftDocument;
use App\Services\ExpenseBreakdownDocumentService;
use App\Support\ExpenseBreakdownData;
use App\Support\ProposalPaperCatalog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ProposalDraftExpenseBreakdownController extends Controller
{
    public function edit(ProposalDraft $proposalDraft, ProposalPaperCatalog $catalog): View
    {
        Gate::authorize('update', $proposalDraft);
        $paper = $catalog->get('expense-breakdown');
        $expenseBreakdownDocument = $this->document($proposalDraft);
        $sourceData = $expenseBreakdownDocument?->source_data ?? ['items' => []];

        return view('faculty.proposal-drafts.expense-breakdown.edit', compact(
            'proposalDraft',
            'paper',
            'expenseBreakdownDocument',
            'sourceData',
        ));
    }

    public function update(
        UpdateProposalDraftExpenseBreakdownRequest $request,
        ProposalDraft $proposalDraft,
        ProposalPaperCatalog $catalog,
        SaveProposalDraftDocument $saveProposalDraftDocument,
    ): RedirectResponse {
        Gate::authorize('update', $proposalDraft);
        $paper = $catalog->get('expense-breakdown');
        $saveProposalDraftDocument->handle(
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
                'completed_at' => now(),
            ],
            changeNote: $request->string('change_note')->toString(),
        );

        return redirect()
            ->route(
                $request->boolean('exit_after_save')
                    ? 'faculty.proposal-drafts.show'
                    : 'faculty.proposal-drafts.expense-breakdown.edit',
                $proposalDraft,
            )
            ->with('success', 'Estimated Expense Breakdown saved.');
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
    ): StreamedResponse {
        Gate::authorize('download', $proposalDraft);
        $expenseBreakdown = ExpenseBreakdownData::fromValidated($request->validated());
        $contents = $documentService->generate($expenseBreakdown);
        $filenameBase = Str::slug($proposalDraft->project_title) ?: 'research-project';

        return response()->streamDownload(
            static function () use ($contents): void {
                echo $contents;
            },
            $filenameBase.'-estimated-expense-breakdown.xlsx',
            ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'],
        );
    }

    private function document(ProposalDraft $proposalDraft): ?ProposalDraftDocument
    {
        return $proposalDraft->documents()
            ->where('document_type', config('proposal_papers.expense-breakdown.document_type'))
            ->where('position', 0)
            ->first();
    }
}

<?php

namespace App\Http\Controllers;

use App\Actions\SaveProposalDraftDocument;
use App\Http\Requests\UpdateProposalDraftDetailedProposalRequest;
use App\Models\ProposalDraft;
use App\Models\ProposalDraftDocument;
use App\Services\DetailedProposalDocumentService;
use App\Support\DetailedProposalData;
use App\Support\LineItemBudgetData;
use App\Support\ProposalPaperCatalog;
use App\Support\ProposalWorkspacePeople;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ProposalDraftDetailedProposalController extends Controller
{
    /** @var list<string> */
    private const SOURCE_FIELDS = [
        'research_agenda',
        'sdgs',
        'leader_email',
        'leader_contact',
        'staff',
        'proponent_department',
        'proponent_college',
        'proponent_campus',
        'cooperating_agency',
        'executive_brief',
        'rationale',
        'objectives',
        'expected_outputs',
        'related_literature',
        'methodology',
        'responsibilities',
        'references',
    ];

    public function edit(
        ProposalDraft $proposalDraft,
        ProposalPaperCatalog $catalog,
        ProposalWorkspacePeople $proposalWorkspacePeople,
    ): View {
        Gate::authorize('update', $proposalDraft);
        $proposalDraft->load(['researchCall', 'owner:id,name,email,college']);
        $paper = $catalog->get('detailed-proposal');
        $detailedProposalDocument = $this->document($proposalDraft);
        $workspacePeople = $proposalWorkspacePeople->forDraft($proposalDraft);
        $sourceData = $detailedProposalDocument?->source_data ?? $this->defaults(
            $proposalDraft,
            $workspacePeople,
        );
        $budgetTotals = $this->budgetTotals($proposalDraft);

        return view('faculty.proposal-drafts.detailed-proposal.edit', compact(
            'proposalDraft',
            'paper',
            'detailedProposalDocument',
            'sourceData',
            'workspacePeople',
            'budgetTotals',
        ));
    }

    public function update(
        UpdateProposalDraftDetailedProposalRequest $request,
        ProposalDraft $proposalDraft,
        ProposalPaperCatalog $catalog,
        SaveProposalDraftDocument $saveProposalDraftDocument,
    ): RedirectResponse {
        Gate::authorize('update', $proposalDraft);
        $paper = $catalog->get('detailed-proposal');
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
                    : 'faculty.proposal-drafts.detailed-proposal.edit',
                $proposalDraft,
            )
            ->with('success', 'Detailed Research Proposal saved.');
    }

    public function preview(
        UpdateProposalDraftDetailedProposalRequest $request,
        ProposalDraft $proposalDraft,
    ): View {
        Gate::authorize('update', $proposalDraft);
        $detailedProposal = DetailedProposalData::fromValidated(
            $request->validated(),
            $this->budgetTotals($proposalDraft),
        );

        return view('faculty.detailed-proposals.preview', compact('detailedProposal'));
    }

    public function download(
        UpdateProposalDraftDetailedProposalRequest $request,
        ProposalDraft $proposalDraft,
        DetailedProposalDocumentService $documentService,
    ): StreamedResponse {
        Gate::authorize('download', $proposalDraft);
        $detailedProposal = DetailedProposalData::fromValidated(
            $request->validated(),
            $this->budgetTotals($proposalDraft),
        );
        $contents = $documentService->generate($detailedProposal);
        $filenameBase = Str::slug($proposalDraft->project_title) ?: 'research-project';

        return response()->streamDownload(
            static function () use ($contents): void {
                echo $contents;
            },
            $filenameBase.'-detailed-research-proposal.docx',
            ['Content-Type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'],
        );
    }

    /** @param list<array{key: string, name: string, email: string, college: string, linked: bool, owner: bool}> $people */
    private function defaults(ProposalDraft $draft, array $people): array
    {
        $leaderName = Str::of((string) $draft->project_leader)->squish()->lower()->toString();
        $leader = collect($people)->first(
            fn (array $person): bool => Str::of($person['name'])->squish()->lower()->toString() === $leaderName,
        ) ?? collect($people)->firstWhere('owner', true);

        return [
            'leader_email' => $leader['email'] ?? $draft->owner->email,
            'staff' => [],
            'proponent_college' => $leader['college'] ?? $draft->owner->college ?? '',
            'proponent_campus' => config('detailed_proposal.default_campus'),
            'sdgs' => [],
            'expected_outputs' => [],
            'methodology' => [],
            'responsibilities' => [[
                'name' => $draft->project_leader,
                'duties' => '',
            ]],
        ];
    }

    /** @return array{mooe_total: float, co_total: float} */
    private function budgetTotals(ProposalDraft $draft): array
    {
        $sourceData = $draft->documents()
            ->where('document_type', config('proposal_papers.line-item-budget.document_type'))
            ->where('position', 0)
            ->value('source_data');

        if (! is_array($sourceData) || $draft->planned_start === null || $draft->planned_end === null) {
            return ['mooe_total' => 0, 'co_total' => 0];
        }

        $budget = LineItemBudgetData::fromValidated([
            ...$sourceData,
            'project_title' => $draft->project_title,
            'planned_start' => $draft->planned_start->toDateString(),
            'planned_end' => $draft->planned_end->toDateString(),
            'project_leader' => $draft->project_leader,
        ]);

        return [
            'mooe_total' => (float) $budget['mooe_total'],
            'co_total' => (float) $budget['co_total'],
        ];
    }

    private function document(ProposalDraft $proposalDraft): ?ProposalDraftDocument
    {
        return $proposalDraft->documents()
            ->where('document_type', config('proposal_papers.detailed-proposal.document_type'))
            ->where('position', 0)
            ->first();
    }
}

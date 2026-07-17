<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateProposalDraftCurriculumVitaeRequest;
use App\Models\ProposalDraft;
use App\Models\ProposalDraftDocument;
use App\Services\CurriculumVitaeDocumentService;
use App\Support\CurriculumVitaeData;
use App\Support\ProposalPaperCatalog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ProposalDraftCurriculumVitaeController extends Controller
{
    public function edit(ProposalDraft $proposalDraft, ProposalPaperCatalog $catalog): View
    {
        Gate::authorize('update', $proposalDraft);
        $paper = $catalog->get('curriculum-vitae');
        $curriculumVitaeDocument = $this->document($proposalDraft);
        $sourceData = $curriculumVitaeDocument?->source_data ?? [
            'people' => $this->seedPeople($proposalDraft),
        ];

        return view('faculty.proposal-drafts.curriculum-vitae.edit', compact(
            'proposalDraft',
            'paper',
            'curriculumVitaeDocument',
            'sourceData',
        ));
    }

    public function update(
        UpdateProposalDraftCurriculumVitaeRequest $request,
        ProposalDraft $proposalDraft,
        ProposalPaperCatalog $catalog,
    ): RedirectResponse {
        Gate::authorize('update', $proposalDraft);
        $paper = $catalog->get('curriculum-vitae');
        $documents = $proposalDraft->documents()
            ->where('document_type', $paper['document_type'])
            ->get();
        $stagedPaths = $documents->pluck('file_path')->filter()->all();

        DB::transaction(function () use ($proposalDraft, $paper, $request): void {
            $document = $proposalDraft->documents()->updateOrCreate(
                ['document_type' => $paper['document_type'], 'position' => 0],
                [
                    'source_data' => ['people' => $request->validated('people')],
                    'file_path' => null,
                    'original_filename' => null,
                    'mime_type' => null,
                    'file_size' => null,
                    'checksum' => null,
                    'completed_at' => now(),
                ],
            );
            $proposalDraft->documents()
                ->where('document_type', $paper['document_type'])
                ->whereKeyNot($document->getKey())
                ->delete();
        });

        Storage::disk('local')->delete($stagedPaths);

        return redirect()
            ->route(
                $request->boolean('exit_after_save')
                    ? 'faculty.proposal-drafts.show'
                    : 'faculty.proposal-drafts.curriculum-vitae.edit',
                $proposalDraft,
            )
            ->with('success', 'Attachment C: Curriculum Vitae saved.');
    }

    public function preview(UpdateProposalDraftCurriculumVitaeRequest $request, ProposalDraft $proposalDraft): View
    {
        Gate::authorize('update', $proposalDraft);
        $curriculumVitae = CurriculumVitaeData::fromValidated($request->validated());

        return view('faculty.curriculum-vitae.preview', compact('curriculumVitae'));
    }

    public function download(
        UpdateProposalDraftCurriculumVitaeRequest $request,
        ProposalDraft $proposalDraft,
        CurriculumVitaeDocumentService $documentService,
    ): StreamedResponse {
        Gate::authorize('download', $proposalDraft);
        $curriculumVitae = CurriculumVitaeData::fromValidated($request->validated());
        $contents = $documentService->generate($curriculumVitae);
        $filenameBase = Str::slug($proposalDraft->project_title) ?: 'research-project';

        return response()->streamDownload(
            static function () use ($contents): void {
                echo $contents;
            },
            $filenameBase.'-curriculum-vitae.docx',
            ['Content-Type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'],
        );
    }

    /** @return array<int, array<string, mixed>> */
    private function seedPeople(ProposalDraft $proposalDraft): array
    {
        $lineItemBudgetSource = $proposalDraft->documents()
            ->where('document_type', config('proposal_papers.line-item-budget.document_type'))
            ->where('position', 0)
            ->value('source_data');
        $staffNames = collect(is_array($lineItemBudgetSource) ? ($lineItemBudgetSource['staff'] ?? []) : [])
            ->filter(fn (mixed $member): bool => is_array($member))
            ->pluck('name')
            ->filter()
            ->all();

        return CurriculumVitaeData::seedPeople([
            ...collect([$proposalDraft->project_leader, ...$staffNames])
                ->filter(fn (mixed $name): bool => is_string($name) && filled($name))
                ->all(),
        ]);
    }

    private function document(ProposalDraft $proposalDraft): ?ProposalDraftDocument
    {
        return $proposalDraft->documents()
            ->where('document_type', config('proposal_papers.curriculum-vitae.document_type'))
            ->where('position', 0)
            ->first();
    }
}

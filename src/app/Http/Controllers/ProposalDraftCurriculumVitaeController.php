<?php

namespace App\Http\Controllers;

use App\Actions\SaveProposalDraftDocument;
use App\Http\Requests\UpdateProposalDraftCurriculumVitaeRequest;
use App\Models\ProposalDraft;
use App\Models\ProposalDraftDocument;
use App\Services\CurriculumVitaeDocumentService;
use App\Support\CurriculumVitaeData;
use App\Support\ProposalPaperCatalog;
use App\Support\ProposalWorkspacePeople;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ProposalDraftCurriculumVitaeController extends Controller
{
    public function edit(
        Request $request,
        ProposalDraft $proposalDraft,
        ProposalPaperCatalog $catalog,
        ProposalWorkspacePeople $proposalWorkspacePeople,
    ): View {
        Gate::authorize('update', $proposalDraft);
        $paper = $catalog->get('curriculum-vitae');
        $curriculumVitaeDocument = $this->document($proposalDraft);
        $workspacePeople = collect($proposalWorkspacePeople->forDraft($proposalDraft))
            ->map(fn (array $person): array => [
                ...$person,
                'cv' => CurriculumVitaeData::seedPeopleWithContacts([$person])[0],
            ])
            ->all();
        $sourceData = $curriculumVitaeDocument?->source_data ?? [
            'people' => $this->seedPeople($proposalDraft, $workspacePeople, (string) ($request->user()?->contact_number ?? '')),
        ];
        $sourceData['people'] = $this->applyOwnerContactNumber(
            $sourceData['people'] ?? [],
            (string) ($proposalDraft->owner?->contact_number ?? $request->user()?->contact_number ?? ''),
        );

        return view('faculty.proposal-drafts.curriculum-vitae.edit', compact(
            'proposalDraft',
            'paper',
            'curriculumVitaeDocument',
            'sourceData',
            'workspacePeople',
        ));
    }

    public function update(
        UpdateProposalDraftCurriculumVitaeRequest $request,
        ProposalDraft $proposalDraft,
        ProposalPaperCatalog $catalog,
        SaveProposalDraftDocument $saveProposalDraftDocument,
    ): RedirectResponse {
        Gate::authorize('update', $proposalDraft);
        $paper = $catalog->get('curriculum-vitae');
        $documents = $proposalDraft->documents()
            ->where('document_type', $paper['document_type'])
            ->get();
        $stagedPaths = $documents->pluck('file_path')->filter()->all();

        $document = $saveProposalDraftDocument->handle(
            $proposalDraft,
            $request->user(),
            $paper['document_type'],
            0,
            $request->integer('document_version'),
            [
                'source_data' => ['people' => $request->validated('people')],
                'file_path' => null,
                'original_filename' => null,
                'mime_type' => null,
                'file_size' => null,
                'checksum' => null,
                'completed_at' => $request->boolean('save_as_draft') ? null : now(),
            ],
            changeNote: $request->string('change_note')->toString(),
        );
        $proposalDraft->documents()
            ->where('document_type', $paper['document_type'])
            ->whereKeyNot($document->getKey())
            ->delete();

        Storage::disk('local')->delete($stagedPaths);

        return redirect()
            ->route(
                $request->boolean('exit_after_save')
                    ? 'faculty.proposal-drafts.show'
                    : 'faculty.proposal-drafts.curriculum-vitae.edit',
                $proposalDraft,
            )
            ->with('proposal_tab', $request->boolean('exit_after_save') ? 'attachments' : null)
            ->with('success', $request->boolean('save_as_draft')
                ? 'Attachment C: Curriculum Vitae saved as a draft.'
                : 'Attachment C: Curriculum Vitae saved.');
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
    private function seedPeople(ProposalDraft $proposalDraft, array $workspacePeople, string $ownerContactNumber = ''): array
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

        $additionalPeople = collect([$proposalDraft->project_leader, ...$staffNames])
            ->filter(fn (mixed $name): bool => is_string($name) && filled($name))
            ->map(fn (string $name): array => ['name' => $name, 'email' => '']);

        $people = CurriculumVitaeData::seedPeopleWithContacts(
            collect($workspacePeople)
                ->map(fn (array $person): array => ['name' => $person['name'], 'email' => $person['email']])
                ->concat($additionalPeople)
                ->unique(fn (array $person): string => Str::lower(Str::squish($person['name'])))
                ->values()
                ->all(),
        );

        return $this->applyOwnerContactNumber($people, $ownerContactNumber);
    }

    /**
     * Stamp the saved contact number onto the project leader's CV record when
     * the CV workspace first opens, so faculty don't have to retype it.
     *
     * The contact number that wins the auto-fill is the proposal owner's
     * `contact_number` (the same single source of truth used by the detailed
     * proposal and the profile page), with the currently signed-in user's
     * `contact_number` as a fallback. This keeps the project leader's CV
     * consistent regardless of whether the editor is a research head, a
     * faculty researcher, or the owner themselves.
     *
     * @param  array<int, array<string, mixed>>  $people
     * @return array<int, array<string, mixed>>
     */
    private function applyOwnerContactNumber(array $people, string $contactNumber): array
    {
        if ($contactNumber === '') {
            return $people;
        }

        foreach ($people as $index => $person) {
            if (! is_array($person)) {
                continue;
            }

            if (! blank($person['cellphone'] ?? null)) {
                continue;
            }

            $people[$index]['cellphone'] = $contactNumber;
        }

        return $people;
    }

    private function document(ProposalDraft $proposalDraft): ?ProposalDraftDocument
    {
        return $proposalDraft->documents()
            ->where('document_type', config('proposal_papers.curriculum-vitae.document_type'))
            ->where('position', 0)
            ->first();
    }
}

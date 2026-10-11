<?php

namespace App\Http\Controllers;

use App\Actions\SaveProposalDraftDocument;
use App\Contracts\DocumentPdfConverter;
use App\Http\Requests\UpdateProposalDraftCurriculumVitaeRequest;
use App\Models\ProposalDraft;
use App\Models\ProposalDraftDocument;
use App\Models\ProposalDraftMember;
use App\Models\User;
use App\Services\CurriculumVitaeDocumentService;
use App\Support\CurriculumVitaeData;
use App\Support\ProposalPaperCatalog;
use App\Support\ProposalWorkspacePeople;
use Illuminate\Http\JsonResponse;
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
        $workspacePeople = $proposalWorkspacePeople->forDraft($proposalDraft);
        $cvDefaults = User::query()
            ->whereKey($proposalDraft->members
                ->filter(fn (ProposalDraftMember $member): bool => $member->isAccepted())
                ->pluck('user_id')->filter()->push($proposalDraft->user_id))
            ->get(['id', 'email', 'cv_defaults'])
            ->mapWithKeys(fn (User $user): array => [mb_strtolower(trim($user->email)) => $user->cv_defaults ?? []]);
        $workspacePeople = collect($workspacePeople)
            ->map(fn (array $person): array => [
                ...$person,
                'cv' => CurriculumVitaeData::seedPeopleWithContacts([[
                    ...$person,
                    ...($cvDefaults[$person['email']] ?? []),
                ]])[0],
            ])
            ->all();
        $sourceData = $curriculumVitaeDocument?->source_data ?? [
            'people' => $this->seedPeople($proposalDraft, $workspacePeople),
        ];

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
    ): JsonResponse|RedirectResponse {
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

        if ($request->expectsJson()) {
            return response()->json([
                'message' => $request->boolean('save_as_draft')
                    ? 'Attachment C: Curriculum Vitae saved as a draft.'
                    : 'Attachment C: Curriculum Vitae saved.',
                'document_version' => $document->lock_version,
                'draft_version' => $proposalDraft->fresh()->lock_version,
                'saved_as_draft' => $request->boolean('save_as_draft'),
            ]);
        }

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
        DocumentPdfConverter $pdfConverter,
    ): StreamedResponse {
        Gate::authorize('download', $proposalDraft);
        $curriculumVitae = CurriculumVitaeData::fromValidated($request->validated());
        $contents = $documentService->generate($curriculumVitae);
        $filenameBase = Str::slug($proposalDraft->project_title) ?: 'research-project';
        $filename = $filenameBase.'-curriculum-vitae.docx';
        $contentType = 'application/vnd.openxmlformats-officedocument.wordprocessingml.document';

        if ($request->header('X-Revision-PDF') === '1') {
            $contents = $pdfConverter->convertDocx($contents);
            $filename = $filenameBase.'-curriculum-vitae.pdf';
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

    /** @return array<int, array<string, mixed>> */
    private function seedPeople(ProposalDraft $proposalDraft, array $workspacePeople): array
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

        return CurriculumVitaeData::seedPeopleWithContacts(
            collect($workspacePeople)
                ->map(fn (array $person): array => ['name' => $person['name'], ...$person['cv']])
                ->concat($additionalPeople)
                ->unique(fn (array $person): string => Str::lower(Str::squish($person['name'])))
                ->values()
                ->all(),
        );
    }

    private function document(ProposalDraft $proposalDraft): ?ProposalDraftDocument
    {
        return $proposalDraft->documents()
            ->where('document_type', config('proposal_papers.curriculum-vitae.document_type'))
            ->where('position', 0)
            ->first();
    }
}

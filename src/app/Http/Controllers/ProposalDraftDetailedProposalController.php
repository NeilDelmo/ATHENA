<?php

namespace App\Http\Controllers;

use App\Actions\SaveProposalDraftDetails;
use App\Actions\SaveProposalDraftDocument;
use App\Http\Requests\UpdateProposalDraftDetailedProposalRequest;
use App\Models\ProposalDraft;
use App\Models\ProposalDraftDocument;
use App\Models\ProposalDraftLiteratureSource;
use App\Services\DetailedProposalDocumentService;
use App\Services\DetailedProposalMethodologyImageService;
use App\Support\DetailedProposalData;
use App\Support\LineItemBudgetData;
use App\Support\ProposalPaperCatalog;
use App\Support\ProposalWorkspacePeople;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ProposalDraftDetailedProposalController extends Controller
{
    /** @var list<string> */
    private const SOURCE_FIELDS = [
        'research_agenda',
        'sdgs',
        'leader_title',
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
        'introduction',
        'related_literature',
        'methodology',
        'methodology_images',
        'responsibilities',
        'checked_verified_by_name',
        'recommending_approval_name',
        'approved_by_name',
        'references',
    ];

    public function edit(
        Request $request,
        ProposalDraft $proposalDraft,
        ProposalPaperCatalog $catalog,
        ProposalWorkspacePeople $proposalWorkspacePeople,
    ): View {
        Gate::authorize('update', $proposalDraft);
        $proposalDraft->load(['researchCall', 'owner:id,name,email,college,contact_number']);
        $paper = $catalog->get('detailed-proposal');
        $detailedProposalDocument = $this->document($proposalDraft);
        $workspacePeople = $proposalWorkspacePeople->forDraft($proposalDraft);
        $sourceData = $detailedProposalDocument?->source_data ?? $this->defaults(
            $proposalDraft,
            $workspacePeople,
            (string) ($request->user()?->college ?? ''),
        );
        if (blank($sourceData['proponent_college'] ?? null)) {
            $sourceData['proponent_college'] = (string) ($proposalDraft->owner?->college ?? $request->user()?->college ?? '');
        }
        if (blank($sourceData['leader_contact'] ?? null)) {
            $sourceData['leader_contact'] = (string) ($proposalDraft->owner?->contact_number ?? $request->user()?->contact_number ?? '');
        }
        $budgetTotals = $this->budgetTotals($proposalDraft);
        $literatureSources = $proposalDraft->literatureSources()
            ->with(['literatureSource.addedBy:id,name', 'literatureSource.collections:id,name,slug'])
            ->get()
            ->map(fn (ProposalDraftLiteratureSource $source): array => $source->toLibraryArray())
            ->values();
        $initialLiteratureSourceId = $request->integer('literature_source') ?: null;
        $initialLiteratureAction = $request->string('apply_to')->toString();

        if (! in_array($initialLiteratureAction, ['rrl', 'reference', 'both'], true)
            || ! $literatureSources->contains(fn (array $source): bool => $source['id'] === $initialLiteratureSourceId)) {
            $initialLiteratureSourceId = null;
            $initialLiteratureAction = null;
        }

        return view('faculty.proposal-drafts.detailed-proposal.edit', compact(
            'proposalDraft',
            'paper',
            'detailedProposalDocument',
            'sourceData',
            'workspacePeople',
            'budgetTotals',
            'literatureSources',
            'initialLiteratureSourceId',
            'initialLiteratureAction',
        ));
    }

    public function update(
        UpdateProposalDraftDetailedProposalRequest $request,
        ProposalDraft $proposalDraft,
        ProposalPaperCatalog $catalog,
        SaveProposalDraftDocument $saveProposalDraftDocument,
        SaveProposalDraftDetails $saveProposalDraftDetails,
        DetailedProposalMethodologyImageService $methodologyImageService,
    ): RedirectResponse {
        Gate::authorize('update', $proposalDraft);
        $paper = $catalog->get('detailed-proposal');
        $validated = $request->validated();
        $sourceData = Arr::only($validated, self::SOURCE_FIELDS);
        $projectLeader = Str::of((string) $validated['project_leader'])->squish()->toString();
        [$sourceData['methodology_images'], $storedImagePaths] = $this->storeMethodologyImages(
            $proposalDraft,
            $sourceData['methodology_images'] ?? [],
            $methodologyImageService,
        );

        try {
            DB::transaction(function () use ($proposalDraft, $request, $paper, $sourceData, $projectLeader, $saveProposalDraftDetails, $saveProposalDraftDocument): void {
                if ($projectLeader !== $proposalDraft->project_leader) {
                    $saveProposalDraftDetails->handle(
                        $proposalDraft,
                        $request->integer('draft_version'),
                        ['project_leader' => $projectLeader],
                    );
                }

                $saveProposalDraftDocument->handle(
                    $proposalDraft,
                    $request->user(),
                    $paper['document_type'],
                    0,
                    $request->integer('document_version'),
                    [
                        'source_data' => $sourceData,
                        'file_path' => null,
                        'original_filename' => null,
                        'mime_type' => null,
                        'file_size' => null,
                        'checksum' => null,
                        'completed_at' => $request->boolean('save_as_draft') ? null : now(),
                    ],
                    changeNote: $request->string('change_note')->toString(),
                );
            }, 3);
        } catch (\Throwable $exception) {
            Storage::disk('local')->delete($storedImagePaths);

            throw $exception;
        }

        return redirect()
            ->route(
                $request->boolean('exit_after_save')
                    ? 'faculty.proposal-drafts.show'
                    : 'faculty.proposal-drafts.detailed-proposal.edit',
                $proposalDraft,
            )
            ->with('proposal_tab', $request->boolean('exit_after_save') ? 'attachments' : null)
            ->with('success', $request->boolean('save_as_draft')
                ? 'Detailed Research Proposal saved as a draft.'
                : 'Detailed Research Proposal saved.');
    }

    public function preview(
        UpdateProposalDraftDetailedProposalRequest $request,
        ProposalDraft $proposalDraft,
        DetailedProposalMethodologyImageService $methodologyImageService,
    ): View {
        Gate::authorize('update', $proposalDraft);
        $detailedProposal = DetailedProposalData::fromValidated(
            $request->validated(),
            $this->budgetTotals($proposalDraft),
        );
        $detailedProposal['methodology_images'] = collect($detailedProposal['methodology_images'])
            ->map(function (array $image) use ($methodologyImageService): array {
                $image['data_url'] = $methodologyImageService->dataUrl($image);

                return $image;
            })
            ->filter(fn (array $image): bool => filled($image['data_url']))
            ->values()
            ->all();

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

    public function methodologyImage(
        ProposalDraft $proposalDraft,
        string $imageId,
    ): Response {
        Gate::authorize('update', $proposalDraft);
        $sourceData = $this->document($proposalDraft)?->source_data;
        $image = collect(is_array($sourceData) ? ($sourceData['methodology_images'] ?? []) : [])
            ->first(fn (mixed $image): bool => is_array($image) && ($image['id'] ?? null) === $imageId);
        $path = is_array($image) ? ($image['path'] ?? null) : null;

        abort_unless(is_string($path) && Storage::disk('local')->exists($path), 404);

        return response(Storage::disk('local')->get($path), 200, [
            'Content-Type' => is_string($image['mime_type'] ?? null) ? $image['mime_type'] : 'application/octet-stream',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    /**
     * Seed the initial detailed-proposal values when no saved document exists.
     *
     * The leader's contact number is always read from the proposal owner — the
     * faculty who created the draft — and never from the currently signed-in
     * user. This guarantees that a research head who is also a faculty member
     * and who opens someone else's draft still sees the OWNER's contact number
     * in the form, not their own. The college uses the same single-identity
     * rule: the owner's `college` first, falling back to the current user's
     * `college` so a draft created by a user with no college set still has
     * something to pre-fill.
     *
     * @param  list<array{key: string, name: string, email: string, contact: string, avatar: string, college: string, linked: bool, owner: bool}>  $people
     */
    private function defaults(ProposalDraft $draft, array $people, string $currentUserCollege): array
    {
        $leaderName = Str::of((string) $draft->project_leader)->squish()->lower()->toString();
        $leader = collect($people)->first(
            fn (array $person): bool => Str::of($person['name'])->squish()->lower()->toString() === $leaderName,
        ) ?? collect($people)->firstWhere('owner', true);

        return [
            'leader_title' => '',
            'leader_email' => $leader['email'] ?? $draft->owner->email,
            'leader_contact' => (string) ($draft->owner->contact_number ?? ''),
            'staff' => [],
            'proponent_department' => '',
            'proponent_college' => $currentUserCollege,
            'proponent_campus' => config('detailed_proposal.default_campus'),
            'sdgs' => [],
            'expected_outputs' => [],
            'methodology' => [],
            'responsibilities' => [[
                'name' => $draft->project_leader,
                'percentage' => '100',
                'duties' => '',
            ]],
            'checked_verified_by_name' => '',
            'recommending_approval_name' => '',
            'approved_by_name' => '',
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

    /**
     * @return array{0: list<array{id: string, section: string, alignment: string, size: string, caption: string, path: string, mime_type: string, original_filename: string}>, 1: list<string>}
     */
    private function storeMethodologyImages(
        ProposalDraft $proposalDraft,
        mixed $images,
        DetailedProposalMethodologyImageService $methodologyImageService,
    ): array {
        $sourceData = $this->document($proposalDraft)?->source_data;
        $savedImages = collect(is_array($sourceData) ? ($sourceData['methodology_images'] ?? []) : [])
            ->filter(fn (mixed $image): bool => is_array($image) && filled($image['id'] ?? null))
            ->keyBy('id');
        $storedImagePaths = [];

        try {
            $normalizedImages = collect(is_array($images) ? $images : [])
                ->filter(fn (mixed $image): bool => is_array($image))
                ->map(function (array $image) use ($savedImages, $proposalDraft, $methodologyImageService, &$storedImagePaths): ?array {
                    $savedImage = $savedImages->get($image['id'] ?? null);
                    $uploadedImage = $image['image'] ?? null;

                    if ($uploadedImage instanceof UploadedFile) {
                        $path = $methodologyImageService->store($proposalDraft, $uploadedImage);
                        $storedImagePaths[] = $path;

                        return [
                            'id' => is_array($savedImage) ? $savedImage['id'] : Str::uuid()->toString(),
                            'section' => $image['section'],
                            'alignment' => $image['alignment'],
                            'size' => $image['size'],
                            'caption' => $image['caption'] ?? '',
                            'path' => $path,
                            'mime_type' => $methodologyImageService->mimeType(['image' => $uploadedImage]) ?? 'application/octet-stream',
                            'original_filename' => Str::limit(basename($uploadedImage->getClientOriginalName()), 255, ''),
                        ];
                    }

                    if (! is_array($savedImage) || ! is_string($savedImage['path'] ?? null)) {
                        return null;
                    }

                    return [
                        'id' => $savedImage['id'],
                        'section' => $image['section'],
                        'alignment' => $image['alignment'],
                        'size' => $image['size'],
                        'caption' => $image['caption'] ?? '',
                        'path' => $savedImage['path'],
                        'mime_type' => $savedImage['mime_type'],
                        'original_filename' => $savedImage['original_filename'],
                    ];
                })
                ->filter()
                ->values()
                ->all();
        } catch (\Throwable $exception) {
            Storage::disk('local')->delete($storedImagePaths);

            throw $exception;
        }

        return [$normalizedImages, $storedImagePaths];
    }
}

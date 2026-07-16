<?php

namespace App\Actions;

use App\Models\ProposalDraft;
use App\Models\ProposalDraftDocument;
use App\Models\TopicProposal;
use App\Models\User;
use App\Notifications\ProposalActivityNotification;
use App\Services\ProposalPackageService;
use App\Services\WorkPlanDocumentService;
use App\Support\ProposalDraftReadiness;
use App\Support\ProposalPaperCatalog;
use App\Support\WorkPlanData;
use App\Support\WorkPlanRules;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

class SubmitProposalDraft
{
    public function __construct(
        private readonly ProposalPaperCatalog $catalog,
        private readonly ProposalDraftReadiness $readiness,
        private readonly ProposalPackageService $packageService,
        private readonly WorkPlanDocumentService $workPlanDocumentService,
    ) {}

    public function handle(ProposalDraft $draft, User $user): TopicProposal
    {
        $permanentDirectory = 'proposal-packages/'.$user->id.'/'.Str::uuid();
        $stagingDirectory = $draft->storageDirectory();
        $permanentFiles = [];

        try {
            $topic = DB::transaction(function () use (
                $draft,
                $user,
                $permanentDirectory,
                &$permanentFiles,
            ): TopicProposal {
                $lockedDraft = ProposalDraft::query()
                    ->whereKey($draft->getKey())
                    ->lockForUpdate()
                    ->firstOrFail();

                if ($lockedDraft->user_id !== $user->id) {
                    abort(403);
                }

                if ($lockedDraft->status !== ProposalDraft::STATUS_DRAFT) {
                    throw ValidationException::withMessages([
                        'status' => 'This proposal draft is already being submitted or is no longer available.',
                    ]);
                }

                $lockedDraft->load('researchCall');
                $documents = ProposalDraftDocument::query()
                    ->where('proposal_draft_id', $lockedDraft->id)
                    ->orderBy('document_type')
                    ->orderBy('position')
                    ->lockForUpdate()
                    ->get();
                $lockedDraft->setRelation('documents', $documents);

                $errors = $this->readiness->errors($lockedDraft);

                if ($errors !== []) {
                    throw ValidationException::withMessages($errors);
                }

                $lockedDraft->update(['status' => ProposalDraft::STATUS_SUBMITTING]);

                foreach ($this->catalog->all() as $paper) {
                    $paperDocuments = $documents
                        ->where('document_type', $paper['document_type'])
                        ->sortBy('position')
                        ->values();

                    if ($paper['mode'] === 'generated') {
                        $permanentFiles[] = $this->generateWorkPlan(
                            $lockedDraft,
                            $paperDocuments->firstOrFail(),
                            $permanentDirectory,
                        );

                        continue;
                    }

                    foreach ($paperDocuments as $document) {
                        $permanentFiles[] = $this->copyStagedDocument(
                            $document,
                            $paper,
                            $permanentDirectory,
                        );
                    }
                }

                $primaryFile = $this->packageService->primaryFile($permanentFiles);
                $topic = $user->proposals()->create([
                    'research_call_id' => $lockedDraft->research_call_id,
                    'title' => $lockedDraft->project_title,
                    'estimated_duration_months' => $lockedDraft->duration_months,
                    'status' => 'pending',
                ]);
                $version = $topic->versions()->create([
                    'submitted_by' => $user->id,
                    'version_number' => 1,
                    'submission_type' => 'initial',
                    'file_path' => $primaryFile['file_path'],
                    'original_filename' => $primaryFile['original_filename'],
                    'mime_type' => $primaryFile['mime_type'],
                    'file_size' => $primaryFile['file_size'],
                    'checksum' => $primaryFile['checksum'],
                    'title' => $lockedDraft->project_title,
                    'estimated_duration_months' => $lockedDraft->duration_months,
                ]);
                $version->files()->createMany($permanentFiles);
                $lockedDraft->delete();

                return $topic;
            }, 3);
        } catch (Throwable $exception) {
            Storage::disk('local')->deleteDirectory($permanentDirectory);

            throw $exception;
        }

        Storage::disk('local')->deleteDirectory($stagingDirectory);

        try {
            Notification::send(
                User::role('research_head')->get(),
                new ProposalActivityNotification(
                    'New proposal submitted',
                    $user->name.' submitted “'.$topic->title.'” for review.',
                    route('topics.show', $topic),
                    'info',
                    $topic->id,
                ),
            );
        } catch (Throwable $exception) {
            report($exception);
        }

        return $topic;
    }

    /**
     * @param  array<string, mixed>  $paper
     * @return array<string, mixed>
     */
    private function copyStagedDocument(
        ProposalDraftDocument $document,
        array $paper,
        string $permanentDirectory,
    ): array {
        $extension = strtolower(pathinfo($document->original_filename, PATHINFO_EXTENSION));
        $path = $permanentDirectory.'/'.$paper['slug'].'/'.Str::uuid().'.'.$extension;

        if (! Storage::disk('local')->copy($document->file_path, $path)) {
            throw ValidationException::withMessages([
                'papers.'.$paper['slug'] => $paper['label'].' could not be copied into the proposal package.',
            ]);
        }

        $absolutePath = Storage::disk('local')->path($path);

        return [
            'source_version_file_id' => null,
            'document_type' => $document->document_type,
            'position' => $document->position,
            'file_path' => $path,
            'original_filename' => $document->original_filename,
            'mime_type' => $document->mime_type ?: Storage::disk('local')->mimeType($path),
            'file_size' => Storage::disk('local')->size($path),
            'checksum' => hash_file('sha256', $absolutePath) ?: null,
            'source_data' => null,
            'is_carried_forward' => false,
        ];
    }

    /** @return array<string, mixed> */
    private function generateWorkPlan(
        ProposalDraft $draft,
        ProposalDraftDocument $document,
        string $permanentDirectory,
    ): array {
        $sourceData = [
            'project_title' => $draft->project_title,
            'total_duration_months' => $draft->duration_months,
            'planned_start' => $draft->planned_start?->toDateString(),
            'planned_end' => $draft->planned_end?->toDateString(),
            'entries' => $document->source_data['entries'] ?? null,
            'prepared_by' => $draft->project_leader,
        ];
        $validated = Validator::make(
            $sourceData,
            WorkPlanRules::rules(),
            [],
            WorkPlanRules::attributes(),
        )->validate();
        $workPlan = WorkPlanData::fromValidated($validated);

        return $this->packageService->storeGeneratedWorkPlan(
            $this->workPlanDocumentService->generate($workPlan),
            $permanentDirectory,
            $draft->project_title,
            $validated,
        );
    }
}

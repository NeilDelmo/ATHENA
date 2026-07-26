<?php

namespace App\Actions;

use App\Models\ProposalDraft;
use App\Models\ProposalDraftDocumentVersion;
use App\Models\ProposalVersionFile;
use App\Models\TopicProposal;
use App\Models\User;
use App\Support\ProposalPaperCatalog;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class CreateProposalRevisionDraft
{
    public function __construct(
        private readonly ProposalPaperCatalog $catalog,
    ) {}

    public function handle(TopicProposal $topic, User $user): ProposalDraft
    {
        return DB::transaction(function () use ($topic, $user): ProposalDraft {
            $existingDraft = ProposalDraft::query()
                ->where('topic_id', $topic->getKey())
                ->first();

            if ($existingDraft) {
                return $existingDraft;
            }

            $topic->loadMissing([
                'researchCall',
                'latestVersion.files',
            ]);

            $history = ProposalDraftDocumentVersion::query()
                ->whereBelongsTo($topic, 'topic')
                ->orderByDesc('id')
                ->get()
                ->unique(fn (ProposalDraftDocumentVersion $version): string => $version->document_type.':'.$version->position)
                ->values();
            $historyByType = $history->keyBy(fn (ProposalDraftDocumentVersion $version): string => $version->document_type.':'.$version->position);
            $versionFiles = $topic->latestVersion?->files
                ->keyBy(fn (ProposalVersionFile $file): string => $file->document_type.':'.$file->position)
                ?? collect();
            $workPlanSource = $this->sourceFor($historyByType, $versionFiles, ProposalVersionFile::TYPE_WORK_PLAN);
            $detailedProposalSource = $this->sourceFor($historyByType, $versionFiles, ProposalVersionFile::TYPE_DETAILED_PROPOSAL);
            $duration = max(1, (int) ($topic->estimated_duration_months ?: ($workPlanSource['total_duration_months'] ?? 1)));
            $plannedStart = $this->dateValue($workPlanSource['planned_start'] ?? null) ?? now()->toDateString();
            $plannedEnd = $this->dateValue($workPlanSource['planned_end'] ?? null)
                ?? Carbon::parse($plannedStart)->addMonths($duration)->subDay()->toDateString();
            $projectLeader = $this->firstFilled([
                $detailedProposalSource['project_leader'] ?? null,
                $workPlanSource['prepared_by'] ?? null,
                $user->name,
            ]);

            $draft = ProposalDraft::query()->create([
                'user_id' => $user->getKey(),
                'research_call_id' => $topic->research_call_id,
                'topic_id' => $topic->getKey(),
                'project_title' => $topic->title,
                'duration_months' => $duration,
                'planned_start' => $plannedStart,
                'planned_end' => $plannedEnd,
                'project_leader' => $projectLeader,
                'status' => ProposalDraft::STATUS_DRAFT,
                'lock_version' => 0,
            ]);

            foreach ($this->catalog->all()->where('mode', '!=', 'automatic') as $paper) {
                $documentType = $paper['document_type'];
                $source = $this->sourceFor($historyByType, $versionFiles, $documentType);
                $positions = $history
                    ->where('document_type', $documentType)
                    ->pluck('position')
                    ->merge($versionFiles->where('document_type', $documentType)->pluck('position'))
                    ->unique()
                    ->sort()
                    ->values();

                if ($positions->isEmpty() && $source !== []) {
                    $positions = collect([0]);
                }

                foreach ($positions as $position) {
                    $key = $documentType.':'.$position;
                    $documentVersion = $historyByType->get($key);
                    $versionFile = $versionFiles->get($key);
                    $documentSource = is_array($documentVersion?->source_data) && $documentVersion->source_data !== []
                        ? $documentVersion->source_data
                        : (is_array($versionFile?->source_data) ? $versionFile->source_data : $source);

                    $draft->documents()->create([
                        'document_type' => $documentType,
                        'position' => $position,
                        'source_data' => $documentSource,
                        'completed_at' => $documentSource !== [] ? now() : null,
                        'lock_version' => max(1, (int) ($documentVersion?->version_number ?? 1)),
                    ]);
                }
            }

            return $draft->fresh(['documents', 'researchCall']);
        }, 3);
    }

    /** @return array<string, mixed> */
    private function sourceFor(Collection $historyByType, Collection $versionFiles, string $documentType): array
    {
        $source = $historyByType
            ->filter(fn (ProposalDraftDocumentVersion $version): bool => $version->document_type === $documentType)
            ->first()
            ?->source_data;

        if (is_array($source) && $source !== []) {
            return $source;
        }

        $source = $versionFiles
            ->filter(fn (ProposalVersionFile $file): bool => $file->document_type === $documentType)
            ->first()
            ?->source_data;

        return is_array($source) ? $source : [];
    }

    private function dateValue(mixed $value): ?string
    {
        if (blank($value)) {
            return null;
        }

        try {
            return Carbon::parse((string) $value)->toDateString();
        } catch (\Throwable) {
            return null;
        }
    }

    /** @param list<mixed> $values */
    private function firstFilled(array $values): string
    {
        return (string) collect($values)->first(fn (mixed $value): bool => filled($value));
    }
}

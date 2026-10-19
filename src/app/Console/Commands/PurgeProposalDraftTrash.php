<?php

namespace App\Console\Commands;

use App\Models\ProjectDocument;
use App\Models\ProposalDraft;
use App\Models\ProposalDraftDocument;
use App\Models\ProposalDraftDocumentVersion;
use App\Models\ProposalTemplate;
use App\Models\ProposalVersion;
use App\Models\ProposalVersionFile;
use App\Models\TopicProposal;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

#[Signature('proposal-drafts:purge')]
#[Description('Permanently remove standalone proposal drafts after their 30-day recovery period')]
class PurgeProposalDraftTrash extends Command
{
    public function handle(): int
    {
        $purged = 0;
        // shortcut: retain linked official workspaces until a separate archival policy covers their history.
        ProposalDraft::onlyTrashed()->where('status', ProposalDraft::STATUS_DRAFT)
            ->whereNull('topic_id')->where('deleted_at', '<=', now()->subDays(ProposalDraft::TRASH_RETENTION_DAYS))
            ->chunkById(100, function ($drafts) use (&$purged): void {
                foreach ($drafts as $candidate) {
                    $purged += DB::transaction(function () use ($candidate): int {
                        $draft = ProposalDraft::onlyTrashed()->where('status', ProposalDraft::STATUS_DRAFT)
                            ->whereNull('topic_id')->where('deleted_at', '<=', now()->subDays(ProposalDraft::TRASH_RETENTION_DAYS))
                            ->lockForUpdate()->find($candidate->id);
                        if ($draft === null || $draft->documentVersions()->whereNotNull('topic_id')->exists()) {
                            return 0;
                        }

                        $directory = $draft->storageDirectory().'/';
                        $referenced = $this->referencedPaths($draft);
                        $files = array_values(array_filter(Storage::disk('local')->allFiles($draft->storageDirectory()),
                            fn (string $path): bool => str_starts_with($path, $directory)
                                && ! str_contains($path, '..') && ! str_contains($path, '\\')
                                && ! in_array($path, $referenced, true)));
                        if ($files !== [] && ! Storage::disk('local')->delete($files)) {
                            throw new RuntimeException('Expired draft files could not be removed; the draft was retained for retry.');
                        }

                        $draft->documentVersions()->whereNull('topic_id')->delete();
                        $draft->forceDelete();

                        return 1;
                    });
                }
            });
        $this->info("Purged {$purged} expired proposal drafts.");

        return self::SUCCESS;
    }

    /** @return list<string> */
    private function referencedPaths(ProposalDraft $draft): array
    {
        $directory = $draft->storageDirectory().'/';
        $paths = [];
        foreach ([ProposalDraftDocument::query(), ProposalDraftDocumentVersion::query(), ProposalVersionFile::query()] as $query) {
            if (! $query->getModel() instanceof ProposalVersionFile) {
                $query->where(fn (Builder $other): Builder => $other->whereNull('proposal_draft_id')->orWhere('proposal_draft_id', '<>', $draft->id));
            }
            $query->where(fn (Builder $references): Builder => $references->where('file_path', 'like', $directory.'%')
                ->orWhere('source_data', 'like', '%'.str_replace('/', '%', $directory).'%'));
            foreach ($query->cursor(['file_path', 'source_data']) as $record) {
                $paths = [...$paths, $record->file_path, ...Arr::flatten($record->source_data ?? [])];
            }
        }
        foreach ([ProposalVersion::class, ProjectDocument::class, ProposalTemplate::class] as $model) {
            $paths = [...$paths, ...$model::where('file_path', 'like', $directory.'%')->pluck('file_path')];
        }
        foreach (['initial_file_path', 'final_file_path'] as $column) {
            $paths = [...$paths, ...TopicProposal::where($column, 'like', $directory.'%')->pluck($column)];
        }

        return array_values(array_unique(array_filter($paths, fn (mixed $path): bool => is_string($path) && str_starts_with($path, $directory))));
    }
}

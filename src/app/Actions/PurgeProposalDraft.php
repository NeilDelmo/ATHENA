<?php

namespace App\Actions;

use App\Models\ProjectDocument;
use App\Models\ProposalDraft;
use App\Models\ProposalDraftDocument;
use App\Models\ProposalDraftDocumentVersion;
use App\Models\ProposalTemplate;
use App\Models\ProposalVersion;
use App\Models\ProposalVersionFile;
use App\Models\TopicProposal;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class PurgeProposalDraft
{
    public function handle(ProposalDraft $draft): void
    {
        $directory = $draft->storageDirectory().'/';
        $referenced = $this->referencedPaths($draft);
        $disk = Storage::disk('local');
        $files = array_values(array_filter($disk->allFiles($draft->storageDirectory()),
            fn (string $path): bool => str_starts_with($path, $directory)
                && ! str_contains($path, '..') && ! str_contains($path, '\\')
                && ! in_array($path, $referenced, true)));

        $draft->documentVersions()->whereNull('topic_id')->delete();
        $draft->forceDelete();

        if ($files !== []) {
            $draftId = $draft->getKey();
            DB::afterCommit(fn () => rescue(function () use ($disk, $files, $draftId): void {
                if (! $disk->delete($files)) {
                    throw new RuntimeException("Files for purged proposal draft {$draftId} could not be removed.");
                }
            }));
        }
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
            foreach ($query->select(['file_path', 'source_data'])->cursor() as $record) {
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

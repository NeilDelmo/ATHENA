<?php

namespace App\Livewire;

use App\Models\ProposalVersion;
use App\Models\ProposalVersionFile;
use App\Models\TopicProposal;
use App\Models\User;
use App\Support\ProposalPaperCatalog;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Livewire\Component;

class ResearchHeadProposalFileChecklist extends Component
{
    public TopicProposal $topic;

    public ProposalVersion $version;

    public function mount(TopicProposal $topic, ProposalVersion $version): void
    {
        $this->topic = $topic;
        $this->version = $version;
        $this->researchHead();
    }

    public function toggle(int $fileId): void
    {
        $researchHead = $this->researchHead();
        $file = $this->reviewableFilesQuery()->whereKey($fileId)->first();
        abort_if($file === null, 404);
        $reviewCheck = $file->reviewChecks()
            ->whereBelongsTo($researchHead, 'reviewer')
            ->first();

        if ($reviewCheck) {
            $reviewCheck->delete();

            return;
        }

        $file->reviewChecks()->create([
            'reviewer_id' => $researchHead->id,
            'reviewed_at' => now(),
        ]);
    }

    public function render(ProposalPaperCatalog $paperCatalog): View
    {
        $researchHead = $this->researchHead();
        $paperOrder = $paperCatalog->all()->pluck('order', 'document_type');
        $files = $this->reviewableFilesQuery()
            ->with(['reviewChecks' => fn (HasMany $query): HasMany => $query->where('reviewer_id', $researchHead->id)])
            ->get()
            ->sortBy(fn (ProposalVersionFile $file): string => sprintf(
                '%03d-%03d',
                (int) $paperOrder->get($file->document_type, 999),
                $file->position,
            ))
            ->values();
        $reviewedFileIds = $files
            ->filter(fn (ProposalVersionFile $file): bool => $file->reviewChecks->isNotEmpty())
            ->pluck('id');
        $availableFileIds = $files
            ->filter(fn (ProposalVersionFile $file): bool => Storage::disk('local')->exists($file->file_path))
            ->pluck('id');
        $viewableFileIds = $files
            ->filter(fn (ProposalVersionFile $file): bool => $availableFileIds->contains($file->id)
                && $file->canPreviewAsPdf())
            ->pluck('id');

        return view('livewire.research-head-proposal-file-checklist', compact(
            'files',
            'reviewedFileIds',
            'availableFileIds',
            'viewableFileIds',
        ));
    }

    private function researchHead(): User
    {
        $user = auth()->user();

        abort_unless(
            $user instanceof User && $user->isUsingWorkspace(User::WORKSPACE_RESEARCH_HEAD),
            403,
        );
        Gate::forUser($user)->authorize('view', $this->topic);
        abort_unless($this->version->topic_id === $this->topic->id, 404);

        return $user;
    }

    private function reviewableFilesQuery(): HasMany
    {
        return $this->version->files()
            ->whereNotIn('document_type', [
                ProposalVersionFile::TYPE_COMMENT_RESPONSE,
                ProposalVersionFile::TYPE_HEAD_UPLOAD,
            ]);
    }
}

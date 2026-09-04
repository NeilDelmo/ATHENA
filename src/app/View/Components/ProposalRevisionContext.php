<?php

namespace App\View\Components;

use App\Models\ProposalDraft;
use App\Models\ProposalFileAnnotation;
use App\Models\TopicReviewFileRevision;
use App\Support\ProposalRevisionTargetCatalog;
use Illuminate\Http\Request;
use Illuminate\View\Component;
use Illuminate\View\View;

class ProposalRevisionContext extends Component
{
    public ?ProposalFileAnnotation $annotation = null;

    public ?string $targetLabel = null;

    public ?string $editorTarget = null;

    /** @var array<int, array{target: ?string, label: ?string, comment: string}> */
    public array $revisionTargets = [];

    /** @var array<string, mixed> */
    public array $originalSourceData = [];

    public function __construct(
        Request $request,
        ProposalRevisionTargetCatalog $revisionTargets,
        public ProposalDraft $proposalDraft,
        public string $documentType,
    ) {
        if (! $proposalDraft->topic_id || (! $request->filled('revision_annotation') && ! $request->boolean('revision_embed')) || ! $request->user()?->can('update', $proposalDraft)) {
            return;
        }

        $annotations = ProposalFileAnnotation::query()
            ->with('file')
            ->when(! $request->boolean('revision_embed'), fn ($query) => $query->whereKey($request->integer('revision_annotation')))
            ->whereHas('fileRevision', fn ($query) => $query->whereNull('resolved_at'))
            ->whereHas('file', fn ($query) => $query
                ->where('document_type', $documentType)
                ->where('position', 0)
                ->whereHas('version.topic', fn ($query) => $query
                    ->whereKey($proposalDraft->topic_id)
                    ->where('status', 'revision_requested')))
            ->get();

        $sourceData = $proposalDraft->documents()
            ->where('document_type', $documentType)
            ->where('position', 0)
            ->first()?->source_data ?? [];
        $revisionFile = TopicReviewFileRevision::query()
            ->with('file')
            ->whereNull('resolved_at')
            ->where('document_type', $documentType)
            ->whereHas('review', fn ($query) => $query->where('topic_id', $proposalDraft->topic_id))
            ->whereHas('file', fn ($query) => $query->where('position', 0))
            ->latest('id')
            ->first();
        $this->originalSourceData = is_array($revisionFile?->file?->source_data)
            ? $revisionFile->file->source_data
            : [];
        foreach ($annotations as $annotation) {
            $this->revisionTargets[$annotation->id] = [
                'target' => $revisionTargets->targetForDraft($annotation->file, $annotation->editor_target, $sourceData),
                'label' => $revisionTargets->labelFor($annotation->file, $annotation->editor_target),
                'comment' => $annotation->comment,
            ];
        }

        $this->annotation = $annotations->firstWhere('id', $request->integer('revision_annotation'));

        if ($this->annotation) {
            $this->targetLabel = $this->revisionTargets[$this->annotation->id]['label'];
            $this->editorTarget = $this->revisionTargets[$this->annotation->id]['target'];
        }
    }

    /**
     * Get the view / contents that represent the component.
     */
    public function render(): View
    {
        return view('components.proposal-revision-context');
    }
}

<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreProposalFileAnnotationRequest;
use App\Http\Requests\UpdateProposalFileAnnotationRequest;
use App\Models\ProposalFileAnnotation;
use App\Models\ProposalVersion;
use App\Models\ProposalVersionFile;
use App\Models\TopicProposal;
use App\Services\ProposalRevisionSectionMap;
use App\Support\ProposalRevisionTargetCatalog;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;

class ProposalFileAnnotationController extends Controller
{
    /** @var list<string> */
    private const ANNOTATABLE_STATUSES = ['pending', 'expert_review', 'resubmitted', 'for_final_decision', TopicProposal::STATUS_GAD_REVIEW, 'lrec_review'];

    public function __construct(private readonly ProposalRevisionTargetCatalog $revisionTargets, private readonly ProposalRevisionSectionMap $sectionMap) {}

    public function index(
        Request $request,
        TopicProposal $topic,
        ProposalVersion $version,
        ProposalVersionFile $file,
    ): View {
        $this->ensureFileCanBeViewed($request, $topic, $version, $file);

        $isResearchHead = $request->user()->isUsingWorkspace('research_head');
        $canAnnotate = $isResearchHead && $this->canAnnotate($topic, $version);
        $annotations = $file->annotations()
            ->with(['reviewer', 'fileRevision'])
            ->where('feedback_source', ProposalFileAnnotation::SOURCE_HEAD)
            ->when(! $isResearchHead, fn ($query) => $query->whereNotNull('topic_review_file_revision_id'))
            ->oldest()
            ->get();

        if (! $isResearchHead) {
            abort_unless($annotations->isNotEmpty(), 404);
        }

        $latestVersion = $topic->latestVersion()->first();
        $draftAnnotations = $latestVersion
            ? ProposalFileAnnotation::query()
                ->whereNull('topic_review_file_revision_id')
                ->where('feedback_source', ProposalFileAnnotation::SOURCE_HEAD)
                ->whereHas('file', fn ($query) => $query
                    ->where('proposal_version_id', $latestVersion->id)
                    ->where('document_type', '!=', ProposalVersionFile::TYPE_HEAD_UPLOAD))
                ->with('file')
                ->oldest()
                ->get()
            : collect();
        $revisionCandidates = $draftAnnotations
            ->groupBy('proposal_version_file_id')
            ->map(fn ($fileAnnotations) => [
                'file' => $fileAnnotations->first()->file,
                'annotation_count' => $fileAnnotations->count(),
            ])
            ->values();
        $annotationConfiguration = [
            'researchHeadName' => $isResearchHead ? $request->user()->name : ($annotations->first()?->reviewer?->name ?? 'Research Head'),
            'researchHeadAvatar' => $isResearchHead ? $request->user()->avatar : $annotations->first()?->reviewer?->avatar,
            'pdfUrl' => route('topics.versions.files.view', [$topic, $version, $file]),
            'storeUrl' => route('topics.versions.files.annotations.store', [$topic, $version, $file]),
            'updateUrlTemplate' => route('topics.versions.files.annotations.update', [$topic, $version, $file, '__ANNOTATION__']),
            'destroyUrlTemplate' => route('topics.versions.files.annotations.destroy', [$topic, $version, $file, '__ANNOTATION__']),
            'csrfToken' => csrf_token(),
            'canAnnotate' => $canAnnotate,
            'fileId' => $file->id,
            'fileLabel' => $file->label(),
            'isResearchHead' => $isResearchHead,
            'editorTargets' => $this->revisionTargets->forFile($file),
            'sections' => $this->sectionMap->forFile($file),
            'revisionUrl' => ! $request->boolean('revision_embed') && ! $isResearchHead && $topic->user_id === $request->user()->id && $topic->status === 'revision_requested'
                ? route('faculty.topics.revision', $topic)
                : null,
            'annotations' => $annotations->map(fn (ProposalFileAnnotation $annotation): array => $this->annotationPayload($annotation, $file))->values(),
            'revisionCandidates' => $revisionCandidates->map(fn (array $candidate): array => [
                'fileId' => $candidate['file']->id,
                'label' => $candidate['file']->label(),
                'annotationCount' => $candidate['annotation_count'],
            ]),
        ];

        return view('topics.file-annotations', compact(
            'topic',
            'version',
            'file',
            'isResearchHead',
            'canAnnotate',
            'annotationConfiguration',
        ));
    }

    public function store(
        StoreProposalFileAnnotationRequest $request,
        TopicProposal $topic,
        ProposalVersion $version,
        ProposalVersionFile $file,
    ): JsonResponse {
        $this->ensureFileScope($topic, $version, $file);
        abort_unless($file->canPreviewAsPdf() && Storage::disk('local')->exists($file->file_path), 404);
        abort_unless($this->canAnnotate($topic, $version), 403);

        $validated = $request->validated();
        $sections = $this->sectionMap->forFile($file);
        $annotation = $file->annotations()->create([
            'reviewer_id' => $request->user()->id,
            'feedback_source' => ProposalFileAnnotation::SOURCE_HEAD,
            'co_evaluator_name' => null,
            'annotation_type' => $validated['annotation_type'],
            'page_number' => $validated['page_number'],
            'selected_text' => $validated['annotation_type'] === ProposalFileAnnotation::TYPE_TEXT
                ? $validated['selected_text']
                : null,
            'rectangles' => collect($validated['rectangles'])
                ->map(fn (array $rectangle): array => collect($rectangle)
                    ->map(fn ($coordinate): float => round((float) $coordinate, 6))
                    ->all())
                ->all(),
            'comment' => $validated['comment'],
            'editor_target' => $sections !== []
                ? $this->sectionMap->match($sections, (int) $validated['page_number'], $validated['rectangles'])
                : ($validated['editor_target'] ?? null),
        ]);
        $annotation->setRelation('reviewer', $request->user());

        return response()->json($this->annotationPayload($annotation, $file), 201);
    }

    public function update(
        UpdateProposalFileAnnotationRequest $request,
        TopicProposal $topic,
        ProposalVersion $version,
        ProposalVersionFile $file,
        ProposalFileAnnotation $annotation,
    ): JsonResponse {
        $this->ensureAnnotationCanBeChanged($request, $topic, $version, $file, $annotation);
        $changes = $request->validated();
        if (str_starts_with($annotation->editor_target ?? '', 'section-')) {
            unset($changes['editor_target']);
        }
        $annotation->update($changes);
        $annotation->load('reviewer');

        return response()->json($this->annotationPayload($annotation, $file));
    }

    private function ensureAnnotationCanBeChanged(
        Request $request,
        TopicProposal $topic,
        ProposalVersion $version,
        ProposalVersionFile $file,
        ProposalFileAnnotation $annotation,
    ): void {
        $this->ensureFileScope($topic, $version, $file);
        abort_unless($request->user()->isUsingWorkspace('research_head'), 403);
        abort_unless($this->canAnnotate($topic, $version), 403);
        abort_unless($annotation->proposal_version_file_id === $file->id, 404);
        abort_unless($annotation->reviewer_id === $request->user()->id, 403);
        abort_unless($annotation->feedback_source === ProposalFileAnnotation::SOURCE_HEAD, 403);
        abort_unless($annotation->topic_review_file_revision_id === null, 409);
    }

    public function destroy(
        Request $request,
        TopicProposal $topic,
        ProposalVersion $version,
        ProposalVersionFile $file,
        ProposalFileAnnotation $annotation,
    ): JsonResponse {
        $this->ensureAnnotationCanBeChanged($request, $topic, $version, $file, $annotation);

        $annotation->delete();

        return response()->json([], 204);
    }

    private function ensureFileCanBeViewed(
        Request $request,
        TopicProposal $topic,
        ProposalVersion $version,
        ProposalVersionFile $file,
    ): void {
        $this->ensureFileScope($topic, $version, $file);

        Gate::forUser($request->user())->authorize('view', $topic);
        abort_unless($file->canPreviewAsPdf(), 415);
        abort_unless(Storage::disk('local')->exists($file->file_path), 404);
    }

    private function ensureFileScope(
        TopicProposal $topic,
        ProposalVersion $version,
        ProposalVersionFile $file,
    ): void {
        abort_unless($version->topic_id === $topic->id, 404);
        abort_unless($file->proposal_version_id === $version->id, 404);
        abort_if($file->document_type === ProposalVersionFile::TYPE_HEAD_UPLOAD, 404);
    }

    private function canAnnotate(TopicProposal $topic, ProposalVersion $version): bool
    {
        return in_array($topic->status, self::ANNOTATABLE_STATUSES, true)
            && $topic->latestVersion()->whereKey($version->id)->exists();
    }

    /** @return array<string, mixed> */
    private function annotationPayload(ProposalFileAnnotation $annotation, ProposalVersionFile $file): array
    {
        return [
            'id' => $annotation->id,
            'type' => $annotation->annotation_type,
            'pageNumber' => $annotation->page_number,
            'selectedText' => $annotation->selected_text,
            'rectangles' => $annotation->rectangles,
            'comment' => $annotation->comment,
            'canEdit' => $annotation->reviewer_id === auth()->id() && $annotation->topic_review_file_revision_id === null,
            'editorTarget' => $annotation->editor_target,
            'editorTargetLabel' => $this->revisionTargets->labelFor($file, $annotation->editor_target),
            'reviewer' => $annotation->reviewer?->name ?? 'Research Head',
            'feedbackSource' => $annotation->feedback_source ?? ProposalFileAnnotation::SOURCE_HEAD,
            'feedbackLabel' => $annotation->feedbackLabel(),
            'coEvaluatorName' => $annotation->co_evaluator_name,
            'feedbackAuthor' => $annotation->feedback_source === ProposalFileAnnotation::SOURCE_CO_EVALUATOR
                ? $annotation->co_evaluator_name : ($annotation->reviewer?->name ?? 'Research Head'),
            'createdAt' => $annotation->created_at?->format('M j, Y g:i A'),
            'state' => match (true) {
                $annotation->topic_review_file_revision_id === null => 'draft',
                $annotation->fileRevision?->resolved_at !== null => 'resolved',
                default => 'requested',
            },
        ];
    }
}

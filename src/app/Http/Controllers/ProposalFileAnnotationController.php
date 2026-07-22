<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreProposalFileAnnotationRequest;
use App\Models\ProposalFileAnnotation;
use App\Models\ProposalVersion;
use App\Models\ProposalVersionFile;
use App\Models\TopicProposal;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ProposalFileAnnotationController extends Controller
{
    /** @var list<string> */
    private const REVIEWABLE_STATUSES = ['pending', 'resubmitted', 'for_final_decision'];

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
            'pdfUrl' => route('topics.versions.files.view', [$topic, $version, $file]),
            'storeUrl' => route('topics.versions.files.annotations.store', [$topic, $version, $file]),
            'destroyUrlTemplate' => route('topics.versions.files.annotations.destroy', [$topic, $version, $file, '__ANNOTATION__']),
            'requestRevisionUrl' => route('research_head.topics.updateStatus', $topic),
            'csrfToken' => csrf_token(),
            'canAnnotate' => $canAnnotate,
            'fileId' => $file->id,
            'fileLabel' => $file->label(),
            'annotations' => $annotations->map(fn (ProposalFileAnnotation $annotation): array => $this->annotationPayload($annotation))->values(),
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
        abort_unless($this->isPdf($file) && Storage::disk('local')->exists($file->file_path), 404);
        abort_unless($this->canAnnotate($topic, $version), 403);

        $validated = $request->validated();
        $annotation = $file->annotations()->create([
            'reviewer_id' => $request->user()->id,
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
        ]);
        $annotation->setRelation('reviewer', $request->user());

        return response()->json($this->annotationPayload($annotation), 201);
    }

    public function destroy(
        Request $request,
        TopicProposal $topic,
        ProposalVersion $version,
        ProposalVersionFile $file,
        ProposalFileAnnotation $annotation,
    ): JsonResponse {
        $this->ensureFileScope($topic, $version, $file);
        abort_unless($request->user()->isUsingWorkspace('research_head'), 403);
        abort_unless($this->canAnnotate($topic, $version), 403);
        abort_unless($annotation->proposal_version_file_id === $file->id, 404);
        abort_unless($annotation->reviewer_id === $request->user()->id, 403);
        abort_unless($annotation->topic_review_file_revision_id === null, 409);

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

        $user = $request->user();
        abort_unless($user->isUsingWorkspace('research_head') || $topic->user_id === $user->id, 403);
        abort_unless($this->isPdf($file), 415);
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
        return in_array($topic->status, self::REVIEWABLE_STATUSES, true)
            && $topic->latestVersion()->whereKey($version->id)->exists();
    }

    private function isPdf(ProposalVersionFile $file): bool
    {
        return $file->mime_type === 'application/pdf'
            || Str::lower(pathinfo($file->original_filename, PATHINFO_EXTENSION)) === 'pdf';
    }

    /** @return array<string, mixed> */
    private function annotationPayload(ProposalFileAnnotation $annotation): array
    {
        return [
            'id' => $annotation->id,
            'type' => $annotation->annotation_type,
            'pageNumber' => $annotation->page_number,
            'selectedText' => $annotation->selected_text,
            'rectangles' => $annotation->rectangles,
            'comment' => $annotation->comment,
            'reviewer' => $annotation->reviewer?->name ?? 'Research Head',
            'createdAt' => $annotation->created_at?->format('M j, Y g:i A'),
            'state' => match (true) {
                $annotation->topic_review_file_revision_id === null => 'draft',
                $annotation->fileRevision?->resolved_at !== null => 'resolved',
                default => 'requested',
            },
        ];
    }
}

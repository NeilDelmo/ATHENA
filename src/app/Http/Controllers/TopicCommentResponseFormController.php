<?php

namespace App\Http\Controllers;

use App\Contracts\DocumentPdfConverter;
use App\Models\ProposalVersionFile;
use App\Models\TopicProposal;
use App\Models\User;
use App\Services\CommentResponseFeedback;
use App\Services\CommentResponseFormDocumentService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class TopicCommentResponseFormController extends Controller
{
    public function preview(Request $request, TopicProposal $topic): View
    {
        Gate::authorize('generateCommentResponseForm', $topic);

        $commentResponseForm = $this->commentResponseFormData($topic, $this->formSource($request), $request->integer('review'));

        return view('faculty.comment-response-form.preview', compact('commentResponseForm', 'topic'));
    }

    public function download(
        Request $request,
        TopicProposal $topic,
        CommentResponseFormDocumentService $documentService,
    ): StreamedResponse {
        Gate::authorize('generateCommentResponseForm', $topic);

        $source = $this->formSource($request);
        $contents = $documentService->generate($this->commentResponseFormData($topic, $source, $request->integer('review')));
        $filenameBase = Str::slug($topic->title) ?: 'research-project';
        $sourceSlug = Str::of($source)->replace('_', '-')->toString();

        return response()->streamDownload(
            static function () use ($contents): void {
                echo $contents;
            },
            $filenameBase.'-'.$sourceSlug.'-comment-response-form.docx',
            ['Content-Type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'],
        );
    }

    public function downloadPdf(
        Request $request,
        TopicProposal $topic,
        CommentResponseFormDocumentService $documentService,
        DocumentPdfConverter $pdfConverter,
    ): Response {
        Gate::authorize('generateCommentResponseForm', $topic);

        $source = $this->formSource($request);
        $contents = $pdfConverter->convertDocx($documentService->generate(
            $this->commentResponseFormData($topic, $source, $request->integer('review')),
        ));
        $filenameBase = Str::slug($topic->title) ?: 'research-project';
        $sourceSlug = Str::of($source)->replace('_', '-')->toString();

        $filename = $filenameBase.'-'.$sourceSlug.'-comment-response-form.pdf';

        return response($contents, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.$filename.'"',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    /**
     * @return array{
     *     project_title: string,
     *     project_leader: string,
     *     leader_campus: string,
     *     leader_college: string,
     *     leader_department: string,
     *     form_source: string,
     *     form_label: string,
     *     review_id: int|null,
     *     feedback: list<array{reviewer: string, location: string, comment: string}>,
     *     staff: list<array{name: string, campus: string, college: string, department: string}>
     * }
     */
    private function commentResponseFormData(TopicProposal $topic, string $source, int $reviewId): array
    {
        $topic->loadMissing(['user:id,name,college', 'latestVersion.files']);
        $files = $topic->latestVersion?->files ?? collect();
        $detailedProposal = $this->sourceData($files->firstWhere(
            'document_type',
            ProposalVersionFile::TYPE_DETAILED_PROPOSAL,
        ));
        $lineItemBudget = $this->sourceData($files->firstWhere(
            'document_type',
            ProposalVersionFile::TYPE_LINE_ITEM_BUDGET,
        ));
        $initialScreeningForm = $this->sourceData($files->firstWhere(
            'document_type',
            ProposalVersionFile::TYPE_INITIAL_SCREENING_FORM,
        ));

        return [
            'project_title' => (string) $topic->title,
            'project_leader' => $this->firstFilled(
                $detailedProposal['project_leader'] ?? null,
                $lineItemBudget['project_leader'] ?? null,
                $initialScreeningForm['project_leader'] ?? null,
                $topic->user?->name,
            ),
            'leader_campus' => $this->firstFilled(
                $detailedProposal['proponent_campus'] ?? null,
                $lineItemBudget['leader_campus'] ?? null,
            ),
            'leader_college' => $this->firstFilled(
                $this->collegeLabel($detailedProposal['proponent_college'] ?? null),
                $this->collegeLabel($lineItemBudget['leader_college'] ?? null),
                $this->collegeLabel($topic->user?->college),
            ),
            'leader_department' => $this->firstFilled(
                $detailedProposal['proponent_department'] ?? null,
            ),
            'staff' => $this->staffRows($detailedProposal, $lineItemBudget),
            'form_source' => $source,
            'form_label' => app(CommentResponseFeedback::class)->formLabel($source),
            'review_id' => $reviewId ?: null,
            'feedback' => $this->feedbackRows($topic, $source, $reviewId),
        ];
    }

    /** @return list<array{reviewer: string, location: string, comment: string}> */
    private function feedbackRows(TopicProposal $topic, string $source, int $reviewId): array
    {
        $review = $topic->reviews()->where('decision', 'revision_requested')
            ->when($reviewId > 0, fn ($query) => $query->whereKey($reviewId))
            ->latest('id')->first();

        abort_if($reviewId > 0 && $review === null, 404);

        return app(CommentResponseFeedback::class)->rowsForSource($review, $source);
    }

    private function formSource(Request $request): string
    {
        $source = $request->string('source', CommentResponseFeedback::FORM_RESEARCH_HEAD)->toString();

        abort_unless(in_array($source, [
            CommentResponseFeedback::FORM_RESEARCH_HEAD,
            CommentResponseFeedback::FORM_CO_EVALUATOR,
        ], true), 404);

        return $source;
    }

    /** @return array<string, mixed> */
    private function sourceData(?ProposalVersionFile $file): array
    {
        return is_array($file?->source_data) ? $file->source_data : [];
    }

    private function firstFilled(mixed ...$values): string
    {
        return collect($values)
            ->map(fn (mixed $value): string => Str::squish((string) $value))
            ->first(fn (string $value): bool => $value !== '', '');
    }

    /**
     * @param  array<string, mixed>  $detailedProposal
     * @param  array<string, mixed>  $lineItemBudget
     * @return list<array{name: string, campus: string, college: string, department: string}>
     */
    private function staffRows(array $detailedProposal, array $lineItemBudget): array
    {
        $detailedStaff = collect(Arr::wrap($detailedProposal['staff'] ?? []))
            ->filter(fn (mixed $member): bool => is_array($member) && filled($member['name'] ?? null));
        $budgetStaff = collect(Arr::wrap($lineItemBudget['staff'] ?? []))
            ->filter(fn (mixed $member): bool => is_array($member) && filled($member['name'] ?? null));

        return $budgetStaff
            ->concat($detailedStaff)
            ->unique(fn (array $member): string => Str::of((string) $member['name'])->squish()->lower()->toString())
            ->take((int) config('comment_response_form.maximum_staff'))
            ->map(function (array $member) use ($budgetStaff, $detailedStaff): array {
                $normalizedName = Str::of((string) $member['name'])->squish()->lower()->toString();
                $budgetMember = $budgetStaff->first(
                    fn (array $candidate): bool => Str::of((string) $candidate['name'])->squish()->lower()->toString() === $normalizedName,
                );
                $detailedMember = $detailedStaff->first(
                    fn (array $candidate): bool => Str::of((string) $candidate['name'])->squish()->lower()->toString() === $normalizedName,
                );

                return [
                    'name' => $this->firstFilled($budgetMember['name'] ?? null, $detailedMember['name'] ?? null),
                    'campus' => $this->firstFilled($budgetMember['campus'] ?? null),
                    'college' => $this->collegeLabel($budgetMember['college'] ?? null),
                    'department' => '',
                ];
            })
            ->values()
            ->all();
    }

    private function collegeLabel(mixed $college): string
    {
        $college = $this->firstFilled($college);
        $acronym = array_search($college, User::COLLEGES, true);

        return is_string($acronym) ? $acronym : $college;
    }
}

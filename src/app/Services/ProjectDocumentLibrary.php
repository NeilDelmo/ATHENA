<?php

namespace App\Services;

use App\Models\ProjectDocument;
use App\Models\ProposalVersion;
use App\Models\ProposalVersionFile;
use App\Models\TopicProposal;
use App\Models\TopicReview;
use App\Models\User;
use App\Support\ProposalPaperCatalog;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ProjectDocumentLibrary
{
    public function __construct(private readonly CommentResponseFeedback $commentResponseFeedback) {}

    /** @return array{categories: Collection<int, array{key: string, label: string, description: string, count: int}>, documents: Collection<int, array<string, mixed>>, total: int, canUpload: bool, uploadCategories: array<string, string>} */
    public function build(TopicProposal $topic, User $viewer): array
    {
        $topic->loadMissing([
            'versions.submitter',
            'versions.files.uploadedBy',
            'projectDocuments.uploadedBy',
            'progressReports.submitter',
            'narrativeReports.submitter',
            'narrativeReports.reviewer',
            'noticeIssuer',
        ]);

        $documents = collect();
        $seenPaths = collect();
        $topic->versions
            ->sortByDesc('version_number')
            ->each(function (ProposalVersion $version) use ($topic, $viewer, $documents, $seenPaths): void {
                $version->files
                    ->sortByDesc('created_at')
                    ->each(function (ProposalVersionFile $file) use ($topic, $viewer, $version, $documents, $seenPaths): void {
                        if (! $file->canPreviewAsPdf()
                            || ! Storage::disk('local')->exists($file->file_path)
                            || $seenPaths->contains($file->file_path)
                            || ! $this->canViewVersionFile($topic, $viewer, $file)) {
                            return;
                        }

                        $seenPaths->push($file->file_path);
                        $documents->push([
                            'key' => 'proposal-version-file-'.$file->id,
                            'category' => $this->categoryForVersionFile($file),
                            'title' => $this->titleForVersionFile($file),
                            'filename' => $file->original_filename,
                            'note' => $file->source_data['note'] ?? null,
                            'source' => $this->sourceForVersionFile($file, $version),
                            'uploaded_by' => $file->uploadedBy?->name ?? $version->submitter?->name,
                            'uploaded_at' => $file->created_at,
                            'file_size' => $file->file_size,
                            'view_url' => route('topics.versions.files.view', [$topic, $version, $file]),
                            'download_url' => route('topics.versions.files.download', [$topic, $version, $file]),
                            'official' => $file->document_type !== ProposalVersionFile::TYPE_HEAD_UPLOAD
                                || ($file->source_data['purpose'] ?? null) === ProposalVersionFile::HEAD_UPLOAD_PURPOSE_SIGNED,
                        ]);
                    });
            });

        $this->appendCommentResponsePapers($topic, $viewer, $documents);
        $this->appendReleasedDocuments($topic, $documents, $seenPaths);
        $this->appendMonitoringDocuments($topic, $documents, $seenPaths);

        $topic->projectDocuments->each(function (ProjectDocument $document) use ($topic, $documents, $seenPaths): void {
            if (! Storage::disk('local')->exists($document->file_path) || $seenPaths->contains($document->file_path)) {
                return;
            }

            $seenPaths->push($document->file_path);
            $documents->push([
                'key' => 'project-document-'.$document->id,
                'category' => $document->category,
                'title' => $document->title,
                'filename' => $document->original_filename,
                'note' => $document->note,
                'source' => 'Added to project files',
                'uploaded_by' => $document->uploadedBy?->name,
                'uploaded_at' => $document->created_at,
                'file_size' => $document->file_size,
                'view_url' => route('topics.documents.view', [$topic, $document]),
                'download_url' => route('topics.documents.download', [$topic, $document]),
                'official' => false,
            ]);
        });

        $documents = $documents
            ->sortByDesc(fn (array $document): int => $document['uploaded_at']?->getTimestamp() ?? 0)
            ->values();

        $categories = collect(ProjectDocument::categoryOptions())
            ->map(fn (string $label, string $key): array => [
                'key' => $key,
                'label' => $label,
                'description' => $this->categoryDescription($key),
                'count' => $documents->where('category', $key)->count(),
            ])
            ->filter(fn (array $category): bool => $category['count'] > 0)
            ->values();

        return [
            'categories' => $categories,
            'documents' => $documents,
            'total' => $documents->count(),
            'canUpload' => $topic->user_id === $viewer->id
                && $viewer->isUsingWorkspace([
                    User::WORKSPACE_FACULTY,
                    User::WORKSPACE_FACULTY_RESEARCHER,
                ]),
            'uploadCategories' => ProjectDocument::uploadCategoryOptions(),
        ];
    }

    /** @param Collection<int, array<string, mixed>> $documents */
    private function appendCommentResponsePapers(TopicProposal $topic, User $viewer, Collection $documents): void
    {
        if (! $viewer->can('generateCommentResponseForm', $topic)) {
            return;
        }

        $topic->loadMissing([
            'reviews.reviewer',
            'reviews.topic',
            'reviews.fileRevisions.file.version.files',
            'reviews.fileRevisions.file.version.topic.stageTransitions',
            'reviews.fileRevisions.annotations.reviewer',
        ]);
        $routePrefix = $viewer->isUsingWorkspace(User::WORKSPACE_RESEARCH_HEAD) ? 'research_head' : 'faculty';

        foreach ($topic->reviews->where('decision', 'revision_requested') as $review) {
            $version = $this->commentResponseFeedback->reviewedVersion($review);

            foreach ([CommentResponseFeedback::FORM_RESEARCH_HEAD, CommentResponseFeedback::FORM_CO_EVALUATOR] as $source) {
                $rows = $this->commentResponseFeedback->rowsForSource($review, $source);
                if ($rows !== []) {
                    $documents->push($this->commentResponsePaper($topic, $version, $routePrefix, $source, $rows, $review));
                }
            }
        }

        if ($routePrefix !== 'research_head'
            || ! in_array($topic->status, ['pending', 'expert_review', 'resubmitted', 'for_final_decision', TopicProposal::STATUS_GAD_REVIEW, TopicProposal::STATUS_LREC_REVIEW], true)) {
            return;
        }

        $version = $topic->versions->sortByDesc('version_number')->first();
        if ($version !== null) {
            $rows = $this->commentResponseFeedback->draftRows($version);
            if ($rows !== []) {
                $documents->push($this->commentResponsePaper($topic, $version, $routePrefix, CommentResponseFeedback::FORM_RESEARCH_HEAD, $rows));
            }
        }
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return array<string, mixed>
     */
    private function commentResponsePaper(TopicProposal $topic, ?ProposalVersion $version, string $routePrefix, string $source, array $rows, ?TopicReview $review = null): array
    {
        $query = ['topic' => $topic, 'source' => $source, ...($review ? ['review' => $review->id] : ['draft_version' => $version->id])];
        $stages = $this->commentResponseFeedback->stagesForRows($rows);
        $stageLabels = array_map(fn (string $stage): string => CommentResponseFeedback::STAGE_LABELS[$stage], $stages);
        $url = route($routePrefix.'.topics.comment-response-form.pdf', $query);
        $versionLabel = $version ? 'Version '.$version->version_number : 'Version not recorded';

        return [
            'key' => 'comment-response-'.($review ? 'review-'.$review->id : 'draft-'.$version->id).'-'.$source,
            'category' => ProjectDocument::CATEGORY_REVIEWS_RESPONSES,
            'title' => ($source === CommentResponseFeedback::FORM_CO_EVALUATOR ? 'Co-Evaluator' : 'Research Head').' Comment Response Paper',
            'filename' => (Str::slug($version?->title ?? $topic->title) ?: 'proposal').'-'.($version ? 'v'.$version->version_number : 'review-'.$review->id).'-'.$source.'-comment-response.pdf',
            'note' => $review ? 'Generated from this review’s saved comments and submitted Faculty responses.' : 'Private draft from saved highlights. Sending a revision request shares the selected papers’ comments with Faculty.',
            'source' => $versionLabel.' · '.implode(' / ', $stageLabels),
            'uploaded_by' => $review?->reviewer?->name,
            'uploaded_at' => $review?->created_at ?? $version?->created_at,
            'file_size' => null,
            'view_url' => $url,
            'download_url' => $url,
            'official' => false,
            'generated_comment_response' => true,
            'draft' => $review === null,
        ];
    }

    private function canViewVersionFile(
        TopicProposal $topic,
        User $viewer,
        ProposalVersionFile $file,
    ): bool {
        if ($viewer->isUsingWorkspace(User::WORKSPACE_RESEARCH_HEAD)) {
            return true;
        }

        return ! ($file->document_type === ProposalVersionFile::TYPE_HEAD_UPLOAD
            && ($file->source_data['purpose'] ?? null) === ProposalVersionFile::HEAD_UPLOAD_PURPOSE_SIGNED
            && (! $topic->hasIssuedNoticeToProceed() || $file->isSuperseded()));
    }

    private function categoryForVersionFile(ProposalVersionFile $file): string
    {
        if ($file->document_type === ProposalVersionFile::TYPE_COMMENT_RESPONSE) {
            return ProjectDocument::CATEGORY_REVIEWS_RESPONSES;
        }

        if ($file->document_type !== ProposalVersionFile::TYPE_HEAD_UPLOAD) {
            return ProjectDocument::CATEGORY_PROPOSAL_PAPERS;
        }

        return match ($file->source_data['purpose'] ?? null) {
            ProposalVersionFile::HEAD_UPLOAD_PURPOSE_SIGNED => ProjectDocument::CATEGORY_SIGNED_PAPERS,
            ProposalVersionFile::HEAD_UPLOAD_PURPOSE_EVALUATION,
            ProposalVersionFile::HEAD_UPLOAD_PURPOSE_GAD_ASSESSMENT => ProjectDocument::CATEGORY_REVIEWS_RESPONSES,
            default => ProjectDocument::CATEGORY_SUPPORTING_FILES,
        };
    }

    private function titleForVersionFile(ProposalVersionFile $file): string
    {
        if ($file->document_type !== ProposalVersionFile::TYPE_HEAD_UPLOAD) {
            return $file->label();
        }

        $documentTitle = $file->source_data['document_title'] ?? null;

        if (filled($documentTitle)) {
            return (string) $documentTitle;
        }

        $targetLabel = app(ProposalPaperCatalog::class)
            ->label($file->source_data['target_document_type'] ?? '');

        return match ($file->source_data['purpose'] ?? null) {
            ProposalVersionFile::HEAD_UPLOAD_PURPOSE_SIGNED => ($targetLabel ?: 'Proposal paper').' — signed copy',
            ProposalVersionFile::HEAD_UPLOAD_PURPOSE_GAD_ASSESSMENT => 'GAD assessment',
            ProposalVersionFile::HEAD_UPLOAD_PURPOSE_EVALUATION => 'Co-evaluator review',
            default => 'Supporting project paper',
        };
    }

    private function sourceForVersionFile(ProposalVersionFile $file, ProposalVersion $version): string
    {
        if ($file->document_type !== ProposalVersionFile::TYPE_HEAD_UPLOAD) {
            return 'Proposal package · Version '.$version->version_number;
        }

        return match ($file->source_data['purpose'] ?? null) {
            ProposalVersionFile::HEAD_UPLOAD_PURPOSE_SIGNED => 'Signing record',
            ProposalVersionFile::HEAD_UPLOAD_PURPOSE_GAD_ASSESSMENT => 'GAD assessment',
            ProposalVersionFile::HEAD_UPLOAD_PURPOSE_EVALUATION => 'Co-evaluator review',
            default => 'Project supporting file',
        };
    }

    /** @param Collection<int, array<string, mixed>> $documents */
    private function appendReleasedDocuments(
        TopicProposal $topic,
        Collection $documents,
        Collection $seenPaths,
    ): void {
        if (filled($topic->signed_approval_path)
            && Storage::disk('local')->exists($topic->signed_approval_path)
            && ! $seenPaths->contains($topic->signed_approval_path)) {
            $seenPaths->push($topic->signed_approval_path);
            $documents->push([
                'key' => 'signed-approval-'.$topic->id,
                'category' => ProjectDocument::CATEGORY_SIGNED_PAPERS,
                'title' => 'Signed approval',
                'filename' => 'signed-approval-'.$topic->id.'.pdf',
                'note' => null,
                'source' => 'Signing record',
                'uploaded_by' => null,
                'uploaded_at' => $topic->updated_at,
                'file_size' => Storage::disk('local')->size($topic->signed_approval_path),
                'view_url' => null,
                'download_url' => route('topics.approval', $topic),
                'official' => true,
            ]);
        }

        if ($topic->hasIssuedNoticeToProceed()
            && filled($topic->notice_to_proceed_path)
            && Storage::disk('local')->exists($topic->notice_to_proceed_path)
            && ! $seenPaths->contains($topic->notice_to_proceed_path)) {
            $seenPaths->push($topic->notice_to_proceed_path);
            $documents->push([
                'key' => 'notice-to-proceed-'.$topic->id,
                'category' => ProjectDocument::CATEGORY_NOTICE_TO_PROCEED,
                'title' => 'Notice to Proceed',
                'filename' => $topic->notice_to_proceed_original_filename ?: 'notice-to-proceed-'.$topic->id.'.pdf',
                'note' => null,
                'source' => 'Official release',
                'uploaded_by' => $topic->noticeIssuer?->name,
                'uploaded_at' => $topic->notice_to_proceed_issued_at,
                'file_size' => Storage::disk('local')->size($topic->notice_to_proceed_path),
                'view_url' => null,
                'download_url' => route('topics.notice-to-proceed.download', $topic),
                'official' => true,
            ]);
        }
    }

    /** @param Collection<int, array<string, mixed>> $documents */
    private function appendMonitoringDocuments(
        TopicProposal $topic,
        Collection $documents,
        Collection $seenPaths,
    ): void {
        $topic->progressReports->each(function ($report) use ($documents, $seenPaths): void {
            if (! filled($report->official_pdf_path)
                || ! Storage::disk('local')->exists($report->official_pdf_path)
                || $seenPaths->contains($report->official_pdf_path)) {
                return;
            }

            $seenPaths->push($report->official_pdf_path);
            $documents->push([
                'key' => 'monitoring-report-'.$report->id,
                'category' => ProjectDocument::CATEGORY_MONITORING_REPORTS,
                'title' => 'Monitoring Tool · '.$report->quarter_label.' '.$report->reporting_year,
                'filename' => $report->official_pdf_filename ?: 'monitoring-tool-'.$report->id.'.pdf',
                'note' => null,
                'source' => $report->reporting_period_label,
                'uploaded_by' => $report->submitter?->name,
                'uploaded_at' => $report->submitted_at ?? $report->created_at,
                'file_size' => $report->official_pdf_size,
                'view_url' => null,
                'download_url' => route('project-progress.monitoring-tool', $report),
                'official' => true,
            ]);
        });

        $topic->narrativeReports->each(function ($report) use ($documents, $seenPaths): void {
            if (filled($report->official_pdf_path)
                && Storage::disk('local')->exists($report->official_pdf_path)
                && ! $seenPaths->contains($report->official_pdf_path)) {
                $seenPaths->push($report->official_pdf_path);
                $documents->push([
                    'key' => 'narrative-report-'.$report->id,
                    'category' => ProjectDocument::CATEGORY_MONITORING_REPORTS,
                    'title' => $report->report_label,
                    'filename' => $report->official_pdf_filename ?: 'project-report-'.$report->id.'.pdf',
                    'note' => null,
                    'source' => $report->submission_date?->format('M j, Y'),
                    'uploaded_by' => $report->submitter?->name,
                    'uploaded_at' => $report->submitted_at ?? $report->created_at,
                    'file_size' => $report->official_pdf_size,
                    'view_url' => null,
                    'download_url' => route('project-narrative-reports.download', $report),
                    'official' => true,
                ]);
            }

            $signedCopy = $report->signedCopy();

            if ($signedCopy === null
                || ! Storage::disk('local')->exists($signedCopy['path'])
                || $seenPaths->contains($signedCopy['path'])) {
                return;
            }

            $seenPaths->push($signedCopy['path']);
            $documents->push([
                'key' => 'signed-narrative-report-'.$report->id,
                'category' => ProjectDocument::CATEGORY_SIGNED_PAPERS,
                'title' => $report->report_label.' — signed copy',
                'filename' => $signedCopy['original_filename'],
                'note' => null,
                'source' => 'Project reporting',
                'uploaded_by' => $report->reviewer?->name,
                'uploaded_at' => filled($signedCopy['uploaded_at']) ? Carbon::parse($signedCopy['uploaded_at']) : $report->updated_at,
                'file_size' => $signedCopy['size'] ?? null,
                'view_url' => null,
                'download_url' => route('project-narrative-reports.signed-copy.download', $report),
                'official' => true,
            ]);
        });
    }

    private function categoryDescription(string $category): string
    {
        return match ($category) {
            ProjectDocument::CATEGORY_PROPOSAL_PAPERS => 'Generated and submitted proposal forms across versions.',
            ProjectDocument::CATEGORY_SIGNED_PAPERS => 'Signed and officially released project papers.',
            ProjectDocument::CATEGORY_REVIEWS_RESPONSES => 'Assessments, evaluations, and comment-response papers.',
            ProjectDocument::CATEGORY_NOTICE_TO_PROCEED => 'The project’s official authority to begin.',
            ProjectDocument::CATEGORY_MONITORING_REPORTS => 'Progress, narrative, and terminal report PDFs.',
            ProjectDocument::CATEGORY_SUPPORTING_FILES => 'References and attachments kept with the project.',
            default => 'Additional PDFs kept for the project record.',
        };
    }
}

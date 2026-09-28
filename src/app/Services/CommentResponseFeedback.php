<?php

namespace App\Services;

use App\Models\ProposalFileAnnotation;
use App\Models\ProposalVersion;
use App\Models\ProposalVersionFile;
use App\Models\TopicProposal;
use App\Models\TopicReview;
use App\Support\ProposalRevisionTargetCatalog;
use DateTimeInterface;

class CommentResponseFeedback
{
    public const FORM_RESEARCH_HEAD = 'research_head';

    public const FORM_CO_EVALUATOR = 'co_evaluator';

    public const STAGE_LABELS = [
        'research_head' => 'Research Head Review',
        'gad' => 'GAD Checklist',
        'co_evaluator' => 'Co-Evaluator Review',
        'lrec' => 'LREC',
    ];

    public function reviewedVersion(TopicReview $review): ?ProposalVersion
    {
        $review->loadMissing(['fileRevisions.file.version.files', 'topic']);

        return $review->fileRevisions->first(fn ($revision): bool => $revision->file?->version instanceof ProposalVersion)?->file?->version
            ?? $review->topic?->versions()->with('files')->where('created_at', '<=', $review->created_at)->orderByDesc('version_number')->first();
    }

    public function currentStage(TopicProposal $topic, ProposalVersion $version): string
    {
        return match ($topic->currentReviewStageLabel($version)) {
            'LREC review' => 'lrec',
            'Co-evaluator review' => 'co_evaluator',
            'GAD assessment' => 'gad',
            default => 'research_head',
        };
    }

    public function reviewStage(?TopicReview $review): ?string
    {
        if ($review === null) {
            return null;
        }

        return match ($review->review_stage) {
            'lrec' => 'lrec',
            'gad' => $this->gadStage($this->reviewedVersion($review), $review->created_at),
            default => 'research_head',
        };
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return list<string>
     */
    public function stagesForRows(array $rows, ?string $fallback = null): array
    {
        $stages = array_column($rows, 'stage');

        return array_values(array_intersect(array_keys(self::STAGE_LABELS), $stages !== [] ? $stages : array_filter([$fallback])));
    }

    private function gadStage(?ProposalVersion $version, DateTimeInterface $at): string
    {
        if ($version === null) {
            return 'gad';
        }

        $version->loadMissing('files');
        $snapshot = clone $version;
        $snapshot->setRelation('files', $version->files->filter(fn (ProposalVersionFile $file): bool => $file->created_at?->lte($at) ?? false));

        return $snapshot->hasPassingGadAssessment() ? 'co_evaluator' : 'gad';
    }

    private function annotationStage(ProposalFileAnnotation $annotation, ProposalVersion $version, string $fallback): string
    {
        $version->loadMissing('topic.stageTransitions');
        $transition = $version->topic->stageTransitions
            ->filter(fn ($transition): bool => $transition->changed_at->lte($annotation->created_at)
                && in_array($transition->to_status, ['pending', TopicProposal::STATUS_GAD_REVIEW, TopicProposal::STATUS_LREC_QUEUED, TopicProposal::STATUS_LREC_REVIEW, TopicProposal::STATUS_READY_FOR_SIGNATURE], true))
            ->sortByDesc('id')->first();

        return match ($transition?->to_status) {
            'pending' => 'research_head',
            TopicProposal::STATUS_GAD_REVIEW => $this->gadStage($version, $annotation->created_at),
            TopicProposal::STATUS_LREC_QUEUED, TopicProposal::STATUS_LREC_REVIEW, TopicProposal::STATUS_READY_FOR_SIGNATURE => 'lrec',
            default => $fallback,
        };
    }

    /** @return list<array{key: string, reviewer: string, location: string, comment: string, response: string, remarks: string, form_source: string, stage: string}> */
    public function rows(?TopicReview $review): array
    {
        return [
            ...$this->rowsForSource($review, self::FORM_RESEARCH_HEAD),
            ...$this->rowsForSource($review, self::FORM_CO_EVALUATOR),
        ];
    }

    /** @return list<array{key: string, reviewer: string, location: string, comment: string, response: string, remarks: string, form_source: string, stage: string}> */
    public function rowsForSource(?TopicReview $review, string $source): array
    {
        if ($review === null) {
            return [];
        }

        $rows = match ($source) {
            self::FORM_RESEARCH_HEAD => $this->researchHeadRows($review),
            self::FORM_CO_EVALUATOR => $this->coEvaluatorRows($review),
            default => [],
        };
        $responses = $review->feedback_responses ?? [];

        return array_map(fn (array $row): array => [
            ...$row,
            'response' => $responses[$row['key']]['response'] ?? '',
            'remarks' => $responses[$row['key']]['remarks'] ?? '',
            'form_source' => $source,
        ], $rows);
    }

    public function formLabel(string $source): string
    {
        return match ($source) {
            self::FORM_CO_EVALUATOR => 'Co-evaluator Comment-Response Form',
            default => 'Research Head Comment-Response Form',
        };
    }

    /** @return list<array{key: string, reviewer: string, location: string, comment: string, response: string, remarks: string, form_source: string, stage: string}> */
    public function draftRows(ProposalVersion $version): array
    {
        $version->loadMissing(['files', 'topic.stageTransitions']);
        $fallbackStage = $this->currentStage($version->topic, $version);

        return ProposalFileAnnotation::query()
            ->whereNull('topic_review_file_revision_id')
            ->where('feedback_source', ProposalFileAnnotation::SOURCE_HEAD)
            ->whereHas('file', fn ($query) => $query->where('proposal_version_id', $version->id)
                ->whereNotIn('document_type', [ProposalVersionFile::TYPE_HEAD_UPLOAD, ProposalVersionFile::TYPE_COMMENT_RESPONSE]))
            ->with(['file', 'reviewer'])
            ->oldest('id')
            ->get()
            ->map(function (ProposalFileAnnotation $annotation) use ($version, $fallbackStage): array {
                $section = app(ProposalRevisionTargetCatalog::class)->labelFor($annotation->file, $annotation->editor_target);

                return [
                    'key' => 'annotation_'.$annotation->id,
                    'reviewer' => $annotation->feedbackLabel().($annotation->reviewer ? ' · '.$annotation->reviewer->name : ''),
                    'location' => implode(' · ', array_filter([$annotation->file->label(), 'Page '.$annotation->page_number, $section])),
                    'comment' => $annotation->comment,
                    'response' => '',
                    'remarks' => '',
                    'form_source' => self::FORM_RESEARCH_HEAD,
                    'stage' => $this->annotationStage($annotation, $version, $fallbackStage),
                ];
            })->all();
    }

    /** @return list<array{key: string, reviewer: string, location: string, comment: string, stage: string}> */
    private function researchHeadRows(TopicReview $review): array
    {
        $review->loadMissing(['reviewer', 'fileRevisions.file.version.files', 'fileRevisions.file.version.topic.stageTransitions', 'fileRevisions.annotations.reviewer']);
        $rows = [];
        $stage = $this->reviewStage($review);
        $headLabel = 'Research Head'.($review->reviewer ? ' · '.$review->reviewer->name : '');

        if (filled($review->comment)) {
            $rows[] = ['key' => 'overall', 'reviewer' => $headLabel, 'location' => 'Overall proposal', 'comment' => $review->comment, 'stage' => $stage];
        }

        foreach ($review->committee_comments ?? [] as $index => $comment) {
            $rows[] = ['key' => 'committee_'.$index, 'reviewer' => ($review->review_stage === 'lrec' ? 'LREC' : 'Reviewer').' · '.$comment['reviewer'], 'location' => $comment['location'] ?? 'Overall proposal', 'comment' => $comment['comment'], 'stage' => $stage];
        }

        foreach ($review->fileRevisions->sortBy('id') as $revision) {
            $file = $revision->file;
            $fileLabel = $file?->label() ?? $revision->original_filename ?? 'Document';

            if (filled($revision->revision_note)) {
                $rows[] = ['key' => 'file_'.$revision->id, 'reviewer' => $headLabel, 'location' => $fileLabel, 'comment' => $revision->revision_note, 'stage' => $stage];
            }

            foreach ($revision->annotations
                ->where('feedback_source', ProposalFileAnnotation::SOURCE_HEAD)
                ->sortBy('id') as $annotation) {
                $section = $file ? app(ProposalRevisionTargetCatalog::class)->labelFor($file, $annotation->editor_target) : null;
                $reviewer = $annotation->feedbackLabel();

                if ($annotation->reviewer) {
                    $reviewer .= ' · '.$annotation->reviewer->name;
                }

                $rows[] = [
                    'key' => 'annotation_'.$annotation->id,
                    'reviewer' => $reviewer,
                    'location' => implode(' · ', array_filter([$fileLabel, 'Page '.$annotation->page_number, $section])),
                    'comment' => $annotation->comment,
                    'stage' => $file?->version ? $this->annotationStage($annotation, $file->version, $stage) : $stage,
                ];
            }
        }

        return $rows;
    }

    /** @return list<array{key: string, reviewer: string, location: string, comment: string, stage: string}> */
    private function coEvaluatorRows(TopicReview $review): array
    {
        if ($review->review_stage === 'lrec') {
            return [];
        }

        $version = $this->reviewedVersion($review);
        $initialScreeningId = $version?->files->firstWhere('document_type', ProposalVersionFile::TYPE_INITIAL_SCREENING_FORM)?->id;
        $evaluation = $version?->files
            ->filter(fn (ProposalVersionFile $file): bool => $file->document_type === ProposalVersionFile::TYPE_HEAD_UPLOAD
                && $initialScreeningId !== null && $file->source_version_file_id === $initialScreeningId
                && ($file->created_at?->lte($review->created_at) ?? false)
                && ($file->source_data['purpose'] ?? null) === ProposalVersionFile::HEAD_UPLOAD_PURPOSE_EVALUATION
                && ($file->source_data['target_document_type'] ?? null) === ProposalVersionFile::TYPE_INITIAL_SCREENING_FORM
                && filled($file->source_data['narrative_evaluation'] ?? null))
            ->sortByDesc('id')
            ->first();

        if (! $evaluation instanceof ProposalVersionFile) {
            return [];
        }

        $coEvaluatorName = trim((string) ($evaluation->source_data['co_evaluator_name'] ?? ''));

        return [[
            'key' => 'co_evaluator_narrative_'.$evaluation->id,
            'reviewer' => 'Co-evaluator'.($coEvaluatorName !== '' ? ' · '.$coEvaluatorName : ''),
            'location' => 'Initial Screening Form · Narrative Evaluation',
            'comment' => trim((string) $evaluation->source_data['narrative_evaluation']),
            'stage' => 'co_evaluator',
        ]];
    }
}

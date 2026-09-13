<?php

namespace App\Services;

use App\Models\ProposalFileAnnotation;
use App\Models\ProposalVersion;
use App\Models\ProposalVersionFile;
use App\Models\TopicReview;
use App\Support\ProposalRevisionTargetCatalog;

class CommentResponseFeedback
{
    public const FORM_RESEARCH_HEAD = 'research_head';

    public const FORM_CO_EVALUATOR = 'co_evaluator';

    /** @return list<array{key: string, reviewer: string, location: string, comment: string, response: string, remarks: string, form_source: string}> */
    public function rows(?TopicReview $review): array
    {
        return [
            ...$this->rowsForSource($review, self::FORM_RESEARCH_HEAD),
            ...$this->rowsForSource($review, self::FORM_CO_EVALUATOR),
        ];
    }

    /** @return list<array{key: string, reviewer: string, location: string, comment: string, response: string, remarks: string, form_source: string}> */
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

    /** @return list<array{key: string, reviewer: string, location: string, comment: string}> */
    private function researchHeadRows(TopicReview $review): array
    {
        $review->loadMissing(['reviewer', 'fileRevisions.file', 'fileRevisions.annotations.reviewer']);
        $rows = [];
        $headLabel = 'Research Head'.($review->reviewer ? ' · '.$review->reviewer->name : '');

        if (filled($review->comment)) {
            $rows[] = ['key' => 'overall', 'reviewer' => $headLabel, 'location' => 'Overall proposal', 'comment' => $review->comment];
        }

        foreach ($review->committee_comments ?? [] as $index => $comment) {
            $rows[] = ['key' => 'committee_'.$index, 'reviewer' => 'LREC · '.$comment['reviewer'], 'location' => $comment['location'] ?? 'Overall proposal', 'comment' => $comment['comment']];
        }

        foreach ($review->fileRevisions->sortBy('id') as $revision) {
            $file = $revision->file;
            $fileLabel = $file?->label() ?? $revision->original_filename ?? 'Document';

            if (filled($revision->revision_note)) {
                $rows[] = ['key' => 'file_'.$revision->id, 'reviewer' => $headLabel, 'location' => $fileLabel, 'comment' => $revision->revision_note];
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
                ];
            }
        }

        return $rows;
    }

    /** @return list<array{key: string, reviewer: string, location: string, comment: string}> */
    private function coEvaluatorRows(TopicReview $review): array
    {
        $review->loadMissing(['topic.latestVersion.files', 'fileRevisions.file.version.files']);
        $reviewedVersion = $review->fileRevisions
            ->first(fn ($revision): bool => $revision->file?->version instanceof ProposalVersion)
            ?->file?->version;
        $version = $reviewedVersion ?? $review->topic?->latestVersion;
        $evaluation = $version?->files
            ->filter(fn (ProposalVersionFile $file): bool => $file->document_type === ProposalVersionFile::TYPE_HEAD_UPLOAD
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
        ]];
    }
}

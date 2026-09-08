<?php

namespace App\Services;

use App\Models\ProposalFileAnnotation;
use App\Models\TopicReview;
use App\Support\ProposalRevisionTargetCatalog;

class CommentResponseFeedback
{
    /** @return list<array{key: string, reviewer: string, location: string, comment: string, response: string, remarks: string}> */
    public function rows(?TopicReview $review): array
    {
        if ($review === null) {
            return [];
        }

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
            foreach ($revision->annotations->sortBy('id') as $annotation) {
                $section = $file ? app(ProposalRevisionTargetCatalog::class)->labelFor($file, $annotation->editor_target) : null;
                $reviewer = $annotation->feedbackLabel();
                if ($annotation->feedback_source !== ProposalFileAnnotation::SOURCE_CO_EVALUATOR && $annotation->reviewer) {
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
        $responses = $review->feedback_responses ?? [];

        return array_map(fn (array $row): array => [
            ...$row,
            'response' => $responses[$row['key']]['response'] ?? '',
            'remarks' => $responses[$row['key']]['remarks'] ?? '',
        ], $rows);
    }
}

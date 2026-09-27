<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProposalVersion extends Model
{
    private const PASSING_GAD_OUTCOMES = ['passed', 'commended'];

    protected $fillable = [
        'submitted_by',
        'version_number',
        'submission_type',
        'change_summary',
        'file_path',
        'original_filename',
        'mime_type',
        'file_size',
        'checksum',
        'title',
        'description',
        'estimated_budget',
        'estimated_duration_months',
    ];

    protected function casts(): array
    {
        return [
            'estimated_budget' => 'decimal:2',
            'file_size' => 'integer',
            'version_number' => 'integer',
        ];
    }

    public function topic(): BelongsTo
    {
        return $this->belongsTo(TopicProposal::class, 'topic_id');
    }

    public function submitter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    public function files(): HasMany
    {
        return $this->hasMany(ProposalVersionFile::class)
            ->orderBy('document_type')
            ->orderBy('position');
    }

    public function hasPassingGadAssessment(): bool
    {
        $files = $this->relationLoaded('files') ? $this->files : $this->files()->get();
        $gadChecklistId = $files
            ->firstWhere('document_type', ProposalVersionFile::TYPE_GAD_CHECKLIST)
            ?->getKey();

        if ($gadChecklistId === null) {
            return false;
        }

        $assessment = $files
            ->filter(fn (ProposalVersionFile $file): bool => $file->document_type === ProposalVersionFile::TYPE_HEAD_UPLOAD
                && $file->source_version_file_id === $gadChecklistId
                && ($file->source_data['purpose'] ?? null) === ProposalVersionFile::HEAD_UPLOAD_PURPOSE_GAD_ASSESSMENT
                && ($file->source_data['target_document_type'] ?? null) === ProposalVersionFile::TYPE_GAD_CHECKLIST)
            ->sortByDesc('id')
            ->first();

        if (! $assessment instanceof ProposalVersionFile) {
            return false;
        }

        if (($assessment->source_data['gad_signature_confirmed'] ?? false) !== true) {
            return false;
        }

        $outcome = $assessment->source_data['gad_outcome'] ?? null;

        if (is_string($outcome)) {
            return in_array($outcome, self::PASSING_GAD_OUTCOMES, true);
        }

        $score = $assessment->source_data['gad_score'] ?? null;

        return is_numeric($score) && (float) $score >= 8;
    }

    public function hasCoEvaluatorReview(): bool
    {
        $files = $this->relationLoaded('files') ? $this->files : $this->files()->get();
        $initialScreeningFormId = $files
            ->firstWhere('document_type', ProposalVersionFile::TYPE_INITIAL_SCREENING_FORM)
            ?->getKey();

        if ($initialScreeningFormId === null) {
            return false;
        }

        return $files->contains(fn (ProposalVersionFile $file): bool => $file->document_type === ProposalVersionFile::TYPE_HEAD_UPLOAD
            && $file->source_version_file_id === $initialScreeningFormId
            && ($file->source_data['purpose'] ?? null) === ProposalVersionFile::HEAD_UPLOAD_PURPOSE_EVALUATION
            && ($file->source_data['target_document_type'] ?? null) === ProposalVersionFile::TYPE_INITIAL_SCREENING_FORM
            && filled($file->source_data['narrative_evaluation'] ?? null));
    }
}

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
        $gadChecklistId = $this->files()
            ->where('document_type', ProposalVersionFile::TYPE_GAD_CHECKLIST)
            ->value('id');

        if ($gadChecklistId === null) {
            return false;
        }

        $assessment = $this->files()
            ->where('document_type', ProposalVersionFile::TYPE_HEAD_UPLOAD)
            ->where('source_version_file_id', $gadChecklistId)
            ->where('source_data->purpose', ProposalVersionFile::HEAD_UPLOAD_PURPOSE_GAD_ASSESSMENT)
            ->where('source_data->target_document_type', ProposalVersionFile::TYPE_GAD_CHECKLIST)
            ->reorder()
            ->latest('id')
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
}

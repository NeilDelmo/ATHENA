<?php

namespace App\Models;

use App\Support\ProposalPaperCatalog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProposalVersionFile extends Model
{
    public const TYPE_DETAILED_PROPOSAL = 'detailed_proposal';

    public const TYPE_WORK_PLAN = 'work_plan';

    public const TYPE_LINE_ITEM_BUDGET = 'line_item_budget';

    public const TYPE_EXPENSE_BREAKDOWN = 'expense_breakdown';

    public const TYPE_CURRICULUM_VITAE = 'curriculum_vitae';

    public const TYPE_GAD_CHECKLIST = 'gad_checklist';

    public const TYPE_INITIAL_SCREENING_FORM = 'initial_screening_form';

    public const TYPE_COMMENT_RESPONSE = 'comment_response';

    public const TYPE_HEAD_UPLOAD = 'head_upload';

    public const HEAD_UPLOAD_PURPOSE_REVISION = 'revision';

    public const HEAD_UPLOAD_PURPOSE_SIGNED = 'signed';

    protected $fillable = [
        'source_version_file_id',
        'document_type',
        'position',
        'file_path',
        'original_filename',
        'mime_type',
        'file_size',
        'checksum',
        'source_data',
        'is_carried_forward',
        'uploaded_by',
    ];

    protected function casts(): array
    {
        return [
            'file_size' => 'integer',
            'position' => 'integer',
            'source_data' => 'array',
            'is_carried_forward' => 'boolean',
        ];
    }

    public function version(): BelongsTo
    {
        return $this->belongsTo(ProposalVersion::class, 'proposal_version_id');
    }

    public function source(): BelongsTo
    {
        return $this->belongsTo(self::class, 'source_version_file_id');
    }

    public function uploadedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function annotations(): HasMany
    {
        return $this->hasMany(ProposalFileAnnotation::class);
    }

    public function label(): string
    {
        $catalogLabel = app(ProposalPaperCatalog::class)->label($this->document_type);

        if ($catalogLabel !== null) {
            return $catalogLabel.($this->document_type === self::TYPE_CURRICULUM_VITAE && $this->position > 0
                ? ' '.($this->position + 1)
                : '');
        }

        return match ($this->document_type) {
            self::TYPE_COMMENT_RESPONSE => 'Comment-Response Form',
            self::TYPE_HEAD_UPLOAD => $this->headUploadLabel(),
            default => str($this->document_type)->replace('_', ' ')->title()->toString(),
        };
    }

    private function headUploadLabel(): string
    {
        if (is_array($this->source_data) && isset($this->source_data['target_document_type'])) {
            $catalogLabel = app(ProposalPaperCatalog::class)->label($this->source_data['target_document_type']);

            if ($catalogLabel !== null) {
                return $catalogLabel.' ('.str($this->headUploadPurposeLabel())->lower().')';
            }
        }

        return 'Research Head upload';
    }

    public function headUploadPurposeLabel(): string
    {
        return match ($this->source_data['purpose'] ?? null) {
            self::HEAD_UPLOAD_PURPOSE_REVISION => 'For revision',
            self::HEAD_UPLOAD_PURPOSE_SIGNED => 'Signed copy',
            default => 'Research Head copy',
        };
    }
}
